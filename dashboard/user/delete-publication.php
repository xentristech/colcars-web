<?php
/**
 * EASY CAR LUXURY - Eliminar publicación
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

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID inválido']);
    exit;
}

$db = Database::getInstance();

// Verificar que la publicación pertenece al usuario
$publicacion = $db->getOne("SELECT * FROM publicaciones WHERE id = ? AND usuario_id = ?", [$id, $user_id]);

if (!$publicacion) {
    echo json_encode(['success' => false, 'message' => 'No autorizado o publicación no encontrada']);
    exit;
}

// Eliminar imágenes físicas
$imagenes = $db->getAll("SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = ?", [$id]);
foreach ($imagenes as $img) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . $img['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Eliminar documentos físicos
$documentos = $db->getAll("SELECT url_documento FROM documentacion_articulos WHERE publicacion_id = ?", [$id]);
foreach ($documentos as $doc) {
    $file_path = $_SERVER['DOCUMENT_ROOT'] . $doc['url_documento'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
}

// Eliminar registros de la base de datos
$db->delete('imagenes_publicaciones', 'publicacion_id = ?', [$id]);
$db->delete('documentacion_articulos', 'publicacion_id = ?', [$id]);
$result = $db->delete('publicaciones', 'id = ?', [$id]);

if ($result) {
    // Registrar en auditoría
    logAudit($user_id, 'DELETE', 'publicaciones', $id, $publicacion, null);
    echo json_encode(['success' => true, 'message' => 'Publicación eliminada correctamente']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar la publicación']);
}
?>