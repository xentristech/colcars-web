<?php
/**
 * Colcars - Membresías y Planes
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

$membresia_actual = $db->getOne("SELECT * FROM membresias_contratadas WHERE usuario_id = ? AND fecha_fin >= CURDATE() ORDER BY fecha_fin DESC LIMIT 1", [$user_id]);

$planes = [
    'free' => ['nombre' => 'FREE', 'precio' => 0, 'precio_formateado' => 'Gratis', 'icono' => 'fa-user', 'color' => 'secondary', 'caracteristicas' => ['2 publicaciones gratis', 'Visibilidad básica', 'Soporte por email', 'Duración: 30 días']],
    'pro' => ['nombre' => 'PRO', 'precio' => 49900, 'precio_formateado' => '$49.900 COP', 'icono' => 'fa-star', 'color' => 'primary', 'caracteristicas' => ['Publicaciones ilimitadas', '2x más apariciones en búsquedas', '2x más visitas', 'Soporte prioritario', 'Impulso en redes sociales', 'Duración: 30 días']],
    'premium' => ['nombre' => 'PREMIUM', 'precio' => 89900, 'precio_formateado' => '$89.900 COP', 'icono' => 'fa-gem', 'color' => 'warning', 'caracteristicas' => ['Publicaciones ilimitadas', '3x más apariciones en búsquedas', '3x más visitas', 'Contenido exclusivo', 'Soporte 24/7', 'Documentos visibles', 'Duración: 30 días']],
    'elite' => ['nombre' => 'ELITE', 'precio' => 168000, 'precio_formateado' => '$168.000 COP', 'icono' => 'fa-crown', 'color' => 'danger', 'caracteristicas' => ['Publicaciones ilimitadas', '20x más apariciones en búsquedas', '10x más visitas', 'Te acompañamos en todo el proceso', 'Participación en ferias presenciales', 'Certificado Colcars', 'Descuentos con asociados', 'Duración: 30 días']]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['plan'])) {
    $plan = $_POST['plan'];
    $auto_renovable = isset($_POST['auto_renovable']) ? 1 : 0;
    
    if (!isset($planes[$plan])) {
        $error = 'Plan no válido';
    } elseif ($plan === 'free') {
        $error = 'No puedes comprar el plan gratuito';
    } else {
        $_SESSION['purchase_plan'] = $plan;
        $_SESSION['purchase_auto_renovable'] = $auto_renovable;
        $_SESSION['purchase_monto'] = $planes[$plan]['precio'];
        header('Location: payment-gateway.php');
        exit;
    }
}

$csrf_token = generateCSRFToken();
$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membresías - Colcars</title>
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
            --text-secondary: #a0a0a0;
            --border-color: #2a2a3e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO OSCURO - TABLA
           ============================================ */
        [data-theme="dark"] .table {
            background-color: #16213e;
            color: #ffffff !important;
        }

        [data-theme="dark"] .table th,
        [data-theme="dark"] .table td {
            background-color: #16213e;
            color: #ffffff !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .table thead th {
            background-color: #1a2a4e;
            color: #ffffff !important;
        }

        [data-theme="dark"] .table tbody tr:hover {
            background-color: #2a2a4e !important;
        }

        /* ============================================
           ESTILOS MODAL BOOTSTRAP EN MODO OSCURO
           ============================================ */
        [data-theme="dark"] .modal-content {
            background-color: #16213e;
            border: 1px solid #2a2a3e;
            color: #ffffff !important;
        }

        [data-theme="dark"] .modal-header {
            border-bottom-color: #2a2a3e;
            background-color: #16213e;
        }

        [data-theme="dark"] .modal-header .modal-title {
            color: #ffffff !important;
        }

        [data-theme="dark"] .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        [data-theme="dark"] .modal-body {
            background-color: #16213e;
            color: #ffffff !important;
        }

        [data-theme="dark"] .modal-body h4,
        [data-theme="dark"] .modal-body p,
        [data-theme="dark"] .modal-body div,
        [data-theme="dark"] .modal-body ul,
        [data-theme="dark"] .modal-body li,
        [data-theme="dark"] .modal-body .text-muted {
            color: #ffffff !important;
        }

        [data-theme="dark"] .modal-body hr {
            background-color: #2a2a3e;
            opacity: 0.5;
        }

        [data-theme="dark"] .modal-body .form-check-label {
            color: #ffffff !important;
        }

        [data-theme="dark"] .modal-footer {
            border-top-color: #2a2a3e;
            background-color: #16213e;
        }

        [data-theme="dark"] .modal-footer .btn-secondary {
            background-color: #2a2a3e;
            border-color: #4a4a5e;
            color: #ffffff !important;
        }

        [data-theme="dark"] .modal-footer .btn-secondary:hover {
            background-color: #3a3a4e;
        }

        [data-theme="dark"] .modal-footer .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            color: #ffffff !important;
        }

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

        .plan-card {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            position: relative;
        }

        .plan-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .plan-card.selected {
            border: 2px solid #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.2);
        }

        .plan-icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .plan-price {
            font-size: 2rem;
            font-weight: bold;
        }

        .plan-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }

        .plan-features li {
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .plan-features li i {
            margin-right: 10px;
            color: #28a745;
        }

        .current-badge {
            position: absolute;
            top: -10px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 12px;
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

        .accordion-button {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        .accordion-item {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
        }
        
        .accordion-body {
            background-color: var(--bg-secondary);
            color: var(--text-primary);
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
                margin-top: 0px;
            }
            
            .plan-price {
                font-size: 1.5rem;
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
                <a class="nav-link active" href="membership.php"><i class="fas fa-gem"></i> Membresía</a>
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
        <!-- COLUMNA DEL SIDEBAR (incluye el sidebar desktop) -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>
        </div>

        <!-- COLUMNA DEL CONTENIDO -->
        <div class="content-column">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2><i class="fas fa-gem"></i> Membresías</h2>
                </div>
                <p class="text-muted">Elige el plan que mejor se adapte a tus necesidades</p>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($user['tipo_cuenta'] !== 'free'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Membresía actual: <?php echo strtoupper($user['tipo_cuenta']); ?></strong><br>
                        Expira el: <?php echo date('d/m/Y', strtotime($user['fecha_expiracion'])); ?>
                        <?php if ($membresia_actual && $membresia_actual['auto_renovable']): ?>
                            <span class="badge bg-success">Renovación automática activada</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Tarjetas de Planes -->
                <div class="row">
                    <?php foreach ($planes as $key => $plan): ?>
                        <div class="col-md-3">
                            <div class="plan-card position-relative text-center" onclick="selectPlan('<?php echo $key; ?>')" id="plan-<?php echo $key; ?>">
                                <?php if ($user['tipo_cuenta'] === $key): ?>
                                    <div class="current-badge"><i class="fas fa-check"></i> Plan Actual</div>
                                <?php endif; ?>
                                <div class="plan-icon"><i class="fas <?php echo $plan['icono']; ?> text-<?php echo $plan['color']; ?>"></i></div>
                                <h3><?php echo $plan['nombre']; ?></h3>
                                <div class="plan-price text-<?php echo $plan['color']; ?>"><?php echo $plan['precio_formateado']; ?></div>
                                <ul class="plan-features text-start">
                                    <?php foreach ($plan['caracteristicas'] as $feature): ?>
                                        <li><i class="fas fa-check-circle"></i> <?php echo $feature; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php if ($user['tipo_cuenta'] !== $key && $key !== 'free'): ?>
                                    <button class="btn btn-<?php echo $plan['color']; ?> w-100" onclick="event.stopPropagation(); showPurchaseModal('<?php echo $key; ?>')"><i class="fas fa-shopping-cart"></i> Comprar</button>
                                <?php elseif ($key === 'free' && $user['tipo_cuenta'] !== 'free'): ?>
                                    <button class="btn btn-outline-secondary w-100" onclick="event.stopPropagation(); downgradeToFree()"><i class="fas fa-arrow-down"></i> Degradar a FREE</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Comparativa de Planes -->
                <div class="stats-card mt-4">
                    <h5><i class="fas fa-chart-simple"></i> Comparativa de Planes</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Característica</th>
                                    <th>FREE</th>
                                    <th>PRO</th>
                                    <th>PREMIUM</th>
                                    <th>ELITE</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>Precio</td><td>$0</td><td>$49.900</td><td>$89.900</td><td>$168.000</td></tr>
                                <tr><td>Publicaciones</td><td>2</td><td>Ilimitadas</td><td>Ilimitadas</td><td>Ilimitadas</td></tr>
                                <tr><td>Apariciones en búsquedas</td><td>1x</td><td>2x</td><td>3x</td><td>20x</td></tr>
                                <tr><td>Visitas</td><td>1x</td><td>2x</td><td>3x</td><td>10x</td></tr>
                                <tr><td>Documentos visibles</td><td>No</td><td>No</td><td>Sí</td><td>Sí</td></tr>
                                <tr><td>Soporte prioritario</td><td>No</td><td>Sí</td><td>Sí</td><td>Sí</td></tr>
                                <tr><td>Participación en ferias</td><td>No</td><td>No</td><td>No</td><td>Sí</td></tr>
                                <tr><td>Certificado Colcars</td><td>No</td><td>No</td><td>No</td><td>Sí</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Preguntas Frecuentes -->
                <div class="stats-card mt-3">
                    <h5><i class="fas fa-question-circle"></i> Preguntas Frecuentes</h5>
                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    ¿Cómo funciona la renovación automática?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">Al activar la renovación automática, tu membresía se renovará automáticamente al vencer, cobrando el mismo método de pago que utilizaste originalmente.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    ¿Puedo cambiar de plan en cualquier momento?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">Sí, puedes actualizar o degradar tu plan en cualquier momento.</div>
                            </div>
                        </div>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    ¿Qué métodos de pago aceptan?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">Aceptamos PSE, tarjetas de crédito/débito, Nequi y Daviplata.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Compra -->
    <div class="modal fade" id="purchaseModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar Compra</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="plan" id="selected_plan">
                        <div id="planInfo"></div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" name="auto_renovable" id="auto_renovable">
                            <label class="form-check-label" for="auto_renovable">Activar renovación automática</label>
                            <small class="d-block text-muted">Tu membresía se renovará automáticamente al vencer</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-credit-card"></i> Continuar al pago</button>
                    </div>
                </form>
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
        const planes = <?php echo json_encode($planes); ?>;
        
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
            
            // Si es un modal de confirmación con botón de confirmar rojo
            if (options.confirmButtonColor === '#d33' || options.confirmButtonText === 'Sí, degradar') {
                swalOptions.confirmButtonColor = '#dc3545';
            }
            
            return Swal.fire(swalOptions);
        }
        
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({ url: '/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
        }
        
        function selectPlan(plan) {
            document.querySelectorAll('.plan-card').forEach(card => card.classList.remove('selected'));
            document.getElementById(`plan-${plan}`).classList.add('selected');
        }
        
        function showPurchaseModal(plan) {
            document.getElementById('selected_plan').value = plan;
            document.getElementById('planInfo').innerHTML = `
                <div class="text-center">
                    <i class="fas ${planes[plan].icono} fa-3x text-${planes[plan].color} mb-3"></i>
                    <h4>${planes[plan].nombre}</h4>
                    <div class="display-6 text-${planes[plan].color}">${planes[plan].precio_formateado}</div>
                    <p class="mt-3">Duración: 30 días</p>
                    <hr>
                    <ul class="text-start">
                        ${planes[plan].caracteristicas.map(f => `<li><i class="fas fa-check-circle text-success"></i> ${f}</li>`).join('')}
                    </ul>
                </div>
            `;
            new bootstrap.Modal(document.getElementById('purchaseModal')).show();
        }
        
        function downgradeToFree() {
            showSwalWithTheme({
                title: '¿Degradar a cuenta FREE?',
                text: 'Perderás todas las ventajas de tu membresía actual. Esta acción es irreversible.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, degradar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/api/v1/memberships/downgrade.php',
                        method: 'POST',
                        success: function(response) {
                            showSwalWithTheme({
                                title: '¡Degradado!',
                                text: 'Tu cuenta ha sido degradada a FREE correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                location.reload();
                            });
                        },
                        error: function(xhr, status, error) {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'No se pudo degradar la cuenta. Intenta nuevamente.',
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