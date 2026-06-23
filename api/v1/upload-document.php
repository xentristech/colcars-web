<?php
/**
 * API - Subir Documentos del Usuario
 * Crea automáticamente una carpeta por usuario al subir el primer documento
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación
requireAuth();

$user_id = $_SESSION['user_id'];
$document_type = $_POST['type'] ?? '';

// Tipos de documento permitidos
$allowed_types = ['cedula', 'rut', 'resolucion_dian'];

if (!in_array($document_type, $allowed_types)) {
    echo json_encode(['success' => false, 'error' => 'Tipo de documento no válido']);
    exit;
}

// Verificar si se subió un archivo
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $error_msg = 'No se recibió el archivo';
    if (isset($_FILES['document']['error'])) {
        switch ($_FILES['document']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_msg = 'El archivo excede el tamaño máximo permitido';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_msg = 'El archivo se subió parcialmente';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_msg = 'No se seleccionó ningún archivo';
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $error_msg = 'Falta la carpeta temporal';
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $error_msg = 'Error al escribir el archivo en el disco';
                break;
            case UPLOAD_ERR_EXTENSION:
                $error_msg = 'Extensión de archivo no permitida';
                break;
        }
    }
    echo json_encode(['success' => false, 'error' => $error_msg]);
    exit;
}

$file = $_FILES['document'];
$file_name = basename($file['name']);
$file_size = $file['size'];
$file_tmp = $file['tmp_name'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

// Validar extensión
$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
if (!in_array($file_ext, $allowed_extensions)) {
    echo json_encode(['success' => false, 'error' => 'Formato no permitido. Use PDF, JPG o PNG']);
    exit;
}

// Validar tamaño máximo (5MB)
if ($file_size > 5 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'El archivo no debe superar los 5MB']);
    exit;
}

// ============================================
// CREAR CARPETA POR USUARIO
// ============================================
$base_upload_dir = __DIR__ . '/../../uploads/user_documents/';
$user_folder = 'user_' . $user_id;
$user_upload_dir = $base_upload_dir . $user_folder . '/';

// Crear directorio base si no existe
if (!file_exists($base_upload_dir)) {
    mkdir($base_upload_dir, 0777, true);
}

// Crear carpeta del usuario si no existe
if (!file_exists($user_upload_dir)) {
    mkdir($user_upload_dir, 0777, true);
    // Crear archivo index.html para proteger la carpeta
    $index_file = $user_upload_dir . 'index.html';
    if (!file_exists($index_file)) {
        file_put_contents($index_file, '<!DOCTYPE html><html><head><title>Acceso denegado</title></head><body><h1>Acceso denegado</h1></body></html>');
    }
}

// Generar nombre único para el archivo dentro de la carpeta del usuario
$new_file_name = $document_type . '_' . time() . '.' . $file_ext;
$relative_path = '/easycarluxury/uploads/user_documents/' . $user_folder . '/' . $new_file_name;
$full_path = $user_upload_dir . $new_file_name;

// Mover el archivo
if (move_uploaded_file($file_tmp, $full_path)) {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    try {
        // Verificar si ya existe un documento de este tipo
        $stmt = $pdo->prepare("SELECT id FROM user_documents WHERE user_id = ? AND document_type = ?");
        $stmt->execute([$user_id, $document_type]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Obtener la ruta del archivo anterior para eliminarlo
            $stmt_old = $pdo->prepare("SELECT file_path FROM user_documents WHERE user_id = ? AND document_type = ?");
            $stmt_old->execute([$user_id, $document_type]);
            $old_doc = $stmt_old->fetch(PDO::FETCH_ASSOC);
            
            if ($old_doc && !empty($old_doc['file_path'])) {
                $old_file_path = $_SERVER['DOCUMENT_ROOT'] . $old_doc['file_path'];
                if (file_exists($old_file_path)) {
                    unlink($old_file_path); // Eliminar archivo anterior
                }
            }
            
            // Actualizar documento existente
            $stmt = $pdo->prepare("UPDATE user_documents SET 
                                    file_path = :file_path, 
                                    file_name = :file_name, 
                                    file_size = :file_size, 
                                    mime_type = :mime_type,
                                    verified = 0,
                                    verified_at = NULL,
                                    verified_by = NULL,
                                    updated_at = NOW()
                                  WHERE user_id = :user_id AND document_type = :document_type");
        } else {
            // Insertar nuevo documento
            $stmt = $pdo->prepare("INSERT INTO user_documents 
                                    (user_id, document_type, file_path, file_name, file_size, mime_type, created_at) 
                                  VALUES 
                                    (:user_id, :document_type, :file_path, :file_name, :file_size, :mime_type, NOW())");
        }
        
        $result = $stmt->execute([
            ':user_id' => $user_id,
            ':document_type' => $document_type,
            ':file_path' => $relative_path,
            ':file_name' => $file_name,
            ':file_size' => $file_size,
            ':mime_type' => $file_ext
        ]);
        
        if ($result) {
            // Registrar en auditoría
            logAudit($user_id, 'CREATE', 'user_documents', null, null, [
                'document_type' => $document_type,
                'file_name' => $file_name,
                'file_path' => $relative_path
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Documento subido correctamente']);
        } else {
            // Si falla la BD, eliminar el archivo subido
            if (file_exists($full_path)) {
                unlink($full_path);
            }
            echo json_encode(['success' => false, 'error' => 'Error al guardar en la base de datos']);
        }
    } catch (Exception $e) {
        // Si hay error, eliminar el archivo subido
        if (file_exists($full_path)) {
            unlink($full_path);
        }
        echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Error al mover el archivo al servidor']);
}
?>