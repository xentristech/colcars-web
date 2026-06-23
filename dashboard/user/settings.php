<?php
/**
 * Colcars - Configuración del Usuario
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

$db = Database::getInstance();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

$error = '';
$success = '';

// Crear tabla user_settings si no existe
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        user_id INT NOT NULL UNIQUE, 
        notificaciones_email TINYINT DEFAULT 1, 
        notificaciones_whatsapp TINYINT DEFAULT 0, 
        auto_renovacion TINYINT DEFAULT 0, 
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $notificaciones_email = isset($_POST['notificaciones_email']) ? 1 : 0;
        $notificaciones_whatsapp = isset($_POST['notificaciones_whatsapp']) ? 1 : 0;
        $auto_renovacion = isset($_POST['auto_renovacion']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("INSERT INTO user_settings (user_id, notificaciones_email, notificaciones_whatsapp, auto_renovacion) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE 
                                   notificaciones_email = VALUES(notificaciones_email), 
                                   notificaciones_whatsapp = VALUES(notificaciones_whatsapp), 
                                   auto_renovacion = VALUES(auto_renovacion)");
            $stmt->execute([$user_id, $notificaciones_email, $notificaciones_whatsapp, $auto_renovacion]);
            $success = 'Configuración guardada correctamente';
        } catch (Exception $e) {
            $error = 'Error al guardar la configuración';
        }
    }
}

$settings = $db->getOne("SELECT * FROM user_settings WHERE user_id = ?", [$user_id]);

$csrf_token = generateCSRFToken();
$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ============================================
           ESTRUCTURA GLOBAL
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* CONTENEDOR PRINCIPAL */
        .dashboard-wrapper {
            display: flex;
            flex: 1;
            width: 100%;
        }

        /* COLUMNA DEL SIDEBAR - ANCHO FIJO */
        .sidebar-column {
            flex-shrink: 0;
            width: 260px;
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* COLUMNA DEL CONTENIDO */
        .content-column {
            flex: 1;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
        }

        /* CONTENIDO PRINCIPAL */
        .main-content {
            padding: 20px;
            flex: 1;
        }

        /* ============================================
           ESTILOS DEL CONTENIDO
           ============================================ */
        :root {
            --bg-primary: #f8f9fa;
            --bg-secondary: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --border-color: #2a2a3e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO OSCURO
           ============================================ */
        [data-theme="dark"] body,
        [data-theme="dark"] .main-content,
        [data-theme="dark"] .settings-card,
        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6,
        [data-theme="dark"] p,
        [data-theme="dark"] span:not(.badge):not(.alert),
        [data-theme="dark"] div:not(.alert):not(.badge):not(.modal-content),
        [data-theme="dark"] small,
        [data-theme="dark"] strong,
        [data-theme="dark"] label,
        [data-theme="dark"] .text-muted,
        [data-theme="dark"] .form-label {
            color: #ffffff !important;
        }

        [data-theme="dark"] i,
        [data-theme="dark"] .fas,
        [data-theme="dark"] .far,
        [data-theme="dark"] .fab {
            color: #ffffff !important;
        }

        [data-theme="dark"] a:not(.btn):not(.nav-link) {
            color: #a0c4ff !important;
        }

        [data-theme="dark"] .text-muted,
        [data-theme="dark"] small.text-muted {
            color: #c0c0c0 !important;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #5a1a1a;
            color: #ffcccc !important;
            border-color: #8b3a3a;
        }

        [data-theme="dark"] .alert-success {
            background-color: #1a4a2a;
            color: #ccffcc !important;
            border-color: #2a6a3a;
        }

        [data-theme="dark"] .alert-danger i,
        [data-theme="dark"] .alert-success i {
            color: inherit !important;
        }

        /* INPUTS SELECT EN MODO OSCURO - COLOR #222F58 */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: #2a3a6a !important;
            color: #ffffff !important;
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        [data-theme="dark"] .form-control::placeholder {
            color: #a0a0b0 !important;
        }

        /* FORM CHECK EN MODO OSCURO */
        [data-theme="dark"] .form-check-input {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .form-check-input:checked {
            background-color: #667eea !important;
            border-color: #667eea !important;
        }

        [data-theme="dark"] .form-check-label {
            color: #ffffff !important;
        }

        /* BOTONES EN MODO OSCURO */
        [data-theme="dark"] .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-primary:hover {
            background-color: #5a6fd6;
            border-color: #5a6fd6;
        }

        [data-theme="dark"] .btn-outline-danger {
            color: #ffffff !important;
            border-color: #dc3545;
        }

        [data-theme="dark"] .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff !important;
        }

        /* FOOTER EN MODO OSCURO */
        [data-theme="dark"] .dashboard-footer {
            background: #16213e;
            border-top-color: #2a2a3e;
        }

        [data-theme="dark"] .dashboard-footer a {
            color: #a0c4ff !important;
        }

        [data-theme="dark"] .dashboard-footer .footer-social a {
            background: rgba(102, 126, 234, 0.2);
            color: #ffffff !important;
        }

        [data-theme="dark"] .dashboard-footer .footer-social a i {
            color: #ffffff !important;
        }

        /* MEMBERSHIP BADGE EN MODO OSCURO */
        [data-theme="dark"] .membership-badge {
            color: #ffffff !important;
        }

        [data-theme="dark"] .membership-badge i,
        [data-theme="dark"] .membership-badge small {
            color: #ffffff !important;
        }

        /* BOTÓN TEMA EN MODO OSCURO */
        [data-theme="dark"] .btn-theme {
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-theme i {
            color: #ffffff !important;
        }

        /* OFFCANVAS MÓVIL EN MODO OSCURO */
        [data-theme="dark"] .mobile-offcanvas {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%) !important;
        }

        [data-theme="dark"] .mobile-offcanvas .nav-link {
            color: rgba(255,255,255,0.8) !important;
        }

        [data-theme="dark"] .mobile-offcanvas .nav-link:hover,
        [data-theme="dark"] .mobile-offcanvas .nav-link.active {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        /* THEME OPTION EN MODO OSCURO */
        [data-theme="dark"] .theme-option {
            color: #ffffff !important;
        }

        [data-theme="dark"] .theme-option i {
            color: #ffffff !important;
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .settings-card,
        [data-theme="light"] h1,
        [data-theme="light"] h2,
        [data-theme="light"] h3,
        [data-theme="light"] h4,
        [data-theme="light"] h5,
        [data-theme="light"] h6,
        [data-theme="light"] p,
        [data-theme="light"] span,
        [data-theme="light"] small,
        [data-theme="light"] strong,
        [data-theme="light"] label,
        [data-theme="light"] .form-label {
            color: #212529 !important;
        }

        [data-theme="light"] i,
        [data-theme="light"] .fas,
        [data-theme="light"] .far,
        [data-theme="light"] .fab {
            color: #212529 !important;
        }

        [data-theme="light"] .text-muted {
            color: #6c757d !important;
        }

        [data-theme="light"] .sidebar-column,
        [data-theme="light"] .sidebar-column *,
        [data-theme="light"] .sidebar-column .nav-link,
        [data-theme="light"] .sidebar-column .nav-link i,
        [data-theme="light"] .sidebar-column .nav-link span,
        [data-theme="light"] .sidebar-column h3,
        [data-theme="light"] .sidebar-column h4,
        [data-theme="light"] .sidebar-column p,
        [data-theme="light"] .sidebar-column div:not(.alert) {
            color: #ffffff !important;
        }

        [data-theme="light"] .membership-badge,
        [data-theme="light"] .membership-badge i,
        [data-theme="light"] .membership-badge small,
        [data-theme="light"] .membership-badge span {
            color: #ffffff !important;
        }

        [data-theme="light"] .btn-outline-danger i {
            color: #dc3545 !important;
        }

        [data-theme="light"] .btn-outline-danger:hover i {
            color: #ffffff !important;
        }

        /* ============================================
           ESTILOS ORIGINALES
           ============================================ */
        .settings-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }

        .settings-card:hover {
            transform: translateY(-5px);
        }

        .theme-option {
            cursor: pointer;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .theme-option:hover {
            transform: translateY(-5px);
        }

        .theme-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .theme-preview {
            height: 80px;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .theme-preview.light {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .theme-preview.dark {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
        }

        .btn-theme {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            z-index: 1000;
        }

        .membership-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            padding: 8px 15px;
            border-radius: 50px;
            color: white;
            font-weight: bold;
            z-index: 1000;
        }

        /* ESTILOS DEL NAVBAR MÓVIL */
        .mobile-navbar {
            display: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 12px 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1002;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .mobile-navbar .navbar-brand {
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .mobile-navbar .navbar-brand img {
            height: 28px;
            width: auto;
        }
        
        .mobile-navbar .btn-menu {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.4rem;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .mobile-navbar .btn-menu:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .mobile-navbar .user-info {
            color: white;
            font-size: 0.75rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ESTILOS DEL OFFCANVAS MÓVIL */
        .mobile-offcanvas {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 280px;
            z-index: 1050;
        }
        
        .mobile-offcanvas .offcanvas-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 15px;
        }
        
        .mobile-offcanvas .offcanvas-title {
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }
        
        .mobile-offcanvas .offcanvas-title img {
            height: 28px;
            width: auto;
        }
        
        .mobile-offcanvas .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }
        
        .mobile-offcanvas .offcanvas-body {
            padding: 15px;
        }
        
        .mobile-offcanvas .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 15px;
            margin: 4px 0;
            border-radius: 8px;
            transition: all 0.3s;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .mobile-offcanvas .nav-link:hover,
        .mobile-offcanvas .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .mobile-offcanvas .nav-link i {
            width: 25px;
            font-size: 1.1rem;
            text-align: center;
        }
        
        .mobile-offcanvas hr {
            border-color: rgba(255, 255, 255, 0.2);
            margin: 10px 0;
        }

        /* FOOTER */
        .dashboard-footer {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            padding: 15px 0;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
            width: 100%;
        }

        .dashboard-footer a {
            color: #667eea;
            text-decoration: none;
        }

        .dashboard-footer .footer-social {
            margin-top: 10px;
        }

        .dashboard-footer .footer-social a {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            margin: 0 5px;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .mobile-navbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .sidebar-column {
                display: none;
            }
            
            body {
                padding-top: 60px;
            }
            
            .main-content {
                padding: 15px;
            }
            
            .membership-badge {
                top: 70px;
                right: 10px;
                font-size: 0.7rem;
                padding: 5px 10px;
            }
            
            .btn-theme {
                bottom: 70px;
                right: 15px;
                width: 45px;
                height: 45px;
            }
            
            .dashboard-footer {
                padding: 12px 0;
                font-size: 0.7rem;
            }
            
            .dashboard-footer .footer-social a {
                width: 25px;
                height: 25px;
                line-height: 25px;
                font-size: 0.7rem;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR MÓVIL (FUERA DEL DASHBOARD-WRAPPER) -->
    <div class="mobile-navbar">
        <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-brand">
            <img src="/easycarluxury/assets/imagenes/logos/colcars.png" alt="Colcars">
            <span>Colcars</span>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars(substr($user['nombre_completo'], 0, 12)); ?></span>
        </div>
    </div>

    <!-- OFFCANVAS MENÚ MÓVIL -->
    <div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">
                <img src="/easycarluxury/assets/imagenes/logos/colcars.png" alt="Colcars">
                Colcars
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="text-center mb-3" style="color: white;">
                <i class="fas fa-user-circle fa-2x"></i>
                <p class="mt-2 mb-0"><?php echo htmlspecialchars($user['nombre_completo']); ?></p>
            </div>
            <hr>
            <nav class="nav flex-column">
                <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="nav-link" href="my-publications.php"><i class="fas fa-list"></i> Mis Publicaciones</a>
                <a class="nav-link" href="new-publication.php"><i class="fas fa-plus-circle"></i> Nueva Publicación</a>
                <a class="nav-link" href="messages.php"><i class="fas fa-envelope"></i> Mensajes
                    <?php if (($unread_messages['total'] ?? 0) > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unread_messages['total']; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="my-offers.php"><i class="fas fa-gavel"></i> Mis Ofertas</a>
                <a class="nav-link" href="statistics.php"><i class="fas fa-chart-line"></i> Estadísticas</a>
                <a class="nav-link" href="membership.php"><i class="fas fa-gem"></i> Membresía</a>
                <a class="nav-link" href="payments.php"><i class="fas fa-credit-card"></i> Pagos</a>
                <a class="nav-link" href="invoices.php"><i class="fas fa-file-invoice"></i> Facturas</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                <a class="nav-link active" href="settings.php"><i class="fas fa-cog"></i> Configuración</a>
                <hr class="my-2">
                <a class="nav-link" href="/easycarluxury/logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
        </div>
    </div>

    <!-- MEMBERSHIP BADGE -->
    <div class="membership-badge">
        <i class="fas fa-crown"></i> Cuenta: <?php echo strtoupper($user['tipo_cuenta']); ?>
        <?php if ($user['tipo_cuenta'] != 'free'): ?>
            <small>(Expira: <?php echo date('d/m/Y', strtotime($user['fecha_expiracion'])); ?>)</small>
        <?php endif; ?>
    </div>

    <!-- BOTÓN TEMA -->
    <button class="btn-theme" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>

    <!-- ESTRUCTURA PRINCIPAL -->
    <div class="dashboard-wrapper">
        <!-- COLUMNA DEL SIDEBAR (incluye el sidebar desktop) -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>
        </div>

        <!-- COLUMNA DEL CONTENIDO -->
        <div class="content-column">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-cog"></i> Configuración</h2>
                </div>
                <p class="text-muted">Personaliza tu experiencia en la plataforma</p>

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

                <div class="row">
                    <!-- Apariencia -->
                    <div class="col-md-6">
                        <div class="settings-card">
                            <h5><i class="fas fa-palette"></i> Apariencia</h5>
                            <p class="text-muted">Elige el tema de tu dashboard</p>
                            <div class="row">
                                <div class="col-6">
                                    <div class="theme-option text-center" onclick="setTheme('light')" id="theme-light">
                                        <div class="theme-preview light"></div>
                                        <i class="fas fa-sun"></i> Claro
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="theme-option text-center" onclick="setTheme('dark')" id="theme-dark">
                                        <div class="theme-preview dark"></div>
                                        <i class="fas fa-moon"></i> Oscuro
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Notificaciones -->
                    <div class="col-md-6">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <div class="settings-card">
                                <h5><i class="fas fa-bell"></i> Notificaciones</h5>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notificaciones_email" id="notificaciones_email" <?php echo ($settings['notificaciones_email'] ?? 1) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificaciones_email">
                                        <i class="fas fa-envelope"></i> Notificaciones por email
                                    </label>
                                    <small class="d-block text-muted">Recibe alertas de nuevos mensajes y ventas</small>
                                </div>
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" name="notificaciones_whatsapp" id="notificaciones_whatsapp" <?php echo ($settings['notificaciones_whatsapp'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="notificaciones_whatsapp">
                                        <i class="fab fa-whatsapp"></i> Notificaciones por WhatsApp
                                    </label>
                                    <small class="d-block text-muted">Recibe alertas instantáneas en tu WhatsApp</small>
                                </div>
                                <hr>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="auto_renovacion" id="auto_renovacion" <?php echo ($settings['auto_renovacion'] ?? 0) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="auto_renovacion">
                                        <i class="fas fa-sync-alt"></i> Renovación automática de membresía
                                    </label>
                                    <small class="d-block text-muted">Tu membresía se renovará automáticamente al vencer</small>
                                </div>
                                <button type="submit" class="btn btn-primary mt-4 w-100"><i class="fas fa-save"></i> Guardar Configuración</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row">
                    <!-- Preferencias de Idioma -->
                    <div class="col-md-6">
                        <div class="settings-card">
                            <h5><i class="fas fa-language"></i> Preferencias de Idioma</h5>
                            <select class="form-select" id="language">
                                <option value="es" selected>Español (Colombia)</option>
                                <option value="en">English</option>
                            </select>
                            <small class="text-muted d-block mt-2">Cambiar idioma de la interfaz</small>
                        </div>
                    </div>

                    <!-- Preferencias de Moneda -->
                    <div class="col-md-6">
                        <div class="settings-card">
                            <h5><i class="fas fa-calculator"></i> Preferencias de Moneda</h5>
                            <select class="form-select" id="currency">
                                <option value="COP" selected>COP - Peso Colombiano</option>
                                <option value="USD">USD - Dólar Americano</option>
                            </select>
                            <small class="text-muted d-block mt-2">Moneda para mostrar precios</small>
                        </div>
                    </div>
                </div>

                <!-- Zona Peligrosa -->
                <div class="settings-card border-danger">
                    <h5 class="text-danger"><i class="fas fa-exclamation-triangle"></i> Zona Peligrosa</h5>
                    <p class="text-muted">Acciones irreversibles</p>
                    <button class="btn btn-outline-danger" onclick="deleteAccount()">
                        <i class="fas fa-trash-alt"></i> Solicitar Eliminación de Cuenta
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="dashboard-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <p>&copy; <?php echo date('Y'); ?> Colcars - Todos los derechos reservados. By Software and Games Cel: 3151056434</p>
                    <p class="mb-0"><a href="/easycarluxury/terms">Términos y condiciones</a> | <a href="/easycarluxury/privacy">Política de privacidad</a> | <a href="/easycarluxury/contact">Contacto</a></p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Función para obtener el tema actual
        function getCurrentTheme() {
            return document.documentElement.getAttribute('data-theme') || 'light';
        }

        // Función para mostrar SweetAlert2 con el tema adecuado
        function showSwalWithTheme(options) {
            const theme = getCurrentTheme();
            const isDark = theme === 'dark';
            
            const swalOptions = {
                ...options,
                background: isDark ? '#1a1a2e' : '#ffffff',
                color: isDark ? '#ffffff' : '#212529',
                confirmButtonColor: '#667eea',
                cancelButtonColor: isDark ? '#dc3545' : '#6c757d',
                backdrop: isDark ? 'rgba(0, 0, 0, 0.8)' : 'rgba(0, 0, 0, 0.4)',
            };
            
            return Swal.fire(swalOptions);
        }

        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({ url: '/easycarluxury/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
            
            // Actualizar selección visual de temas
            document.getElementById('theme-light')?.classList.remove('selected');
            document.getElementById('theme-dark')?.classList.remove('selected');
            document.getElementById(`theme-${newTheme}`)?.classList.add('selected');
            
            showSwalWithTheme({
                title: 'Tema cambiado',
                text: 'El tema se ha actualizado correctamente',
                icon: 'success',
                confirmButtonText: 'Aceptar'
            });
        }

        // Marcar tema actual como seleccionado
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        if (currentTheme === 'light') {
            document.getElementById('theme-light')?.classList.add('selected');
        } else {
            document.getElementById('theme-dark')?.classList.add('selected');
        }

        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            document.cookie = `user_theme=${theme}; path=/; max-age=31536000`;
            $.ajax({ url: '/easycarluxury/api/v1/users/settings.php', method: 'POST', data: { theme: theme } });
            
            document.getElementById('theme-light')?.classList.remove('selected');
            document.getElementById('theme-dark')?.classList.remove('selected');
            document.getElementById(`theme-${theme}`)?.classList.add('selected');
            
            showSwalWithTheme({
                title: 'Tema cambiado',
                text: 'El tema se ha actualizado correctamente',
                icon: 'success',
                confirmButtonText: 'Aceptar'
            });
        }

        // Guardar idioma
        $('#language').on('change', function() {
            const lang = $(this).val();
            document.cookie = `user_lang=${lang}; path=/; max-age=31536000`;
            showSwalWithTheme({
                title: 'Idioma cambiado',
                text: 'Los cambios se aplicarán al recargar la página',
                icon: 'info',
                confirmButtonText: 'Aceptar'
            });
        });

        // Guardar moneda
        $('#currency').on('change', function() {
            const currency = $(this).val();
            document.cookie = `user_currency=${currency}; path=/; max-age=31536000`;
            showSwalWithTheme({
                title: 'Moneda cambiada',
                text: 'Los cambios se aplicarán al recargar la página',
                icon: 'info',
                confirmButtonText: 'Aceptar'
            });
        });

        // Eliminar cuenta
        function deleteAccount() {
            showSwalWithTheme({
                title: '¿Eliminar cuenta permanentemente?',
                text: 'Esta acción no se puede deshacer. Todos tus datos serán eliminados.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                input: 'text',
                inputPlaceholder: 'Escribe "ELIMINAR" para confirmar',
                inputValidator: (value) => {
                    if (value !== 'ELIMINAR') {
                        return 'Debes escribir ELIMINAR para confirmar';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    showSwalWithTheme({
                        title: 'Procesando...',
                        text: 'Eliminando tu cuenta',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    $.ajax({
                        url: '/easycarluxury/api/v1/users/delete-account.php',
                        method: 'DELETE',
                        success: function() {
                            showSwalWithTheme({
                                title: 'Cuenta eliminada',
                                text: 'Tu cuenta ha sido eliminada correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                setTimeout(() => {
                                    window.location.href = '/easycarluxury/logout';
                                }, 2000);
                            });
                        },
                        error: function() {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'No se pudo eliminar la cuenta. Contacta con soporte.',
                                icon: 'error',
                                confirmButtonText: 'Cerrar'
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>