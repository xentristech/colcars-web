<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\public\soporte.php
 * EASY CAR LUXURY - Página de Soporte Técnico
 * MODIFICADO: Solo valida cédula de ciudadanía (CC)
 * MODIFICADO: Mensajes con SweetAlert2
 * Contacto para soporte con verificación de identidad
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit-log.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$userData = [];

// Si el usuario está logueado, precargar sus datos
$isLoggedIn = isset($_SESSION['usuario_id']) || isset($_SESSION['user_id']);

if ($isLoggedIn) {
    try {
        $database = Database::getInstance();
        $pdo = $database->getConnection();
        
        $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'];
        $query = "SELECT id, email, nombre_completo, tipo_documento, numero_documento, telefono, direccion, ciudad, departamento, username 
                  FROM usuarios WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $userId]);
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($userData) {
            $_SESSION['user_document'] = $userData['numero_documento'];
            $_SESSION['user_document_type'] = $userData['tipo_documento'];
        }
    } catch (Exception $e) {
        error_log("Error loading user data: " . $e->getMessage());
    }
}

// Procesar formulario de soporte
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_support') {
    
    // Datos personales
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $tipo_documento = $_POST['tipo_documento'] ?? 'CC';
    $numero_documento = trim($_POST['numero_documento'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $departamento = $_POST['departamento'] ?? '';
    
    // Datos de soporte
    $tipo_problema = $_POST['tipo_problema'] ?? 'otro';
    $asunto = trim($_POST['asunto'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $urgencia = $_POST['urgencia'] ?? 'media';
    
    // Validaciones
    $errors = [];
    
    if (empty($nombre_completo)) {
        $errors[] = 'El nombre completo es obligatorio';
    }
    
    // Solo validar CC (Cédula de Ciudadanía)
    if ($tipo_documento !== 'CC') {
        $errors[] = 'Solo se aceptan solicitudes con Cédula de Ciudadanía (CC)';
    }
    
    if (empty($numero_documento)) {
        $errors[] = 'El número de documento es obligatorio';
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico es obligatorio y debe ser válido';
    }
    
    if (empty($asunto)) {
        $errors[] = 'El asunto es obligatorio';
    }
    
    if (empty($descripcion)) {
        $errors[] = 'La descripción del problema es obligatoria';
    }
    
    // Validar que el usuario existe por email y documento (solo CC)
    if (empty($errors)) {
        try {
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            
            // Buscar usuario por email y documento (solo CC)
            $query = "SELECT id, email, nombre_completo, activo 
                      FROM usuarios 
                      WHERE email = :email AND numero_documento = :documento AND tipo_documento = 'CC'";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':email' => $email,
                ':documento' => $numero_documento
            ]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                $errors[] = 'No pudimos verificar tu identidad. Por favor verifica tu email y número de cédula.';
            } elseif ($user['activo'] != 1) {
                $errors[] = 'Tu cuenta está inactiva. Por favor contacta a soporte para reactivarla.';
            } else {
                // Guardar user_id para el ticket
                $userId = $user['id'];
            }
        } catch (Exception $e) {
            $errors[] = 'Error al verificar tus datos. Intenta nuevamente.';
            error_log("Support verification error: " . $e->getMessage());
        }
    }
    
    // Procesar archivo de cédula
    $documento_path = null;
    if (empty($errors) && isset($_FILES['documento_cedula']) && $_FILES['documento_cedula']['error'] === UPLOAD_ERR_OK) {
        $archivo = $_FILES['documento_cedula'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (!in_array($extension, $allowed)) {
            $errors[] = 'El documento debe ser una imagen (JPG, PNG) o PDF';
        } elseif ($archivo['size'] > 5 * 1024 * 1024) { // 5MB máximo
            $errors[] = 'El documento no puede superar los 5MB';
        } else {
            // Crear directorio si no existe
            $uploadDir = __DIR__ . '/../uploads/support_tickets/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $timestamp = date('Ymd_His');
            $safeName = preg_replace('/[^a-zA-Z0-9]/', '_', $nombre_completo);
            $filename = "cedula_{$safeName}_{$timestamp}.{$extension}";
            $filepath = $uploadDir . $filename;
            
            if (move_uploaded_file($archivo['tmp_name'], $filepath)) {
                $documento_path = '/uploads/support_tickets/' . $filename;
            } else {
                $errors[] = 'Error al subir el documento. Intenta nuevamente.';
            }
        }
    } elseif (empty($errors)) {
        $errors[] = 'Debes adjuntar una foto o PDF de tu cédula (ambas caras en una sola imagen/archivo)';
    }
    
    // Guardar ticket de soporte
    if (empty($errors)) {
        try {
            $ticket_id = 'TKT-' . strtoupper(uniqid()) . '-' . date('Ymd');
            
            // Crear tabla de tickets si no existe
            $createTableSQL = "
                CREATE TABLE IF NOT EXISTS support_tickets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    ticket_number VARCHAR(50) NOT NULL UNIQUE,
                    user_id INT NULL,
                    nombre_completo VARCHAR(200) NOT NULL,
                    tipo_documento VARCHAR(20) NOT NULL,
                    numero_documento VARCHAR(50) NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    telefono VARCHAR(20),
                    direccion TEXT,
                    ciudad VARCHAR(100),
                    departamento VARCHAR(50),
                    tipo_problema VARCHAR(50) NOT NULL,
                    asunto VARCHAR(200) NOT NULL,
                    descripcion TEXT NOT NULL,
                    urgencia ENUM('baja', 'media', 'alta', 'critica') DEFAULT 'media',
                    documento_path VARCHAR(500),
                    status ENUM('pendiente', 'en_proceso', 'resuelto', 'cerrado') DEFAULT 'pendiente',
                    respuesta_admin TEXT,
                    respondido_por INT NULL,
                    fecha_respuesta DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_email (email),
                    INDEX idx_ticket_number (ticket_number),
                    INDEX idx_status (status)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_spanish_ci
            ";
            $pdo->exec($createTableSQL);
            
            $userId = isset($userId) ? $userId : ($isLoggedIn ? ($_SESSION['usuario_id'] ?? $_SESSION['user_id']) : null);
            
            $insertQuery = "INSERT INTO support_tickets 
                            (ticket_number, user_id, nombre_completo, tipo_documento, numero_documento, 
                             email, telefono, direccion, ciudad, departamento, tipo_problema, 
                             asunto, descripcion, urgencia, documento_path) 
                            VALUES 
                            (:ticket_number, :user_id, :nombre_completo, :tipo_documento, :numero_documento,
                             :email, :telefono, :direccion, :ciudad, :departamento, :tipo_problema,
                             :asunto, :descripcion, :urgencia, :documento_path)";
            
            $insertStmt = $pdo->prepare($insertQuery);
            $insertStmt->execute([
                ':ticket_number' => $ticket_id,
                ':user_id' => $userId,
                ':nombre_completo' => $nombre_completo,
                ':tipo_documento' => $tipo_documento,
                ':numero_documento' => $numero_documento,
                ':email' => $email,
                ':telefono' => $telefono,
                ':direccion' => $direccion,
                ':ciudad' => $ciudad,
                ':departamento' => $departamento,
                ':tipo_problema' => $tipo_problema,
                ':asunto' => $asunto,
                ':descripcion' => $descripcion,
                ':urgencia' => $urgencia,
                ':documento_path' => $documento_path
            ]);
            
            // Registrar en auditoría
            $audit = new AuditLog($pdo, $userId, $email, 'usuario');
            $audit->register('SUPPORT_TICKET_CREATED', 'support_tickets', null, null, null, '/soporte.php', "Ticket de soporte creado: {$ticket_id} - {$asunto}");
            
            // Enviar email de confirmación
            $subject = "Ticket de Soporte #{$ticket_id} - Easy Car Luxury";
            $emailMessage = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #1a5276, #2980b9); color: white; padding: 20px; text-align: center; }
                    .content { padding: 20px; background: #f9f9f9; }
                    .ticket-number { font-size: 24px; font-weight: bold; color: #2980b9; }
                    .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Easy Car Luxury</h2>
                        <p>Soporte Técnico</p>
                    </div>
                    <div class='content'>
                        <p>Hola <strong>" . htmlspecialchars($nombre_completo) . "</strong>,</p>
                        <p>Hemos recibido tu solicitud de soporte con el ticket:</p>
                        <p class='ticket-number'>" . $ticket_id . "</p>
                        <p><strong>Asunto:</strong> " . htmlspecialchars($asunto) . "</p>
                        <p><strong>Urgencia:</strong> " . ucfirst($urgencia) . "</p>
                        <p>Nuestro equipo de soporte se pondrá en contacto contigo en las próximas 24 horas hábiles.</p>
                        <hr>
                        <p><strong>Resumen de tu problema:</strong></p>
                        <p>" . nl2br(htmlspecialchars($descripcion)) . "</p>
                    </div>
                    <div class='footer'>
                        <p>&copy; " . date('Y') . " Easy Car Luxury. Todos los derechos reservados.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Easy Car Luxury Soporte <soporte@easycarluxury.com>" . "\r\n";
            
            mail($email, $subject, $emailMessage, $headers);
            
            // También enviar al equipo de soporte
            $adminSubject = "Nuevo Ticket de Soporte #{$ticket_id}";
            $adminMessage = "
            <html>
            <head><style>body{font-family:Arial;}</style></head>
            <body>
                <h2>Nuevo Ticket de Soporte</h2>
                <p><strong>Ticket:</strong> {$ticket_id}</p>
                <p><strong>Usuario:</strong> {$nombre_completo}</p>
                <p><strong>Documento:</strong> {$tipo_documento} {$numero_documento}</p>
                <p><strong>Email:</strong> {$email}</p>
                <p><strong>Teléfono:</strong> " . ($telefono ?: 'No especificado') . "</p>
                <p><strong>Urgencia:</strong> " . ucfirst($urgencia) . "</p>
                <p><strong>Asunto:</strong> {$asunto}</p>
                <p><strong>Descripción:</strong><br>" . nl2br(htmlspecialchars($descripcion)) . "</p>
                " . ($documento_path ? "<p><strong>Documento adjunto:</strong> <a href='{$documento_path}'>Ver documento</a></p>" : "") . "
            </body>
            </html>
            ";
            
            mail('soporte@easycarluxury.com', $adminSubject, $adminMessage, $headers);
            
            $success = "¡Ticket creado exitosamente!<br><br>
                        <strong>Tu número de ticket es: {$ticket_id}</strong><br><br>
                        Hemos enviado una copia a tu correo electrónico.<br>
                        Te contactaremos en las próximas 24 horas hábiles.";
            
            // Limpiar formulario en caso de éxito
            $_POST = [];
            
        } catch (Exception $e) {
            $errors[] = 'Error al guardar el ticket. Intenta nuevamente.';
            error_log("Support ticket error: " . $e->getMessage());
        }
    }
    
    $error = implode('<br>', $errors);
}

// Tema
$tema = 'light';
if (isset($_COOKIE['theme'])) {
    $tema = $_COOKIE['theme'];
}

// Departamentos de Colombia
$departamentos = [
    'Amazonas', 'Antioquia', 'Arauca', 'Atlántico', 'Bolívar', 'Boyacá', 'Caldas', 'Caquetá',
    'Casanare', 'Cauca', 'Cesar', 'Chocó', 'Córdoba', 'Cundinamarca', 'Guainía', 'Guaviare',
    'Huila', 'La Guajira', 'Magdalena', 'Meta', 'Nariño', 'Norte de Santander', 'Putumayo',
    'Quindío', 'Risaralda', 'San Andrés y Providencia', 'Santander', 'Sucre', 'Tolima',
    'Valle del Cauca', 'Vaupés', 'Vichada'
];

// Tipos de problema
$tipos_problema = [
    'tecnico' => 'Problema técnico con la plataforma',
    'cuenta' => 'Problema con mi cuenta/acceso',
    'pago' => 'Problema con pagos/facturación',
    'publicacion' => 'Problema con publicaciones',
    'facturacion_dian' => 'Problema con facturación DIAN',
    'otro' => 'Otro problema'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soporte Técnico - Easy Car Luxury</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background 0.3s ease;
            position: relative;
            padding: 40px 20px;
        }
        
        /* Tema claro */
        body.light-theme {
            background: linear-gradient(135deg, #1a5276, #2980b9);
        }
        
        /* Tema oscuro */
        body.dark-theme {
            background: linear-gradient(135deg, #08011e, #1f0c5b, #03045C);
        }
        
        /* Logo flotante */
        .floating-logo {
            position: fixed;
            top: 10px;
            left: 20px;
            z-index: 1000;
        }
        
        .floating-logo img {
            height: 40px;
            width: auto;
            transition: transform 0.3s ease;
        }
        
        .floating-logo img:hover {
            transform: scale(1.05);
        }
        
        .support-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .support-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        body.dark-theme .support-card {
            background: #050128;
            color: white;
        }
        
        .support-header {
            background: linear-gradient(135deg, #030137, #2980b9);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        body.dark-theme .support-header {
            background: linear-gradient(135deg, #000010, #010132);
        }
        
        .support-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .support-header p {
            margin: 8px 0 0;
            opacity: 0.8;
            font-size: 0.85rem;
        }
        
        .support-body {
            padding: 30px;
        }
        
        /* Información de contacto */
        .contact-info-card {
            background: linear-gradient(135deg, #e8f4fd, #d6ecf9);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        body.dark-theme .contact-info-card {
            background: linear-gradient(135deg, #0a0a2a, #0f0f35);
            border: 1px solid #2a2a4e;
        }
        
        .contact-info-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 15px;
            color: #1a5276;
        }
        
        body.dark-theme .contact-info-title {
            color: #5dade2;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        body.dark-theme .contact-item {
            border-bottom-color: rgba(255,255,255,0.05);
        }
        
        .contact-item i {
            width: 30px;
            font-size: 1.1rem;
            color: #2980b9;
        }
        
        body.dark-theme .contact-item i {
            color: #5dade2;
        }
        
        .contact-item span {
            font-size: 0.8rem;
            color: #333;
        }
        
        body.dark-theme .contact-item span {
            color: #ccc;
        }
        
        /* Formulario */
        .form-section-title {
            font-size: 1rem;
            font-weight: 700;
            margin: 20px 0 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #2980b9;
            display: inline-block;
        }
        
        body.dark-theme .form-section-title {
            color: #5dade2;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            font-size: 0.85rem;
            height: 38px;
        }
        
        body.dark-theme .form-control,
        body.dark-theme .form-select {
            background: #2a2a3e;
            border-color: #3a3a4e;
            color: white;
        }
        
        body.dark-theme .form-control:focus,
        body.dark-theme .form-select:focus {
            background: #2a2a3e;
            color: white;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #2980b9;
            box-shadow: 0 0 0 0.15rem rgba(41, 128, 185, 0.25);
        }
        
        textarea.form-control {
            height: auto;
            resize: vertical;
        }
        
        .form-label {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 4px;
        }
        
        .input-group-text {
            background: transparent;
            border-right: none;
            padding: 8px 10px;
            font-size: 0.85rem;
        }
        
        .input-group .form-control,
        .input-group .form-select {
            border-left: none;
        }
        
        body.dark-theme .input-group-text {
            background: #2a2a3e;
            border-color: #3a3a4e;
            color: white;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 600;
            font-size: 0.9rem;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            color: white;
            background: linear-gradient(135deg, #0e3a5c, #1a5276);
        }
        
        .document-upload-box {
            border: 2px dashed #c0c0c0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(41, 128, 185, 0.02);
        }
        
        body.dark-theme .document-upload-box {
            border-color: #3a3a4e;
            background: rgba(93, 109, 226, 0.05);
        }
        
        .document-upload-box:hover {
            border-color: #2980b9;
            background: rgba(41, 128, 185, 0.05);
        }
        
        .document-upload-box i {
            font-size: 2rem;
            color: #2980b9;
            margin-bottom: 10px;
        }
        
        .document-upload-box p {
            font-size: 0.7rem;
            margin: 0;
            color: #666;
        }
        
        body.dark-theme .document-upload-box p {
            color: #aaa;
        }
        
        .document-requirements {
            font-size: 0.65rem;
            color: #e74c3c;
            margin-top: 5px;
        }
        
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            border: none;
            font-size: 1.1rem;
            z-index: 1000;
        }
        
        .theme-toggle:hover {
            background: rgba(0,0,0,0.7);
            transform: scale(1.05);
        }
        
        body.dark-theme .theme-toggle {
            background: rgba(255,255,255,0.2);
        }
        
        body.dark-theme .theme-toggle:hover {
            background: rgba(255,255,255,0.3);
        }
        
        /* Ocultar alerts de Bootstrap */
        .alert {
            display: none;
        }
        
        hr {
            margin: 20px 0;
        }
        
        .row {
            margin: 0 -10px;
        }
        
        .col-md-6, .col-md-4, .col-md-12 {
            padding: 0 10px;
        }
        
        .mb-3 {
            margin-bottom: 15px !important;
        }
        
        .badge-urgente {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }
        
        .badge-baja { background: #27ae60; color: white; }
        .badge-media { background: #f39c12; color: white; }
        .badge-alta { background: #e67e22; color: white; }
        .badge-critica { background: #e74c3c; color: white; }
        
        @media (max-width: 768px) {
            .floating-logo img {
                height: 45px;
            }
            .support-body {
                padding: 20px;
            }
            .support-header h2 {
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 576px) {
            .contact-info-card {
                margin-bottom: 20px;
            }
            .row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    
    <!-- Logo flotante -->
    <div class="floating-logo">
        <img src="/assets/imagenes/logos/colcars_b.png" alt="Easy Car Luxury" id="logoImage">
    </div>
    
    <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <div class="support-container">
        <div class="row g-4">
            <!-- Columna izquierda - Información de contacto -->
            <div class="col-md-4">
                <div class="support-card">
                    <div class="support-header">
                        <h2><i class="fas fa-headset"></i> Soporte Técnico</h2>
                        <p>Estamos aquí para ayudarte</p>
                    </div>
                    <div class="support-body">
                        <div class="contact-info-card">
                            <div class="contact-info-title">
                                <i class="fas fa-phone-alt"></i> Canales de atención
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-phone"></i>
                                <span><strong>Teléfono:</strong> +57 300 123 4567</span>
                            </div>
                            <div class="contact-item">
                                <i class="fab fa-whatsapp"></i>
                                <span><strong>WhatsApp:</strong> +57 300 123 4567</span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-envelope"></i>
                                <span><strong>Email:</strong> soporte@easycarluxury.com</span>
                            </div>
                            <div class="contact-item">
                                <i class="fas fa-clock"></i>
                                <span><strong>Horario:</strong> Lun-Vie 8am-8pm, Sáb 9am-2pm</span>
                            </div>
                        </div>
                        
                        <div class="contact-info-card">
                            <div class="contact-info-title">
                                <i class="fas fa-info-circle"></i> Antes de contactarnos
                            </div>
                            <ul style="font-size: 0.75rem; margin: 0; padding-left: 20px;">
                                <li>Revisa nuestra <a href="#" style="color:#2980b9;">base de conocimiento</a></li>
                                <li>Consulta las <a href="#" style="color:#2980b9;">preguntas frecuentes</a></li>
                                <li>Verifica que tus datos sean correctos</li>
                            </ul>
                        </div>
                        
                        <div class="contact-info-card">
                            <div class="contact-info-title">
                                <i class="fas fa-shield-alt"></i> Seguridad
                            </div>
                            <p style="font-size: 0.7rem; margin: 0;">
                                Nunca compartiremos tus datos personales. Para verificar tu identidad, 
                                necesitamos tu cédula.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Columna derecha - Formulario de soporte -->
            <div class="col-md-8">
                <div class="support-card">
                    <div class="support-header">
                        <h2><i class="fas fa-ticket-alt"></i> Crear Ticket de Soporte</h2>
                        <p>Completa el formulario con tus datos y describe tu problema</p>
                    </div>
                    <div class="support-body">
                        
                        <!-- Los mensajes de error/éxito se muestran con SweetAlert2, no con alertas de Bootstrap -->
                        <div id="errorMessage" style="display: none;"><?php echo $error; ?></div>
                        <div id="successMessage" style="display: none;"><?php echo $success; ?></div>

                        <form method="POST" action="" enctype="multipart/form-data" id="supportForm">
                            <input type="hidden" name="action" value="send_support">
                            
                            <!-- Sección: Datos personales -->
                            <div class="form-section-title">
                                <i class="fas fa-user-circle"></i> Datos personales
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label for="nombre_completo" class="form-label">Nombre completo *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" 
                                               value="<?php echo htmlspecialchars($userData['nombre_completo'] ?? $_POST['nombre_completo'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="tipo_documento" class="form-label">Tipo documento *</label>
                                    <select class="form-select" id="tipo_documento" name="tipo_documento" required>
                                        <option value="CC" <?php echo (($userData['tipo_documento'] ?? 'CC') == 'CC') ? 'selected' : ''; ?>>Cédula de Ciudadanía (CC)</option>
                                    </select>
                                </div>
                                <div class="col-md-8 mb-3">
                                    <label for="numero_documento" class="form-label">Número de documento *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                        <input type="text" class="form-control" id="numero_documento" name="numero_documento" 
                                               value="<?php echo htmlspecialchars($userData['numero_documento'] ?? $_POST['numero_documento'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Correo electrónico *</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($userData['email'] ?? $_POST['email'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono / WhatsApp</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
                                        <input type="tel" class="form-control" id="telefono" name="telefono" 
                                               value="<?php echo htmlspecialchars($userData['telefono'] ?? $_POST['telefono'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ciudad" class="form-label">Ciudad</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-city"></i></span>
                                        <input type="text" class="form-control" id="ciudad" name="ciudad" 
                                               value="<?php echo htmlspecialchars($userData['ciudad'] ?? $_POST['ciudad'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="departamento" class="form-label">Departamento</label>
                                    <select class="form-select" id="departamento" name="departamento">
                                        <option value="">Seleccionar</option>
                                        <?php foreach ($departamentos as $dep): ?>
                                            <option value="<?php echo $dep; ?>" <?php echo (($userData['departamento'] ?? '') == $dep) ? 'selected' : ''; ?>>
                                                <?php echo $dep; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                                    <input type="text" class="form-control" id="direccion" name="direccion" 
                                           value="<?php echo htmlspecialchars($userData['direccion'] ?? $_POST['direccion'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <!-- Sección: Documento de identidad -->
                            <div class="form-section-title">
                                <i class="fas fa-file-alt"></i> Verificación de identidad
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Cédula de Ciudadanía (ambas caras) *</label>
                                <div class="document-upload-box" onclick="document.getElementById('documento_cedula').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p>Haz clic para subir tu documento</p>
                                    <p class="text-muted" style="font-size: 0.65rem;">Formatos: JPG, PNG, PDF (máx 5MB)</p>
                                    <p class="document-requirements">
                                        <i class="fas fa-exclamation-triangle"></i> Debe incluir ambas caras de la cédula en un solo archivo
                                    </p>
                                </div>
                                <input type="file" class="d-none" id="documento_cedula" name="documento_cedula" accept=".jpg,.jpeg,.png,.pdf" required>
                                <div id="fileNameDisplay" class="small text-muted mt-2"></div>
                            </div>
                            
                            <!-- Sección: Detalle del problema -->
                            <div class="form-section-title">
                                <i class="fas fa-bug"></i> Detalle del problema
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="tipo_problema" class="form-label">Tipo de problema *</label>
                                    <select class="form-select" id="tipo_problema" name="tipo_problema" required>
                                        <option value="">Seleccionar</option>
                                        <?php foreach ($tipos_problema as $key => $label): ?>
                                            <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="urgencia" class="form-label">Nivel de urgencia *</label>
                                    <select class="form-select" id="urgencia" name="urgencia" required>
                                        <option value="baja">Baja - No es urgente</option>
                                        <option value="media" selected>Media - Puedo esperar</option>
                                        <option value="alta">Alta - Afecta mi uso normal</option>
                                        <option value="critica">Crítica - No puedo usar la plataforma</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="asunto" class="form-label">Asunto *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-heading"></i></span>
                                    <input type="text" class="form-control" id="asunto" name="asunto" 
                                           placeholder="Ej: No puedo iniciar sesión" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="descripcion" class="form-label">Descripción del problema *</label>
                                <textarea class="form-control" id="descripcion" name="descripcion" rows="5" 
                                          placeholder="Describe detalladamente el problema que estás presentando. Incluye:
- ¿Qué estabas haciendo cuando ocurrió?
- ¿Has podido replicar el error?
- ¿Qué mensajes de error aparecen?
- ¿Desde cuándo presentas este problema?" required></textarea>
                            </div>
                            
                            <hr>
                            
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i> Enviar ticket de soporte
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Mostrar mensaje de éxito con SweetAlert2
        <?php if ($success): ?>
        Swal.fire({
            title: '¡Ticket Enviado!',
            html: `<?php echo addslashes($success); ?>`,
            icon: 'success',
            confirmButtonColor: '#1a5276',
            confirmButtonText: 'Aceptar',
            backdrop: true,
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/public/index.php';
            }
        });
        <?php endif; ?>
        
        // Mostrar mensaje de error con SweetAlert2
        <?php if ($error && !$success): ?>
        Swal.fire({
            title: 'Error',
            html: `<?php echo addslashes($error); ?>`,
            icon: 'error',
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'Intentar de nuevo'
        });
        <?php endif; ?>
        
        // Mostrar nombre del archivo seleccionado
        const fileInput = document.getElementById('documento_cedula');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    fileNameDisplay.innerHTML = '<i class="fas fa-check-circle" style="color:#27ae60;"></i> Archivo seleccionado: ' + this.files[0].name;
                    fileNameDisplay.style.color = '#27ae60';
                } else {
                    fileNameDisplay.innerHTML = '';
                }
            });
        }
        
        // Validación del formulario con SweetAlert2
        const form = document.getElementById('supportForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                // Validar que haya un archivo
                if (!fileInput.files || !fileInput.files[0]) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Documento requerido',
                        text: 'Debes adjuntar una foto o PDF de tu cédula (ambas caras)',
                        icon: 'warning',
                        confirmButtonColor: '#f39c12',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                // Validar que el archivo sea válido
                const file = fileInput.files[0];
                const validExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
                const extension = file.name.split('.').pop().toLowerCase();
                
                if (!validExtensions.includes(extension)) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Formato no válido',
                        text: 'Usa JPG, PNG o PDF.',
                        icon: 'warning',
                        confirmButtonColor: '#f39c12',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Archivo muy grande',
                        text: 'El archivo no puede superar los 5MB.',
                        icon: 'warning',
                        confirmButtonColor: '#f39c12',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                // Validar campos obligatorios
                const nombre = document.getElementById('nombre_completo')?.value.trim();
                const documento = document.getElementById('numero_documento')?.value.trim();
                const email = document.getElementById('email')?.value.trim();
                const asunto = document.getElementById('asunto')?.value.trim();
                const descripcion = document.getElementById('descripcion')?.value.trim();
                
                if (!nombre || !documento || !email || !asunto || !descripcion) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Campos incompletos',
                        text: 'Por favor completa todos los campos obligatorios.',
                        icon: 'warning',
                        confirmButtonColor: '#f39c12',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                // Validar que el tipo de documento sea CC
                const tipoDocumento = document.getElementById('tipo_documento')?.value;
                if (tipoDocumento !== 'CC') {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Tipo de documento no válido',
                        text: 'Solo se aceptan solicitudes con Cédula de Ciudadanía (CC).',
                        icon: 'warning',
                        confirmButtonColor: '#f39c12',
                        confirmButtonText: 'Entendido'
                    });
                    return false;
                }
                
                // Mostrar loading al enviar
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Enviando...';
            });
        }
        
        // Tema
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const logoImage = document.getElementById('logoImage');
        
        function updateLogo(theme) {
            if (logoImage) {
                if (theme === 'dark') {
                    logoImage.src = '/assets/imagenes/logos/colcars_b.png';
                } else {
                    logoImage.src = '/assets/imagenes/logos/logo_d.png';
                }
            }
        }
        
        if (themeToggle) {
            themeToggle.addEventListener('click', () => {
                const isDark = body.classList.contains('dark-theme');
                if (isDark) {
                    body.classList.remove('dark-theme');
                    body.classList.add('light-theme');
                    themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                    document.cookie = "theme=light; path=/";
                    updateLogo('light');
                } else {
                    body.classList.remove('light-theme');
                    body.classList.add('dark-theme');
                    themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                    document.cookie = "theme=dark; path=/";
                    updateLogo('dark');
                }
            });
        }
        
        // Inicializar logo según tema actual
        const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
        updateLogo(currentTheme);
        
        // Auto-completar si el usuario está logueado
        <?php if ($isLoggedIn && !empty($userData)): ?>
        if (document.getElementById('nombre_completo')) document.getElementById('nombre_completo').value = '<?php echo addslashes($userData['nombre_completo']); ?>';
        if (document.getElementById('numero_documento')) document.getElementById('numero_documento').value = '<?php echo addslashes($userData['numero_documento']); ?>';
        if (document.getElementById('email')) document.getElementById('email').value = '<?php echo addslashes($userData['email']); ?>';
        if (document.getElementById('telefono')) document.getElementById('telefono').value = '<?php echo addslashes($userData['telefono'] ?? ''); ?>';
        if (document.getElementById('ciudad')) document.getElementById('ciudad').value = '<?php echo addslashes($userData['ciudad'] ?? ''); ?>';
        if (document.getElementById('direccion')) document.getElementById('direccion').value = '<?php echo addslashes($userData['direccion'] ?? ''); ?>';
        <?php endif; ?>
    </script>
</body>
</html>