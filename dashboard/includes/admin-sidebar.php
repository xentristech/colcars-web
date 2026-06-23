<?php
/**
 * Easy Car Luxury - Sidebar del Administrador
 * ARCHIVO ESTANDARIZADO PARA EL PANEL DE ADMINISTRACIÓN
 * MODIFICADO: Usa CDN en lugar de archivos locales
 * MODIFICADO: Rutas absolutas corregidas (sin /easycarluxury/)
 * MODIFICADO: Sidebar ocupa todo el largo disponible (100vh)
 * Incluye sidebar colapsable con botón hamburguesa dentro, navbar móvil y offcanvas
 * 
 * Variables requeridas: $admin (array con datos del admin) o variables de sesión
 * Uso: include_once __DIR__ . '/../includes/admin-sidebar.php';
 */

// Prevenir inclusión múltiple del sidebar
if (defined('ADMIN_SIDEBAR_LOADED')) {
    return;
}
define('ADMIN_SIDEBAR_LOADED', true);

// Asegurar que la sesión está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Obtener nombre del admin para mostrar
$admin_name_display = $_SESSION['admin_name'] ?? ($admin['full_name'] ?? ($admin['nombre_completo'] ?? 'Administrador'));
$admin_role_display = $_SESSION['admin_role'] ?? ($admin['role'] ?? 'admin');

// Obtener la página actual para resaltar el enlace activo
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- ESTILOS DEL SIDEBAR Y NAVBAR MÓVIL PARA ADMIN -->
<style>
/* ============================================
        SIDEBAR COLLAPSIBLE CON HAMBURGUESA DENTRO
        QUE OCUPA TODO EL LARGO DISPONIBLE
       ============================================ */

/* RESET Y ESTILOS BASE */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* SIDEBAR CON ALTURA COMPLETA */
.sidebar-column {
    transition: width 0.3s ease;
    overflow-x: visible;
    overflow-y: hidden;
    position: fixed;
    z-index: 100;
    padding: 0px;
    height: 100vh !important;
    top: 0;
    left: 0;
    bottom: 0;
    width: 250px;
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
    padding: 3px;
}

.sidebar-column.collapsed .sidebar-desktop .logo-container {
    padding: 10px 0;
}

/* SIDEBAR DESKTOP - ESTILOS NORMALES CON ALTURA COMPLETA */
.sidebar-desktop {
    background: linear-gradient(180deg, #0a0e27 0%, #0f1535 100%);
    height: 100% !important;
    min-height: 100vh;
    color: white;
    display: flex;
    flex-direction: column;
    padding: 0;
    overflow-y: auto;
    overflow-x: hidden;
}

/* Personalizar scrollbar para el sidebar */
.sidebar-desktop::-webkit-scrollbar {
    width: 5px;
}

.sidebar-desktop::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
}

.sidebar-desktop::-webkit-scrollbar-thumb {
    background: #c8a86b;
    border-radius: 5px;
}

.sidebar-desktop::-webkit-scrollbar-thumb:hover {
    background: #b8945a;
}

.logo-container {
    text-align: center;
    padding: 20px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 10px;
    transition: all 0.3s ease;
    flex-shrink: 0;
}

.logo-container img {
    height: 60px;
    width: auto;
    transition: all 0.3s ease;
}

