<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\publications.php
 * PLANTILLA BASE PARA ADMINISTRACIÓN - CON FUNCIONALIDAD DE PUBLICACIONES
 * 
 * CORREGIDO: Sidebar visible en móviles IGUAL que users.php
 * - Eliminado el div contenedor .sidebar-column que ocultaba el sidebar
 * - Sidebar ahora se incluye directamente como en users.php
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// VERIFICACIÓN DE ERRORES - Modo debug
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ============================================
// INCLUDES CON VERIFICACIÓN
// ============================================
$database_path = __DIR__ . '/../../config/database.php';
$functions_path = __DIR__ . '/../../includes/functions.php';
$auth_path = __DIR__ . '/../../includes/auth.php';
$audit_path = __DIR__ . '/../../includes/audit-log.php';

if (!file_exists($database_path)) {
    die("Error: No se encuentra database.php en: " . $database_path);
}
if (!file_exists($functions_path)) {
    die("Error: No se encuentra functions.php en: " . $functions_path);
}
if (!file_exists($auth_path)) {
    die("Error: No se encuentra auth.php en: " . $auth_path);
}

require_once $database_path;
require_once $functions_path;
require_once $auth_path;

if (file_exists($audit_path)) {
    require_once $audit_path;
}

// ============================================
// VERIFICAR CONEXIÓN A BASE DE DATOS
// ============================================
try {
    $db = Database::getInstance();
    if (!$db) {
        die("Error: No se pudo obtener instancia de Database");
    }
    
    $test_query = $db->getOne("SELECT 1 as test");
    if (!$test_query) {
        die("Error: No se pudo ejecutar consulta de prueba a la base de datos");
    }
} catch (Exception $e) {
    die("Error de conexión a BD: " . $e->getMessage());
}

// Verificar que el usuario es administrador
requireAuth();

// Obtener usuario actual
$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
$current_user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

if (!$current_user) {
    die("Error: No se pudo obtener información del usuario actual");
}

// Verificar rol de administrador
if (!in_array($current_user['rol_id'], [1, 2, 3, 4, 5, 7])) {
    header('Location: /easycarluxury/dashboard/user/index.php');
    exit;
}

// ============================================
// PREPARAR VARIABLES PARA EL SIDEBAR
// ============================================
$admin = [
    'full_name' => $current_user['nombre_completo'] ?? 'Administrador',
    'role' => 'admin'
];

$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];

// ============================================
// VERIFICAR QUE EXISTE EL SIDEBAR
// ============================================
$sidebar_path = __DIR__ . '/../includes/admin-sidebar.php';
$sidebar_exists = file_exists($sidebar_path);

if (!$sidebar_exists) {
    error_log("ADVERTENCIA: No se encuentra admin-sidebar.php en: " . $sidebar_path);
}

// ============================================
// === CONTENIDO PHP PERSONALIZADO ===========
// ============================================
$page_title = "Todas las Publicaciones";
$page_description = "Administra todas las publicaciones de la plataforma";

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 3;
$offset = ($page - 1) * $per_page;

$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'all';

if (!function_exists('formatMoney')) {
    function formatMoney($amount) {
        if ($amount === null) return '$ 0';
        return '$ ' . number_format($amount, 0, ',', '.');
    }
}

$search_conditions = "";
$search_params = [];

if (!empty($search)) {
    $search_conditions = " AND (
        u.nombre_completo LIKE ? 
        OR u.email LIKE ? 
        OR p.titulo LIKE ? 
        OR p.descripcion LIKE ? 
        OR p.brand LIKE ?
        OR p.model LIKE ?
        OR p.linea_modelo_comercial LIKE ?
        OR p.color LIKE ?
        OR p.ubicacion LIKE ?
    )";
    
    $search_param = "%$search%";
    $search_params = [
        $search_param, $search_param, $search_param, $search_param,
        $search_param, $search_param, $search_param, $search_param,
        $search_param
    ];
}

$usuarios_sql = "SELECT DISTINCT u.id, u.nombre_completo, u.email, u.tipo_cuenta, u.activo as usuario_activo,
                        (SELECT COUNT(p2.id) FROM publicaciones p2 WHERE p2.usuario_id = u.id) as total_publicaciones
                 FROM usuarios u
                 INNER JOIN publicaciones p ON u.id = p.usuario_id
                 WHERE 1=1";
