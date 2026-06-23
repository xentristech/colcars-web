<?php
/**
 * Colcars - Dashboard del Usuario (Vendedor)
 * Ruta: /dashboard/user/index.php
 * MODIFICADO: Verifica si la cuenta está activa. Si no, muestra mensaje y bloquea funcionalidades.
 * CORREGIDO: Problemas con la sesión y carga de usuario.
 * CORREGIDO: Tema (theme) movido antes de la etiqueta <html>
 * CORREGIDO: SweetAlert2 con soporte para modo claro/oscuro
 * CORREGIDO: Todos los iconos del contenido con colores específicos y !important
 */

error_log("=== INICIO DASHBOARD USER ===");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_log("Session iniciada");

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
error_log("Includes cargados");

// ========== VERIFICACIÓN DE AUTENTICACIÓN MEJORADA ==========
requireAuth();
error_log("requireAuth() completado");

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
error_log("User ID: " . ($user_id ?? 'null'));

if (!$user_id) {
    error_log("No user ID - redirigiendo a login");
    header('Location: /easycarluxury/public/login.php');
    exit;
}

$db = Database::getInstance();
$pdo = $db->getConnection();
error_log("Database instanciado");

// ========== CARGA DEL USUARIO CON VERIFICACIÓN ==========
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);
error_log("Usuario cargado: " . ($user ? 'SI' : 'NO'));

if (!$user) {
    // El usuario no existe en la BD, destruir sesión
    error_log("Usuario no encontrado - destruyendo sesión");
    session_destroy();
    header('Location: /easycarluxury/public/login.php?error=cuenta_no_encontrada');
    exit;
}
error_log("Usuario: " . ($user['nombre_completo'] ?? 'sin nombre'));

// ========== ACTUALIZAR DATOS DE SESIÓN ==========
// Asegurar que la sesión tenga los datos más recientes
$_SESSION['usuario_id'] = $user['id'];
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['nombre_completo'];
$_SESSION['tipo_cuenta'] = $user['tipo_cuenta'];
$_SESSION['rol_id'] = $user['rol_id'];
error_log("Sesión actualizada");

// Obtener nombre del rol
$rol = $db->getOne("SELECT nombre FROM roles WHERE id = ?", [$user['rol_id']]);
$_SESSION['rol_nombre'] = $rol['nombre'] ?? 'usuario';
error_log("Rol cargado: " . $_SESSION['rol_nombre']);

// ============================================
// VERIFICAR SI LA CUENTA ESTÁ DESACTIVADA
// ============================================
$cuenta_activa = ($user['activo'] == 1);
error_log("Cuenta activa: " . ($cuenta_activa ? 'SI' : 'NO'));

$mensaje_cuenta_inactiva = '';
if (!$cuenta_activa) {
    $mensaje_cuenta_inactiva = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="fas fa-ban" style="color: #dc3545;"></i> <strong>¡Cuenta desactivada!</strong> Tu cuenta ha sido desactivada por el administrador. No puedes crear nuevas publicaciones ni editar las existentes. Tus publicaciones no son visibles al público. Para más información, contacta al soporte.
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>';
}

// ============================================
// ESTADÍSTICAS - SOLO SI LA CUENTA ESTÁ ACTIVA
// ============================================
error_log("Iniciando sección de estadísticas - cuenta activa: " . ($cuenta_activa ? 'SI' : 'NO'));

