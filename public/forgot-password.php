<?php
/**
 * EASY CAR LUXURY - Página de Recuperación de Contraseña
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit-log.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, redirigir según su rol
if (isset($_SESSION['usuario_id']) || isset($_SESSION['user_id'])) {
    $userRole = $_SESSION['user_role'] ?? '';
    $adminRoles = ['superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor'];
    
    if (in_array($userRole, $adminRoles)) {
        header('Location: /easycarluxury/dashboard/admin/index.php');
    } else {
        header('Location: /easycarluxury/dashboard/user/index.php');
    }
    exit;
}

$error = '';
$success = '';
$step = 'request'; // request, verify, reset

// ============================================
// PASO 1: Solicitar recuperación (enviar email)
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Por favor ingresa tu correo electrónico';
    } else {
        try {
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            
            if (!$pdo) {
                throw new Exception('Error de conexión a la base de datos');
            }
            
            // Buscar usuario por email
            $query = "SELECT id, email, nombre_completo, activo FROM usuarios WHERE email = :email";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['activo'] == 1) {
                // Generar token único
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Guardar token en la base de datos
                $updateQuery = "UPDATE usuarios SET reset_password_token = :token, reset_password_expires = :expires WHERE id = :id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([
                    ':token' => $token,
                    ':expires' => $expires,
                    ':id' => $user['id']
                ]);
                
                // Construir link de recuperación
                $resetLink = "https://" . $_SERVER['HTTP_HOST'] . "/easycarluxury/public/reset-password.php?token=" . $token;
                
                // Enviar email
                $subject = "Recuperación de contraseña - Easy Car Luxury";
                $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #1a5276, #2980b9); color: white; padding: 20px; text-align: center; }
                        .content { padding: 20px; background: #f9f9f9; }
                        .button { display: inline-block; padding: 12px 24px; background: #2980b9; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                        .footer { text-align: center; padding: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Easy Car Luxury</h2>
                            <p>Recuperación de contraseña</p>
                        </div>
                        <div class='content'>
                            <p>Hola <strong>" . htmlspecialchars($user['nombre_completo']) . "</strong>,</p>
                            <p>Hemos recibido una solicitud para restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva contraseña:</p>
                            <p style='text-align: center;'>
                                <a href='" . $resetLink . "' class='button' style='color: white;'>Restablecer contraseña</a>
                            </p>
                            <p>Si no solicitaste este cambio, puedes ignorar este mensaje. El enlace expirará en 1 hora.</p>
                            <p>Si el botón no funciona, copia y pega este enlace en tu navegador:</p>
                            <p><small>" . $resetLink . "</small></p>
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
                $headers .= "From: Easy Car Luxury <no-reply@easycarluxury.com>" . "\r\n";
                
                if (mail($user['email'], $subject, $message, $headers)) {
                    $success = 'Hemos enviado un enlace de recuperación a tu correo electrónico. Revisa tu bandeja de entrada.';
                    $step = 'sent';
                } else {
                    $error = 'Error al enviar el correo. Por favor intenta nuevamente.';
                }
                
                // Registrar en auditoría
                $audit = new AuditLog($pdo, $user['id'], $user['email'], null);
                $audit->register('PASSWORD_RESET_REQUEST', 'usuarios', $user['id'], null, null, '/easycarluxury/public/forgot-password.php', 'Solicitud de recuperación de contraseña');
                
            } else {
                // No revelar si el email existe o no por seguridad
                $success = 'Si el correo existe en nuestro sistema, recibirás un enlace de recuperación.';
                $step = 'sent';
            }
        } catch (Exception $e) {
            $error = 'Error al procesar la solicitud. Intente nuevamente.';
            error_log("Password reset error: " . $e->getMessage());
        }
    }
}

// ============================================
// PASO 2: Verificar token y mostrar formulario
// ============================================
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $step = 'reset_form';
    
    try {
        $database = Database::getInstance();
        $pdo = $database->getConnection();
        
        // Verificar token
        $query = "SELECT id, email, nombre_completo FROM usuarios 
                  WHERE reset_password_token = :token 
                  AND reset_password_expires > NOW() 
                  AND activo = 1";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $error = 'El enlace de recuperación ha expirado o es inválido. Por favor solicita uno nuevo.';
            $step = 'expired';
        }
    } catch (Exception $e) {
        $error = 'Error al verificar el token.';
        error_log("Token verification error: " . $e->getMessage());
    }
}

// ============================================
// PASO 3: Resetear contraseña
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirm_password)) {
        $error = 'Por favor completa todos los campos';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden';
    } else {
        try {
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            
            // Verificar token nuevamente
            $query = "SELECT id, email, nombre_completo FROM usuarios 
                      WHERE reset_password_token = :token 
                      AND reset_password_expires > NOW() 
                      AND activo = 1";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Actualizar contraseña
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE usuarios 
                                SET password_hash = :password_hash, 
                                    reset_password_token = NULL, 
                                    reset_password_expires = NULL 
                                WHERE id = :id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([
                    ':password_hash' => $password_hash,
                    ':id' => $user['id']
                ]);
                
                // Registrar en auditoría
                $audit = new AuditLog($pdo, $user['id'], $user['email'], null);
                $audit->register('PASSWORD_RESET_COMPLETED', 'usuarios', $user['id'], null, null, '/easycarluxury/public/reset-password.php', 'Contraseña restablecida exitosamente');
                
                $success = 'Tu contraseña ha sido actualizada correctamente. Ahora puedes iniciar sesión con tu nueva contraseña.';
                $step = 'completed';
            } else {
                $error = 'El enlace de recuperación ha expirado o es inválido. Por favor solicita uno nuevo.';
            }
        } catch (Exception $e) {
            $error = 'Error al actualizar la contraseña. Intente nuevamente.';
            error_log("Password reset error: " . $e->getMessage());
        }
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - Easy Car Luxury</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS LOCAL -->
    <link rel="stylesheet" href="/easycarluxury/assets/libs/bootstrap/css/bootstrap.min.css">
    
    <!-- Font Awesome CSS LOCAL -->
    <link rel="stylesheet" href="/easycarluxury/assets/libs/fontawesome/css/all.min.css">
    
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
            position: relative;
        }
        
        /* Tema claro - TUS COLORES ORIGINALES */
        body.light-theme {
            background: linear-gradient(135deg, #1a5276, #2980b9);
        }
        
        /* Tema oscuro - TUS COLORES ORIGINALES */
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
            height: 150px;
            width: auto;
            transition: transform 0.3s ease;
        }
        
        .floating-logo img:hover {
            transform: scale(1.05);
        }
        
        .recovery-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 400px;
            width: 100%;
            margin: 15px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        body.dark-theme .recovery-card {
            background: #050128;
            color: white;
        }
        
        .recovery-header {
            background: linear-gradient(135deg, #030137, #2980b9);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        body.dark-theme .recovery-header {
            background: linear-gradient(135deg, #000010, #010132);
        }
        
        .recovery-header h2 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .recovery-header p {
            margin: 5px 0 0;
            opacity: 0.8;
            font-size: 0.75rem;
        }
        
        .recovery-body {
            padding: 25px;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 8px 12px;
            border: 1px solid #e0e0e0;
            font-size: 0.85rem;
            height: 38px;
        }
        
        body.dark-theme .form-control {
            background: #2a2a3e;
            border-color: #3a3a4e;
            color: white;
        }
        
        body.dark-theme .form-control:focus {
            background: #2a2a3e;
            color: white;
        }
        
        .form-control:focus {
            border-color: #2980b9;
            box-shadow: 0 0 0 0.15rem rgba(41, 128, 185, 0.25);
        }
        
        .form-label {
            font-size: 0.8rem;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .btn-recovery {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            border: none;
            border-radius: 10px;
            padding: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            width: 100%;
            color: white;
            transition: transform 0.2s;
        }
        
        .btn-recovery:hover {
            transform: translateY(-1px);
            color: white;
            background: linear-gradient(135deg, #0e3a5c, #1a5276);
        }
        
        .input-group-text {
            background: transparent;
            border-right: none;
            padding: 8px 10px;
            font-size: 0.85rem;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        body.dark-theme .input-group-text {
            background: #2a2a3e;
            border-color: #3a3a4e;
            color: white;
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
        
        .text-decoration-none {
            text-decoration: none;
            font-size: 0.75rem;
        }
        
        body.dark-theme .text-decoration-none {
            color: #5dade2;
        }
        
        .alert {
            border-radius: 10px;
            padding: 8px 12px;
            font-size: 0.75rem;
            margin-bottom: 15px;
        }
        
        hr {
            margin: 15px 0;
        }
        
        .mb-3 {
            margin-bottom: 12px !important;
        }
        
        .password-requirements {
            font-size: 0.7rem;
            color: #666;
            margin-top: 5px;
        }
        
        body.dark-theme .password-requirements {
            color: #aaa;
        }
        
        .password-requirements i {
            margin-right: 5px;
        }
        
        .password-requirements .valid {
            color: #27ae60;
        }
        
        .password-requirements .invalid {
            color: #e74c3c;
        }
        
        .success-icon {
            font-size: 3rem;
            color: #27ae60;
            margin-bottom: 15px;
        }
        
        @media (max-width: 480px) {
            .floating-logo img {
                height: 45px;
            }
            .recovery-card {
                max-width: 340px;
            }
        }
    </style>
</head>
<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    
    <!-- Logo flotante -->
    <div class="floating-logo">
        <img src="/easycarluxury/assets/imagenes/logos/colcars_b.png" alt="Easy Car Luxury">
    </div>
    
    <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <div class="recovery-card">
        <div class="recovery-header">
            <h2><i class="fas fa-key me-2"></i>RECUPERAR CONTRASEÑA</h2>
            <p>Te ayudamos a restablecer tu acceso</p>
        </div>
        <div class="recovery-body">
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($step === 'completed'): ?>
                <!-- PASO COMPLETADO - ÉXITO -->
                <div class="text-center">
                    <div class="success-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p>Tu contraseña ha sido actualizada exitosamente.</p>
                    <a href="login.php" class="btn btn-recovery mt-3">
                        <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                    </a>
                </div>

            <?php elseif ($step === 'reset_form' && isset($user)): ?>
                <!-- PASO 2: Formulario para nueva contraseña -->
                <form method="POST" action="" id="resetForm">
                    <input type="hidden" name="action" value="reset">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="mb-3">
                        <label for="email_display" class="form-label">Restableciendo para</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="text" class="form-control" id="email_display" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements" id="passwordRequirements">
                            <small><i class="fas fa-info-circle"></i> La contraseña debe tener al menos 6 caracteres</small>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="••••••••" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-requirements" id="confirmRequirements">
                            <small><i class="fas fa-info-circle"></i> Las contraseñas deben coincidir</small>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-recovery" id="submitBtn">
                        <i class="fas fa-save me-2"></i> Restablecer Contraseña
                    </button>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>

            <?php elseif ($step === 'expired'): ?>
                <!-- TOKEN EXPIRADO -->
                <div class="text-center">
                    <i class="fas fa-hourglass-end fa-3x mb-3" style="color: #e74c3c;"></i>
                    <p>El enlace de recuperación ha expirado o es inválido.</p>
                    <p class="small">Por favor solicita un nuevo enlace a continuación.</p>
                    <hr>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="request">
                        <div class="mb-3">
                            <input type="email" class="form-control" name="email" placeholder="tu@email.com" required>
                        </div>
                        <button type="submit" class="btn btn-recovery">
                            <i class="fas fa-paper-plane me-2"></i> Enviar nuevo enlace
                        </button>
                    </form>
                    <hr>
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                    </a>
                </div>

            <?php elseif ($step === 'sent'): ?>
                <!-- EMAIL ENVIADO -->
                <div class="text-center">
                    <i class="fas fa-envelope-open-text fa-3x mb-3" style="color: #27ae60;"></i>
                    <p>¡Listo! Hemos enviado las instrucciones a tu correo electrónico.</p>
                    <p class="small text-muted">Revisa tu bandeja de entrada y sigue los pasos para restablecer tu contraseña.</p>
                    <hr>
                    <a href="login.php" class="btn btn-recovery">
                        <i class="fas fa-sign-in-alt me-2"></i> Volver al inicio de sesión
                    </a>
                </div>

            <?php else: ?>
                <!-- PASO 1: Solicitar email -->
                <form method="POST" action="">
                    <input type="hidden" name="action" value="request">
                    
                    <p class="text-center mb-3 small">
                        Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña.
                    </p>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-envelope"></i>
                            </span>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="correo@ejemplo.com" required autofocus>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-recovery">
                        <i class="fas fa-paper-plane me-2"></i> Enviar enlace de recuperación
                    </button>
                </form>
                
                <hr>
                
                <div class="text-center">
                    <p class="mb-2">
                        <a href="login.php" class="text-decoration-none">
                            <i class="fas fa-arrow-left"></i> Volver al inicio de sesión
                        </a>
                    </p>
                    <p class="mb-0">
                        ¿No tienes cuenta? 
                        <a href="register.php" class="text-decoration-none fw-bold">
                            Regístrate aquí <i class="fas fa-arrow-right"></i>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="/easycarluxury/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword(fieldId) {
            const password = document.getElementById(fieldId);
            const type = password.type === 'password' ? 'text' : 'password';
            password.type = type;
        }
        
        // Validación de contraseña en tiempo real
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');
        
        function validatePassword() {
            if (!passwordInput || !confirmInput) return;
            
            const password = passwordInput.value;
            const confirm = confirmInput.value;
            const isValid = password.length >= 6 && password === confirm;
            
            if (submitBtn) {
                submitBtn.disabled = !isValid;
            }
            
            // Actualizar indicadores visuales
            const confirmReq = document.getElementById('confirmRequirements');
            if (confirmReq) {
                if (confirm.length > 0) {
                    if (password === confirm && password.length >= 6) {
                        confirmReq.innerHTML = '<small><i class="fas fa-check-circle valid"></i> Las contraseñas coinciden</small>';
                    } else {
                        confirmReq.innerHTML = '<small><i class="fas fa-times-circle invalid"></i> Las contraseñas no coinciden</small>';
                    }
                } else {
                    confirmReq.innerHTML = '<small><i class="fas fa-info-circle"></i> Las contraseñas deben coincidir</small>';
                }
            }
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('input', validatePassword);
        }
        if (confirmInput) {
            confirmInput.addEventListener('input', validatePassword);
        }
        
        // Tema
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        themeToggle?.addEventListener('click', () => {
            const isDark = body.classList.contains('dark-theme');
            if (isDark) {
                body.classList.remove('dark-theme');
                body.classList.add('light-theme');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                document.cookie = "theme=light; path=/";
            } else {
                body.classList.remove('light-theme');
                body.classList.add('dark-theme');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                document.cookie = "theme=dark; path=/";
            }
        });
    </script>
</body>
</html>