$usuarios_params = [];

if ($statusFilter === 'active') {
    $usuarios_sql .= " AND p.status = 'active'";
} elseif ($statusFilter === 'inactive') {
    $usuarios_sql .= " AND p.status = 'inactive'";
}

if (!empty($search)) {
    $usuarios_sql .= $search_conditions;
    $usuarios_params = array_merge($usuarios_params, $search_params);
}

$usuarios_sql .= " GROUP BY u.id ORDER BY u.nombre_completo ASC";
$todos_usuarios = $db->getAll($usuarios_sql, $usuarios_params);

$publicaciones_por_usuario = [];

foreach ($todos_usuarios as $usuario) {
    $pub_sql = "SELECT p.*, c.nombre as categoria_nombre,
                       (SELECT image_path FROM imagenes_publicaciones 
                        WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as imagen_principal,
                       (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id AND visible = 1) as comentarios_count
                FROM publicaciones p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                WHERE p.usuario_id = ?";
    
    $pub_params = [$usuario['id']];
    
    if ($statusFilter === 'active') {
        $pub_sql .= " AND p.status = 'active'";
    } elseif ($statusFilter === 'inactive') {
        $pub_sql .= " AND p.status = 'inactive'";
    }
    
    if (!empty($search)) {
        $pub_sql .= " AND (
            p.titulo LIKE ? 
            OR p.descripcion LIKE ? 
            OR p.brand LIKE ?
            OR p.model LIKE ?
            OR p.linea_modelo_comercial LIKE ?
            OR p.color LIKE ?
            OR p.ubicacion LIKE ?
        )";
        $search_param = "%$search%";
        for ($i = 0; $i < 7; $i++) {
            $pub_params[] = $search_param;
        }
    }
    
    $pub_sql .= " ORDER BY p.created_at DESC";
    $publicaciones = $db->getAll($pub_sql, $pub_params);
    
    if (!empty($publicaciones)) {
        $publicaciones_por_usuario[] = [
            'usuario' => $usuario,
            'publicaciones' => $publicaciones
        ];
    }
}

$total_usuarios_con_pubs = count($publicaciones_por_usuario);
$total_pages = $total_usuarios_con_pubs > 0 ? ceil($total_usuarios_con_pubs / $per_page) : 1;
$usuarios_paginados = array_slice($publicaciones_por_usuario, $offset, $per_page);

$total_publicaciones = 0;
foreach ($publicaciones_por_usuario as $grupo) {
    $total_publicaciones += count($grupo['publicaciones']);
}

$activas = 0;
foreach ($publicaciones_por_usuario as $grupo) {
    foreach ($grupo['publicaciones'] as $pub) {
        if ($pub['status'] === 'active') $activas++;
    }
}

// Obtener el tema del administrador (usando admin_theme como en audit.php)
$theme = $_COOKIE['admin_theme'] ?? 'light';