if ($cuenta_activa) {
    error_log("=== EJECUTANDO CONSULTAS SQL ===");
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM publicaciones WHERE usuario_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $total_publicaciones = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    error_log("total_publicaciones: " . $total_publicaciones);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(visitas), 0) as total FROM publicaciones WHERE usuario_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $total_visitas = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    error_log("total_visitas: " . $total_visitas);

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(likes), 0) as total FROM publicaciones WHERE usuario_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $total_likes = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    error_log("total_likes: " . $total_likes);

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM votes v INNER JOIN publicaciones p ON v.publication_id = p.id WHERE p.usuario_id = ? AND v.vote_type = 'up'");
    $stmt->execute([$user_id]);
    $total_ups = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    error_log("total_ups: " . $total_ups);

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM votes v INNER JOIN publicaciones p ON v.publication_id = p.id WHERE p.usuario_id = ? AND v.vote_type = 'down'");
    $stmt->execute([$user_id]);
    $total_downs = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    error_log("total_downs: " . $total_downs);

    $stmt = $pdo->prepare("SELECT COUNT(c.id) as total FROM comentarios c INNER JOIN publicaciones p ON c.publicacion_id = p.id WHERE p.usuario_id = ? AND p.status = 'active'");
    $stmt->execute([$user_id]);
    $total_comentarios = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    error_log("total_comentarios: " . $total_comentarios);

    // Visitas por día
    $visits_by_day = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM publication_views pv INNER JOIN publicaciones p ON pv.publication_id = p.id WHERE p.usuario_id = ? AND DATE(pv.viewed_at) = ?");
        $stmt->execute([$user_id, $date]);
        $visits_by_day[] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    }
    error_log("visits_by_day generado");

    // Últimas publicaciones
    $recent_posts = $db->getAll("
        SELECT p.*, 
                (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id) as comentarios_count,
                (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as imagen_principal
        FROM publicaciones p
        WHERE p.usuario_id = ? AND p.status = 'active'
        ORDER BY p.created_at DESC
        LIMIT 5
    ", [$user_id]);
    error_log("recent_posts obtenidos: " . count($recent_posts));

    $can_post = canUserPost($user_id);
    $current_posts = $total_publicaciones;
    $max_posts = $user['limite_publicaciones_int'] ?? 2;
    $posts_left = $max_posts - $current_posts;
    error_log("can_post: " . ($can_post ? 'SI' : 'NO') . ", posts_left: " . $posts_left);
    
} else {
    error_log("Cuenta inactiva - asignando valores por defecto");
    // Cuenta inactiva: estadísticas en cero y listas vacías
    $total_publicaciones = 0;
    $total_visitas = 0;
    $total_likes = 0;
    $total_ups = 0;
    $total_downs = 0;
    $total_comentarios = 0;
    $visits_by_day = array_fill(0, 30, 0);
    $recent_posts = [];
    $can_post = false;
    $posts_left = 0;
}

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);
error_log("unread_messages: " . ($unread_messages['total'] ?? 0));

// ========== CORRECCIÓN DEL TEMA - MOVIDO ANTES DEL HTML ==========
$theme = 'light'; // Valor por defecto
if (isset($_COOKIE['user_theme']) && in_array($_COOKIE['user_theme'], ['light', 'dark'])) {
    $theme = $_COOKIE['user_theme'];
} elseif (isset($user['tema_oscuro'])) {
    $theme = $user['tema_oscuro'] ? 'dark' : 'light';
}
// Guardar el tema en la sesión para que otras páginas lo usen
$_SESSION['user_theme'] = $theme;
error_log("Theme: " . $theme);

// Etiquetas para el gráfico
$display_labels = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('d/m', strtotime("-$i days"));
    $display_labels[] = ($i % 5 == 0 || $i == 0 || $i == 29) ? $date : '';
}

error_log("=== FIN DE LA SECCIÓN PHP - INICIANDO HTML ===");
?>


<?php
/*
// ========== DIAGNÓSTICO ==========
echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc; font-family: monospace;">';
echo "<strong>DIAGNÓSTICO:</strong><br>";
echo "total_publicaciones: " . ($total_publicaciones ?? 'no definida') . "<br>";
echo "total_visitas: " . ($total_visitas ?? 'no definida') . "<br>";
echo "total_likes: " . ($total_likes ?? 'no definida') . "<br>";
echo "total_comentarios: " . ($total_comentarios ?? 'no definida') . "<br>";
echo "cuenta_activa: " . ($cuenta_activa ? 'SI' : 'NO') . "<br>";
echo "can_post: " . ($can_post ? 'SI' : 'NO') . "<br>";
echo "posts_left: " . ($posts_left ?? 'no definida') . "<br>";
echo "recent_posts: " . (isset($recent_posts) ? count($recent_posts) : '0') . " publicaciones<br>";
echo "</div>";
*/
?>

