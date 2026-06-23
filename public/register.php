<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\public\register.php
 * EASY CAR LUXURY - Página de Registro
 * MODIFICADO: Formulario más compacto (PC)
 * MODIFICADO: Tema oscuro con logo correcto
 * MODIFICADO: Icono de tema visible
 * MODIFICADO: Usa CDN en lugar de archivos locales
 * MODIFICADO: Agregada sección de Documentos de Identidad (Cédula, RUT, Cámara de Comercio)
 * MODIFICADO: Auto-completar Usuario con el Email completo en tiempo real
 * MODIFICADO: Mostrar Cámara de Comercio solo si selecciona NIT
 * MODIFICADO: CORREGIDA validación de coincidencia de contraseñas
 * MODIFICADO: Mensajes de error/éxito con SweetAlert2
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, redirigir
if (isset($_SESSION['usuario_id'])) {
    header('Location: /dashboard/user/');
    exit;
}

$error = '';
$success = '';
$form_data = [];

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'nombre_completo' => trim($_POST['nombre_completo'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'username' => trim($_POST['username'] ?? ''),
        'tipo_documento' => $_POST['tipo_documento'] ?? 'CC',
        'numero_documento' => trim($_POST['numero_documento'] ?? ''),
        'telefono' => trim($_POST['telefono'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'terminos' => isset($_POST['terminos'])
    ];
    
    // Validaciones
    $errors = [];
    
    if (empty($form_data['nombre_completo'])) {
        $errors[] = 'El nombre completo es requerido';
    }
    
    if (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no es válido';
    }
    
    // El usuario es opcional, si está vacío se usará el email
    if (empty($form_data['username'])) {
        $form_data['username'] = $form_data['email'];
    }
    
    if (strlen($form_data['username']) < 3) {
        $errors[] = 'El usuario debe tener al menos 3 caracteres';
    }
    
    if (empty($form_data['numero_documento']) || strlen($form_data['numero_documento']) < 6) {
        $errors[] = 'El número de documento debe tener al menos 6 dígitos';
    }
    
    if (empty($form_data['telefono']) || strlen($form_data['telefono']) < 10) {
        $errors[] = 'El teléfono debe tener al menos 10 dígitos';
    }
    
    if (strlen($form_data['password']) < 8) {
        $errors[] = 'La contraseña debe tener al menos 8 caracteres';
    }
    
    if ($form_data['password'] !== $form_data['password_confirm']) {
        $errors[] = 'Las contraseñas no coinciden';
    }
    
    if (!$form_data['terminos']) {
        $errors[] = 'Debe aceptar los términos y condiciones';
    }
    
    // Verificar si email ya existe
    if (empty($errors)) {
        try {
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? OR username = ? OR numero_documento = ?");
            $stmt->execute([$form_data['email'], $form_data['username'], $form_data['numero_documento']]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $errors[] = 'El email, usuario o documento ya está registrado';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al verificar disponibilidad';
        }
    }
    
    // Crear usuario
    if (empty($errors)) {
        try {
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            
            $password_hash = password_hash($form_data['password'], PASSWORD_DEFAULT);
            $fecha_expiracion = date('Y-m-d', strtotime('+30 days'));
            
            $stmt = $pdo->prepare("INSERT INTO usuarios (email, username, password_hash, nombre_completo, tipo_documento, numero_documento, telefono, rol_id, tipo_cuenta, fecha_expiracion, limite_publicaciones_int, email_verificado, activo, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 6, 'free', ?, 2, 1, 1, NOW())");
            
            if ($stmt->execute([$form_data['email'], $form_data['username'], $password_hash, $form_data['nombre_completo'], $form_data['tipo_documento'], $form_data['numero_documento'], $form_data['telefono'], $fecha_expiracion])) {
                $user_id = $pdo->lastInsertId();
                
                // ============================================
                // PROCESAR DOCUMENTOS SUBIDOS
                // ============================================
                $documentos_subidos = [];
                
                // Procesar Cédula (siempre se permite)
                if (isset($_FILES['documento_cedula']) && $_FILES['documento_cedula']['error'] === UPLOAD_ERR_OK) {
                    $result = procesarDocumento($pdo, $user_id, 'cedula', $_FILES['documento_cedula']);
                    if ($result['success']) {
                        $documentos_subidos[] = 'Cédula';
                    } else {
                        $errors[] = $result['error'];
                    }
                }
                
                // Procesar RUT (siempre se permite)
                if (isset($_FILES['documento_rut']) && $_FILES['documento_rut']['error'] === UPLOAD_ERR_OK) {
                    $result = procesarDocumento($pdo, $user_id, 'rut', $_FILES['documento_rut']);
                    if ($result['success']) {
                        $documentos_subidos[] = 'RUT';
                    } else {
                        $errors[] = $result['error'];
                    }
                }
                
                // Procesar Cámara de Comercio (solo si el tipo de documento es NIT)
                if ($form_data['tipo_documento'] === 'NIT' && isset($_FILES['documento_camara']) && $_FILES['documento_camara']['error'] === UPLOAD_ERR_OK) {
                    $result = procesarDocumento($pdo, $user_id, 'camara_comercio', $_FILES['documento_camara']);
                    if ($result['success']) {
                        $documentos_subidos[] = 'Cámara de Comercio';
                    } else {
                        $errors[] = $result['error'];
                    }
                }
                
                if (empty($errors)) {
                    $mensaje_documentos = '';
                    if (!empty($documentos_subidos)) {
                        $mensaje_documentos = '<br><br>Documentos subidos: ' . implode(', ', $documentos_subidos);
                    }
                    $success = '¡Registro exitoso! Ya puedes iniciar sesión.' . $mensaje_documentos;
                    $form_data = [];
                } else {
                    $error = implode('<br>', $errors);
                }
            } else {
                $errors[] = 'Error al crear el usuario';
            }
        } catch (Exception $e) {
            $errors[] = 'Error en el registro: ' . $e->getMessage();
            error_log("Registration error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $error = implode('<br>', $errors);
    }
}

// Función para procesar documentos
function procesarDocumento($pdo, $user_id, $tipo, $archivo) {
    $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowed)) {
        return ['success' => false, 'error' => 'El documento debe ser JPG, PNG o PDF'];
    }
    
    if ($archivo['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'El documento no puede superar los 5MB'];
    }
    
    // Crear directorio para el usuario si no existe
    $uploadDir = __DIR__ . '/../uploads/user_documents/user_' . $user_id . '/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $timestamp = time();
    $nombre_limpio = preg_replace('/[^a-zA-Z0-9]/', '_', pathinfo($archivo['name'], PATHINFO_FILENAME));
    $filename = $tipo . '_' . $timestamp . '.' . $extension;
    $filepath = $uploadDir . $filename;
    
    if (move_uploaded_file($archivo['tmp_name'], $filepath)) {
        // Verificar si ya existe un documento de este tipo
        $checkStmt = $pdo->prepare("SELECT id FROM user_documents WHERE user_id = ? AND document_type = ?");
        $checkStmt->execute([$user_id, $tipo]);
        $existing = $checkStmt->fetch();
        
        if ($existing) {
            // Actualizar documento existente
            $stmt = $pdo->prepare("UPDATE user_documents SET file_path = ?, file_name = ?, file_size = ?, mime_type = ?, updated_at = NOW() WHERE user_id = ? AND document_type = ?");
            $stmt->execute([
                '/uploads/user_documents/user_' . $user_id . '/' . $filename,
                $archivo['name'],
                $archivo['size'],
                $archivo['type'],
                $user_id,
                $tipo
            ]);
        } else {
            // Insertar nuevo documento
            $stmt = $pdo->prepare("INSERT INTO user_documents (user_id, document_type, file_path, file_name, file_size, mime_type, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $user_id,
                $tipo,
                '/uploads/user_documents/user_' . $user_id . '/' . $filename,
                $archivo['name'],
                $archivo['size'],
                $archivo['type']
            ]);
        }
        
        return ['success' => true, 'filepath' => $filepath];
    } else {
        return ['success' => false, 'error' => 'Error al subir el documento'];
    }
}

// Tema
$tema = 'light';
if (isset($_COOKIE['theme'])) {
    $tema = $_COOKIE['theme'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Registro - Easy Car Luxury</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
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
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            transition: background 0.3s ease;
            padding: 20px 0;
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
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .floating-logo img {
            height: 50px;
            width: auto;
            transition: transform 0.3s ease;
        }
        
        .floating-logo img:hover {
            transform: scale(1.05);
        }
        
        /* Tarjeta de registro más compacta */
        .register-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 550px;
            margin: 0 15px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        body.dark-theme .register-card {
            background: #050128;
            color: white;
        }
        
        .register-header {
            background: linear-gradient(135deg, #030137, #2980b9);
            color: white;
            padding: 10px 15px;
            text-align: center;
        }
        
        body.dark-theme .register-header {
            background: linear-gradient(135deg, #000010, #010132);
        }
        
        .register-header h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .register-header p {
            margin: 2px 0 0;
            opacity: 0.8;
            font-size: 0.65rem;
        }
        
        .register-body {
            padding: 15px;
        }
        
        .form-control, .form-select {
            border-radius: 6px;
            padding: 5px 8px;
            border: 1px solid #ddd;
            font-size: 0.75rem;
            height: 30px;
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
        
        body.dark-theme .form-label {
            color: #ffffff !important;
        }
        
        body.dark-theme .form-text {
            color: #aaa !important;
        }
        
        body.dark-theme .form-check-label {
            color: #ffffff !important;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #2980b9;
            box-shadow: 0 0 0 0.15rem rgba(41, 128, 185, 0.25);
        }
        
        .form-label {
            font-size: 0.65rem;
            font-weight: 600;
            margin-bottom: 2px;
        }
        
        .btn-register {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            border: none;
            border-radius: 6px;
            padding: 6px;
            font-weight: 600;
            font-size: 0.75rem;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        
        .btn-register:hover {
            transform: translateY(-1px);
            color: white;
            background: linear-gradient(135deg, #0e3a5c, #1a5276);
        }
        
        /* Botón de tema - más visible */
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.6);
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 1rem;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        
        .theme-toggle:hover {
            background: rgba(0,0,0,0.8);
            transform: scale(1.05);
        }
        
        body.light-theme .theme-toggle {
            background: rgba(0,0,0,0.5);
        }
        
        body.dark-theme .theme-toggle {
            background: rgba(255,255,255,0.2);
        }
        
        body.dark-theme .theme-toggle:hover {
            background: rgba(255,255,255,0.35);
        }
        
        .text-decoration-none {
            text-decoration: none;
            font-size: 0.65rem;
        }
        
        body.dark-theme .text-decoration-none {
            color: #5dade2;
        }
        
        .form-check {
            margin-bottom: 8px;
        }
        
        .form-check-label {
            font-size: 0.65rem;
        }
        
        hr {
            margin: 10px 0;
        }
        
        .mb-2 {
            margin-bottom: 6px !important;
        }
        
        /* Compactar filas */
        .row {
            margin-right: -4px;
            margin-left: -4px;
        }
        
        .row > [class*="col-"] {
            padding-right: 4px;
            padding-left: 4px;
        }
        
        /* Password strength */
        .password-strength {
            height: 2px;
            margin-top: 3px;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .strength-weak { width: 25%; background: #dc3545; }
        .strength-medium { width: 50%; background: #ffc107; }
        .strength-strong { width: 75%; background: #28a745; }
        .strength-very-strong { width: 100%; background: #20c997; }
        
        .form-text {
            font-size: 0.6rem;
            margin-top: 2px;
        }
        
        /* Estilos para documentos */
        .section-title {
            font-size: 0.75rem;
            font-weight: 700;
            margin: 10px 0 8px;
            padding-bottom: 3px;
            border-bottom: 2px solid #2980b9;
            display: inline-block;
        }
        
        body.dark-theme .section-title {
            color: #5dade2;
        }
        
        .document-upload-box {
            border: 1px dashed #c0c0c0;
            border-radius: 6px;
            padding: 8px;
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
            font-size: 1.2rem;
            color: #2980b9;
            margin-bottom: 3px;
        }
        
        .document-upload-box p {
            font-size: 0.55rem;
            margin: 0;
            color: #666;
        }
        
        body.dark-theme .document-upload-box p {
            color: #aaa;
        }
        
        .file-name-display {
            font-size: 0.55rem;
            margin-top: 3px;
            color: #27ae60;
        }
        
        .document-optional {
            font-size: 0.5rem;
            color: #888;
            margin-top: 2px;
        }
        
        /* Ocultar cámara de comercio por defecto */
        .camara-comercio-container {
            display: none;
        }
        
        .camara-comercio-container.show {
            display: block;
        }
        
        @media (max-width: 480px) {
            .register-card {
                max-width: 380px;
            }
            .register-body {
                padding: 12px;
            }
            .floating-logo img {
                height: 40px;
            }
            .theme-toggle {
                width: 30px;
                height: 30px;
                font-size: 0.9rem;
                top: 10px;
                right: 10px;
            }
            .document-upload-box {
                padding: 6px;
            }
            .document-upload-box i {
                font-size: 1rem;
            }
            .document-upload-box p {
                font-size: 0.5rem;
            }
        }
    </style>
</head>
<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    
    <!-- Logo flotante - cambia según el tema -->
    <div class="floating-logo">
        <img src="/assets/imagenes/logos/colcars_b.png" alt="Easy Car Luxury" id="logoImage">
    </div>
    
    <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <div class="register-card">
        <div class="register-header">
            <h2><i class="fas fa-user-plus me-1"></i>Crear Cuenta</h2>
            <p>Únete a Colcars - Ingresa tus datos</p>
        </div>
        <div class="register-body">
            <!-- Los mensajes de error/éxito se muestran con SweetAlert2, no con alertas de Bootstrap -->
            
            <form method="POST" action="" enctype="multipart/form-data" id="registerForm">
                <!-- Sección: Datos personales -->
                <div class="section-title">
                    <i class="fas fa-user-circle"></i> Datos personales
                </div>
                
                <!-- Nombre completo -->
                <div class="mb-2">
                    <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="nombre_completo" 
                           value="<?php echo htmlspecialchars($form_data['nombre_completo'] ?? ''); ?>" 
                           placeholder="Ej: Juan Pérez García" required>
                </div>
                
                <!-- Email y Usuario (se autocompleta con el email en tiempo real) -->
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($form_data['email'] ?? ''); ?>" 
                               placeholder="correo@ejemplo.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control" id="username" name="username" 
                               value="<?php echo htmlspecialchars($form_data['username'] ?? ''); ?>" 
                               placeholder="Se autocompleta con tu email">
                        <small class="form-text text-muted">Se autocompleta automáticamente con tu email</small>
                    </div>
                </div>
                
                <!-- Tipo documento y N° Documento -->
                <div class="row mb-2">
                    <div class="col-md-5">
                        <label class="form-label">Tipo Doc. <span class="text-danger">*</span></label>
                        <select class="form-select" id="tipo_documento" name="tipo_documento">
                            <option value="CC" <?php echo ($form_data['tipo_documento'] ?? 'CC') == 'CC' ? 'selected' : ''; ?>>CC</option>
                            <option value="NIT" <?php echo ($form_data['tipo_documento'] ?? '') == 'NIT' ? 'selected' : ''; ?>>NIT</option>
                            <option value="CE" <?php echo ($form_data['tipo_documento'] ?? '') == 'CE' ? 'selected' : ''; ?>>CE</option>
                            <option value="PASAPORTE" <?php echo ($form_data['tipo_documento'] ?? '') == 'PASAPORTE' ? 'selected' : ''; ?>>Pasaporte</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label">N° Documento <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="numero_documento" 
                               value="<?php echo htmlspecialchars($form_data['numero_documento'] ?? ''); ?>" 
                               placeholder="Ej: 12345678" required>
                    </div>
                </div>
                
                <!-- Teléfono -->
                <div class="mb-2">
                    <label class="form-label">Teléfono <span class="text-danger">*</span></label>
                    <input type="tel" class="form-control" name="telefono" 
                           value="<?php echo htmlspecialchars($form_data['telefono'] ?? ''); ?>" 
                           placeholder="3001234567" required>
                    <div class="form-text text-muted">Mínimo 10 dígitos, ej: 3001234567</div>
                </div>
                
                <!-- Contraseña y Confirmar -->
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="••••••••" required>
                        <div class="password-strength" id="passwordStrength"></div>
                        <div class="form-text text-muted">Mínimo 8 caracteres</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirmar <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" 
                               placeholder="••••••••" required>
                        <div class="form-text" id="passwordMatch"></div>
                    </div>
                </div>
                
                <!-- Sección: Documentos de Identidad -->
                <div class="section-title">
                    <i class="fas fa-file-upload"></i> Documentos de Identidad
                </div>
                <p class="text-muted small mb-1">Documentos requeridos para registro</p>
                
                <div class="row">
                    <!-- Cédula -->
                    <div class="col-md-6 mb-2">
                        <div class="document-upload-box" onclick="document.getElementById('documento_cedula').click()">
                            <i class="fas fa-id-card"></i>
                            <p><strong>Cédula</strong></p>
                            <p class="small text-muted">JPG, PNG o PDF (máx 5MB)</p>
                        </div>
                        <input type="file" class="d-none" id="documento_cedula" name="documento_cedula" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div id="fileNameCedula" class="file-name-display"></div>
                    </div>
                    
                    <!-- RUT -->
                    <div class="col-md-6 mb-2">
                        <div class="document-upload-box" onclick="document.getElementById('documento_rut').click()">
                            <i class="fas fa-building"></i>
                            <p><strong>RUT</strong></p>
                            <p class="small text-muted">JPG, PNG o PDF (máx 5MB)</p>
                        </div>
                        <input type="file" class="d-none" id="documento_rut" name="documento_rut" accept=".jpg,.jpeg,.png,.pdf" required>
                        <div id="fileNameRut" class="file-name-display"></div>
                    </div>
                </div>
                
                <!-- Cámara de Comercio (solo visible si selecciona NIT) -->
                <div class="camara-comercio-container" id="camaraComercioContainer">
                    <div class="row">
                        <div class="col-md-12 mb-2">
                            <div class="document-upload-box" onclick="document.getElementById('documento_camara').click()">
                                <i class="fas fa-building"></i>
                                <p><strong>Cámara de Comercio</strong></p>
                                <p class="small text-muted">JPG, PNG o PDF (máx 5MB)</p>
                                <p class="document-optional">Obligatorio para personas jurídicas (NIT)</p>
                            </div>
                            <input type="file" class="d-none" id="documento_camara" name="documento_camara" accept=".jpg,.jpeg,.png,.pdf">
                            <div id="fileNameCamara" class="file-name-display"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Términos -->
                <div class="mb-2 form-check">
                    <input type="checkbox" class="form-check-input" id="terminos" name="terminos" required>
                    <label class="form-check-label" for="terminos">
                        Acepto <a href="#" class="text-decoration-none">Términos y Condiciones</a> <span class="text-danger">*</span>
                    </label>
                </div>
                
                <!-- Botón -->
                <button type="submit" class="btn btn-register" id="submitBtn">
                    <i class="fas fa-user-plus me-1"></i> Registrarme
                </button>
            </form>
            
            <hr>
            
            <div class="text-center">
                <p class="mb-0">
                    ¿Ya tienes cuenta? 
                    <a href="/login" class="text-decoration-none fw-bold">
                        Inicia Sesión <i class="fas fa-arrow-right"></i>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // ==========================================
        // TEMA CLARO/OSCURO
        // ==========================================
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        const logoImage = document.getElementById('logoImage');
        
        function updateLogo(theme) {
            if (logoImage) {
                if (theme === 'dark') {
                    logoImage.src = '/assets/imagenes/logos/colcars_b.png';
                } else {
                    logoImage.src = '/assets/imagenes/logos/colcars.png';
                }
            }
        }
        
        function setTheme(theme) {
            if (theme === 'dark') {
                body.classList.remove('light-theme');
                body.classList.add('dark-theme');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                document.cookie = "theme=dark; path=/; max-age=" + (365 * 24 * 60 * 60);
                updateLogo('dark');
            } else {
                body.classList.remove('dark-theme');
                body.classList.add('light-theme');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                document.cookie = "theme=light; path=/; max-age=" + (365 * 24 * 60 * 60);
                updateLogo('light');
            }
        }
        
        themeToggle?.addEventListener('click', () => {
            const isDark = body.classList.contains('dark-theme');
            if (isDark) {
                setTheme('light');
            } else {
                setTheme('dark');
            }
        });
        
        // Inicializar logo según tema actual
        const currentTheme = body.classList.contains('dark-theme') ? 'dark' : 'light';
        updateLogo(currentTheme);
        
        // ==========================================
        // MOSTRAR MENSAJES CON SWEETALERT2
        // ==========================================
        <?php if ($error && !$success): ?>
        Swal.fire({
            title: 'Error en el registro',
            html: `<?php echo addslashes($error); ?>`,
            icon: 'error',
            confirmButtonColor: '#1a5276',
            confirmButtonText: 'Intentar de nuevo'
        });
        <?php endif; ?>
        
        <?php if ($success): ?>
        Swal.fire({
            title: '¡Registro exitoso!',
            html: `<?php echo addslashes($success); ?>`,
            icon: 'success',
            confirmButtonColor: '#1a5276',
            confirmButtonText: 'Aceptar'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/login';
            }
        });
        <?php endif; ?>
        
        // ==========================================
        // AUTO-COMPLETAR USUARIO CON EL EMAIL COMPLETO
        // ==========================================
        const emailInput = document.getElementById('email');
        const usernameInput = document.getElementById('username');
        
        if (emailInput && usernameInput) {
            emailInput.addEventListener('input', function() {
                let emailValue = this.value;
                let usernameValue = emailValue.replace(/[^a-zA-Z0-9._@-]/g, '');
                usernameInput.value = usernameValue;
            });
        }
        
        // ==========================================
        // MOSTRAR/OCULTAR CÁMARA DE COMERCIO SEGÚN TIPO DE DOCUMENTO
        // ==========================================
        const tipoDocumentoSelect = document.getElementById('tipo_documento');
        const camaraContainer = document.getElementById('camaraComercioContainer');
        
        function toggleCamaraComercio() {
            if (tipoDocumentoSelect && camaraContainer) {
                if (tipoDocumentoSelect.value === 'NIT') {
                    camaraContainer.classList.add('show');
                    document.getElementById('documento_camara').setAttribute('required', 'required');
                } else {
                    camaraContainer.classList.remove('show');
                    const camaraInput = document.getElementById('documento_camara');
                    const camaraDisplay = document.getElementById('fileNameCamara');
                    if (camaraInput) {
                        camaraInput.value = '';
                        camaraInput.removeAttribute('required');
                    }
                    if (camaraDisplay) camaraDisplay.innerHTML = '';
                }
            }
        }
        
        toggleCamaraComercio();
        
        if (tipoDocumentoSelect) {
            tipoDocumentoSelect.addEventListener('change', toggleCamaraComercio);
        }
        
        // ==========================================
        // MOSTRAR NOMBRE DE ARCHIVOS SELECCIONADOS
        // ==========================================
        document.getElementById('documento_cedula')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const display = document.getElementById('fileNameCedula');
            if (fileName) {
                display.innerHTML = '<i class="fas fa-check-circle"></i> ' + fileName;
                display.style.color = '#27ae60';
            } else {
                display.innerHTML = '';
            }
        });
        
        document.getElementById('documento_rut')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const display = document.getElementById('fileNameRut');
            if (fileName) {
                display.innerHTML = '<i class="fas fa-check-circle"></i> ' + fileName;
                display.style.color = '#27ae60';
            } else {
                display.innerHTML = '';
            }
        });
        
        document.getElementById('documento_camara')?.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const display = document.getElementById('fileNameCamara');
            if (fileName) {
                display.innerHTML = '<i class="fas fa-check-circle"></i> ' + fileName;
                display.style.color = '#27ae60';
            } else {
                display.innerHTML = '';
            }
        });
        
        // ==========================================
        // VALIDACIÓN DE CONTRASEÑA (fuerza)
        // ==========================================
        const passwordField = document.getElementById('password');
        const confirmPasswordField = document.getElementById('password_confirm');
        const passwordMatchSpan = document.getElementById('passwordMatch');
        
        function checkPasswordMatch() {
            const password = passwordField.value;
            const confirm = confirmPasswordField.value;
            
            if (confirm === '') {
                passwordMatchSpan.innerHTML = '';
                passwordMatchSpan.className = 'form-text';
            } else if (password === confirm && password !== '') {
                passwordMatchSpan.innerHTML = '<i class="fas fa-check-circle text-success"></i> Las contraseñas coinciden';
                passwordMatchSpan.className = 'form-text text-success';
            } else {
                passwordMatchSpan.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Las contraseñas no coinciden';
                passwordMatchSpan.className = 'form-text text-danger';
            }
        }
        
        function checkPasswordStrength() {
            const password = passwordField.value;
            let strength = 0;
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]+/)) strength++;
            if (password.match(/[A-Z]+/)) strength++;
            if (password.match(/[0-9]+/)) strength++;
            if (password.match(/[$@#&!]+/)) strength++;
            
            let strengthClass = '';
            
            if (strength <= 2) {
                strengthClass = 'strength-weak';
            } else if (strength === 3) {
                strengthClass = 'strength-medium';
            } else if (strength === 4) {
                strengthClass = 'strength-strong';
            } else if (strength === 5) {
                strengthClass = 'strength-very-strong';
            }
            
            const strengthDiv = document.getElementById('passwordStrength');
            if (strengthDiv) {
                strengthDiv.className = 'password-strength ' + strengthClass;
            }
            
            // También verificar coincidencia cuando cambia la contraseña
            checkPasswordMatch();
        }
        
        if (passwordField && confirmPasswordField) {
            passwordField.addEventListener('keyup', checkPasswordStrength);
            confirmPasswordField.addEventListener('keyup', checkPasswordMatch);
        }
        
        // Validar archivos antes de enviar
        function validarArchivo(file, tipo) {
            if (!file) return true;
            
            const extensiones = ['jpg', 'jpeg', 'png', 'pdf'];
            const extension = file.name.split('.').pop().toLowerCase();
            
            if (!extensiones.includes(extension)) {
                Swal.fire({
                    title: 'Formato no válido',
                    text: 'El documento ' + tipo + ' debe ser JPG, PNG o PDF',
                    icon: 'warning',
                    confirmButtonColor: '#1a5276'
                });
                return false;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire({
                    title: 'Archivo muy grande',
                    text: 'El documento ' + tipo + ' no puede superar los 5MB',
                    icon: 'warning',
                    confirmButtonColor: '#1a5276'
                });
                return false;
            }
            
            return true;
        }
        
        // Prevenir envío duplicado y validar
        const form = document.getElementById('registerForm');
        const submitBtn = document.getElementById('submitBtn');
        
        if (form) {
            form.addEventListener('submit', function(e) {
                const cedula = document.getElementById('documento_cedula').files[0];
                const rut = document.getElementById('documento_rut').files[0];
                const tipoDoc = document.getElementById('tipo_documento')?.value;
                const camara = document.getElementById('documento_camara').files[0];
                
                // Validar cédula (obligatoria)
                if (!cedula) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Documento requerido',
                        text: 'Debes subir tu cédula de ciudadanía.',
                        icon: 'warning',
                        confirmButtonColor: '#1a5276'
                    });
                    return false;
                }
                if (!validarArchivo(cedula, 'Cédula')) {
                    e.preventDefault();
                    return false;
                }
                
                // Validar RUT (obligatorio)
                if (!rut) {
                    e.preventDefault();
                    Swal.fire({
                        title: 'Documento requerido',
                        text: 'Debes subir tu RUT.',
                        icon: 'warning',
                        confirmButtonColor: '#1a5276'
                    });
                    return false;
                }
                if (!validarArchivo(rut, 'RUT')) {
                    e.preventDefault();
                    return false;
                }
                
                // Si el tipo de documento es NIT, la cámara de comercio es obligatoria
                if (tipoDoc === 'NIT') {
                    if (!camara) {
                        e.preventDefault();
                        Swal.fire({
                            title: 'Documento requerido',
                            text: 'Para registrarte como persona jurídica (NIT), debes subir tu Cámara de Comercio.',
                            icon: 'warning',
                            confirmButtonColor: '#1a5276'
                        });
                        return false;
                    }
                    if (!validarArchivo(camara, 'Cámara de Comercio')) {
                        e.preventDefault();
                        return false;
                    }
                }
                
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i> Registrando...';
            });
        }
    </script>
</body>
</html>