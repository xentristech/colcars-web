<?php
/**
 * API para procesar formulario de contacto
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos del formulario
$input = json_decode(file_get_contents('php://input'), true);

// Validar campos requeridos
$nombre = trim($input['nombre'] ?? '');
$email = trim($input['email'] ?? '');
$telefono = trim($input['telefono'] ?? '');
$asunto = trim($input['asunto'] ?? '');
$mensaje = trim($input['mensaje'] ?? '');

$errors = [];

if (empty($nombre)) {
    $errors[] = 'El nombre es obligatorio';
} elseif (strlen($nombre) < 2) {
    $errors[] = 'El nombre debe tener al menos 2 caracteres';
}

if (empty($email)) {
    $errors[] = 'El email es obligatorio';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'El email no es válido';
}

if (empty($asunto)) {
    $errors[] = 'El asunto es obligatorio';
}

if (empty($mensaje)) {
    $errors[] = 'El mensaje es obligatorio';
} elseif (strlen($mensaje) < 10) {
    $errors[] = 'El mensaje debe tener al menos 10 caracteres';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Obtener IP y User Agent
$ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

// Guardar en la base de datos
try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    if (!$pdo) {
        throw new Exception('Error de conexión a la base de datos');
    }
    
    $sql = "INSERT INTO contactos (nombre, email, telefono, asunto, mensaje, ip_address, user_agent) 
            VALUES (:nombre, :email, :telefono, :asunto, :mensaje, :ip_address, :user_agent)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':nombre' => $nombre,
        ':email' => $email,
        ':telefono' => $telefono,
        ':asunto' => $asunto,
        ':mensaje' => $mensaje,
        ':ip_address' => $ip_address,
        ':user_agent' => $user_agent
    ]);
    
    $contacto_id = $pdo->lastInsertId();
    
    // Enviar email de confirmación al usuario (opcional)
    $to = $email;
    $subject = "Gracias por contactarnos - Colcars";
    $email_message = "
    <html>
    <head><title>Confirmación de contacto - Colcars</title></head>
    <body>
        <h2>Hola $nombre,</h2>
        <p>Hemos recibido tu mensaje y te responderemos a la brevedad posible.</p>
        <br>
        <p><strong>Asunto:</strong> $asunto</p>
        <p><strong>Mensaje:</strong></p>
        <p>" . nl2br(htmlspecialchars($mensaje)) . "</p>
        <br>
        <p>Saludos cordiales,<br><strong>Equipo de Colcars</strong></p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: Colcars <no-reply@easycarluxury.com>" . "\r\n";
    
    @mail($to, $subject, $email_message, $headers);
    
    echo json_encode([
        'success' => true,
        'message' => 'Mensaje enviado correctamente. Te responderemos a la brevedad.'
    ]);
    
} catch (Exception $e) {
    error_log("Error al guardar contacto: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error al procesar el mensaje. Por favor intenta de nuevo.'
    ]);
}
?>