<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Colcars Usuario</title>
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
            --text-secondary: #e0e0e0;
            --border-color: #2a2a3e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO OSCURO
           ============================================ */
        [data-theme="dark"] body,
        [data-theme="dark"] .main-content,
        [data-theme="dark"] .stat-card,
        [data-theme="dark"] .post-card,
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

        /* Los iconos dentro de .stat-icon conservan su color inline */
        [data-theme="dark"] .stat-icon i {
            /* No forzar color, mantener el style inline */
        }

        /* Los demás iconos siguen el tema */
        [data-theme="dark"] i:not(.stat-icon i),
        [data-theme="dark"] .fas:not(.stat-icon i),
        [data-theme="dark"] .far:not(.stat-icon i),
        [data-theme="dark"] .fab:not(.stat-icon i),
        [data-theme="dark"] .btn i:not(.stat-icon i) {
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

        [data-theme="dark"] .alert-warning {
            background-color: #6a4a1a;
            color: #ffdd99 !important;
            border-color: #8a6a3a;
        }

        [data-theme="dark"] .alert-info {
            background-color: #1a3a5a;
            color: #ccffff !important;
            border-color: #2a5a7a;
        }

        [data-theme="dark"] .alert-danger i,
        [data-theme="dark"] .alert-warning i,
        [data-theme="dark"] .alert-info i {
            color: inherit !important;
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

        [data-theme="dark"] .btn-info {
            background-color: #0dcaf0;
            border-color: #0dcaf0;
            color: #1a1a2e !important;
        }

        [data-theme="dark"] .btn-outline-primary {
            color: #ffffff !important;
            border-color: #667eea;
        }

        [data-theme="dark"] .btn-outline-danger {
            color: #ffffff !important;
            border-color: #dc3545;
        }

        [data-theme="dark"] .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff !important;
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

        [data-theme="dark"] .form-control {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control:focus {
            background-color: #2a3a6a !important;
            color: #ffffff !important;
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .stat-card,
        [data-theme="light"] .post-card,
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

        /* Los iconos dentro de .stat-icon conservan su color inline */
        [data-theme="light"] .stat-icon i {
            /* No forzar color, mantener el style inline */
        }

        /* Los demás iconos siguen el tema */
        [data-theme="light"] i:not(.stat-icon i),
        [data-theme="light"] .fas:not(.stat-icon i),
        [data-theme="light"] .far:not(.stat-icon i),
        [data-theme="light"] .fab:not(.stat-icon i),
        [data-theme="light"] .btn i:not(.stat-icon i) {
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

        [data-theme="light"] .btn-info i {
            color: #ffffff !important;
        }

        /* ============================================
           ESTILOS ORIGINALES
           ============================================ */
        .stat-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
        }

        .post-card {
            background: var(--bg-secondary);
            border-radius: 10px;
            margin-bottom: 15px;
            padding: 15px;
            border: 1px solid var(--border-color);
        }

        .post-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 10px;
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
                margin-top: 0px;
            }
            
            .stat-value {
                font-size: 1.4rem;
            }
            
            .stat-icon {
                font-size: 2rem;
            }
            
            .post-img {
                width: 60px;
                height: 60px;
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

    <!-- NAVBAR MÓVIL -->
    <div class="mobile-navbar">
        <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
            <i class="fas fa-bars" style="color: #ffffff;"></i>
        </button>
        <div class="navbar-brand">
            <img src="/easycarluxury/assets/imagenes/logos/colcars.png" alt="Colcars">
            <span>Colcars</span>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle" style="color: #ffffff;"></i>
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
                <i class="fas fa-user-circle fa-2x" style="color: #ffffff;"></i>
                <p class="mt-2 mb-0"><?php echo htmlspecialchars($user['nombre_completo']); ?></p>
            </div>
            <hr>
            <nav class="nav flex-column">
                <a class="nav-link active" href="index.php">
                    <i class="fas fa-tachometer-alt" style="color: #c8a86b;"></i> Dashboard
                </a>
                <a class="nav-link" href="my-publications.php">
                    <i class="fas fa-list" style="color: #17a2b8;"></i> Mis Publicaciones
                </a>
                <a class="nav-link" href="new-publication.php">
                    <i class="fas fa-plus-circle" style="color: #28a745;"></i> Nueva Publicación
                </a>
                <a class="nav-link" href="messages.php">
                    <i class="fas fa-envelope" style="color: #ffc107;"></i> Mensajes
                    <?php if (($unread_messages['total'] ?? 0) > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unread_messages['total']; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="my-offers.php">
                    <i class="fas fa-gavel" style="color: #6f42c1;"></i> Mis Ofertas
                </a>
                <a class="nav-link" href="statistics.php">
                    <i class="fas fa-chart-line" style="color: #28a745;"></i> Estadísticas
                </a>
                <a class="nav-link" href="membership.php">
                    <i class="fas fa-gem" style="color: #c8a86b;"></i> Membresía
                </a>
                <a class="nav-link" href="payments.php">
                    <i class="fas fa-credit-card" style="color: #007bff;"></i> Pagos
                </a>
                <a class="nav-link" href="invoices.php">
                    <i class="fas fa-file-invoice" style="color: #17a2b8;"></i> Facturas
                </a>
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user" style="color: #007bff;"></i> Mi Perfil
                </a>
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog" style="color: #6c757d;"></i> Configuración
                </a>
                <hr class="my-2">
                <a class="nav-link" href="/easycarluxury/logout">
                    <i class="fas fa-sign-out-alt" style="color: #dc3545;"></i> Cerrar Sesión
                </a>
            </nav>
        </div>
    </div>

    <div class="membership-badge">
        <i class="fas fa-crown" style="color: #f9ca24;"></i> Cuenta: <?php echo strtoupper($user['tipo_cuenta']); ?>
        <?php if ($user['tipo_cuenta'] != 'free' && !empty($user['fecha_expiracion'])): ?>
            <small>(Expira: <?php echo date('d/m/Y', strtotime($user['fecha_expiracion'])); ?>)</small>
        <?php endif; ?>
    </div>

    <button class="btn-theme" onclick="toggleTheme()"><i class="fas fa-moon" style="color: #ffffff;"></i></button>

    <!-- ESTRUCTURA PRINCIPAL -->
    <div class="dashboard-wrapper">
        <!-- COLUMNA DEL SIDEBAR -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>
        </div>

        <!-- COLUMNA DEL CONTENIDO -->
        <div class="content-column">
            <div class="main-content">

                <a href="/public/index.php" target="_blank" class="btn btn-primary" id="refreshData" style="background-color: #1c04fa;">
                    <i class="fas fa-sync-alt" style="color: #ffffff;"></i> Página web
                </a>
                <hr>
                <hr>

                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2><i class="fas fa-tachometer-alt" style="color: #c8a86b;"></i> Dashboard <small class="text-muted">Bienvenido,
                            <?php echo htmlspecialchars($user['nombre_completo']); ?></small></h2>
                </div>

                <!-- MOSTRAR MENSAJE SI CUENTA ESTÁ DESACTIVADA -->
                <?php echo $mensaje_cuenta_inactiva; ?>

                <?php if ($cuenta_activa): ?>
                    <?php if (!$can_post && $user['tipo_cuenta'] == 'free'): ?>
                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle" style="color: #856404;"></i> Has alcanzado el límite de
                            2 publicaciones gratis. <a href="membership.php" class="alert-link">Actualiza tu membresía</a></div>
                    <?php elseif ($posts_left <= 2 && $user['tipo_cuenta'] == 'free'): ?>
                        <div class="alert alert-info"><i class="fas fa-info-circle" style="color: #0c5460;"></i> Te quedan <?php echo $posts_left; ?>
                            publicaciones disponibles. <a href="membership.php" class="alert-link">Actualiza a PRO</a></div>
                    <?php endif; ?>

                    <!-- Stats Cards (visible solo si cuenta activa) - ICONOS CON COLORES -->
                    <div class="row">
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_publicaciones); ?></div>
                                        <div class="text-muted">Publicaciones</div>
                                    </div>
                                    <div class="stat-icon" style="color: #007bff;"><i class="fas fa-car"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_visitas); ?></div>
                                        <div class="text-muted">Visitas Totales</div>
                                    </div>
                                    <div class="stat-icon" style="color: #17a2b8;"><i class="fas fa-eye"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_comentarios); ?></div>
                                        <div class="text-muted">Comentarios</div>
                                    </div>
                                    <div class="stat-icon" style="color: #92fd07 !important;"><i class="fas fa-comments"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_likes); ?></div>
                                        <div class="text-muted">Favorito</div>
                                    </div>
                                    <div class="stat-icon" style="color: #fa051e;"><i class="fas fa-heart"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_ups); ?></div>
                                        <div class="text-muted">Me Gusta</div>
                                    </div>
                                    <div class="stat-icon" style="color: #28a745;"><i class="fas fa-thumbs-up"></i></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <div class="stat-value"><?php echo number_format($total_downs); ?></div>
                                        <div class="text-muted">No Gusta</div>
                                    </div>
                                    <div class="stat-icon" style="color: #f5041c;"><i class="fas fa-thumbs-down"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráficos -->
                    <div class="row">
                        <div class="col-md-8">
                            <div class="stat-card">
                                <h5><i class="fas fa-chart-line" style="color: #28a745;"></i> Visitas Últimos 30 Días</h5>
                                <canvas id="visitsChart" height="140"></canvas>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h5><i class="fas fa-chart-pie" style="color: #dc3545;"></i> Distribución</h5>
                                <canvas id="distributionChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Últimas Publicaciones -->
                    <div class="stat-card">
                        <div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
                            <h5><i class="fas fa-clock" style="color: #17a2b8;"></i> Últimas Publicaciones</h5>
                            <a href="my-publications.php" class="btn btn-sm btn-primary" style="background-color: #007bff; border-color: #007bff;"><i class="fas fa-arrow-right" style="color: #ffffff;"></i> Ver todas</a>
                        </div>
                        <?php if (empty($recent_posts)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-car fa-4x mb-3" style="color: #6c757d;"></i>
                                <p>Aún no tienes publicaciones.</p>
                                <a href="new-publication.php" class="btn btn-primary"><i class="fas fa-plus" style="color: #ffffff;"></i> Crear primera publicación</a>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recent_posts as $post): ?>
                                <div class="post-card">
                                    <div class="row align-items-center">
                                        <div class="col-auto">
                                            <?php if ($post['imagen_principal']): ?>
                                                <img src="<?php echo $post['imagen_principal']; ?>" class="post-img">
                                            <?php else: ?>
                                                <div class="post-img bg-secondary d-flex align-items-center justify-content-center">
                                                    <i class="fas fa-image fa-2x text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($post['titulo']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-eye" style="color: #17a2b8 !important;"></i> <?php echo number_format($post['visitas']); ?> visitas |
                                                <i class="fas fa-heart" style="color: #dc3545 !important;"></i> <?php echo number_format($post['likes']); ?> likes |
                                                <i class="fas fa-comment" style="color: #ffc107 !important;"></i> <?php echo $post['comentarios_count']; ?> comentarios
                                            </small>
                                        </div>
                                        <div class="col-auto">
                                            <a href="edit-publication.php?id=<?php echo $post['id']; ?>"
                                                class="btn btn-sm btn-outline-primary"><i class="fas fa-edit" style="color: #007bff !important;"></i></a>
                                            <button onclick="deletePublication(<?php echo $post['id']; ?>)"
                                                class="btn btn-sm btn-outline-danger"><i class="fas fa-trash" style="color: #dc3545 !important;"></i></button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Acciones Rápidas -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stat-card text-center">
                                <i class="fas fa-chart-simple fa-3x mb-3" style="color: #ff0000 !important;"></i>
                                <h5>¿Necesitas más visibilidad?</h5>
                                <p>Actualiza a PREMIUM o ELITE</p>
                                <a href="membership.php" class="btn btn-primary" style="background-color: #007bff; border-color: #007bff;">Ver Planes</a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="stat-card text-center">
                                <i class="fas fa-question-circle fa-3x mb-3" style="color: #17a2b8 !important;"></i>
                                <h5>¿Necesitas ayuda?</h5>
                                <p>Consulta Soporte Técnico</p>
                                <button class="btn btn-info" onclick="showSupportModal()" style="background-color: #17a2b8; border-color: #17a2b8; color: #ffffff;">Solicitar Ayuda</button>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Si la cuenta está desactivada, mostrar solo el mensaje y opciones limitadas -->
                    <div class="row">
                        <div class="col-md-12">
                            <div class="stat-card text-center">
                                <i class="fas fa-ban fa-3x mb-3 text-danger" style="color: #dc3545;"></i>
                                <h4>Tu cuenta está desactivada</h4>
                                <p>No puedes crear nuevas publicaciones ni editar las existentes.</p>
                                <p>Para reactivar tu cuenta, por favor contacta al soporte o realiza un nuevo pago.</p>
                                <a href="membership.php" class="btn btn-primary">Ver Planes de Membresía</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="dashboard-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <p>&copy; <?php echo date('Y'); ?> Colcars - Todos los derechos reservados. By Software and Games
                        Cel: 3151056434</p>
                    <p class="mb-0"><a href="/easycarluxury/terms">Términos y condiciones</a> | <a
                            href="/easycarluxury/privacy">Política de privacidad</a> | <a
                            href="/easycarluxury/contact">Contacto</a></p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f" style="color: #3b5998;"></i></a>
                        <a href="#"><i class="fab fa-instagram" style="color: #e4405f;"></i></a>
                        <a href="#"><i class="fab fa-whatsapp" style="color: #25D366;"></i></a>
                        <a href="#"><i class="fab fa-youtube" style="color: #ff0000;"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- Modal de Soporte -->
    <div class="modal fade" id="supportModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Soporte Técnico</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="supportForm">
                        <div class="mb-3"><label class="form-label">Asunto</label><input type="text"
                                class="form-control" name="subject" required></div>
                        <div class="mb-3"><label class="form-label">Mensaje</label><textarea class="form-control"
                                name="message" rows="5" required></textarea></div>
                        <button type="submit" class="btn btn-primary">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

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
                confirmButtonColor: '#c8a86b',
                cancelButtonColor: isDark ? '#dc3545' : '#6c757d',
                backdrop: isDark ? 'rgba(0, 0, 0, 0.8)' : 'rgba(0, 0, 0, 0.4)',
            };
            
            return Swal.fire(swalOptions);
        }

        <?php if ($cuenta_activa): ?>
        const displayLabels = <?php echo json_encode($display_labels); ?>;
        const visitsData = <?php echo json_encode($visits_by_day); ?>;
        const totalVisitas = <?php echo $total_visitas; ?>;
        const totalLikes = <?php echo $total_likes; ?>;
        const totalComentarios = <?php echo $total_comentarios; ?>;
        
        // Obtener colores del tema para los gráficos
        const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
        const textColor = isDarkMode ? '#ffffff' : '#212529';
        const gridColor = isDarkMode ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';
        
        // GRÁFICO DE LÍNEAS - Visitas Últimos 30 Días
        new Chart(document.getElementById('visitsChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: displayLabels,
                datasets: [{
                    label: 'Visitas',
                    data: visitsData,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102,126,234,0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor
                        }
                    },
                    tooltip: {
                        titleColor: textColor,
                        bodyColor: textColor,
                        backgroundColor: isDarkMode ? '#1a1a2e' : '#ffffff',
                        borderColor: '#667eea',
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        ticks: { color: textColor },
                        grid: { color: gridColor },
                        title: {
                            display: true,
                            text: 'Número de visitas',
                            color: textColor
                        }
                    },
                    x: {
                        ticks: { color: textColor },
                        grid: { color: gridColor },
                        title: {
                            display: true,
                            text: 'Días',
                            color: textColor
                        }
                    }
                }
            }
        });
        
        // GRÁFICO DE DONA - Distribución
        new Chart(document.getElementById('distributionChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Visitas', 'Likes', 'Comentarios'],
                datasets: [{
                    data: [totalVisitas, totalLikes, totalComentarios],
                    backgroundColor: ['#667eea', '#f093fb', '#4facfe']
                }]
            },
            options: { 
                responsive: true, 
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        labels: {
                            color: textColor
                        }
                    },
                    tooltip: {
                        titleColor: textColor,
                        bodyColor: textColor,
                        backgroundColor: isDarkMode ? '#1a1a2e' : '#ffffff',
                        borderColor: '#667eea',
                        borderWidth: 1
                    }
                }
            }
        });
        <?php endif; ?>
        
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({ url: '/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
            // Recargar la página para actualizar los colores de los gráficos
            setTimeout(() => location.reload(), 100);
        }
        
        function deletePublication(id) {
            showSwalWithTheme({
                title: '¿Eliminar publicación?',
                text: 'Esta acción es irreversible. La publicación será eliminada permanentemente.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/api/v1/publications.php',
                        method: 'DELETE',
                        data: { id: id },
                        success: function () {
                            showSwalWithTheme({
                                title: '¡Eliminado!',
                                text: 'La publicación ha sido eliminada correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => location.reload());
                        },
                        error: function () {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'No se pudo eliminar la publicación. Intenta nuevamente.',
                                icon: 'error',
                                confirmButtonText: 'Cerrar'
                            });
                        }
                    });
                }
            });
        }
        
        function showSupportModal() {
            new bootstrap.Modal(document.getElementById('supportModal')).show();
        }
        
        $('#supportForm').on('submit', function (e) {
            e.preventDefault();
            showSwalWithTheme({
                title: '¿Enviar solicitud?',
                text: 'Tu mensaje será enviado al equipo de soporte',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/api/v1/support.php',
                        method: 'POST',
                        data: $(this).serialize(),
                        success: function () {
                            showSwalWithTheme({
                                title: '¡Enviado!',
                                text: 'Tu solicitud ha sido enviada correctamente',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            });
                            bootstrap.Modal.getInstance(document.getElementById('supportModal')).hide();
                            $('#supportForm')[0].reset();
                        },
                        error: function () {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'No se pudo enviar la solicitud. Intenta nuevamente.',
                                icon: 'error',
                                confirmButtonText: 'Cerrar'
                            });
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>