?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?php echo $page_title; ?> - Easy Car Luxury</title>
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* ============================================
           ESTILOS IGUAL QUE audit.php
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #666666;
            --border-color: #e0e0e0;
            --card-bg: #ffffff;
            --table-hover: #f8f9fa;
            --input-bg: #ffffff;
            --input-border: #dddddd;
            --header-bg: #ffffff;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --border-color: #2a2a3e;
            --card-bg: #16213e;
            --table-hover: #1f2a4a;
            --input-bg: #222F58;
            --input-border: #4a4a5e;
            --header-bg: #16213e;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        .admin-main {
            flex: 1;
            width: auto;
            padding: 20px 25px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px 20px;
            background: var(--header-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid var(--border-color);
        }

        .header-title h1 {
            font-size: 1.3rem;
            margin: 0 0 3px;
            color: var(--text-primary);
        }

        .header-title p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .filter-bar {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
        }

        .btn-outline-primary {
            background: transparent;
            border: 1px solid #c8a86b;
            color: #c8a86b;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-primary:hover, .btn-outline-primary.active {
            background: #c8a86b;
            color: white;
        }

        .btn-outline-success {
            background: transparent;
            border: 1px solid #28a745;
            color: #28a745;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .btn-outline-success:hover, .btn-outline-success.active {
            background: #28a745;
            color: white;
        }

        .btn-outline-danger {
            background: transparent;
            border: 1px solid #dc3545;
            color: #dc3545;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            text-decoration: none;
        }

        .btn-outline-danger:hover, .btn-outline-danger.active {
            background: #dc3545;
            color: white;
        }

        .user-group-card {
            background: var(--card-bg);
            border-radius: 15px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .user-group-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 15px 20px;
            color: white;
        }

        .user-group-header h4 {
            margin: 0;
            color: white;
            font-size: 1.2rem;
        }

        .publication-card {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 15px;
            transition: all 0.3s;
        }

        .publication-card:hover {
            background: rgba(102, 126, 234, 0.05);
        }

        .publication-img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 10px;
        }

        .placeholder-img {
            width: 100px;
            height: 100px;
            background: var(--border-color);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
            flex-wrap: nowrap;
            justify-content: flex-end;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
        }

        .badge-success { background: #28a745; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        .badge-dark { background: #343a40; color: white; }
        .badge-light { background: #f8f9fa; color: #212529; }

        .search-active-badge {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .pagination {
            display: flex;
            gap: 4px;
            list-style: none;
            flex-wrap: wrap;
            justify-content: center;
            margin: 20px 0;
        }

        .page-link {
            padding: 6px 12px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s;
            font-size: 0.75rem;
        }

        .page-link:hover, .page-item.active .page-link {
            background: #c8a86b;
            color: white;
            border-color: #c8a86b;
        }

        .btn-sm {
            padding: 4px 10px;
            font-size: 0.7rem;
            border-radius: 4px;
            text-decoration: none;
            display: inline-block;
            cursor: pointer;
        }

        .btn-outline-info {
            border: 1px solid #17a2b8;
            background: transparent;
            color: #17a2b8;
        }

        .btn-outline-warning {
            border: 1px solid #ffc107;
            background: transparent;
            color: #ffc107;
        }

        .btn-outline-info:hover { background: #17a2b8; color: white; }
        .btn-outline-warning:hover { background: #ffc107; color: #212529; }

        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .loader-content {
            text-align: center;
            background: var(--card-bg);
            padding: 30px 40px;
            border-radius: 20px;
        }

        .loader-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid var(--border-color);
            border-top: 4px solid #c8a86b;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .text-center { text-align: center; }
        .text-muted { color: var(--text-secondary); }
        .text-primary { color: #c8a86b; }
        .mt-2 { margin-top: 8px; }
        .mt-3 { margin-top: 16px; }
        .mt-4 { margin-top: 24px; }
        .mb-2 { margin-bottom: 8px; }
        .mb-3 { margin-bottom: 16px; }
        .mb-4 { margin-bottom: 24px; }
        .py-5 { padding-top: 48px; padding-bottom: 48px; }
        .w-100 { width: 100%; }

        .form-control, .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            padding: 8px 12px;
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            border-color: #c8a86b;
            outline: none;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
        }

        /* Botón tema claro/oscuro */
        .btn-theme {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            color: white;
            border: none;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .btn-theme:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(200,168,107,0.4);
        }

        /* Modal en modo oscuro */
        [data-theme="dark"] .modal-content {
            background-color: #16213e;
            border-color: #2a2a3e;
        }

        [data-theme="dark"] .modal-header {
            border-bottom-color: #2a2a3e;
        }

        [data-theme="dark"] .modal-footer {
            border-top-color: #2a2a3e;
        }

        [data-theme="dark"] .modal-title {
            color: #ffffff;
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        /* ============================================
           RESPONSIVE - IGUAL QUE audit.php
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px;
            }
        }

        @media (max-width: 768px) {
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }

        .swal2-container {
            z-index: 99999 !important;
        }
    </style>
</head>
<body>

<!-- LOADER -->
<div id="pageLoader" class="loader-overlay">
    <div class="loader-content">
        <div class="loader-spinner"></div>
        <div class="loader-text">Cargando publicaciones...</div>
    </div>
</div>

<div class="admin-container">
    <!-- SIDEBAR - INCLUIDO DIRECTAMENTE SIN DIV CONTENEDOR (como en users.php) -->
    <?php 
    if ($sidebar_exists) {
        include $sidebar_path;
    } else {
        echo '<div class="alert alert-danger m-3">Error: No se encuentra el archivo admin-sidebar.php<br>Ruta: ' . htmlspecialchars($sidebar_path) . '</div>';
    }
    ?>
    
    <!-- CONTENIDO PRINCIPAL -->
    <main class="admin-main">
        <!-- HEADER -->
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-list-alt"></i> <?php echo $page_title; ?></h1>
                <p><?php echo $page_description; ?></p>
            </div>
            <div>
                <a href="/dashboard/admin/new-publication.php" class="btn-primary">
                    <i class="fas fa-plus"></i> Nueva Publicación
                </a>
            </div>
        </div>
        
        <!-- STATS CARDS -->
        <div class="stats-card">
            <div class="row text-center">
                <div class="col-md-3 col-6">
                    <h4><?php echo number_format($total_usuarios_con_pubs); ?></h4>
                    <p class="text-muted">Usuarios con publicaciones</p>
                </div>
                <div class="col-md-3 col-6">
                    <h4><?php echo number_format($total_publicaciones); ?></h4>
                    <p class="text-muted">Total publicaciones</p>
                </div>
                <div class="col-md-3 col-6">
                    <h4><?php echo number_format($activas); ?></h4>
                    <p class="text-muted">Activas</p>
                </div>
                <div class="col-md-3 col-6">
                    <h4><?php echo number_format($total_publicaciones - $activas); ?></h4>
                    <p class="text-muted">Inactivas</p>
                </div>
            </div>
        </div>
        
        <!-- FILTROS Y BÚSQUEDA -->
        <div class="filter-bar">
            <div class="row align-items-center">
                <div class="col-md-4 mb-2 mb-md-0">
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <a href="?status=all&search=<?php echo urlencode($search); ?>&page=1" 
                           class="<?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            Todas
                        </a>
                        <a href="?status=active&search=<?php echo urlencode($search); ?>&page=1" 
                           class="<?php echo $statusFilter === 'active' ? 'btn-primary' : 'btn-outline-success'; ?>">
                            Activas
                        </a>
                        <a href="?status=inactive&search=<?php echo urlencode($search); ?>&page=1" 
                           class="<?php echo $statusFilter === 'inactive' ? 'btn-primary' : 'btn-outline-danger'; ?>">
                            Inactivas
                        </a>
                    </div>
                </div>
                <div class="col-md-5 mb-2 mb-md-0">
                    <form method="GET" class="d-flex" id="searchForm" style="display: flex; gap: 8px;">
                        <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Buscar por: nombre, apellido, email, marca, modelo, título, descripción..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               style="flex: 1;">
                        <button type="submit" class="btn-primary" style="padding: 8px 16px;">
                            <i class="fas fa-search"></i> Buscar
                        </button>
                    </form>
                </div>
                <div class="col-md-3 text-md-end">
                    <small class="text-muted">Página <?php echo $page; ?> de <?php echo max(1, $total_pages); ?></small>
                    <small class="text-muted d-block">Mostrando <?php echo count($usuarios_paginados); ?> de <?php echo $total_usuarios_con_pubs; ?> usuarios</small>
                </div>
            </div>
        </div>
        
        <!-- BADGE DE BÚSQUEDA ACTIVA -->
        <?php if (!empty($search)): ?>
            <div class="mb-3">
                <span class="search-active-badge">
                    <i class="fas fa-search"></i> Buscando: "<?php echo htmlspecialchars($search); ?>"
                    <a href="?status=<?php echo $statusFilter; ?>&page=1" class="text-white" style="text-decoration: none;">
                        <i class="fas fa-times-circle"></i>
                    </a>
                </span>
            </div>
        <?php endif; ?>
        
        <!-- LISTADO DE PUBLICACIONES -->
        <?php if (empty($usuarios_paginados)): ?>
            <div class="data-card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h5>No hay publicaciones para mostrar</h5>
                    <p class="text-muted">
                        <?php if (!empty($search)): ?>
                            No se encontraron resultados para "<?php echo htmlspecialchars($search); ?>"
                            <br><a href="?status=<?php echo $statusFilter; ?>&page=1" class="btn-primary" style="display: inline-block; margin-top: 15px;">
                                <i class="fas fa-eraser"></i> Limpiar búsqueda
                            </a>
                        <?php else: ?>
                            No hay publicaciones registradas en el sistema
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($usuarios_paginados as $grupo): 
                $usuario = $grupo['usuario'];
                $publicaciones = $grupo['publicaciones'];
            ?>
                <div class="user-group-card">
                    <div class="user-group-header">
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div>
                                <h4>
                                    <i class="fas fa-user-circle"></i> 
                                    <?php echo htmlspecialchars($usuario['nombre_completo']); ?>
                                </h4>
                                <small>
                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($usuario['email']); ?>
                                    <span class="badge" style="background: #17a2b8; color: white; margin-left: 5px;"><?php echo strtoupper($usuario['tipo_cuenta'] ?? 'free'); ?></span>
                                    <?php if (($usuario['usuario_activo'] ?? 1) == 0): ?>
                                        <span class="badge" style="background: #dc3545; color: white; margin-left: 5px;"><i class="fas fa-ban"></i> Cuenta inactiva</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div>
                                <span class="badge" style="background: #f8f9fa; color: #212529;">
                                    <i class="fas fa-car"></i> <?php echo count($publicaciones); ?> publicaciones
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <?php foreach ($publicaciones as $pub): ?>
                        <div class="publication-card">
                            <div class="row align-items-center">
                                <div class="col-md-2 col-12 text-center text-md-start">
                                    <?php if (!empty($pub['imagen_principal'])): ?>
                                        <img src="<?php echo htmlspecialchars($pub['imagen_principal']); ?>" 
                                             class="publication-img" alt="Imagen" style="width: 100px; height: auto; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="placeholder-img mx-auto mx-md-0">
                                            <i class="fas fa-image fa-2x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-7 col-12 mt-2 mt-md-0">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($pub['titulo'] ?? 'Sin título'); ?></h6>
                                    <div class="mb-2">
                                        <span class="badge badge-secondary"><?php echo htmlspecialchars($pub['categoria_nombre'] ?? 'Sin categoría'); ?></span>
                                        <span class="badge badge-info"><?php echo ucfirst($pub['estado_articulo'] ?? 'usado'); ?></span>
                                        <span class="badge <?php echo ($pub['status'] ?? 'inactive') === 'active' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo ($pub['status'] ?? 'inactive') === 'active' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                        <?php if (!empty($pub['brand'])): ?>
                                            <span class="badge badge-dark"><?php echo htmlspecialchars($pub['brand']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="fas fa-eye"></i> <?php echo number_format($pub['visitas'] ?? 0); ?> |
                                        <i class="fas fa-heart"></i> <?php echo number_format($pub['likes'] ?? 0); ?> |
                                        <i class="fas fa-comment"></i> <?php echo $pub['comentarios_count'] ?? 0; ?> |
                                        <i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($pub['created_at'] ?? 'now')); ?>
                                    </div>
                                </div>
                                <div class="col-md-3 col-12 mt-2 mt-md-0">
                                    <div class="text-md-end text-center">
                                        <strong class="text-primary"><?php echo formatMoney($pub['precio'] ?? 0); ?></strong>
                                        <?php if (!empty($pub['negociable'])): ?>
                                            <small class="text-muted d-block">(Negociable)</small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="action-buttons mt-2 justify-content-center justify-content-md-end">
                                        <a href="/public/catalog/detail.php?id=<?php echo $pub['id']; ?>" 
                                           target="_blank" class="btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i> Ver
                                        </a>
                                        <a href="edit-publication.php?id=<?php echo $pub['id']; ?>" 
                                           class="btn-sm btn-outline-success">
                                            <i class="fas fa-edit"></i> Editar
                                        </a>
                                        <button onclick="toggleStatus(<?php echo $pub['id']; ?>, '<?php echo $pub['status'] ?? 'inactive'; ?>')"
                                                class="btn-sm btn-outline-warning">
                                            <i class="fas fa-<?php echo ($pub['status'] ?? 'inactive') === 'active' ? 'pause' : 'play'; ?>"></i>
                                        </button>
                                        <button onclick="deletePublication(<?php echo $pub['id']; ?>)"
                                                class="btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i> Eliminar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
            
            <!-- PAGINACIÓN -->
            <?php if ($total_pages > 1): ?>
                <nav>
                    <ul class="pagination">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-chevron-left"></i> Anterior
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>">
                                    Siguiente <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
        
    </main>
</div>

<!-- Botón para cambiar tema claro/oscuro -->
<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<!-- FOOTER -->
<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<!-- SCRIPTS BASE -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ============================================
    // FUNCIONES DE TEMA
    // ============================================
    
    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', newTheme);
        document.cookie = `admin_theme=${newTheme}; path=/; max-age=31536000`;
        
        const btnIcon = document.querySelector('.btn-theme i');
        if (btnIcon) {
            if (newTheme === 'dark') {
                btnIcon.classList.remove('fa-moon');
                btnIcon.classList.add('fa-sun');
            } else {
                btnIcon.classList.remove('fa-sun');
                btnIcon.classList.add('fa-moon');
            }
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const btnIcon = document.querySelector('.btn-theme i');
        if (btnIcon) {
            if (currentTheme === 'dark') {
                btnIcon.classList.remove('fa-moon');
                btnIcon.classList.add('fa-sun');
            } else {
                btnIcon.classList.remove('fa-sun');
                btnIcon.classList.add('fa-moon');
            }
        }
    });
    
    // ============================================
    // FUNCIONES BASE
    // ============================================
    
    function showLoader() {
        var loader = document.getElementById('pageLoader');
        if (loader) loader.style.display = 'flex';
    }
    
    function hideLoader() {
        var loader = document.getElementById('pageLoader');
        if (loader) loader.style.display = 'none';
    }
    
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            hideLoader();
        }, 500);
    });
    
    document.querySelectorAll('.pagination a, .filter-bar a, .btn-group a, #searchForm').forEach(function(element) {
        if (element.tagName === 'FORM') {
            element.addEventListener('submit', function() {
                showLoader();
            });
        } else if (element.tagName === 'A') {
            element.addEventListener('click', function() {
                if (this.getAttribute('href') && !this.getAttribute('href').startsWith('#')) {
                    showLoader();
                }
            });
        }
    });
    
    // ============================================
    // FUNCIONES PERSONALIZADAS
    // ============================================
    
    function toggleStatus(id, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
        const action = newStatus === 'active' ? 'activar' : 'desactivar';
        Swal.fire({
            title: `¿${action === 'activar' ? 'Activar' : 'Desactivar'} publicación?`,
            text: `La publicación quedará ${action === 'activar' ? 'visible' : 'oculta'} para los usuarios`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: `Sí, ${action}`,
            cancelButtonText: 'Cancelar',
            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a2e' : '#ffffff',
            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#ffffff' : '#212529',
            confirmButtonColor: '#c8a86b'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoader();
                $.ajax({
                    url: '/dashboard/admin/update-status.php',
                    method: 'POST',
                    data: { id: id, status: newStatus },
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            Swal.fire('Éxito', `Publicación ${action}da correctamente`, 'success');
                            location.reload();
                        } else {
                            hideLoader();
                            Swal.fire('Error', r.message || 'Error al cambiar estado', 'error');
                        }
                    },
                    error: function() {
                        hideLoader();
                        Swal.fire('Error', 'No se pudo cambiar el estado', 'error');
                    }
                });
            }
        });
    }
    
    function deletePublication(id) {
        Swal.fire({
            title: '¿Eliminar publicación?',
            text: 'Esta acción no se puede deshacer. Se eliminarán todas las imágenes, documentos y datos asociados.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Sí, eliminar permanentemente',
            cancelButtonText: 'Cancelar',
            background: document.documentElement.getAttribute('data-theme') === 'dark' ? '#1a1a2e' : '#ffffff',
            color: document.documentElement.getAttribute('data-theme') === 'dark' ? '#ffffff' : '#212529'
        }).then((result) => {
            if (result.isConfirmed) {
                showLoader();
                $.ajax({
                    url: '/dashboard/admin/delete-publication.php',
                    method: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            Swal.fire('Eliminado', 'Publicación eliminada correctamente', 'success');
                            location.reload();
                        } else {
                            hideLoader();
                            Swal.fire('Error', r.message || 'Error al eliminar', 'error');
                        }
                    },
                    error: function() {
                        hideLoader();
                        Swal.fire('Error', 'No se pudo eliminar la publicación', 'error');
                    }
                });
            }
        });
    }
    
</script>

</body>
</html>