<?php
/**
 * Colcars - Eliminar publicidad
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación
$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /login');
    exit;
}

$db = Database::getInstance();
$admin = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

if (!$admin || !is_array($admin)) {
    session_destroy();
    header('Location: /login');
    exit;
}

// Verificar que sea administrador
if ($admin['rol_id'] != 1 && $admin['tipo_cuenta'] !== 'admin' && $admin['rol'] !== 'admin') {
    header('Location: /dashboard/user/index.php');
    exit;
}

header('Content-Type: application/json');

try {
    $id = intval($_POST['id'] ?? 0);
    
    if (!$id) {
        throw new Exception('ID de publicidad no válido');
    }
    
    $publicidad = $db->getOne("SELECT * FROM publicidad WHERE id = ?", [$id]);
    
    if ($publicidad) {
        if ($publicidad['archivo_url']) {
            $file_path = $_SERVER['DOCUMENT_ROOT'] . $publicidad['archivo_url'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        $db->query("DELETE FROM publicidad WHERE id = ?", [$id]);
        
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Publicidad no encontrada');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>