.logo-container h5 {
    margin-top: 10px;
    margin-bottom: 5px;
    font-size: 1rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.logo-container small {
    font-size: 0.65rem;
    opacity: 0.7;
    display: block;
}

/* MENÚ DE NAVEGACIÓN - OCUPA EL ESPACIO RESTANTE */
.sidebar-desktop nav {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Contenedor del menú principal */
.nav-menu-container {
    flex: 1;
}

/* Sección de cerrar sesión al final */
.nav-footer {
    margin-top: auto;
    flex-shrink: 0;
    border-top: 1px solid var(--border-color);
}

.sidebar-desktop .nav-link {
    color: rgba(255, 255, 255, 0.8);
    padding: 10px 15px;
    margin: 3px 8px;
    border-radius: 8px;
    transition: all 0.3s;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-desktop .nav-link:hover,
.sidebar-desktop .nav-link.active {
    background: rgba(200, 168, 107, 0.15);
    color: #c8a86b;
}

.sidebar-desktop .nav-link i {
    width: 22px;
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

/* Badge para notificaciones en sidebar */
.sidebar-desktop .badge-notify {
    background: #c8a86b;
    color: #0a0e27;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    margin-left: auto;
}

/* ===============================================
        NAVBAR MÓVIL - RESPONSIVE (FUERA DEL SIDEBAR)
       =============================================== */
.mobile-navbar {
    display: none;
    background: linear-gradient(180deg, #0a0e27 0%, #0f1535 100%);
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
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #c8a86b;
    font-size: 1.4rem;
    padding: 6px 12px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.mobile-navbar .btn-menu:hover {
    background: rgba(200, 168, 107, 0.3);
    color: white;
}

.mobile-navbar .user-info {
    color: white;
    font-size: 0.7rem;
    background: rgba(200, 168, 107, 0.2);
    padding: 6px 12px;
    border-radius: 20px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.mobile-navbar .user-info i {
    color: #c8a86b;
}

/* OFFCANVAS MÓVIL */
.mobile-offcanvas {
    background: linear-gradient(180deg, #0a0e27 0%, #0f1535 100%);
    width: 280px;
    z-index: 1050;
}

.mobile-offcanvas .offcanvas-header {
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
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
    background: rgba(200, 168, 107, 0.15);
    color: #c8a86b;
}

.mobile-offcanvas .nav-link i {
    width: 25px;
    font-size: 1.1rem;
    text-align: center;
}

.mobile-offcanvas hr {
    border-color: rgba(255, 255, 255, 0.1);
    margin: 10px 0;
}

/* Rol badge en offcanvas */
.role-badge-mobile {
    background: rgba(200, 168, 107, 0.2);
    color: #c8a86b;
    font-size: 0.7rem;
    padding: 3px 8px;
    border-radius: 20px;
    display: inline-block;
}

/* ESTILOS PARA EL CONTENIDO PRINCIPAL */
.admin-main {
    margin-left: 280px;
    transition: margin-left 0.3s ease;
    min-height: 100vh;
    width: auto;
}

/* Cuando el sidebar está contraído */
.sidebar-column.collapsed~.admin-main,
.sidebar-column.collapsed+.admin-main {
    margin-left: 70px;
}

/* RESPONSIVE: MÓVIL */
@media (max-width: 992px) {

    /* El sidebar se oculta en móvil */
    .sidebar-column {
        display: none !important;
    }

    /* Mostrar navbar móvil */
    .mobile-navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    /* Ajustar contenido principal en móvil */
    .admin-main {
        margin-left: 0 !important;
        margin-top: 60px;
    }
}

/* DESKTOP */
@media (min-width: 993px) {
    .mobile-navbar {
        display: none !important;
    }

    .sidebar-column {
        display: block !important;
    }
}

/* Estilo base para tooltips dinámicos con JavaScript */
.admin-tooltip {
    position: fixed;
    background: #c8a86b;
    color: #0a0e27;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 500;
    white-space: nowrap;
    z-index: 999999;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
    pointer-events: none;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    transition: opacity 0.15s ease;
}
</style>

<!-- jQuery - CDN (necesario para Bootstrap) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JS Bundle - CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Font Awesome - CDN -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- NAVBAR MÓVIL (FUERA DEL SIDEBAR) -->
<div class="mobile-navbar">
    <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
        <i class="fas fa-bars"></i>
    </button>
    <div class="navbar-brand">
        <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">
        <span>Easy Car</span>
    </div>
    <div class="user-info">
        <i class="fas fa-user-shield"></i>
        <span><?php echo htmlspecialchars(substr($admin_name_display, 0, 12)); ?></span>
    </div>
</div>

<!-- OFFCANVAS MENÚ MÓVIL -->
<div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileOffcanvas">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title">
            <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">
            Colcars
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body">
        <div class="text-center mb-3">
            <i class="fas fa-user-shield fa-2x" style="color: #c8a86b;"></i>
            <p class="mt-2 mb-0" style="color: white; font-weight: 500;">
                <?php echo htmlspecialchars($admin_name_display); ?></p>
            <small class="role-badge-mobile mt-1"><?php echo ucfirst(htmlspecialchars($admin_role_display)); ?></small>
        </div>
        <hr>
        <nav class="nav flex-column">
            <a class="nav-link <?php echo $current_page == 'index.php' || $current_page == 'dashboard.php' ? 'active' : ''; ?>"
                href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users"></i> Usuarios
            </a>
            <a class="nav-link <?php echo $current_page == 'publications.php' ? 'active' : ''; ?>"
                href="publications.php">
                <i class="fas fa-car"></i> Publicaciones
            </a>
            <a class="nav-link <?php echo $current_page == 'categorias.php' ? 'active' : ''; ?>" href="categorias.php">
                <i class="fas fa-tags"></i> Categorías
            </a>
            <a class="nav-link <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>" href="payments.php">
                <i class="fas fa-credit-card"></i> Pagos
            </a>
            <a class="nav-link <?php echo $current_page == 'memberships.php' ? 'active' : ''; ?>"
                href="memberships.php">
                <i class="fas fa-crown"></i> Membresías
            </a>
            <a class="nav-link <?php echo $current_page == 'advertisements.php' ? 'active' : ''; ?>"
                href="advertisements.php">
                <i class="fas fa-ad"></i> Anuncios
            </a>
            <a class="nav-link <?php echo $current_page == 'listar-publicidad.php' ? 'active' : ''; ?>"
                href="listar-publicidad.php">
                <i class="fas fa-bullhorn"></i> Publicidad
            </a>
            <a class="nav-link <?php echo $current_page == 'audit.php' ? 'active' : ''; ?>" href="audit.php">
                <i class="fas fa-history"></i> Auditoría
            </a>
            <a class="nav-link <?php echo $current_page == 'statistics.php' ? 'active' : ''; ?>" href="statistics.php">
                <i class="fas fa-chart-line"></i> Estadísticas
            </a>
            <a class="nav-link <?php echo $current_page == 'mass-communications.php' ? 'active' : ''; ?>"
                href="mass-communications.php">
                <i class="fas fa-bullhorn"></i> Comunicación Masiva
            </a>
            <a class="nav-link <?php echo $current_page == 'dian-management.php' ? 'active' : ''; ?>"
                href="dian-management.php">
                <i class="fas fa-building"></i> Gestión DIAN
            </a>
            <a class="nav-link <?php echo $current_page == 'system-config.php' ? 'active' : ''; ?>"
                href="system-config.php">
                <i class="fas fa-cog"></i> Configuración del Sistema
            </a>
            <hr class="my-2">
            <a class="nav-link" href="/logout.php">
                <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
            </a>
        </nav>
    </div>
</div>

<!-- SIDEBAR DESKTOP CON BOTÓN HAMBURGUESA DENTRO Y ALTURA COMPLETA -->
<div class="sidebar-column" id="adminSidebar">
    <div class="sidebar-desktop">
        <!-- BOTÓN HAMBURGUESA DENTRO DEL SIDEBAR -->
        <div class="sidebar-toggle-container">
            <button class="sidebar-toggle-btn" id="sidebarToggleBtn" onclick="toggleAdminSidebar()">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- LOGO -->
        <div class="logo-container">
            <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">
            <h5 class="logo-text">Administración</h5>
            <small><?php echo htmlspecialchars($admin_name_display); ?></small>
        </div>

        <!-- MENÚ DE NAVEGACIÓN PRINCIPAL -->
        <nav class="nav flex-column">
            <div class="nav-menu-container">
                <a class="nav-link <?php echo $current_page == 'index.php' || $current_page == 'dashboard.php' ? 'active' : ''; ?>"
                    href="index.php" data-tooltip="Dashboard">
                    <i class="fas fa-chart-line"></i>
                    <span class="link-text">Dashboard</span>
                </a>


                <a class="nav-link <?php echo $current_page == 'contact_messages.php' ? 'active' : ''; ?>" href="contact_messages.php"
                    data-tooltip="Formulario del Inico de la Web">
                    <i class="fa fa-envelope"></i>
                    <span class="link-text">Contacto inicio</span>
                </a>
                <a class="nav-link <?php echo $current_page == '/dashboard/admin/contact_messages_admin.php' ? 'active' : ''; ?>" href="/dashboard/admin/contact_messages_admin.php"
                    data-tooltip="Formulario del Inico de la Web">
                    <i class="fa fa-envelope"></i>
                    <span class="link-text">Formulario de Contacto</span>
                </a>







                <a class="nav-link <?php echo $current_page == 'users.php' ? 'active' : ''; ?>" href="users.php"
                    data-tooltip="Usuarios">
                    <i class="fas fa-users"></i>
                    <span class="link-text">Usuarios</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'publications.php' ? 'active' : ''; ?>"
                    href="publications.php" data-tooltip="Publicaciones">
                    <i class="fas fa-car"></i>
                    <span class="link-text">Publicaciones</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'categorias.php' ? 'active' : ''; ?>"
                    href="categorias.php" data-tooltip="Categorías">
                    <i class="fas fa-tags"></i>
                    <span class="link-text">Categorías</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'payments.php' ? 'active' : ''; ?>" href="payments.php"
                    data-tooltip="Pagos">
                    <i class="fas fa-credit-card"></i>
                    <span class="link-text">Pagos</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'memberships.php' ? 'active' : ''; ?>"
                    href="memberships.php" data-tooltip="Membresías">
                    <i class="fas fa-crown"></i>
                    <span class="link-text">Membresías</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'advertisements.php' ? 'active' : ''; ?>"
                    href="advertisements.php" data-tooltip="Anuncios">
                    <i class="fas fa-ad"></i>
                    <span class="link-text">Anuncios</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'listar-publicidad.php' ? 'active' : ''; ?>"
                    href="listar-publicidad.php" data-tooltip="Publicidad">
                    <i class="fas fa-bullhorn"></i>
                    <span class="link-text">Publicidad</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'audit.php' ? 'active' : ''; ?>" href="audit.php"
                    data-tooltip="Auditoría">
                    <i class="fas fa-history"></i>
                    <span class="link-text">Auditoría</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'statistics.php' ? 'active' : ''; ?>"
                    href="statistics.php" data-tooltip="Estadísticas">
                    <i class="fas fa-chart-line"></i>
                    <span class="link-text">Estadísticas</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'mass-communications.php' ? 'active' : ''; ?>"
                    href="mass-communications.php" data-tooltip="Comunicación Masiva">
                    <i class="fas fa-bullhorn"></i>
                    <span class="link-text">Comunicación Masiva</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'dian-management.php' ? 'active' : ''; ?>"
                    href="dian-management.php" data-tooltip="Gestión DIAN">
                    <i class="fas fa-building"></i>
                    <span class="link-text">Gestión DIAN</span>
                </a>
                <a class="nav-link <?php echo $current_page == 'system-config.php' ? 'active' : ''; ?>"
                    href="system-config.php" data-tooltip="Configuración">
                    <i class="fas fa-cog"></i>
                    <span class="link-text">Configuración</span>
                </a>
            </div>
            <br>
            <br>
            <!-- SECCIÓN CERRAR SESIÓN (SIEMPRE AL FINAL) -->
            <div class="nav-footer">
                <hr class="my-2 mx-2">
                <a class="nav-link" href="/logout.php" data-tooltip="Cerrar Sesión">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="link-text">Cerrar Sesión</span>
                </a>
                <br>
            </div>
        </nav>
    </div>
</div>

<!-- SCRIPT PARA TOGGLE DEL SIDEBAR Y TOOLTIPS DINÁMICOS -->
<script>
// Función para colapsar/expandir el sidebar
function toggleAdminSidebar() {
    const sidebarColumn = document.getElementById('adminSidebar');
    const adminMain = document.querySelector('.admin-main');
    const toggleBtn = document.getElementById('sidebarToggleBtn');

    if (sidebarColumn && toggleBtn) {
        const icon = toggleBtn.querySelector('i');
        sidebarColumn.classList.toggle('collapsed');

        // Ajustar el ancho del contenido principal dinámicamente
        if (adminMain) {
            if (sidebarColumn.classList.contains('collapsed')) {
                adminMain.style.marginLeft = '70px';
                if (icon) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-chevron-right');
                }
                localStorage.setItem('adminSidebarCollapsed', 'true');
            } else {
                adminMain.style.marginLeft = '280px';
                if (icon) {
                    icon.classList.remove('fa-chevron-right');
                    icon.classList.add('fa-bars');
                }
                localStorage.setItem('adminSidebarCollapsed', 'false');
            }
        }

        // Reaplicar tooltips después del cambio
        setTimeout(initDynamicTooltips, 100);
    }
}

// Cargar el estado guardado del sidebar
document.addEventListener('DOMContentLoaded', function() {
    const sidebarColumn = document.getElementById('adminSidebar');
    const adminMain = document.querySelector('.admin-main');
    const toggleBtn = document.getElementById('sidebarToggleBtn');

    if (sidebarColumn && adminMain && toggleBtn) {
        const isCollapsed = localStorage.getItem('adminSidebarCollapsed') === 'true';

        if (isCollapsed) {
            sidebarColumn.classList.add('collapsed');
            adminMain.style.marginLeft = '70px';
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-chevron-right');
            }
        } else {
            adminMain.style.marginLeft = '280px';
        }
    }

    // Inicializar tooltips dinámicos después de cargar el DOM
    initDynamicTooltips();
});

// Tooltips dinámicos con JavaScript
function initDynamicTooltips() {
    // Solo aplicar en desktop y cuando el sidebar está contraído
    let currentTooltip = null;

    const checkAndApplyTooltips = function() {
        const sidebar = document.getElementById('adminSidebar');
        const isCollapsed = sidebar && sidebar.classList.contains('collapsed');
        const isDesktop = window.innerWidth > 992;

        if (isDesktop && isCollapsed) {
            enableTooltips();
        } else {
            disableTooltips();
        }
    };

    function enableTooltips() {
        const navLinks = document.querySelectorAll('#adminSidebar.collapsed .sidebar-desktop .nav-link');

        navLinks.forEach(link => {
            // Remover event listeners existentes para evitar duplicados
            link.removeEventListener('mouseenter', showTooltip);
            link.removeEventListener('mouseleave', hideTooltip);
            // Agregar nuevos
            link.addEventListener('mouseenter', showTooltip);
            link.addEventListener('mouseleave', hideTooltip);
        });
    }

    function disableTooltips() {
        const navLinks = document.querySelectorAll('#adminSidebar.collapsed .sidebar-desktop .nav-link');
        navLinks.forEach(link => {
            link.removeEventListener('mouseenter', showTooltip);
            link.removeEventListener('mouseleave', hideTooltip);
        });
        if (currentTooltip) {
            currentTooltip.remove();
            currentTooltip = null;
        }
    }

    function showTooltip(e) {
        const link = e.currentTarget;
        const tooltipText = link.getAttribute('data-tooltip');
        if (!tooltipText) return;

        // Eliminar tooltip existente
        if (currentTooltip) {
            currentTooltip.remove();
        }

        // Crear nuevo tooltip
        const tooltip = document.createElement('div');
        tooltip.className = 'admin-tooltip';
        tooltip.textContent = tooltipText;
        document.body.appendChild(tooltip);

        // Obtener posición del icono
        const icon = link.querySelector('i');
        if (icon) {
            const rect = icon.getBoundingClientRect();

            // Posicionar tooltip a la derecha del icono
            tooltip.style.left = (rect.right + 10) + 'px';
            tooltip.style.top = (rect.top + (rect.height / 2) - (tooltip.offsetHeight / 2)) + 'px';
        }

        currentTooltip = tooltip;
    }

    function hideTooltip() {
        if (currentTooltip) {
            currentTooltip.remove();
            currentTooltip = null;
        }
    }

    // Observar cambios en el sidebar (cuando se colapsa/expande)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                checkAndApplyTooltips();
            }
        });
    });

    const sidebar = document.getElementById('adminSidebar');
    if (sidebar) {
        observer.observe(sidebar, {
            attributes: true
        });
    }

    // Escuchar cambios de tamaño de ventana
    window.addEventListener('resize', function() {
        checkAndApplyTooltips();
    });

    // Aplicar al inicio
    checkAndApplyTooltips();
}
</script>