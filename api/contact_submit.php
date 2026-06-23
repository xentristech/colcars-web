<?php
/**
 * API para recibir mensajes del formulario de contacto
 * Ruta: C:\ServidorWeb\htdocs\easycarluxury\api\contact_submit.php
 * 
 * Método: POST
 * Content-Type: application/json
 * 
 * Campos requeridos: nombre, email, asunto, mensaje
 * Campos opcionales: telefono
 */

// Configurar cabeceras para API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder a preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Solo aceptar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido. Use POST.'
    ]);
    exit();
}

// Incluir configuración de base de datos
require_once dirname(__DIR__) . '/config/database.php';

// Función para validar email
function validarEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Función para limpiar entrada
function limpiarEntrada($dato) {
    $dato = trim($dato);
    $dato = stripslashes($dato);
    $dato = htmlspecialchars($dato, ENT_QUOTES, 'UTF-8');
    return $dato;
}

// Función para obtener IP real del usuario
function obtenerIpReal() {
    $ip = '';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

// Leer y decodificar JSON recibido
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

// Verificar que se recibió JSON válido
if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode([
        'success' => false,
        'error' => 'JSON inválido: ' . json_last_error_msg()
    ]);
    exit();
}

// Validar campos requeridos
$errores = [];

if (empty($input['nombre']) || strlen(trim($input['nombre'])) < 2) {
    $errores[] = 'El nombre debe tener al menos 2 caracteres.';
}

if (empty($input['email']) || !validarEmail($input['email'])) {
    $errores[] = 'El email no es válido.';
}

if (empty($input['asunto']) || strlen(trim($input['asunto'])) < 3) {
    $errores[] = 'El asunto debe tener al menos 3 caracteres.';
}

if (empty($input['mensaje']) || strlen(trim($input['mensaje'])) < 10) {
    $errores[] = 'El mensaje debe tener al menos 10 caracteres.';
}

// Si hay errores, devolverlos
if (!empty($errores)) {
    echo json_encode([
        'success' => false,
        'errors' => $errores
    ]);
    exit();
}

// Limpiar datos
$nombre = limpiarEntrada($input['nombre']);
$email = limpiarEntrada($input['email']);
$telefono = isset($input['telefono']) ? limpiarEntrada($input['telefono']) : null;
$asunto = limpiarEntrada($input['asunto']);
$mensaje = limpiarEntrada($input['mensaje']);
$ip_usuario = obtenerIpReal();
$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 500) : null;

try {
    // Obtener conexión a la base de datos
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    // Preparar consulta SQL
    $sql = "INSERT INTO contact_messages_new 
            (nombre, email, telefono, asunto, mensaje, ip_usuario, user_agent, fecha_creacion) 
            VALUES 
            (:nombre, :email, :telefono, :asunto, :mensaje, :ip_usuario, :user_agent, NOW())";
    
    $stmt = $pdo->prepare($sql);
    
    // Ejecutar con los parámetros
    $resultado = $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':telefono' => $telefono,
        ':asunto' => $asunto,
        ':mensaje' => $mensaje,
        ':ip_usuario' => $ip_usuario,
        ':user_agent' => $user_agent
    ]);
    
    if ($resultado) {
        $mensaje_id = $pdo->lastInsertId();
        
        // Registrar en log de auditoría
        try {
            $logSql = "INSERT INTO contact_acciones_log 
                       (mensaje_id, accion, detalles, ip_usuario, user_agent, fecha_accion) 
                       VALUES 
                       (:mensaje_id, 'ver', :detalles, :ip_usuario, :user_agent, NOW())";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([
                ':mensaje_id' => $mensaje_id,
                ':detalles' => 'Mensaje recibido desde formulario web',
                ':ip_usuario' => $ip_usuario,
                ':user_agent' => $user_agent
            ]);
        } catch (Exception $e) {
            // No fallar si el log no se puede escribir
            error_log("Error al escribir en log de auditoría: " . $e->getMessage());
        }
        
        // Enviar confirmación automática al usuario (opcional)
        // Esto se puede implementar más tarde con PHPMailer
        
        echo json_encode([
            'success' => true,
            'message' => 'Mensaje enviado correctamente. Te responderemos a la brevedad.',
            'message_id' => $mensaje_id
        ]);
    } else {
        throw new Exception('No se pudo guardar el mensaje en la base de datos.');
    }
    
} catch (PDOException $e) {
    // Error de base de datos
    error_log("Error en contact_submit.php - DB: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al guardar el mensaje. Por favor intenta de nuevo más tarde.'
    ]);
} catch (Exception $e) {
    // Otros errores
    error_log("Error en contact_submit.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Ocurrió un error inesperado. Por favor intenta de nuevo.'
    ]);
}
?>