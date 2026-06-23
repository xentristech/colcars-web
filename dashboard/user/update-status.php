<?php
/**
 * EASY CAR LUXURY - Actualizar estado de publicación (Activar/Desactivar)
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Verificar autenticación
requireAuth();

// Obtener ID de usuario
$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'No autenticado']);
    exit;
}

// Obtener datos del POST
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';

if ($id <= 0 || empty($status)) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Validar status
if ($status !== 'active' && $status !== 'inactive') {
    echo json_encode(['success' => false, 'message' => 'Estado inválido']);
    exit;
}

$db = Database::getInstance();

// Verificar que la publicación pertenece al usuario
$publicacion = $db->getOne("SELECT * FROM publicaciones WHERE id = ? AND usuario_id = ?", [$id, $user_id]);

if (!$publicacion) {
    echo json_encode(['success' => false, 'message' => 'No autorizado o publicación no encontrada']);
    exit;
}

// Actualizar estado
$result = $db->query("UPDATE publicaciones SET status = ? WHERE id = ?", [$status, $id]);

if ($result) {
    // Registrar en auditoría
    logAudit($user_id, 'UPDATE', 'publicaciones', $id, ['status' => $publicacion['status']], ['status' => $status]);
    echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado']);
}
?>