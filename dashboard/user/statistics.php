<?php
/**
 * Colcars - Estadísticas del Usuario
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

$periodo = $_GET['periodo'] ?? '30';
$fecha_inicio = date('Y-m-d', strtotime("-$periodo days"));

// ============================================
// CONSULTAS CORREGIDAS CON LAS TABLAS CORRECTAS
// ============================================

// 1. Visitas por día - Desde estadisticas_publicaciones
$visitas_diarias = $db->getAll("
    SELECT fecha, SUM(visitas_dia) as visitas 
    FROM estadisticas_publicaciones ep 
    JOIN publicaciones p ON ep.publicacion_id = p.id 
    WHERE p.usuario_id = ? AND ep.fecha >= ? 
    GROUP BY ep.fecha 
    ORDER BY ep.fecha ASC
", [$user_id, $fecha_inicio]);

// Si no hay datos en estadisticas_publicaciones, usar publication_views
if (empty($visitas_diarias)) {
    $visitas_diarias = $db->getAll("
        SELECT DATE(pv.viewed_at) as fecha, COUNT(*) as visitas 
        FROM publication_views pv 
        JOIN publicaciones p ON pv.publication_id = p.id 
        WHERE p.usuario_id = ? AND pv.viewed_at >= ? 
        GROUP BY DATE(pv.viewed_at) 
        ORDER BY fecha ASC
    ", [$user_id, $fecha_inicio]);
}

// 2. Top Publicaciones - Desde publicaciones (totales acumulados)
$top_publicaciones = $db->getAll("
    SELECT p.id, p.titulo, p.visitas, p.likes, 
           (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id AND visible = 1) as comentarios 
    FROM publicaciones p 
    WHERE p.usuario_id = ? AND p.status = 'active'
    ORDER BY p.visitas DESC 
    LIMIT 5
", [$user_id]);

// 3. Rendimiento por categoría - Suma de visitas agrupada por categoría
$categorias_stats = $db->getAll("
    SELECT c.nombre, 
           COUNT(p.id) as total_publicaciones, 
           SUM(p.visitas) as visitas,
           SUM(p.likes) as likes
    FROM publicaciones p 
    JOIN categorias c ON p.categoria_id = c.id 
    WHERE p.usuario_id = ? AND p.status = 'active'
    GROUP BY c.id
    ORDER BY visitas DESC
", [$user_id]);

// 4. Horario con más visitas - Desde publication_views
$horario_stats = $db->getAll("
    SELECT HOUR(pv.viewed_at) as hora, COUNT(*) as visitas 
    FROM publication_views pv 
    JOIN publicaciones p ON pv.publication_id = p.id 
    WHERE p.usuario_id = ? 
    GROUP BY HOUR(pv.viewed_at)
    ORDER BY hora ASC
", [$user_id]);

// Si no hay datos en publication_views, usar auditoria
if (empty($horario_stats)) {
    $horario_stats = $db->getAll("
        SELECT HOUR(created_at) as hora, COUNT(*) as visitas 
        FROM auditoria 
        WHERE usuario_id = ? AND accion = 'READ' AND tabla_afectada = 'publicaciones' 
        GROUP BY HOUR(created_at)
    ", [$user_id]);
}

$csrf_token = generateCSRFToken();
$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');

// Preparar datos para los gráficos
$visitas_fechas = array_map(function($v) { return date('d/m', strtotime($v['fecha'])); }, $visitas_diarias);
$visitas_datos = array_column($visitas_diarias, 'visitas');
$categorias_nombres = array_column($categorias_stats, 'nombre');
$categorias_visitas = array_column($categorias_stats, 'visitas');
$horas = array_fill(0, 24, 0);
foreach ($horario_stats as $h) {
    $horas[$h['hora']] = $h['visitas'];
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estadísticas - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .dashboard-wrapper {
            display: flex;
            flex: 1;
            width: 100%;
        }

        .sidebar-column {
            flex-shrink: 0;
            width: 260px;
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        .content-column {
            flex: 1;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
        }

        .main-content {
            padding: 20px;
            flex: 1;
            margin-top: 60px;
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
        [data-theme="dark"] body,
        [data-theme="dark"] .main-content,
        [data-theme="dark"] .stats-card,
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
        [data-theme="dark"] .list-group-item,
        [data-theme="dark"] .table,
        [data-theme="dark"] .table th,
        [data-theme="dark"] .table td,
        [data-theme="dark"] .form-label,
        [data-theme="dark"] .form-select,
        [data-theme="dark"] .form-control {
            color: #ffffff !important;
        }

        [data-theme="dark"] i,
        [data-theme="dark"] .fas,
        [data-theme="dark"] .far,
        [data-theme="dark"] .fab,
        [data-theme="dark"] .btn i {
            color: #ffffff !important;
        }

        [data-theme="dark"] a:not(.btn):not(.nav-link) {
            color: #a0c4ff !important;
        }

        [data-theme="dark"] .text-muted,
        [data-theme="dark"] small.text-muted {
            color: #c0c0c0 !important;
        }

        [data-theme="dark"] .alert-info {
            background-color: #1a3a5a;
            color: #ccffff !important;
            border-color: #2a5a7a;
        }

        /* INPUTS Y SELECTS EN MODO OSCURO - COLOR #222F58 */
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

        [data-theme="dark"] .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-outline-primary {
            color: #ffffff !important;
            border-color: #667eea;
        }

        [data-theme="dark"] .btn-outline-primary.active,
        [data-theme="dark"] .btn-outline-primary:hover {
            background-color: #667eea;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-sm.btn-outline-primary {
            color: #ffffff !important;
            border-color: #667eea;
        }

        [data-theme="dark"] .list-group-item {
            background-color: #2a2a3e !important;
            border-color: #4a4a5e !important;
        }

        /* TABLA EN MODO OSCURO - FONDO OSCURO Y TEXTO BLANCO */
        [data-theme="dark"] .table {
            background-color: #16213e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .table th,
        [data-theme="dark"] .table td {
            background-color: #16213e !important;
            color: #ffffff !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .table thead th {
            background-color: #1a2a4e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .table tbody tr:hover {
            background-color: #2a2a4e !important;
        }

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

        [data-theme="dark"] .membership-badge {
            color: #ffffff !important;
        }

        [data-theme="dark"] .membership-badge i,
        [data-theme="dark"] .membership-badge small {
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-theme {
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-theme i {
            color: #ffffff !important;
        }

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

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .stats-card,
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
        [data-theme="light"] .list-group-item,
        [data-theme="light"] .table,
        [data-theme="light"] .table th,
        [data-theme="light"] .table td,
        [data-theme="light"] .form-label {
            color: #212529 !important;
        }

        [data-theme="light"] i,
        [data-theme="light"] .fas,
        [data-theme="light"] .far,
        [data-theme="light"] .fab,
        [data-theme="light"] .btn i {
            color: #212529 !important;
        }

        [data-theme="light"] .text-muted {
            color: #6c757d !important;
        }

        [data-theme="light"] .btn-info i {
            color: #ffffff !important;
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

        /* ============================================
           ESTILOS ORIGINALES
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

        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 20px;
        }

        .btn-group .btn-outline-primary {
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        .btn-group .btn-outline-primary:hover,
        .btn-group .btn-outline-primary.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-color: transparent;
            color: white;
        }

        .list-group-item {
            background: var(--bg-secondary) !important;
            color: var(--text-primary);
            border-color: var(--border-color);
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
        }
        
        .mobile-offcanvas hr {
            border-color: rgba(255, 255, 255, 0.2);
            margin: 10px 0;
        }

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
        }

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
                margin-top: 0px;
            }
            
            .chart-container {
                height: 250px;
            }
            
            .btn-group {
                flex-wrap: wrap;
                gap: 5px;
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

    <!-- OFFCANVAS MÓVIL -->
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
                <a class="nav-link active" href="statistics.php"><i class="fas fa-chart-line"></i> Estadísticas</a>
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
        <!-- SIDEBAR DESKTOP -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>
        </div>

        <!-- CONTENIDO PRINCIPAL -->
        <div class="content-column">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2><i class="fas fa-chart-line"></i> Estadísticas</h2>
                    <div class="btn-group">
                        <a href="?periodo=7" class="btn btn-outline-primary <?php echo $periodo == 7 ? 'active' : ''; ?>">7 días</a>
                        <a href="?periodo=30" class="btn btn-outline-primary <?php echo $periodo == 30 ? 'active' : ''; ?>">30 días</a>
                        <a href="?periodo=90" class="btn btn-outline-primary <?php echo $periodo == 90 ? 'active' : ''; ?>">90 días</a>
                        <a href="?periodo=365" class="btn btn-outline-primary <?php echo $periodo == 365 ? 'active' : ''; ?>">1 año</a>
                    </div>
                </div>

                <!-- VISITAS POR DÍA -->
                <div class="row">
                    <div class="col-md-8">
                        <div class="stats-card">
                            <h5><i class="fas fa-chart-line"></i> Visitas por día</h5>
                            <div class="chart-container">
                                <canvas id="visitsChart"></canvas>
                            </div>
                            <?php if (empty($visitas_diarias)): ?>
                                <div class="alert alert-info mt-3">No hay datos de visitas en el período seleccionado.</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- TOP PUBLICACIONES -->
                    <div class="col-md-4">
                        <div class="stats-card">
                            <h5><i class="fas fa-trophy"></i> Top Publicaciones</h5>
                            <div class="list-group">
                                <?php if (empty($top_publicaciones)): ?>
                                    <div class="list-group-item text-center">No hay publicaciones aún.</div>
                                <?php else: ?>
                                    <?php foreach ($top_publicaciones as $pub): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars(substr($pub['titulo'], 0, 30)); ?></strong>
                                                    <div class="small text-muted">
                                                        <i class="fas fa-eye"></i> <?php echo number_format($pub['visitas']); ?> | 
                                                        <i class="fas fa-heart"></i> <?php echo number_format($pub['likes']); ?> | 
                                                        <i class="fas fa-comment"></i> <?php echo $pub['comentarios']; ?>
                                                    </div>
                                                </div>
                                                <a href="edit-publication.php?id=<?php echo $pub['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- RENDIMIENTO POR CATEGORÍA Y HORARIO -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="stats-card">
                            <h5><i class="fas fa-chart-pie"></i> Rendimiento por categoría</h5>
                            <div class="chart-container" style="height:250px;">
                                <canvas id="categoryChart"></canvas>
                            </div>
                            <?php if (empty($categorias_stats)): ?>
                                <div class="alert alert-info mt-3">No hay datos de categorías disponibles.</div>
                            <?php endif; ?>
                            <?php if (!empty($categorias_stats)): ?>
                                <div class="table-responsive mt-3">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Categoría</th>
                                                <th>Visitas</th>
                                                <th>Publicaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($categorias_stats as $cat): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($cat['nombre']); ?></td>
                                                    <td><?php echo number_format($cat['visitas']); ?></td>
                                                    <td><?php echo $cat['total_publicaciones']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="stats-card">
                            <h5><i class="fas fa-clock"></i> Horario con más visitas</h5>
                            <div class="chart-container" style="height:250px;">
                                <canvas id="hourChart"></canvas>
                            </div>
                            <?php if (empty(array_filter($horas))): ?>
                                <div class="alert alert-info mt-3">No hay datos de horarios disponibles.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- EXPORTAR REPORTES -->
                <div class="stats-card">
                    <h5><i class="fas fa-download"></i> Exportar Reportes</h5>
                    <form id="exportForm" method="POST" action="/easycarluxury/api/v1/export-stats.php" class="row g-3">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
                        <div class="col-md-3">
                            <label class="form-label">Formato</label>
                            <select name="formato" class="form-select">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Seleccione</label>
                            <select name="tipo_reporte" class="form-select">
                                <option value="visitas">Reporte de visitas</option>
                                <option value="publicaciones">Reporte de publicaciones</option>
                                <option value="completo">Reporte completo</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha fin</label>
                            <input type="date" name="fecha_fin" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-download"></i> Exportar</button>
                        </div>
                    </form>
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
            $.ajax({ url: '/easycarluxury/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
        }

        // DATOS DESDE LA BASE DE DATOS
        const visitasFechas = <?php echo json_encode($visitas_fechas); ?>;
        const visitasDiarias = <?php echo json_encode($visitas_datos); ?>;
        const categoriasNombres = <?php echo json_encode($categorias_nombres); ?>;
        const categoriasVisitas = <?php echo json_encode($categorias_visitas); ?>;
        const horasData = <?php echo json_encode($horas); ?>;

        // GRÁFICO 1: Visitas por día (Línea)
        if (visitasFechas.length > 0) {
            new Chart(document.getElementById('visitsChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: visitasFechas,
                    datasets: [{
                        label: 'Visitas',
                        data: visitasDiarias,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102,126,234,0.1)',
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.raw + ' visitas';
                                }
                            }
                        },
                        legend: {
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') },
                            grid: { color: 'rgba(102,126,234,0.2)' }
                        },
                        x: {
                            ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') }
                        }
                    }
                }
            });
        }

        // GRÁFICO 2: Rendimiento por categoría (Dona)
        if (categoriasNombres.length > 0) {
            new Chart(document.getElementById('categoryChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: categoriasNombres,
                    datasets: [{
                        data: categoriasVisitas,
                        backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#fdcb6e', '#6c5ce7', '#00cec9', '#e84393', '#0984e3']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + context.raw + ' visitas';
                                }
                            }
                        },
                        legend: {
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    }
                }
            });
        } else {
            const categoryChart = document.getElementById('categoryChart');
            if (categoryChart) categoryChart.style.display = 'none';
        }

        // GRÁFICO 3: Horario con más visitas (Barras)
        if (horasData.some(v => v > 0)) {
            new Chart(document.getElementById('hourChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: Array.from({length: 24}, (_, i) => i + ':00'),
                    datasets: [{
                        label: 'Visitas',
                        data: horasData,
                        backgroundColor: '#667eea',
                        borderRadius: 5
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Número de visitas', color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') },
                            ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') },
                            grid: { color: 'rgba(102,126,234,0.2)' }
                        },
                        x: {
                            title: { display: true, text: 'Hora del día', color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') },
                            ticks: { color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary') }
                        }
                    }
                }
            });
        }

        // EXPORTAR REPORTE
        $('#exportForm').on('submit', function(e) {
            e.preventDefault();
            
            Swal.fire({
                title: 'Generando reporte...',
                text: 'Por favor espere',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            
            $.ajax({
                url: '/easycarluxury/api/v1/export-stats.php',
                method: 'POST',
                data: $(this).serialize(),
                xhrFields: { responseType: 'blob' },
                success: function(response, status, xhr) {
                    const contentType = xhr.getResponseHeader('Content-Type');
                    const contentDisposition = xhr.getResponseHeader('Content-Disposition');
                    let filename = 'reporte.xlsx';
                    
                    if (contentDisposition && contentDisposition.indexOf('filename=') !== -1) {
                        filename = contentDisposition.split('filename=')[1].replace(/["']/g, '');
                    } else if (contentType === 'text/csv') {
                        filename = 'reporte.csv';
                    } else if (contentType === 'application/pdf') {
                        filename = 'reporte.pdf';
                    } else if (contentType === 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet') {
                        filename = 'reporte.xlsx';
                    }
                    
                    const blob = new Blob([response], { type: contentType });
                    const link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = filename;
                    link.click();
                    URL.revokeObjectURL(link.href);
                    
                    Swal.fire('Éxito', 'Reporte generado correctamente', 'success');
                },
                error: function(xhr) {
                    let errorMsg = 'Error al generar el reporte';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (response.error) errorMsg = response.error;
                    } catch(e) {}
                    Swal.fire('Error', errorMsg, 'error');
                }
            });
        });
    </script>
</body>
</html>