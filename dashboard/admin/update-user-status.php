<?php
/**
 * EASY CAR LUXURY - Cambiar estado de usuario (Activar/Desactivar)
 * Ruta: /dashboard/admin/update-user-status.php
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';
require_once '../../includes/functions.php';

// Obtener conexión
$database = Database::getInstance();
$pdo = $database->getConnection();

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

if (!$admin) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

$user_id = intval($_POST['user_id'] ?? 0);
$action = $_POST['action'] ?? ''; // 'activate' o 'deactivate'

if (!$user_id || !in_array($action, ['activate', 'deactivate'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
    exit;
}

// No permitir desactivar al propio admin
if ($user_id == $admin['id']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No puedes desactivar tu propia cuenta']);
    exit;
}

// Verificar que el usuario existe
$stmt = $pdo->prepare("SELECT id, nombre_completo, email, activo FROM usuarios WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

$nuevo_estado = ($action === 'activate') ? 1 : 0;
$estado_texto = ($action === 'activate') ? 'activada' : 'desactivada';

// Actualizar estado
$update = $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
$result = $update->execute([$nuevo_estado, $user_id]);

if ($result) {
    // Registrar en auditoría
    $detalles = json_encode([
        'action' => strtoupper($action),
        'user_id' => $user_id,
        'user_email' => $user['email'],
        'previous_status' => $user['activo'],
        'new_status' => $nuevo_estado,
        'admin_id' => $admin['id'],
        'admin_name' => $admin['full_name']
    ]);
    
    $audit = $pdo->prepare("INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $audit->execute([
        $admin['id'],
        strtoupper($action),
        'usuario',
        $user_id,
        $detalles,
        $_SERVER['REMOTE_ADDR'] ?? '',
        $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => "Cuenta {$estado_texto} correctamente",
        'new_status' => $nuevo_estado
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Error al actualizar el estado']);
}