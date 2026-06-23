<?php
/**
 * Colcars - Mis Facturas
 */

if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación
requireAuth();

$db = Database::getInstance();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];

// Obtener datos del usuario
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

if (!$user) {
    session_destroy();
    header('Location: /easycarluxury/public/login.php');
    exit;
}

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

$userMembership = $user['tipo_cuenta'] ?? 'free';
$canGenerateInvoices = in_array($userMembership, ['pro', 'premium', 'elite']);

// CORREGIDO: Usar los nombres correctos de columnas según la estructura de la BD
$query = "SELECT i.*, p.payment_date, p.amount, p.payment_method, p.status as payment_status,
          dt.cufe, dt.status as dian_status, dt.pdf_path
          FROM invoices i
          JOIN payments p ON i.payment_id = p.id
          LEFT JOIN dian_transactions dt ON i.id = dt.invoice_id
          WHERE i.user_id = :user_id
          ORDER BY p.payment_date DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Facturas - Colcars</title>
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
            margin-top: 60px; /* MARGEN SUPERIOR PARA QUE EL BADGE NO TAPE EL CONTENIDO */
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
            --text-secondary: #e0e0e0;
            --border-color: #2a2a3e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO OSCURO
           ============================================ */
        /* TEXTO E ICONOS EN MODO OSCURO - GLOBAL */
        [data-theme="dark"] body,
        [data-theme="dark"] .main-content,
        [data-theme="dark"] .stats-card,
        [data-theme="dark"] .invoice-card-mobile,
        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6,
        [data-theme="dark"] p,
        [data-theme="dark"] span:not(.badge):not(.alert),
        [data-theme="dark"] div:not(.alert):not(.badge),
        [data-theme="dark"] small,
        [data-theme="dark"] strong,
        [data-theme="dark"] label,
        [data-theme="dark"] .text-muted,
        [data-theme="dark"] .table-invoices th,
        [data-theme="dark"] .table-invoices td,
        [data-theme="dark"] .mobile-section-header small {
            color: #ffffff !important;
        }

        /* ICONOS EN MODO OSCURO - INCLUYE LOS DE LA TABLA */
        [data-theme="dark"] i,
        [data-theme="dark"] .fas,
        [data-theme="dark"] .far,
        [data-theme="dark"] .fab,
        [data-theme="dark"] .table-invoices i,
        [data-theme="dark"] .btn i,
        [data-theme="dark"] .btn-group i,
        [data-theme="dark"] .invoice-card-mobile i {
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
        [data-theme="dark"] small.text-muted,
        [data-theme="dark"] .field-label {
            color: #c0c0c0 !important;
        }

        /* ALERTAS EN MODO OSCURO */
        [data-theme="dark"] .alert-info {
            background-color: #1a3a5a;
            color: #ccffff !important;
            border-color: #2a5a7a;
        }
        [data-theme="dark"] .alert-info i,
        [data-theme="dark"] .alert-success i {
            color: inherit !important;
        }
        [data-theme="dark"] .alert-success {
            background-color: #1a4a2a;
            color: #ccffcc !important;
            border-color: #2a6a3a;
        }

        /* FORMULARIOS EN MODO OSCURO */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #2a2a3e;
            border-color: #4a4a5e;
            color: #ffffff !important;
        }
        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: #3a3a4e;
            color: #ffffff !important;
        }
        [data-theme="dark"] .form-control::placeholder {
            color: #a0a0b0;
        }
        [data-theme="dark"] .form-label {
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
        [data-theme="dark"] .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-info {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
            color: #1a1a2e !important;
        }
        [data-theme="dark"] .btn-info i {
            color: #1a1a2e !important;
        }
        [data-theme="dark"] .btn-secondary i {
            color: #ffffff !important;
        }

        /* MODAL EN MODO OSCURO */
        [data-theme="dark"] .modal-content {
            background-color: #16213e;
            border-color: #2a2a3e;
        }
        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer {
            border-color: #2a2a3e;
        }
        [data-theme="dark"] .modal-title {
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        /* TABLA EN MODO OSCURO */
        [data-theme="dark"] .table-invoices th {
            background: #16213e;
            color: #ffffff !important;
            border-bottom-color: #2a2a3e;
        }
        [data-theme="dark"] .table-invoices td {
            border-bottom-color: #2a2a3e;
        }
        [data-theme="dark"] .table-invoices tr:hover {
            background: rgba(102, 126, 234, 0.1);
        }

        /* BADGES EN MODO OSCURO */
        [data-theme="dark"] .badge.bg-success {
            background-color: #28a745 !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .badge.bg-danger {
            background-color: #dc3545 !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .badge.bg-info {
            background-color: #0dcaf0 !important;
            color: #1a1a2e !important;
        }
        [data-theme="dark"] .badge.bg-secondary {
            background-color: #6c757d !important;
            color: #ffffff !important;
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

        /* TARJETA MÓVIL EN MODO OSCURO */
        [data-theme="dark"] .invoice-card-mobile .field-value {
            color: #ffffff !important;
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        /* TEXTO E ICONOS EN MODO CLARO - NEGROS */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .stats-card,
        [data-theme="light"] .invoice-card-mobile,
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
        [data-theme="light"] .table-invoices th,
        [data-theme="light"] .table-invoices td {
            color: #212529 !important;
        }

        [data-theme="light"] i,
        [data-theme="light"] .fas,
        [data-theme="light"] .far,
        [data-theme="light"] .fab,
        [data-theme="light"] .table-invoices i,
        [data-theme="light"] .btn i {
            color: #212529 !important;
        }

        [data-theme="light"] .text-muted {
            color: #6c757d !important;
        }

        [data-theme="light"] .btn-info i {
            color: #ffffff !important;
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

        /* MEMBERSHIP BADGE EN MODO CLARO - TEXTO BLANCO */
        [data-theme="light"] .membership-badge,
        [data-theme="light"] .membership-badge i,
        [data-theme="light"] .membership-badge small,
        [data-theme="light"] .membership-badge span {
            color: #ffffff !important;
        }

        /* ============================================
           ESTILOS ORIGINALES - CARD Y COMPONENTES
           ============================================ */
        .stats-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        /* Tarjeta para móvil - vista de factura individual */
        .invoice-card-mobile {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .invoice-card-mobile .invoice-field {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .invoice-card-mobile .invoice-field:last-child {
            border-bottom: none;
        }
        
        .invoice-card-mobile .field-label {
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 0.8rem;
            width: 35%;
        }
        
        .invoice-card-mobile .field-value {
            font-weight: 500;
            color: var(--text-primary);
            text-align: right;
            word-break: break-word;
            width: 65%;
        }
        
        .invoice-card-mobile .badge {
            font-size: 0.7rem;
            padding: 4px 8px;
        }
        
        .invoice-card-mobile .btn-group {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .cufe-short {
            cursor: help;
            font-family: monospace;
            font-size: 0.75rem;
        }

        /* Tabla Desktop */
        .table-invoices {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-invoices th,
        .table-invoices td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table-invoices th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .table-invoices tr:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        /* Encabezado de sección móvil */
        .mobile-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .mobile-section-header h5 {
            margin: 0;
        }
        
        .mobile-section-header small {
            font-size: 0.7rem;
            color: var(--text-secondary);
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
                margin-top: 0px; /* Resetear margen en móvil porque ya tiene padding-top del body */
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
            
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            /* Ocultar tabla desktop en móvil */
            .desktop-table {
                display: none;
            }
        }
        
        @media (min-width: 769px) {
            /* Ocultar tarjetas móviles en desktop */
            .mobile-invoices {
                display: none;
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
                <a class="nav-link active" href="invoices.php"><i class="fas fa-file-invoice"></i> Facturas</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Configuración</a>
                <hr class="my-2">
                <a class="nav-link" href="/easycarluxury/logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
        </div>
    </div>

    <!-- MEMBERSHIP BADGE -->
    <div class="membership-badge">
        <i class="fas fa-crown"></i> Cuenta: <?php echo strtoupper($user['tipo_cuenta'] ?? 'FREE'); ?>
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
                <div class="page-header">
                    <h2><i class="fas fa-file-invoice"></i> Mis Facturas</h2>
                    <div class="header-actions">
                        <?php if ($canGenerateInvoices): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateInvoiceModal">
                                <i class="fas fa-plus"></i> Generar Factura
                            </button>
                        <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle"></i> Actualiza a <strong>Pro, Premium o Elite</strong> para generar facturas electrónicas
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo $_GET['success'] == 'invoice_sent' ? '✓ Factura enviada exitosamente a la DIAN' : '✓ Nota crédito enviada exitosamente a la DIAN'; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Tabla de Facturas - VISTA DESKTOP -->
                <div class="stats-card desktop-table">
                    <h5><i class="fas fa-receipt"></i> Historial de Facturas</h5>
                    <div class="table-responsive">
                        <table class="table-invoices">
                            <thead>
                                <tr>
                                    <th># Factura</th>
                                    <th>Fecha</th>
                                    <th>Concepto</th>
                                    <th>Valor</th>
                                    <th>Estado DIAN</th>
                                    <th>CUFE</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($invoices) > 0): ?>
                                    <?php foreach ($invoices as $invoice): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></td>
                                            <td><?php echo date('d/m/Y H:i', strtotime($invoice['payment_date'] ?? $invoice['created_at'] ?? 'now')); ?></td>
                                            <td>Membresía <?php echo ucfirst($userMembership); ?></td>
                                            <td>$ <?php echo number_format($invoice['amount'] ?? 0, 0, ',', '.'); ?></td>
                                            <td>
                                                <?php 
                                                $dianStatus = $invoice['dian_status'] ?? 'PENDING';
                                                if ($dianStatus === 'ACCEPTED'): ?>
                                                    <span class="badge bg-success">Aceptada</span>
                                                <?php elseif ($dianStatus === 'REJECTED'): ?>
                                                    <span class="badge bg-danger">Rechazada</span>
                                                <?php elseif ($dianStatus === 'SENT'): ?>
                                                    <span class="badge bg-info">Enviada</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Pendiente</span>
                                                <?php endif; ?>
                                              </td>
                                            <td>
                                                <?php if ($invoice['cufe']): ?>
                                                    <span class="cufe-short" title="<?php echo htmlspecialchars($invoice['cufe']); ?>">
                                                        <?php echo substr($invoice['cufe'], 0, 20) . '...'; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">---</span>
                                                <?php endif; ?>
                                              </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="2invoices.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info" title="Ver Detalle">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($invoice['pdf_path']): ?>
                                                        <a href="<?php echo $invoice['pdf_path']; ?>" class="btn btn-secondary" target="_blank" title="Descargar PDF">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                              </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5">
                                            <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
                                            No hay facturas generadas aún
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tarjetas de Facturas - VISTA MÓVIL -->
                <div class="mobile-invoices">
                    <div class="mobile-section-header">
                        <h5><i class="fas fa-receipt"></i> Mis Facturas</h5>
                        <small class="text-muted">Desliza para ver más</small>
                    </div>
                    
                    <!-- Leyenda de campos para móvil -->
                    <div class="stats-card mb-3" style="padding: 10px; background: rgba(102,126,234,0.1);">
                        <div class="row text-center">
                            <div class="col-3"><small><strong>Factura</strong></small></div>
                            <div class="col-2"><small><strong>Fecha</strong></small></div>
                            <div class="col-2"><small><strong>Valor</strong></small></div>
                            <div class="col-2"><small><strong>Estado</strong></small></div>
                            <div class="col-3"><small><strong>Acciones</strong></small></div>
                        </div>
                    </div>
                    
                    <?php if (count($invoices) > 0): ?>
                        <?php foreach ($invoices as $invoice): ?>
                            <div class="invoice-card-mobile">
                                <div class="invoice-field">
                                    <span class="field-label"><i class="fas fa-hashtag"></i> # Factura</span>
                                    <span class="field-value"><?php echo htmlspecialchars($invoice['invoice_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="invoice-field">
                                    <span class="field-label"><i class="fas fa-calendar"></i> Fecha</span>
                                    <span class="field-value"><?php echo date('d/m/Y H:i', strtotime($invoice['payment_date'] ?? $invoice['created_at'] ?? 'now')); ?></span>
                                </div>
                                <div class="invoice-field">
                                    <span class="field-label"><i class="fas fa-tag"></i> Concepto</span>
                                    <span class="field-value">Membresía <?php echo ucfirst($userMembership); ?></span>
                                </div>
                                <div class="invoice-field">
                                    <span class="field-label"><i class="fas fa-dollar-sign"></i> Valor</span>
                                    <span class="field-value">$ <?php echo number_format($invoice['amount'] ?? 0, 0, ',', '.'); ?></span>
                                </div>
                                <div class="invoice-field">
                                    <span class="field-label"><i class="fas fa-check-circle"></i> Estado DIAN</span>
                                    <span class="field-value">
                                        <?php 
                                        $dianStatus = $invoice['dian_status'] ?? 'PENDING';
                                        if ($dianStatus === 'ACCEPTED'): ?>
                                            <span class="badge bg-success">Aceptada</span>
                                        <?php elseif ($dianStatus === 'REJECTED'): ?>
                                            <span class="badge bg-danger">Rechazada</span>
                                        <?php elseif ($dianStatus === 'SENT'): ?>
                                            <span class="badge bg-info">Enviada</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pendiente</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($invoice['cufe']): ?>
                                    <div class="invoice-field">
                                        <span class="field-label"><i class="fas fa-key"></i> CUFE</span>
                                        <span class="field-value cufe-short" title="<?php echo htmlspecialchars($invoice['cufe']); ?>">
                                            <?php echo substr($invoice['cufe'], 0, 20) . '...'; ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="btn-group">
                                    <a href="2invoices.php?id=<?php echo $invoice['id']; ?>" class="btn btn-info btn-sm">
                                        <i class="fas fa-eye"></i> Detalle
                                    </a>
                                    <?php if ($invoice['pdf_path']): ?>
                                        <a href="<?php echo $invoice['pdf_path']; ?>" class="btn btn-secondary btn-sm" target="_blank">
                                            <i class="fas fa-download"></i> PDF
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="stats-card text-center py-5">
                            <i class="fas fa-receipt fa-3x text-muted mb-3 d-block"></i>
                            <p>No hay facturas generadas aún</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Generar Factura -->
    <div class="modal fade" id="generateInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-file-invoice"></i> Generar Factura Electrónica</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> La factura se generará automáticamente y se enviará a la DIAN.
                    </div>
                    <form id="invoiceForm">
                        <div class="mb-3">
                            <label class="form-label">Tipo de Documento</label>
                            <select class="form-select" id="documentType">
                                <option value="CC">Cédula de Ciudadanía</option>
                                <option value="NIT">NIT</option>
                                <option value="CE">Cédula de Extranjería</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Número de Documento</label>
                            <input type="text" class="form-control" id="documentNumber" placeholder="Ej: 123456789">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Nombre o Razón Social</label>
                            <input type="text" class="form-control" id="customerName" placeholder="Nombre completo o razón social">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="customerEmail" placeholder="cliente@ejemplo.com">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Producto/Servicio</label>
                                <select class="form-select" id="productType">
                                    <option value="membership">Membresía</option>
                                    <option value="advertisement">Publicidad</option>
                                    <option value="featured">Publicación Destacada</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Valor (COP)</label>
                                <input type="number" class="form-control" id="amount" placeholder="0">
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="generateInvoiceBtn">Generar Factura</button>
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
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({ url: '/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
        }
        
        $('#generateInvoiceBtn').on('click', function() {
            Swal.fire('En desarrollo', 'La generación de facturas electrónicas estará disponible próximamente', 'info');
        });
    </script>
</body>
</html>