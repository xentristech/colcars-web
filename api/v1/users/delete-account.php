<?php
/**
 * API - Eliminar Cuenta de Usuario
 * Elimina todos los datos del usuario, incluyendo documentos y publicaciones
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';

// Configurar header para JSON
header('Content-Type: application/json');

// Verificar autenticación
try {
    requireAuth();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$user_id = $_SESSION['user_id'];
$db = Database::getInstance();
$pdo = $db->getConnection();

// Verificar que el usuario existe
$user = $db->getOne("SELECT id, email, username FROM usuarios WHERE id = ?", [$user_id]);
if (!$user) {
    echo json_encode(['success' => false, 'error' => 'Usuario no encontrado']);
    exit;
}

try {
    // Iniciar transacción
    $pdo->beginTransaction();
    
    // ============================================
    // 1. OBTENER RUTAS DE DOCUMENTOS ANTES DE ELIMINAR
    // ============================================
    $stmt = $pdo->prepare("SELECT file_path FROM user_documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ============================================
    // 2. ELIMINAR REGISTROS DE TABLAS RELACIONADAS
    // ============================================
    
    // 2.1 Documentos del usuario
    $stmt = $pdo->prepare("DELETE FROM user_documents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2.2 Configuración del usuario
    $stmt = $pdo->prepare("DELETE FROM user_settings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2.3 Membresías del usuario
    $stmt = $pdo->prepare("DELETE FROM user_memberships WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2.4 Notificaciones del usuario
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2.5 Mensajes (enviados y recibidos)
    $stmt = $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?");
    $stmt->execute([$user_id, $user_id]);
    
    // 2.6 Ofertas realizadas por el usuario
    $stmt = $pdo->prepare("DELETE FROM offers WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2.7 Favoritos del usuario
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2.8 Votos del usuario
    $stmt = $pdo->prepare("DELETE FROM votes WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // 2.9 Comentarios del usuario
    $stmt = $pdo->prepare("DELETE FROM comentarios WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    
    // ============================================
    // 3. ELIMINAR PUBLICACIONES DEL USUARIO Y DATOS RELACIONADOS
    // ============================================
    
    // Obtener IDs de publicaciones del usuario
    $stmt = $pdo->prepare("SELECT id FROM publicaciones WHERE usuario_id = ?");
    $stmt->execute([$user_id]);
    $publications = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($publications)) {
        $placeholders = implode(',', array_fill(0, count($publications), '?'));
        
        // Eliminar visitas de publicaciones
        $stmt = $pdo->prepare("DELETE FROM publication_views WHERE publication_id IN ($placeholders)");
        $stmt->execute($publications);
        
        // Eliminar estadísticas de publicaciones
        $stmt = $pdo->prepare("DELETE FROM estadisticas_publicaciones WHERE publicacion_id IN ($placeholders)");
        $stmt->execute($publications);
        
        // Eliminar imágenes de publicaciones
        $stmt = $pdo->prepare("SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id IN ($placeholders)");
        $stmt->execute($publications);
        $images = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Eliminar registros de imágenes
        $stmt = $pdo->prepare("DELETE FROM imagenes_publicaciones WHERE publicacion_id IN ($placeholders)");
        $stmt->execute($publications);
        
        // Eliminar ofertas de publicaciones
        $stmt = $pdo->prepare("DELETE FROM offers WHERE publication_id IN ($placeholders)");
        $stmt->execute($publications);
        
        // Eliminar comentarios de publicaciones
        $stmt = $pdo->prepare("DELETE FROM comentarios WHERE publicacion_id IN ($placeholders)");
        $stmt->execute($publications);
        
        // Eliminar votos de publicaciones
        $stmt = $pdo->prepare("DELETE FROM votes WHERE publication_id IN ($placeholders)");
        $stmt->execute($publications);
        
        // Eliminar publicaciones
        $stmt = $pdo->prepare("DELETE FROM publicaciones WHERE usuario_id = ?");
        $stmt->execute([$user_id]);
        
        // Eliminar archivos de imágenes de publicaciones
        foreach ($images as $image_path) {
            if (!empty($image_path)) {
                $full_path = $_SERVER['DOCUMENT_ROOT'] . $image_path;
                if (file_exists($full_path)) {
                    unlink($full_path);
                }
            }
        }
    }
    
    // ============================================
    // 4. ELIMINAR FACTURAS Y TRANSACCIONES DIAN
    // ============================================
    
    // Obtener facturas del usuario
    $stmt = $pdo->prepare("SELECT id FROM invoices WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $invoices = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($invoices)) {
        $placeholders = implode(',', array_fill(0, count($invoices), '?'));
        
        // Eliminar transacciones DIAN
        $stmt = $pdo->prepare("DELETE FROM dian_transactions WHERE invoice_id IN ($placeholders)");
        $stmt->execute($invoices);
        
        // Eliminar facturas
        $stmt = $pdo->prepare("DELETE FROM invoices WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }
    
    // Eliminar pagos del usuario
    $stmt = $pdo->prepare("DELETE FROM payments WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // ============================================
    // 5. REGISTRAR EN AUDITORÍA ANTES DE ELIMINAR
    // ============================================
    logAudit($user_id, 'DELETE', 'usuarios', $user_id, $user, ['reason' => 'account_deletion']);
    
    // ============================================
    // 6. ELIMINAR EL USUARIO
    // ============================================
    $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // ============================================
    // 7. ELIMINAR ARCHIVOS DE DOCUMENTOS DEL USUARIO
    // ============================================
    $user_doc_folder = $_SERVER['DOCUMENT_ROOT'] . '/easycarluxury/uploads/user_documents/user_' . $user_id;
    
    if (file_exists($user_doc_folder)) {
        // Eliminar todos los archivos dentro de la carpeta
        $files = glob($user_doc_folder . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        // Eliminar la carpeta
        rmdir($user_doc_folder);
    }
    
    // También eliminar archivos de documentos individuales si no están en la carpeta del usuario
    foreach ($documents as $doc) {
        if (!empty($doc['file_path'])) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . $doc['file_path'];
            if (file_exists($full_path)) {
                unlink($full_path);
            }
        }
    }
    
    // ============================================
    // 8. LIMPIAR SESIÓN
    // ============================================
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    
    // Confirmar transacción
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Cuenta eliminada correctamente']);
    
} catch (Exception $e) {
    // Revertir cambios en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    echo json_encode(['success' => false, 'error' => 'Error al eliminar la cuenta: ' . $e->getMessage()]);
}
?>