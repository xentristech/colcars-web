<?php
/**
 * Colcars - Mensajes del Usuario
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

$database = Database::getInstance();
$pdo = $database->getConnection();

if (!$pdo) { die('Error de conexión a la base de datos'); }

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) { header('Location: /easycarluxury/public/login.php'); exit; }

$user = $database->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

$error = '';
$success = '';

// Responder a un comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'responder') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $comentario_id = intval($_POST['comentario_id']);
        $respuesta = sanitize($_POST['respuesta']);
        
        if (empty($respuesta)) {
            $error = 'La respuesta no puede estar vacía';
        } else {
            $stmt = $pdo->prepare("UPDATE comentarios SET respuesta = ?, updated_at = NOW() WHERE id = ? AND publicacion_id IN (SELECT id FROM publicaciones WHERE usuario_id = ?)");
            $result = $stmt->execute([$respuesta, $comentario_id, $user_id]);
            if ($result) { logAudit($user_id, 'UPDATE', 'comentarios', $comentario_id, null, ['respuesta' => $respuesta]); $success = 'Respuesta enviada correctamente'; }
            else { $error = 'Error al enviar la respuesta'; }
        }
    }
}

// Ocultar/Eliminar comentario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && ($_POST['action'] === 'ocultar' || $_POST['action'] === 'eliminar')) {
    $comentario_id = intval($_POST['comentario_id']);
    if ($_POST['action'] === 'ocultar') { $stmt = $pdo->prepare("UPDATE comentarios SET oculto_por_vendedor = 1 WHERE id = ?"); $stmt->execute([$comentario_id]); $success = 'Comentario ocultado'; }
    else { $stmt = $pdo->prepare("DELETE FROM comentarios WHERE id = ?"); $stmt->execute([$comentario_id]); $success = 'Comentario eliminado'; }
    logAudit($user_id, 'UPDATE', 'comentarios', $comentario_id);
}

// Obtener mensajes
$sql = "SELECT c.*, p.titulo as publicacion_titulo, p.id as publicacion_id, (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as imagen_principal FROM comentarios c JOIN publicaciones p ON c.publicacion_id = p.id WHERE p.usuario_id = ? ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_messages = $database->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);
$csrf_token = generateCSRFToken();
$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ============================================
           ESTRUCTURA GLOBAL
           ============================================ */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { height: 100%; margin: 0; padding: 0; }
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
        }
        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #ffffff;
            --text-secondary: #e0e0e0;
            --border-color: #2a2a3e;
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO OSCURO
           ============================================ */
        /* TEXTO E ICONOS EN MODO OSCURO - GLOBAL */
        [data-theme="dark"] body,
        [data-theme="dark"] .main-content,
        [data-theme="dark"] .message-card,
        [data-theme="dark"] .message-header,
        [data-theme="dark"] .message-body,
        [data-theme="dark"] .reply-form,
        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6,
        [data-theme="dark"] p,
        [data-theme="dark"] span:not(.badge):not(.alert),
        [data-theme="dark"] div:not(.alert):not(.badge):not(.message-response),
        [data-theme="dark"] small,
        [data-theme="dark"] strong,
        [data-theme="dark"] label,
        [data-theme="dark"] .text-muted {
            color: #ffffff !important;
        }

        /* ICONOS EN MODO OSCURO */
        [data-theme="dark"] i,
        [data-theme="dark"] .fas,
        [data-theme="dark"] .far,
        [data-theme="dark"] .fab {
            color: #ffffff !important;
        }

        /* ENLACES EN MODO OSCURO */
        [data-theme="dark"] a:not(.btn):not(.nav-link) {
            color: #a0c4ff !important;
        }
        [data-theme="dark"] a:not(.btn):not(.nav-link):hover {
            color: #c0e0ff !important;
        }

        /* TEXTOS SECUNDARIOS EN MODO OSCURO */
        [data-theme="dark"] .text-muted,
        [data-theme="dark"] small.text-muted {
            color: #c0c0c0 !important;
        }

        /* BADGES EN MODO OSCURO */
        [data-theme="dark"] .badge:not(.bg-danger):not(.badge-nuevo) {
            color: #ffffff !important;
            background-color: #2a2a3e !important;
        }
        [data-theme="dark"] .badge-nuevo {
            color: #ffffff !important;
        }

        /* ALERTAS EN MODO OSCURO */
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

        /* MENSAJE DE RESPUESTA EN MODO OSCURO */
        [data-theme="dark"] .message-response {
            background: rgba(64, 224, 96, 0.15);
            border-left-color: #40e060;
        }
        [data-theme="dark"] .message-response strong,
        [data-theme="dark"] .message-response .text-muted {
            color: #ffffff !important;
        }

        /* FORMULARIOS EN MODO OSCURO - TEXTAREA CON COLOR #222F58 */
        [data-theme="dark"] .form-control {
            background-color: #2a2a3e;
            border-color: #4a4a5e;
            color: #ffffff !important;
        }
        [data-theme="dark"] .form-control:focus {
            background-color: #3a3a4e;
            color: #ffffff !important;
        }
        [data-theme="dark"] .form-control::placeholder {
            color: #a0a0b0;
        }
        [data-theme="dark"] .form-label {
            color: #ffffff !important;
        }

        /* TEXTAREA EN MODO OSCURO - COLOR #222F58 */
        [data-theme="dark"] textarea.form-control {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] textarea.form-control:focus {
            background-color: #2a3a6a !important;
            color: #ffffff !important;
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }
        [data-theme="dark"] textarea.form-control::placeholder {
            color: #a0a0b0 !important;
        }

        /* BOTONES EN MODO OSCURO */
        [data-theme="dark"] .btn-outline-primary {
            color: #ffffff !important;
            border-color: #667eea;
        }
        [data-theme="dark"] .btn-outline-primary:hover {
            background-color: #667eea;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-outline-warning {
            color: #ffffff !important;
            border-color: #ffc107;
        }
        [data-theme="dark"] .btn-outline-warning:hover {
            background-color: #ffc107;
            color: #1a1a2e !important;
        }
        [data-theme="dark"] .btn-outline-danger {
            color: #ffffff !important;
            border-color: #dc3545;
        }
        [data-theme="dark"] .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-primary:hover {
            background-color: #5a6fd6;
            border-color: #5a6fd6;
        }
        [data-theme="dark"] .btn-sm i {
            color: inherit !important;
        }

        /* FOOTER EN MODO OSCURO */
        [data-theme="dark"] .dashboard-footer {
            background: #16213e;
            border-top-color: #2a2a3e;
        }
        [data-theme="dark"] .dashboard-footer .text-muted {
            color: #c0c0c0 !important;
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
        [data-theme="dark"] .dashboard-footer .footer-social a:hover {
            background: rgba(102, 126, 234, 0.4);
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

        /* TARJETAS EN MODO OSCURO */
        [data-theme="dark"] .card,
        [data-theme="dark"] .card-body,
        [data-theme="dark"] .card-title,
        [data-theme="dark"] .card-text {
            color: #ffffff !important;
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        /* TEXTO E ICONOS EN MODO CLARO - NEGROS */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .message-card,
        [data-theme="light"] .message-header,
        [data-theme="light"] .message-body,
        [data-theme="light"] .reply-form,
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

        [data-theme="light"] .message-response {
            background: rgba(40,167,69,0.1);
            border-left-color: #28a745;
        }

        /* SIDEBAR EN MODO CLARO - TEXTO BLANCO */
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

        /* BADGE "SIN RESPONDER" EN MODO CLARO - TEXTO BLANCO */
        [data-theme="light"] .badge-nuevo {
            color: #ffffff !important;
            background-color: #dc3545 !important;
        }

        /* BADGE DE MEMBERSHIP EN MODO CLARO - TEXTO BLANCO */
        [data-theme="light"] .membership-badge,
        [data-theme="light"] .membership-badge i,
        [data-theme="light"] .membership-badge small,
        [data-theme="light"] .membership-badge span {
            color: #ffffff !important;
        }

        /* ============================================
           ESTILOS DE BOTONES EN MODO CLARO - COLORES SÓLIDOS
           ============================================ */
        /* Botón Ver Publicación - Azul sólido */
        [data-theme="light"] .btn-outline-primary {
            color: #0d6efd !important;
            background-color: transparent !important;
            border-color: #0d6efd !important;
        }
        [data-theme="light"] .btn-outline-primary:hover {
            background-color: #0d6efd !important;
            color: #ffffff !important;
        }

        /* Botón Ocultar - Amarillo sólido */
        [data-theme="light"] .btn-outline-warning {
            color: #ffc107 !important;
            background-color: transparent !important;
            border-color: #ffc107 !important;
        }
        [data-theme="light"] .btn-outline-warning:hover {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }

        /* Botón Eliminar - Rojo sólido */
        [data-theme="light"] .btn-outline-danger {
            color: #dc3545 !important;
            background-color: transparent !important;
            border-color: #dc3545 !important;
        }
        [data-theme="light"] .btn-outline-danger:hover {
            background-color: #dc3545 !important;
            color: #ffffff !important;
        }

        /* Botón Enviar Respuesta - Azul sólido primario */
        [data-theme="light"] .btn-primary {
            background-color: #0d6efd !important;
            border-color: #0d6efd !important;
            color: #ffffff !important;
        }
        [data-theme="light"] .btn-primary:hover {
            background-color: #0b5ed7 !important;
            border-color: #0a58ca !important;
        }

        /* ============================================
           ESTILOS ORIGINALES - CARD Y COMPONENTES
           ============================================ */
        .message-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        .message-header {
            padding: 15px 20px;
            background: rgba(102,126,234,0.1);
            border-bottom: 1px solid var(--border-color);
        }
        .message-body { padding: 20px; }
        .message-response {
            background: rgba(40,167,69,0.1);
            padding: 15px;
            border-radius: 10px;
            margin-top: 15px;
            border-left: 3px solid #28a745;
        }
        .reply-form {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }
        .badge-nuevo { background: #dc3545; color: white; }

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
        .mobile-navbar .navbar-brand img { height: 28px; width: auto; }
        .mobile-navbar .btn-menu {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 1.4rem;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        .mobile-navbar .user-info {
            color: white;
            font-size: 0.75rem;
            background: rgba(255,255,255,0.15);
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
            border-bottom: 1px solid rgba(255,255,255,0.2);
            padding: 15px;
        }
        .mobile-offcanvas .offcanvas-title {
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }
        .mobile-offcanvas .offcanvas-title img { height: 28px; width: auto; }
        .mobile-offcanvas .btn-close { filter: invert(1); opacity: 0.8; }
        .mobile-offcanvas .offcanvas-body { padding: 15px; }
        .mobile-offcanvas .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 15px;
            margin: 4px 0;
            border-radius: 8px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .mobile-offcanvas .nav-link:hover,
        .mobile-offcanvas .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        .mobile-offcanvas .nav-link i { width: 25px; font-size: 1.1rem; text-align: center; }
        .mobile-offcanvas hr { border-color: rgba(255,255,255,0.2); margin: 10px 0; }

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
        .dashboard-footer a { color: #667eea; text-decoration: none; }
        .dashboard-footer .footer-social { margin-top: 10px; }
        .dashboard-footer .footer-social a {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            margin: 0 5px;
            border-radius: 50%;
            background: rgba(102,126,234,0.1);
            transition: all 0.3s ease;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .mobile-navbar { display: flex; align-items: center; justify-content: space-between; }
            .sidebar-column { display: none; }
            body { padding-top: 60px; }
            .main-content { padding: 15px; }
            .membership-badge { top: 70px; right: 10px; font-size: 0.7rem; padding: 5px 10px; }
            .btn-theme { bottom: 70px; right: 15px; width: 45px; height: 45px; }
            .dashboard-footer { padding: 12px 0; font-size: 0.7rem; }
            .dashboard-footer .footer-social a { width: 25px; height: 25px; line-height: 25px; font-size: 0.7rem; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR MÓVIL -->
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
                <a class="nav-link active" href="messages.php"><i class="fas fa-envelope"></i> Mensajes <?php if (($unread_messages['total'] ?? 0) > 0): ?><span class="badge bg-danger ms-1"><?php echo $unread_messages['total']; ?></span><?php endif; ?></a>
                <a class="nav-link" href="my-offers.php"><i class="fas fa-gavel"></i> Mis Ofertas</a>
                <a class="nav-link" href="statistics.php"><i class="fas fa-chart-line"></i> Estadísticas</a>
                <a class="nav-link" href="membership.php"><i class="fas fa-gem"></i> Membresía</a>
                <a class="nav-link" href="payments.php"><i class="fas fa-credit-card"></i> Pagos</a>
                <a class="nav-link" href="invoices.php"><i class="fas fa-file-invoice"></i> Facturas</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Configuración</a>
                <hr class="my-2">
                <a class="nav-link" href="/easycarluxury/logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
        </div>
    </div>

    <div class="membership-badge">
        <i class="fas fa-crown"></i> Cuenta: <?php echo strtoupper($user['tipo_cuenta']); ?>
        <?php if ($user['tipo_cuenta'] != 'free'): ?>
            <small>(Expira: <?php echo date('d/m/Y', strtotime($user['fecha_expiracion'])); ?>)</small>
        <?php endif; ?>
    </div>

    <button class="btn-theme" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>

    <!-- ESTRUCTURA PRINCIPAL -->
    <div class="dashboard-wrapper">
        <!-- COLUMNA DEL SIDEBAR -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>
        </div>

        <!-- COLUMNA DEL CONTENIDO -->
        <div class="content-column">
            <div class="main-content">
                <h2><i class="fas fa-envelope"></i> Mensajes</h2>
                <p class="text-muted">Gestiona los comentarios y preguntas de tus publicaciones</p>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

                <?php if (empty($mensajes)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h5>No tienes mensajes</h5>
                        <p class="text-muted">Cuando los usuarios comenten en tus publicaciones, aparecerán aquí</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($mensajes as $msg): ?>
                        <div class="message-card">
                            <div class="message-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><i class="fas fa-user"></i> <?php echo htmlspecialchars($msg['nombre'] ?? ($msg['usuario_id'] ? 'Usuario' : 'Anónimo')); ?></strong>
                                        <small class="text-muted ms-2"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></small>
                                        <?php if (!$msg['respuesta']): ?><span class="badge badge-nuevo ms-2">Sin responder</span><?php endif; ?>
                                    </div>
                                    <div>
                                        <a href="/easycarluxury/public/catalog/detail.php?id=<?php echo $msg['publicacion_id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> Ver publicación</a>
                                    </div>
                                </div>
                                <div class="mt-1">
                                    <small class="text-muted"><i class="fas fa-car"></i> Publicación: <?php echo htmlspecialchars($msg['publicacion_titulo']); ?></small>
                                </div>
                            </div>
                            <div class="message-body">
                                <p class="mb-0"><strong>Mensaje:</strong><br><?php echo nl2br(htmlspecialchars($msg['comentario'])); ?></p>
                                <?php if ($msg['respuesta']): ?>
                                    <div class="message-response">
                                        <strong><i class="fas fa-reply"></i> Tu respuesta:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($msg['respuesta'])); ?>
                                        <div class="text-muted small mt-1">Respondido el: <?php echo date('d/m/Y H:i', strtotime($msg['updated_at'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="reply-form">
                                    <form method="POST" class="ajax-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                        <input type="hidden" name="action" value="responder">
                                        <input type="hidden" name="comentario_id" value="<?php echo $msg['id']; ?>">
                                        <div class="mb-2">
                                            <label class="form-label">Tu respuesta:</label>
                                            <textarea class="form-control" name="respuesta" rows="2" placeholder="Escribe tu respuesta aquí..."><?php echo htmlspecialchars($msg['respuesta'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="d-flex justify-content-between">
                                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i> Enviar respuesta</button>
                                            <div>
                                                <button type="button" class="btn btn-sm btn-outline-warning" onclick="ocultarComentario(<?php echo $msg['id']; ?>)"><i class="fas fa-eye-slash"></i> Ocultar</button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarComentario(<?php echo $msg['id']; ?>)"><i class="fas fa-trash"></i> Eliminar</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FOOTER INCLUDE -->
    <?php include __DIR__ . '/../includes/user-footer.php'; ?>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Función para obtener el tema actual
        function getCurrentTheme() {
            return document.documentElement.getAttribute('data-theme') || 'light';
        }

        // Función para configurar SweetAlert2 con el tema actual
        function getSwalConfig() {
            const theme = getCurrentTheme();
            const isDark = theme === 'dark';
            
            return {
                background: isDark ? '#1a1a2e' : '#ffffff',
                color: isDark ? '#ffffff' : '#212529',
                confirmButtonColor: '#667eea',
                cancelButtonColor: isDark ? '#dc3545' : '#6c757d',
                backdrop: isDark ? 'rgba(0, 0, 0, 0.8)' : 'rgba(0, 0, 0, 0.4)',
                customClass: {
                    popup: isDark ? 'swal-dark-popup' : 'swal-light-popup',
                    title: isDark ? 'swal-dark-title' : 'swal-light-title',
                    htmlContainer: isDark ? 'swal-dark-text' : 'swal-light-text',
                    confirmButton: 'swal-confirm-btn',
                    cancelButton: 'swal-cancel-btn'
                }
            };
        }

        // Función para actualizar SweetAlert2 cuando cambia el tema
        let swalQueue = [];
        
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
            $.ajax({ url: '/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
        }

        // Interceptar formularios AJAX con SweetAlert2 personalizado
        $('.ajax-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const textarea = form.find('textarea[name="respuesta"]');
            const respuesta = textarea.val().trim();
            
            if (!respuesta) {
                showSwalWithTheme({
                    title: 'Campo vacío',
                    text: 'Por favor escribe una respuesta antes de enviar',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return;
            }
            
            showSwalWithTheme({
                title: '¿Enviar respuesta?',
                text: 'Tu respuesta será visible para el usuario',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: form.serialize(),
                        success: function() {
                            showSwalWithTheme({
                                title: '¡Respuesta enviada!',
                                text: 'Tu respuesta ha sido publicada correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => location.reload());
                        },
                        error: function() {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'No se pudo enviar la respuesta. Intenta nuevamente.',
                                icon: 'error',
                                confirmButtonText: 'Cerrar'
                            });
                        }
                    });
                }
            });
        });

        function ocultarComentario(id) {
            showSwalWithTheme({
                title: '¿Ocultar comentario?',
                text: 'El comentario ya no será visible para otros usuarios',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, ocultar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { 
                            action: 'ocultar', 
                            comentario_id: id, 
                            csrf_token: '<?php echo $csrf_token; ?>' 
                        },
                        success: function() {
                            showSwalWithTheme({
                                title: 'Comentario ocultado',
                                text: 'El comentario ha sido ocultado correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => location.reload());
                        },
                        error: function() {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'No se pudo ocultar el comentario',
                                icon: 'error',
                                confirmButtonText: 'Cerrar'
                            });
                        }
                    });
                }
            });
        }

        function eliminarComentario(id) {
            showSwalWithTheme({
                title: '¿Eliminar comentario?',
                text: 'Esta acción es irreversible. El comentario será eliminado permanentemente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: { 
                            action: 'eliminar', 
                            comentario_id: id, 
                            csrf_token: '<?php echo $csrf_token; ?>' 
                        },
                        success: function() {
                            showSwalWithTheme({
                                title: 'Comentario eliminado',
                                text: 'El comentario ha sido eliminado correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => location.reload());
                        },
                        error: function() {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'No se pudo eliminar el comentario',
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