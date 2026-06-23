<?php
/**
 * EASY CAR LUXURY - Funciones Helper
 * Funciones utilitarias para toda la aplicación
 */

// =============================================
// Incluir configuración de base de datos (necesario para la clase Database)
// =============================================
require_once __DIR__ . '/../config/database.php';

// =============================================
// SANITIZACIÓN Y VALIDACIÓN
// =============================================

/**
 * Sanitizar input para prevenir XSS
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validar número de documento colombiano (CC)
 */
function validateCC($cc) {
    // Básico: solo números, 6-10 dígitos
    return preg_match('/^[0-9]{6,10}$/', $cc);
}

/**
 * Validar teléfono colombiano
 */
function validatePhone($phone) {
    // Formato: 3xx o 6xx más 7 dígitos, opcional +57
    return preg_match('/^(?:\+57)?[3|6|7][0-9]{9}$/', $phone);
}

/**
 * Validar placa colombiana
 */
function validatePlate($plate) {
    // Formato: XXX-000 o XXX000
    return preg_match('/^[A-Z]{3}-?[0-9]{3}$/', $plate);
}

/**
 * Generar slug amigable para URLs
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

// =============================================
// AUDITORÍA - NUEVO SISTEMA CENTRALIZADO
// =============================================

/**
 * Registrar acción en auditoría - Versión mejorada usando AuditLog
 * Mantiene compatibilidad con código existente
 * 
 * @param int $usuario_id ID del usuario que realiza la acción
 * @param string $accion Acción realizada (CREATE, UPDATE, DELETE, LOGIN, LOGOUT, etc.)
 * @param string $tabla_afectada Tabla afectada (usuarios, publicaciones, etc.)
 * @param int|null $registro_id ID del registro afectado
 * @param array|string|null $datos_anteriores Datos antes del cambio
 * @param array|string|null $datos_nuevos Datos después del cambio
 * @return bool
 */
function logAudit($usuario_id, $accion, $tabla_afectada, $registro_id = null, $datos_anteriores = null, $datos_nuevos = null) {
    try {
        // Incluir archivo de auditoría si está disponible
        $auditPath = __DIR__ . '/audit-log.php';
        if (!file_exists($auditPath)) {
            // Si no existe el nuevo sistema, registrar en log de errores
            error_log("Auditoría: $accion en $tabla_afectada#$registro_id por usuario $usuario_id");
            return false;
        }
        
        require_once $auditPath;
        
        // Obtener conexión a la base de datos
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        if (!$pdo) {
            error_log("logAudit: No se pudo obtener conexión a la base de datos");
            return false;
        }
        
        // Obtener datos del usuario
        $usuario_email = null;
        $rol_usuario = null;
        
        if ($usuario_id) {
            try {
                $user = $pdo->prepare("SELECT email, (SELECT nombre FROM roles WHERE id = u.rol_id) as rol FROM usuarios u WHERE id = ?");
                $user->execute([$usuario_id]);
                $userData = $user->fetch(PDO::FETCH_ASSOC);
                if ($userData) {
                    $usuario_email = $userData['email'];
                    $rol_usuario = $userData['rol'];
                }
            } catch (Exception $e) {
                error_log("logAudit: Error al obtener datos del usuario: " . $e->getMessage());
            }
        }
        
        // Crear instancia de auditoría
        $audit = new AuditLog($pdo, $usuario_id, $usuario_email, $rol_usuario);
        
        // Determinar el tipo de target basado en la tabla afectada
        $targetType = $tabla_afectada;
        
        // Registrar la acción
        return $audit->register($accion, $targetType, $registro_id, $datos_anteriores, $datos_nuevos);
        
    } catch (Exception $e) {
        error_log("Error en logAudit: " . $e->getMessage());
        return false;
    }
}

/**
 * Función rápida para registrar auditoría sin necesidad de instanciar la clase
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param string $accion Acción realizada
 * @param string|null $targetType Tipo de elemento afectado
 * @param int|null $targetId ID del elemento afectado
 * @param array|string|null $oldData Datos anteriores
 * @param array|string|null $newData Datos nuevos
 * @return bool
 */
