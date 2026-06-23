<?php
/**
 * C:\wamp64\www\easycarluxury\dashboard\user\my-offers.php
 * Panel del usuario - Ofertas recibidas en sus publicaciones
 */

if (session_status() === PHP_SESSION_NONE) { session_start(); }

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación usando requireAuth()
requireAuth();

$database = Database::getInstance();
$pdo = $database->getConnection();

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /easycarluxury/public/login.php');
    exit;
}

$user = $database->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

// Obtener ofertas para las publicaciones del usuario
$query = "SELECT o.*, p.titulo as publication_title, p.id as publication_id
          FROM offers o
          JOIN publicaciones p ON o.publication_id = p.id
          WHERE p.usuario_id = :user_id
          ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute([':user_id' => $user_id]);
$offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unread_messages = $database->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);
$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <title>Mis Ofertas Recibidas - Easy Car Luxury</title>
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
        [data-theme="dark"] .offer-card,
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
        [data-theme="dark"] label {
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

        /* ALERTAS EN MODO OSCURO */
        [data-theme="dark"] .alert-info {
            background-color: #1a3a5a;
            color: #ccffff !important;
            border-color: #2a5a7a;
        }
        [data-theme="dark"] .alert-info i {
            color: inherit !important;
        }

        /* FORMULARIOS EN MODO OSCURO */
        [data-theme="dark"] .form-control {
            background-color: #2a2a3e;
            border-color: #4a4a5e;
            color: #ffffff !important;
        }
        [data-theme="dark"] .form-control:focus {
            background-color: #3a3a4e;
            color: #ffffff !important;
        }

        /* BOTONES EN MODO OSCURO */
        [data-theme="dark"] .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #ffffff !important;
        }
        [data-theme="dark"] .btn-outline-light {
            color: #ffffff !important;
            border-color: #ffffff;
        }
        [data-theme="dark"] .btn-outline-light:hover {
            background-color: #ffffff;
            color: #1a1a2e !important;
        }

        /* FOOTER EN MODO OSCURO */
        [data-theme="dark"] .footer {
            background: #0a0a15 !important;
            color: #c0c0c0 !important;
        }
        [data-theme="dark"] .footer p {
            color: #c0c0c0 !important;
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

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        /* TEXTO E ICONOS EN MODO CLARO - NEGROS */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .offer-card,
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
        [data-theme="light"] label {
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
        .offer-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .offer-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.15);
        }

        .badge-pending {
            background: #f39c12;
            color: white;
        }

        .badge-accepted {
            background: #27ae60;
            color: white;
        }

        .badge-rejected {
            background: #e74c3c;
            color: white;
        }

        .badge-counter {
            background: #3498db;
            color: white;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-action {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.75rem;
            margin: 0 3px;
        }

        .offer-amount {
            font-size: 1.2rem;
            font-weight: bold;
            color: #27ae60;
        }

        [data-theme="dark"] .offer-amount {
            color: #2ecc71;
        }

        .footer {
            background: #0a0a15;
            padding: 20px 0;
            margin-top: 40px;
            text-align: center;
            color: #b0b0b0;
        }

        .back-btn {
            margin-bottom: 20px;
        }

        .container-custom {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            width: 100%;
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

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .mobile-navbar { display: flex; align-items: center; justify-content: space-between; }
            .sidebar-column { display: none; }
            body { padding-top: 60px; }
            .main-content { padding: 15px; }
            .membership-badge { top: 70px; right: 10px; font-size: 0.7rem; padding: 5px 10px; }
            .btn-theme { bottom: 70px; right: 15px; width: 45px; height: 45px; }
            .container-custom { padding: 15px; }
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
                <a class="nav-link" href="messages.php"><i class="fas fa-envelope"></i> Mensajes <?php if (($unread_messages['total'] ?? 0) > 0): ?><span class="badge bg-danger ms-1"><?php echo $unread_messages['total']; ?></span><?php endif; ?></a>
                <a class="nav-link active" href="my-offers.php"><i class="fas fa-gavel"></i> Mis Ofertas</a>
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
            <div class="container-custom">
                <div class="back-btn">
                    <a href="/easycarluxury/dashboard/user/" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Volver al Dashboard</a>
                </div>

                <h2><i class="fas fa-gavel"></i> Ofertas Recibidas</h2>
                <p class="text-muted">Aquí puedes ver todas las ofertas económicas que los compradores han hecho en tus publicaciones.</p>

                <?php if (count($offers) > 0): ?>
                    <?php foreach ($offers as $offer): ?>
                        <div class="offer-card" data-offer-id="<?php echo $offer['id']; ?>">
                            <div class="row">
                                <div class="col-md-8">
                                    <h5><i class="fas fa-car"></i> <?php echo htmlspecialchars($offer['publication_title']); ?></h5>
                                    <p class="mb-1"><strong><i class="fas fa-user"></i> Comprador:</strong>
                                        <?php echo htmlspecialchars($offer['buyer_name']); ?></p>
                                    <p class="mb-1"><strong><i class="fab fa-whatsapp"></i> Contacto:</strong>
                                        <?php echo htmlspecialchars($offer['buyer_phone']); ?></p>
                                    <p class="mb-1"><strong><i class="fas fa-comment"></i> Mensaje:</strong>
                                        <?php echo nl2br(htmlspecialchars($offer['message'] ?? 'Sin mensaje')); ?></p>
                                    <small class="text-muted"><i class="fas fa-calendar"></i> Recibida:
                                        <?php echo date('d/m/Y H:i', strtotime($offer['created_at'])); ?></small>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="offer-amount">$ <?php echo number_format($offer['amount'], 0, ',', '.'); ?></div>
                                    <div class="mt-2 mb-2">
                                        <span class="status-badge badge-<?php echo $offer['status']; ?>">
                                            <?php
                                            $statusText = ['pending' => 'Pendiente', 'accepted' => 'Aceptada', 'rejected' => 'Rechazada', 'counter' => 'Contra-oferta'];
                                            echo $statusText[$offer['status']] ?? $offer['status'];
                                            ?>
                                        </span>
                                    </div>
                                    <?php if ($offer['status'] === 'pending'): ?>
                                        <div>
                                            <button class="btn-action btn-success accept-offer"
                                                data-offer-id="<?php echo $offer['id']; ?>"><i class="fas fa-check"></i> Aceptar</button>
                                            <button class="btn-action btn-danger reject-offer"
                                                data-offer-id="<?php echo $offer['id']; ?>"><i class="fas fa-times"></i> Rechazar</button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="alert alert-info">No has recibido ofertas aún. Cuando los compradores hagan ofertas en tus publicaciones, aparecerán aquí.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Colcars - Todos los derechos reservados</p>
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

        function updateOfferStatus(offerId, status) {
            fetch('/easycarluxury/api/v1/interactions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_offer_status', offer_id: offerId, status: status })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: status === 'accepted' ? 'Oferta aceptada' : 'Oferta rechazada',
                            text: status === 'accepted' ? 'Has aceptado la oferta correctamente' : 'Has rechazado la oferta correctamente',
                            icon: 'success'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Error', 'Error al actualizar el estado de la oferta', 'error');
                    }
                })
                .catch(() => Swal.fire('Error', 'Error de conexión', 'error'));
        }

        document.querySelectorAll('.accept-offer').forEach(btn => {
            btn.addEventListener('click', () => {
                Swal.fire({
                    title: '¿Aceptar oferta?',
                    text: 'Al aceptar esta oferta, se notificará al comprador',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    confirmButtonText: 'Sí, aceptar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        updateOfferStatus(btn.dataset.offerId, 'accepted');
                    }
                });
            });
        });

        document.querySelectorAll('.reject-offer').forEach(btn => {
            btn.addEventListener('click', () => {
                Swal.fire({
                    title: '¿Rechazar oferta?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, rechazar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        updateOfferStatus(btn.dataset.offerId, 'rejected');
                    }
                });
            });
        });
    </script>
</body>
</html>