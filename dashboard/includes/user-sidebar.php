<?php
/**
 * Colcars - Sidebar del Usuario (Vendedor)
 * ESTE ARCHIVO SOLO CONTIENE EL CONTENIDO HTML DEL SIDEBAR
 * Variables requeridas: $user, $unread_messages
 */

// Asegurar que la sesión está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_name_display = $_SESSION['user_name'] ?? ($user['nombre_completo'] ?? ($user['username'] ?? 'Usuario'));
?>

<!-- ESTILOS DEL SIDEBAR Y NAVBAR MÓVIL -->
<style>
    /* ============================================
       SIDEBAR COLLAPSIBLE CON HAMBURGUESA DENTRO
       ============================================ */
    
    /* SIDEBAR CON ANIMACIÓN */
    .sidebar-column {
        transition: width 0.3s ease;
        overflow-x: hidden;
        position: relative;
    }
    
    /* SIDEBAR COLLAPSED (CONTRAÍDO) */
    .sidebar-column.collapsed {
        width: 70px !important;
    }
    
    /* BOTÓN HAMBURGUESA DENTRO DEL SIDEBAR */
    .sidebar-toggle-btn {
        width: 35px;
        height: 35px;
        border-radius: 8px;
        background: rgba(255, 255, 255, 0.15);
        border: none;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        margin: 10px auto;
    }
    
    .sidebar-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.05);
    }
    
    /* CONTENEDOR DEL BOTÓN EN EL SIDEBAR */
    .sidebar-toggle-container {
        text-align: center;
        padding: 5px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 10px;
    }
    
    /* CONTENIDO DEL SIDEBAR CUANDO ESTÁ CONTRAÍDO */
    .sidebar-column.collapsed .sidebar-desktop .nav-link span,
    .sidebar-column.collapsed .sidebar-desktop .nav-link .link-text {
        display: none;
    }
    
    .sidebar-column.collapsed .sidebar-desktop .nav-link i {
        margin: 0 auto;
        font-size: 1.3rem;
        text-align: center;
        width: auto;
    }
    
    .sidebar-column.collapsed .sidebar-desktop .nav-link {
        justify-content: center;
        padding: 12px 0;
        text-align: center;
    }
    
    /* LOGO MÁS PEQUEÑO CUANDO ESTÁ CONTRAÍDO */
    .sidebar-column.collapsed .sidebar-desktop .logo-container h5,
    .sidebar-column.collapsed .sidebar-desktop .logo-container small,
    .sidebar-column.collapsed .sidebar-desktop .logo-container .logo-text {
        display: none;
    }
    
    .sidebar-column.collapsed .sidebar-desktop .logo-container img {
        height: 40px !important;
        margin: 5px 0;
    }
    
    .sidebar-column.collapsed .sidebar-desktop .logo-container {
        padding: 10px 0;
    }
    
    /* Tooltip para el texto cuando está contraído */
    .sidebar-column.collapsed .sidebar-desktop .nav-link {
        position: relative;
    }
    
    .sidebar-column.collapsed .sidebar-desktop .nav-link:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: #667eea;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 0.75rem;
        white-space: nowrap;
        margin-left: 10px;
        z-index: 1000;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        pointer-events: none;
    }
    
    /* ============================================
       SIDEBAR DESKTOP - MODO CLARO (DEGRADADO)
       ============================================ */
    .sidebar-desktop {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        height: 100%;
        color: white;
        display: flex;
        flex-direction: column;
        padding: 0;
        transition: all 0.3s ease;
    }
    
    /* ============================================
       SIDEBAR DESKTOP - MODO OSCURO
       ============================================ */
    [data-theme="dark"] .sidebar-desktop {
        background: #16213E !important;
    }
    
    [data-theme="dark"] .sidebar-desktop .sidebar-toggle-btn {
        background: rgba(255, 255, 255, 0.1);
    }
    
    [data-theme="dark"] .sidebar-desktop .sidebar-toggle-btn:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    [data-theme="dark"] .sidebar-desktop .sidebar-toggle-container {
        border-bottom-color: rgba(255, 255, 255, 0.15);
    }
    
    [data-theme="dark"] .sidebar-desktop .nav-link {
        color: rgba(255, 255, 255, 0.85);
    }
    
    [data-theme="dark"] .sidebar-desktop .nav-link:hover,
    [data-theme="dark"] .sidebar-desktop .nav-link.active {
        background: rgba(255, 255, 255, 0.15);
        color: white;
    }
    
    [data-theme="dark"] .sidebar-desktop .logo-container {
        border-bottom-color: rgba(255, 255, 255, 0.15);
    }
    
    [data-theme="dark"] .sidebar-desktop hr {
        border-color: rgba(255, 255, 255, 0.15);
    }
    
    /* Tooltip en modo oscuro */
    [data-theme="dark"] .sidebar-column.collapsed .sidebar-desktop .nav-link:hover::after {
        background: #16213E;
        color: white;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    .logo-container {
        text-align: center;
        padding: 20px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 10px;
        transition: all 0.3s ease;
    }
    
    .logo-container img {
        height: 70px;
        width: auto;
        transition: all 0.3s ease;
    }
    
    .logo-container h5 {
        margin-top: 10px;
        margin-bottom: 5px;
        font-size: 1.1rem;
        transition: all 0.3s ease;
    }
    
    .logo-container small {
        font-size: 0.7rem;
        opacity: 0.8;
    }
    
    .sidebar-desktop .nav-link {
        color: rgba(255, 255, 255, 0.8);
        padding: 10px 15px;
        margin: 3px 0;
        border-radius: 8px;
        transition: all 0.3s;
        font-size: 0.9rem;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .sidebar-desktop .nav-link:hover,
    .sidebar-desktop .nav-link.active {
        background: rgba(255, 255, 255, 0.2);
        color: white;
    }
    
    .sidebar-desktop .nav-link i {
        width: 25px;
        font-size: 1.1rem;
        text-align: center;
    }
    
    .sidebar-desktop .nav-link .link-text {
        flex: 1;
    }
    
    .sidebar-desktop hr {
        border-color: rgba(255, 255, 255, 0.1);
        margin: 10px 15px;
    }
    
    /* ============================================
       NAVBAR MÓVIL - RESPONSIVE (FUERA DEL SIDEBAR)
       ============================================ */
    
    /* NAVBAR MÓVIL - MODO CLARO */
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
    
    /* NAVBAR MÓVIL - MODO OSCURO */
    [data-theme="dark"] .mobile-navbar {
        background: #16213E !important;
    }
    
    [data-theme="dark"] .mobile-navbar .btn-menu {
        background: rgba(255, 255, 255, 0.1);
    }
    
    [data-theme="dark"] .mobile-navbar .btn-menu:hover {
        background: rgba(255, 255, 255, 0.2);
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
    
    /* OFFCANVAS MÓVIL - MODO CLARO */
    .mobile-offcanvas {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        width: 280px;
        z-index: 1050;
    }
    
    /* OFFCANVAS MÓVIL - MODO OSCURO */
    [data-theme="dark"] .mobile-offcanvas {
        background: #16213E !important;
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

    /* RESPONSIVE: MÓVIL */
    @media (max-width: 768px) {
        /* Mostrar navbar móvil */
        .mobile-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        /* Ajustar body para no quedar debajo del navbar fijo */
        body {
            padding-top: 60px !important;
        }
        
        /* Ajustar badges fijos en móvil */
        .membership-badge {
            top: 70px !important;
            right: 10px !important;
            font-size: 0.65rem !important;
            padding: 4px 8px !important;
            z-index: 1001 !important;
        }
        
        .btn-theme {
            bottom: 20px !important;
            right: 15px !important;
            width: 40px !important;
            height: 40px !important;
            font-size: 1rem !important;
        }
        
        /* Ajustar dashboard-wrapper en móvil */
        .dashboard-wrapper {
            display: block !important;
        }
        
        /* Ocultar columna del sidebar en móvil */
        .sidebar-column {
            display: none !important;
        }
        
        /* Ajustar columna de contenido en móvil */
        .content-column {
            width: 100% !important;
        }
        
        /* Ajustar padding del main-content en móvil */
        .main-content {
            padding: 15px !important;
            margin-top: 0 !important;
        }
    }
    
    /* DESKTOP */
    @media (min-width: 769px) {
        .mobile-navbar {
            display: none !important;
        }
        
        .sidebar-column {
            width: 260px;
            display: flex !important;
        }
    }
</style>

<!-- NAVBAR MÓVIL (FUERA DEL SIDEBAR) -->
<div class="mobile-navbar">
    <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
        <i class="fas fa-bars"></i>
    </button>
    <div class="navbar-brand">
        <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">
        <span>Colcars</span>
    </div>
    <div class="user-info">
        <i class="fas fa-user-circle"></i>
        <span><?php echo htmlspecialchars(substr($user_name_display, 0, 12)); ?></span>
    </div>
</div>

<!-- OFFCANVAS MENÚ MÓVIL -->
<div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">
            <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">

        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="text-center mb-3" style="color: white;">
            <i class="fas fa-user-circle fa-2x"></i>
            <p class="mt-2 mb-0"><?php echo htmlspecialchars($user_name_display); ?></p>
        </div>
        <hr>
        <nav class="nav flex-column">
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-publications.php' ? 'active' : ''; ?>" href="my-publications.php">
                <i class="fas fa-list"></i> Mis Publicaciones
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'new-publication.php' ? 'active' : ''; ?>" href="new-publication.php">
                <i class="fas fa-plus-circle"></i> Nueva Publicación
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                <i class="fas fa-envelope"></i> Mensajes
                <?php if (($unread_messages['total'] ?? 0) > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo $unread_messages['total']; ?></span>
                <?php endif; ?>
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-offers.php' ? 'active' : ''; ?>" href="my-offers.php">
                <i class="fas fa-gavel"></i> Mis Ofertas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>" href="statistics.php">
                <i class="fas fa-chart-line"></i> Estadísticas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'membership.php' ? 'active' : ''; ?>" href="membership.php">
                <i class="fas fa-gem"></i> Membresía
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                <i class="fas fa-credit-card"></i> Pagos
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' || basename($_SERVER['PHP_SELF']) == '2invoices.php' ? 'active' : ''; ?>" href="invoices.php">
                <i class="fas fa-file-invoice"></i> Facturas
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
                <i class="fas fa-user"></i> Mi Perfil
            </a>
            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog"></i> Configuración
            </a>
            <hr class="my-2">
            <a class="nav-link" href="/logout.php">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
</div>

<!-- SIDEBAR DESKTOP CON BOTÓN HAMBURGUESA DENTRO -->
<div class="sidebar-desktop">
    <!-- BOTÓN HAMBURGUESA DENTRO DEL SIDEBAR -->
    <div class="sidebar-toggle-container">
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <!-- LOGO -->
    <div class="logo-container">
        <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">
        <h5 class="logo-text">Colcars</h5>
        <small><?php echo htmlspecialchars($user_name_display); ?></small>
    </div>
    
    <!-- MENÚ DE NAVEGACIÓN -->
    <nav class="nav flex-column">
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" href="index.php" data-tooltip="Dashboard">
            <i class="fas fa-tachometer-alt"></i>
            <span class="link-text">Dashboard</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-publications.php' ? 'active' : ''; ?>" href="my-publications.php" data-tooltip="Mis Publicaciones">
            <i class="fas fa-list"></i>
            <span class="link-text">Mis Publicaciones</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'new-publication.php' ? 'active' : ''; ?>" href="new-publication.php" data-tooltip="Nueva Publicación">
            <i class="fas fa-plus-circle"></i>
            <span class="link-text">Nueva Publicación</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'messages.php' ? 'active' : ''; ?>" href="messages.php" data-tooltip="Mensajes">
            <i class="fas fa-envelope"></i>
            <span class="link-text">Mensajes</span>
            <?php if (($unread_messages['total'] ?? 0) > 0): ?>
                <span class="badge bg-danger ms-auto"><?php echo $unread_messages['total']; ?></span>
            <?php endif; ?>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'my-offers.php' ? 'active' : ''; ?>" href="my-offers.php" data-tooltip="Mis Ofertas">
            <i class="fas fa-gavel"></i>
            <span class="link-text">Mis Ofertas</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'active' : ''; ?>" href="statistics.php" data-tooltip="Estadísticas">
            <i class="fas fa-chart-line"></i>
            <span class="link-text">Estadísticas</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'membership.php' ? 'active' : ''; ?>" href="membership.php" data-tooltip="Membresía">
            <i class="fas fa-gem"></i>
            <span class="link-text">Membresía</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : ''; ?>" href="payments.php" data-tooltip="Pagos">
            <i class="fas fa-credit-card"></i>
            <span class="link-text">Pagos</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'invoices.php' || basename($_SERVER['PHP_SELF']) == '2invoices.php' ? 'active' : ''; ?>" href="invoices.php" data-tooltip="Facturas">
            <i class="fas fa-file-invoice"></i>
            <span class="link-text">Facturas</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" href="profile.php" data-tooltip="Mi Perfil">
            <i class="fas fa-user"></i>
            <span class="link-text">Mi Perfil</span>
        </a>
        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" href="settings.php" data-tooltip="Configuración">
            <i class="fas fa-cog"></i>
            <span class="link-text">Configuración</span>
        </a>
        <hr class="my-2 mx-2">
            <a class="nav-link" href="/logout.php" data-tooltip="Cerrar Sesión">
            <i class="fas fa-sign-out-alt"></i>
            <span class="link-text">Cerrar Sesión</span>
        </a>
    </nav>
</div>

<!-- SCRIPT PARA TOGGLE DEL SIDEBAR -->
<script>
    // Función para colapsar/expandir el sidebar
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar-column');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        
        if (sidebar && toggleBtn) {
            const icon = toggleBtn.querySelector('i');
            sidebar.classList.toggle('collapsed');
            
            if (sidebar.classList.contains('collapsed')) {
                if (icon) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-chevron-right');
                }
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                if (icon) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-bars');
                }
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }
    }
    
    // Cargar el estado guardado del sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.querySelector('.sidebar-column');
        const toggleBtn = document.getElementById('sidebarToggleBtn');
        
        if (sidebar && toggleBtn && localStorage.getItem('sidebarCollapsed') === 'true') {
            sidebar.classList.add('collapsed');
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-chevron-right');
            }
        }
    });
</script>