function quickAudit($pdo, $accion, $targetType = null, $targetId = null, $oldData = null, $newData = null) {
    try {
        $auditPath = __DIR__ . '/audit-log.php';
        if (!file_exists($auditPath)) {
            error_log("quickAudit: archivo audit-log.php no encontrado");
            return false;
        }
        
        require_once $auditPath;
        
        // Obtener datos del usuario desde sesión
        $userId = $_SESSION['user_id'] ?? $_SESSION['usuario_id'] ?? null;
        $userEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;
        $userRole = $_SESSION['user_role'] ?? $_SESSION['rol_nombre'] ?? null;
        
        $audit = new AuditLog($pdo, $userId, $userEmail, $userRole);
        return $audit->register($accion, $targetType, $targetId, $oldData, $newData);
    } catch (Exception $e) {
        error_log("Error en quickAudit: " . $e->getMessage());
        return false;
    }
}

/**
 * Registrar inicio de sesión en auditoría
 */
function logLogin($usuario_id, $email, $exito = true) {
    try {
        $auditPath = __DIR__ . '/audit-log.php';
        if (!file_exists($auditPath)) {
            return false;
        }
        
        require_once $auditPath;
        
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        if (!$pdo) return false;
        
        // Obtener rol del usuario
        $rol_usuario = null;
        if ($usuario_id) {
            try {
                $user = $pdo->prepare("SELECT (SELECT nombre FROM roles WHERE id = u.rol_id) as rol FROM usuarios u WHERE id = ?");
                $user->execute([$usuario_id]);
                $userData = $user->fetch(PDO::FETCH_ASSOC);
                $rol_usuario = $userData['rol'] ?? null;
            } catch (Exception $e) {}
        }
        
        $audit = new AuditLog($pdo, $usuario_id, $email, $rol_usuario);
        
        if ($exito) {
            return $audit->registerLogin('Inicio de sesión exitoso');
        } else {
            return $audit->register('LOGIN_FAILED', 'session', null, null, null, null, "Intento de inicio de sesión fallido para email: $email");
        }
    } catch (Exception $e) {
        error_log("Error en logLogin: " . $e->getMessage());
        return false;
    }
}

/**
 * Registrar cierre de sesión en auditoría
 */
function logLogout($usuario_id, $email, $rol = null) {
    try {
        $auditPath = __DIR__ . '/audit-log.php';
        if (!file_exists($auditPath)) {
            return false;
        }
        
        require_once $auditPath;
        
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        if (!$pdo) return false;
        
        $audit = new AuditLog($pdo, $usuario_id, $email, $rol);
        return $audit->registerLogout('Cierre de sesión');
    } catch (Exception $e) {
        error_log("Error en logLogout: " . $e->getMessage());
        return false;
    }
}

// =============================================
// SEGURIDAD
// =============================================

/**
 * Generar token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verificar token CSRF
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar contraseña aleatoria
 */
function generateRandomPassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Encriptar datos sensibles
 */
function encryptData($data) {
    $key = hex2bin($_ENV['ENCRYPTION_KEY'] ?? '0123456789abcdef0123456789abcdef');
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Desencriptar datos
 */
function decryptData($encryptedData) {
    $key = hex2bin($_ENV['ENCRYPTION_KEY'] ?? '0123456789abcdef0123456789abcdef');
    $data = base64_decode($encryptedData);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

// =============================================
// MANEJO DE ARCHIVOS
// =============================================

/**
 * Subir archivo de manera segura
 */
function uploadFile($file, $destination, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf'], $maxSize = 5242880) {
    // Validar error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo'];
    }
    
    // Validar tamaño
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'El archivo excede el tamaño máximo de ' . ($maxSize / 1024 / 1024) . 'MB'];
    }
    
    // Validar tipo
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Tipo de archivo no permitido'];
    }
    
    // Generar nombre único
    $filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $fullPath = $destination . $filename;
    
    // Crear directorio si no existe
    if (!is_dir($destination)) {
        mkdir($destination, 0777, true);
    }
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $fullPath];
    }
    
    return ['success' => false, 'message' => 'Error al guardar el archivo'];
}

