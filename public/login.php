<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\public\login.php
 * EASY CAR LUXURY - Página de Login
 * MODIFICADO: Usa CDN en lugar de archivos locales
 * MODIFICADO: Rutas absolutas corregidas (sin /easycarluxury/)
 * MODIFICADO: Enlaces corregidos a páginas existentes (soporte.php y register.php)
 * MODIFICADO: Manejo de cierre de sesión para enlaces rotos y errores
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/audit-log.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// NUEVO: MANEJO DE CIERRE DE SESIÓN POR ENLACES ROTOS O ERRORES
// ==========================================
// Si viene con error de sesión, cerrar sesión automáticamente
if(isset($_GET['error']) && ($_GET['error'] == 'sesion_expirada' || $_GET['error'] == 'acceso_denegado' || $_GET['error'] == 'error_servidor' || $_GET['error'] == 'sesion_cerrada')) {
    // Destruir la sesión completamente
    session_destroy();
    
    // Limpiar cookies de sesión si existen
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Guardar mensaje de error para mostrar
    $mensaje_error = '';
    switch($_GET['error']) {
        case 'sesion_expirada':
            $mensaje_error = '⏰ Tu sesión ha expirado o el enlace no es válido. Por favor, inicia sesión nuevamente.';
            break;
        case 'acceso_denegado':
            $mensaje_error = '🚫 No tienes permiso para acceder a esta página.';
            break;
        case 'error_servidor':
            $mensaje_error = '⚡ Ha ocurrido un error en el servidor. Por favor, inicia sesión nuevamente.';
            break;
        case 'sesion_cerrada':
            $mensaje_error = '🔒 Sesión cerrada correctamente.';
            break;
        default:
            $mensaje_error = '🔒 Por favor, inicia sesión para continuar.';
    }
    
    // Asignar el error para mostrarlo en el formulario
    $error = $mensaje_error;
}
// ==========================================
// FIN NUEVO: MANEJO DE CIERRE DE SESIÓN
// ==========================================

// Si ya está logueado, redirigir según su rol
if (isset($_SESSION['usuario_id']) || isset($_SESSION['user_id'])) {
    $userRole = $_SESSION['user_role'] ?? '';
    $adminRoles = ['superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor'];
    
    if (in_array($userRole, $adminRoles)) {
        header('Location: /dashboard/admin/');
    } else {
        header('Location: /dashboard/user/');
    }
    exit;
}

$error = '';
$success = '';

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor complete todos los campos';
    } else {
        try {
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            
            if (!$pdo) {
                throw new Exception('Error de conexión a la base de datos');
            }
            
            // Buscar usuario por email
            $query = "SELECT u.*, r.nombre as rol_nombre, r.id as rol_id
                     FROM usuarios u 
                     JOIN roles r ON u.rol_id = r.id 
                     WHERE u.email = :email AND u.activo = 1";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password_hash'])) {
                // Actualizar último acceso
                $updateQuery = "UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id";
                $updateStmt = $pdo->prepare($updateQuery);
                $updateStmt->execute([':id' => $user['id']]);
                
                // Crear sesión - AMBAS variables para compatibilidad
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['user_id'] = $user['id'];  // Para compatibilidad con auth.php
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_name'] = $user['nombre_completo'];
                $_SESSION['nombre_completo'] = $user['nombre_completo'];
                $_SESSION['full_name'] = $user['nombre_completo'];
                $_SESSION['user_role'] = $user['rol_nombre'];
                $_SESSION['rol_nombre'] = $user['rol_nombre'];
                $_SESSION['user_tipo_cuenta'] = $user['tipo_cuenta'];
                $_SESSION['tipo_cuenta'] = $user['tipo_cuenta'];
                $_SESSION['rol_id'] = $user['rol_id'];
                
                // ============================================
                // REGISTRAR INICIO DE SESIÓN EN AUDITORÍA
                // ============================================
                $audit = new AuditLog($pdo, $user['id'], $user['email'], $user['rol_nombre']);
                $audit->registerLogin('Inicio de sesión exitoso desde ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'dispositivo desconocido'));
                
                // Roles que pueden acceder al panel de administración
                $adminRoles = ['superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor'];
                
                // Redirigir según rol
                if (in_array($user['rol_nombre'], $adminRoles)) {
                    header('Location: /dashboard/admin/');
                } else {
                    header('Location: /dashboard/user/');
                }
                exit;
            } else {
                $error = 'Email o contraseña incorrectos';
                
                // Registrar intento de login fallido
                $audit = new AuditLog($pdo, null, $email, null);
                $audit->register('LOGIN_FAILED', 'session', null, null, null, '/login', 'Intento de inicio de sesión fallido para email: ' . $email);
            }
        } catch (Exception $e) {
            $error = 'Error al iniciar sesión. Intente nuevamente.';
            error_log("Login error: " . $e->getMessage());
            
            // Registrar error de login
            $audit = new AuditLog($pdo, null, $email ?? 'unknown', null);
            $audit->register('LOGIN_ERROR', 'session', null, null, null, '/login', 'Error en inicio de sesión: ' . $e->getMessage());
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
    <title>Iniciar Sesión - Easy Car Luxury</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        
        /* Logo flotante - NO AFECTA AL LOGIN */
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
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 380px;
            width: 100%;
            margin: 15px;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        body.dark-theme .login-card {
            background: #050128;
            color: white;
        }
        
        .login-header {
            background: linear-gradient(135deg, #030137, #2980b9);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        body.dark-theme .login-header {
            background: linear-gradient(135deg, #000010, #010132);
        }
        
        .login-header h2 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .login-header p {
            margin: 5px 0 0;
            opacity: 0.8;
            font-size: 0.75rem;
        }
        
        .login-body {
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
        
        .btn-login {
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
        
        .btn-login:hover {
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
        
        .form-check {
            margin-bottom: 15px;
        }
        
        .form-check-label {
            font-size: 0.75rem;
        }
        
        hr {
            margin: 15px 0;
        }
        
        .btn-outline-secondary {
            padding: 0 10px;
            font-size: 0.8rem;
        }
        
        .mb-3 {
            margin-bottom: 12px !important;
        }
        
        @media (max-width: 480px) {
            .floating-logo img {
                height: 45px;
            }
            .login-card {
                max-width: 340px;
            }
        }
    </style>
</head>
<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    
    <!-- Logo flotante -->
    <div class="floating-logo">
        <img src="/assets/imagenes/logos/colcars_b.png" alt="Easy Car Luxury">
    </div>
    
    <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <div class="login-card">
        <div class="login-header">
            <h2><br>INICIA SESION EN TU CUENTA </h2>
            <p style="font-size: 20px"><br></p>
        </div>
        <div class="login-body">
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

            <form method="POST" action="">
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
                
                <div class="mb-3">
                    <label for="password" class="form-label">Contraseña</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="••••••••" required>
                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label" for="remember">Recordarme</label>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i> Iniciar Sesión
                </button>
            </form>
            
            <hr>
            
            <div class="text-center">
                <p class="mb-2">
                    <a href="/soporte.php" class="text-decoration-none">
                        <i class="fas fa-key"></i> ¿Olvidaste tu contraseña?
                    </a>
                </p>
                <p class="mb-0">
                    ¿No tienes cuenta? 
                    <a href="/register.php" class="text-decoration-none fw-bold">
                        Regístrate aquí <i class="fas fa-arrow-right"></i>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle - CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const type = password.type === 'password' ? 'text' : 'password';
            password.type = type;
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