/**
 * Eliminar archivo
 */
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// =============================================
// MEMBRESÍAS
// =============================================

/**
 * Verificar si usuario puede publicar más anuncios
 * CORREGIDA: Ahora usa 'status' en lugar de 'activo', y 'active' en lugar de 1
 */
function canUserPost($usuario_id) {
    try {
        $db = Database::getInstance();
        
        // Obtener usuario
        $user = $db->getOne("SELECT tipo_cuenta, limite_publicaciones_int FROM usuarios WHERE id = ?", [$usuario_id]);
        
        // Verificar que el usuario existe y es un array
        if (!$user || !is_array($user)) {
            error_log("canUserPost: Usuario no encontrado ID: " . $usuario_id);
            return false;
        }
        
        $tipo_cuenta = $user['tipo_cuenta'] ?? 'free';
        $limite = $user['limite_publicaciones_int'] ?? 2;
        
        // Contar publicaciones activas - CORREGIDO: usar 'status' y 'active'
        $publicaciones = $db->getOne("SELECT COUNT(*) as total FROM publicaciones WHERE usuario_id = ? AND status = 'active'", [$usuario_id]);
        
        // Verificar que la consulta devolvió resultados
        if (!$publicaciones || !is_array($publicaciones)) {
            error_log("canUserPost: Error al contar publicaciones para usuario ID: " . $usuario_id);
            // Si hay error, asumir que puede publicar (por seguridad)
            return true;
        }
        
        $total_publicaciones = $publicaciones['total'] ?? 0;
        
        // Usuarios con membresía premium pueden publicar más (sin límite)
        if (in_array($tipo_cuenta, ['pro', 'premium', 'elite'])) {
            return true;
        }
        
        // Usuarios free tienen límite
        return $total_publicaciones < $limite;
        
    } catch (Exception $e) {
        error_log("Error en canUserPost: " . $e->getMessage());
        // En caso de error, permitir publicar (evita bloquear al usuario)
        return true;
    }
}

/**
 * Verificar membresía activa
 */
function checkMembership($usuario_id) {
    try {
        $db = Database::getInstance();
        $user = $db->getOne("SELECT tipo_cuenta, fecha_expiracion FROM usuarios WHERE id = ?", [$usuario_id]);
        
        if (!$user || !is_array($user)) {
            return false;
        }
        
        // Si es free, siempre activa
        if ($user['tipo_cuenta'] === 'free') return true;
        
        // Verificar expiración
        if ($user['fecha_expiracion'] && strtotime($user['fecha_expiracion']) < time()) {
            // Degradar a free
            $db->update('usuarios', 
                ['tipo_cuenta' => 'free', 'limite_publicaciones_int' => 2], 
                'id = ?', [$usuario_id]
            );
            // Registrar downgrade usando nueva auditoría
            logAudit($usuario_id, 'DOWNGRADE', 'usuarios', $usuario_id, ['tipo_cuenta' => $user['tipo_cuenta']], ['tipo_cuenta' => 'free']);
            return false;
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Error en checkMembership: " . $e->getMessage());
        return false;
    }
}

// =============================================
// RESPUESTAS JSON
// =============================================

/**
 * Enviar respuesta JSON exitosa
 */
function jsonResponse($data = null, $message = 'Success', $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Enviar respuesta JSON de error
 */
function jsonError($message = 'Error', $code = 400, $errors = null) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $message,
        'errors' => $errors
    ]);
    exit;
}

// =============================================
// FORMATOS
// =============================================

/**
 * Formatear dinero COP
 */
function formatMoney($amount) {
    return '$' . number_format($amount, 0, ',', '.') . ' COP';
}

/**
 * Formatear fecha
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date || $date === '0000-00-00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

/**
 * Truncar texto
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) return $text;
    return substr($text, 0, $length) . $suffix;
}

/**
 * Obtener URL base del sitio
 */
function baseUrl($path = '') {
    $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost/easycarluxury';
    return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
}

/**
 * Redirigir a una URL
 */
function redirect($url) {
    header('Location: ' . baseUrl($url));
    exit;
}

/**
 * Verificar si es una petición AJAX
 */
function isAjax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Obtener IP del usuario
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}
?>