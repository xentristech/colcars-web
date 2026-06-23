<?php
/**
 * Colcars - Listado de Publicidad (Banners y Videos)
 * Muestra todas las publicidades creadas con opciones de editar/eliminar
 * MODIFICADO: Tema claro/oscuro completo, sidebar responsivo
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /login');
    exit;
}

$db = Database::getInstance();
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

if (!$user || !is_array($user)) {
    session_destroy();
    header('Location: /login');
    exit;
}

// Verificar que sea administrador
if ($user['rol_id'] != 1 && $user['tipo_cuenta'] !== 'admin' && $user['rol'] !== 'admin') {
    header('Location: /dashboard/user/index.php');
    exit;
}

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

// Paginación
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtros
$tipoFilter = $_GET['tipo'] ?? 'todos';
$posicionFilter = $_GET['posicion'] ?? 'todas';
$activoFilter = $_GET['activo'] ?? 'todos';

$where = "1=1";
$params = [];

if ($tipoFilter !== 'todos') {
    $where .= " AND tipo = ?";
    $params[] = $tipoFilter;
}

if ($posicionFilter !== 'todas') {
    $where .= " AND posicion = ?";
    $params[] = $posicionFilter;
}

if ($activoFilter !== 'todos') {
    $where .= " AND activo = ?";
    $params[] = $activoFilter;
}

// Total de registros
$totalStmt = $db->query("SELECT COUNT(*) as total FROM publicidad WHERE $where", $params);
$totalRow = $totalStmt ? $totalStmt->fetch(PDO::FETCH_ASSOC) : ['total' => 0];
$total_count = $totalRow['total'];
$total_pages = ceil($total_count / $per_page);

// Obtener publicidades
$params[] = $per_page;
$params[] = $offset;

$sql = "SELECT * FROM publicidad 
        WHERE $where 
        ORDER BY posicion, orden, created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $db->query($sql, $params);
$publicidades = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

$page_title = 'Listado de Publicidad';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Colcars Admin</title>
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* ============================================
           VARIABLES DE TEMA CLARO
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

        /* ============================================
           VARIABLES DE TEMA OSCURO
           ============================================ */
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
            max-width: 100%;
            overflow-x: hidden;
        }

        .sidebar-column {
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .admin-main {
            flex: 1;
            width: auto;
            padding: 15px 15px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow-x: hidden;
        }

        .admin-main > * {
            max-width: 100%;
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

        /* Filter Bar */
        .filter-bar {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        /* Table styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-primary);
        }

        .admin-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.75rem;
            position: sticky;
            top: 0;
        }

        .admin-table tr:hover {
            background: var(--table-hover);
        }

        /* Badges */
        .badge-activo {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-inactivo {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-banner {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-video {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-principal {
            background: #c8a86b;
            color: #1a1a2e;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-video-espacio {
            background: #6c757d;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        /* Preview images */
        .preview-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }

        .preview-video {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            background: #000;
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.8rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.8rem;
        }

        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
        }

        .btn-outline-primary {
            border: 1px solid #c8a86b;
            background: transparent;
            color: #c8a86b;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-primary:hover,
        .btn-outline-primary.active {
            background: #c8a86b;
            color: white;
        }

        .btn-outline-secondary {
            border: 1px solid var(--border-color);
            background: transparent;
            color: var(--text-secondary);
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-secondary:hover,
        .btn-outline-secondary.active {
            background: #c8a86b;
            color: white;
            border-color: #c8a86b;
        }

        .btn-outline-success {
            border: 1px solid #28a745;
            background: transparent;
            color: #28a745;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-success:hover,
        .btn-outline-success.active {
            background: #28a745;
            color: white;
        }

        .btn-outline-danger {
            border: 1px solid #dc3545;
            background: transparent;
            color: #dc3545;
            padding: 5px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-danger:hover,
        .btn-outline-danger.active {
            background: #dc3545;
            color: white;
        }

        .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 2px;
        }

        .btn-icon.edit {
            background: #3498db;
            color: white;
        }

        .btn-icon.edit:hover {
            background: #2980b9;
        }

        .btn-icon.delete {
            background: #e74c3c;
            color: white;
        }

        .btn-icon.delete:hover {
            background: #c0392b;
        }

        /* Pagination */
        .pagination-container {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 1px solid var(--border-color);
            background: var(--card-bg);
            margin-top: 20px;
            border-radius: 12px;
        }

        .pagination {
            display: flex;
            gap: 4px;
            list-style: none;
            flex-wrap: wrap;
            margin: 0;
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
            cursor: pointer;
        }

        .page-link:hover,
        .page-item.active .page-link {
            background: #c8a86b;
            color: white;
            border-color: #c8a86b;
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

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-label {
            color: var(--text-primary);
        }

        [data-theme="dark"] .text-muted {
            color: var(--text-secondary) !important;
        }

        /* SweetAlert2 en modo oscuro */
        [data-theme="dark"] .swal2-popup {
            background: #16213e;
            color: #ffffff;
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        /* ============================================
           RESPONSIVE: Ajustes para móvil
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px !important;
            }
        }

        @media (max-width: 768px) {
            .admin-table {
                min-width: 800px;
            }
            .pagination-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            .filter-bar .btn-group {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-bottom: 10px;
            }
            .filter-bar .btn-group .btn {
                flex: 1;
                text-align: center;
            }
            .preview-img, .preview-video {
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 480px) {
            .admin-main {
                padding: 60px 8px 8px 8px !important;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
    <main class="admin-main">
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-ad"></i> Gestión de Publicidad</h1>
                <p>Administra banners y anuncios en la plataforma</p>
            </div>
            <div class="header-actions">
                <a href="/dashboard/admin/new-publication.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Publicidad
                </a>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="row align-items-center g-2">
                <div class="col-md-4">
                    <div class="btn-group w-100">
                        <a href="?tipo=todos&posicion=<?php echo $posicionFilter; ?>&activo=<?php echo $activoFilter; ?>&page=<?php echo $page; ?>"
                            class="btn btn-outline-primary <?php echo $tipoFilter === 'todos' ? 'active' : ''; ?>">Todos</a>
                        <a href="?tipo=banner&posicion=<?php echo $posicionFilter; ?>&activo=<?php echo $activoFilter; ?>&page=<?php echo $page; ?>"
                            class="btn btn-outline-primary <?php echo $tipoFilter === 'banner' ? 'active' : ''; ?>">Banners</a>
                        <a href="?tipo=video&posicion=<?php echo $posicionFilter; ?>&activo=<?php echo $activoFilter; ?>&page=<?php echo $page; ?>"
                            class="btn btn-outline-primary <?php echo $tipoFilter === 'video' ? 'active' : ''; ?>">Videos</a>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="btn-group w-100">
                        <a href="?tipo=<?php echo $tipoFilter; ?>&posicion=todas&activo=<?php echo $activoFilter; ?>&page=<?php echo $page; ?>"
                            class="btn btn-outline-secondary <?php echo $posicionFilter === 'todas' ? 'active' : ''; ?>">Todas</a>
                        <a href="?tipo=<?php echo $tipoFilter; ?>&posicion=banner_principal&activo=<?php echo $activoFilter; ?>&page=<?php echo $page; ?>"
                            class="btn btn-outline-secondary <?php echo $posicionFilter === 'banner_principal' ? 'active' : ''; ?>">Banner Principal</a>
                        <a href="?tipo=<?php echo $tipoFilter; ?>&posicion=video_espacio&activo=<?php echo $activoFilter; ?>&page=<?php echo $page; ?>"
                            class="btn btn-outline-secondary <?php echo $posicionFilter === 'video_espacio' ? 'active' : ''; ?>">Espacio Video</a>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="btn-group w-100">
                        <a href="?tipo=<?php echo $tipoFilter; ?>&posicion=<?php echo $posicionFilter; ?>&activo=todos&page=<?php echo $page; ?>"
                            class="btn btn-outline-success <?php echo $activoFilter === 'todos' ? 'active' : ''; ?>">Todos</a>
                        <a href="?tipo=<?php echo $tipoFilter; ?>&posicion=<?php echo $posicionFilter; ?>&activo=1&page=<?php echo $page; ?>"
                            class="btn btn-outline-success <?php echo $activoFilter === '1' ? 'active' : ''; ?>">Activos</a>
                        <a href="?tipo=<?php echo $tipoFilter; ?>&posicion=<?php echo $posicionFilter; ?>&activo=0&page=<?php echo $page; ?>"
                            class="btn btn-outline-danger <?php echo $activoFilter === '0' ? 'active' : ''; ?>">Inactivos</a>
                    </div>
                </div>
                <div class="col-md-1 text-md-end">
                    <small class="text-muted">Total: <?php echo $total_count; ?></small>
                </div>
            </div>
        </div>

        <?php if (empty($publicidades)): ?>
            <div class="text-center py-5" style="background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border-color);">
                <i class="fas fa-ad fa-4x text-muted mb-3"></i>
                <h5>No hay publicidades</h5>
                <p class="text-muted">Crea tu primera publicidad haciendo clic en "Nueva Publicidad"</p>
                <a href="/dashboard/admin/new-publication.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Crear Publicidad
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Posición</th>
                            <th>Vista previa</th>
                            <th>Orden</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publicidades as $pub): ?>
                        <tr>
                            <td><?php echo $pub['id']; ?></td>
                            <td>
                                <?php echo htmlspecialchars($pub['titulo']); ?>
                                <?php if (!empty($pub['descripcion'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($pub['descripcion'], 0, 50)); ?></small>
                                <?php endif; ?>
                            </div>
                            <td>
                                <?php if ($pub['tipo'] == 'banner'): ?>
                                    <span class="badge-banner">Banner</span>
                                <?php else: ?>
                                    <span class="badge-video">Video</span>
                                <?php endif; ?>
                            </div>
                            <td>
                                <?php if ($pub['posicion'] == 'banner_principal'): ?>
                                    <span class="badge-principal">Banner Principal</span>
                                <?php else: ?>
                                    <span class="badge-video-espacio">Espacio Video</span>
                                <?php endif; ?>
                            </div>
                            <td>
                                <?php if ($pub['tipo'] == 'banner' && $pub['archivo_url']): ?>
                                    <img src="<?php echo htmlspecialchars($pub['archivo_url']); ?>" class="preview-img" alt="Banner">
                                <?php elseif ($pub['tipo'] == 'video' && $pub['video_embed']): ?>
                                    <i class="fab fa-youtube fa-2x" style="color: #ff0000;"></i>
                                    <div class="small text-muted">YouTube</div>
                                <?php elseif ($pub['tipo'] == 'video' && $pub['archivo_url']): ?>
                                    <video class="preview-video" muted>
                                        <source src="<?php echo htmlspecialchars($pub['archivo_url']); ?>">
                                    </video>
                                <?php else: ?>
                                    <i class="fas fa-image fa-2x text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <td><?php echo $pub['orden']; ?></div>
                            <td>
                                <?php if ($pub['activo'] == 1): ?>
                                    <span class="badge-activo">Activo</span>
                                <?php else: ?>
                                    <span class="badge-inactivo">Inactivo</span>
                                <?php endif; ?>
                                <?php if ($pub['fecha_inicio'] && $pub['fecha_inicio'] > date('Y-m-d')): ?>
                                    <br><small class="text-muted">Inicia: <?php echo date('d/m/Y', strtotime($pub['fecha_inicio'])); ?></small>
                                <?php endif; ?>
                                <?php if ($pub['fecha_fin'] && $pub['fecha_fin'] < date('Y-m-d')): ?>
                                    <br><small class="text-danger">Expirado</small>
                                <?php endif; ?>
                            </div>
                            <td>
                                <a href="/dashboard/admin/new-publication.php?id=<?php echo $pub['id']; ?>" class="btn-icon edit" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="deletePublicidad(<?php echo $pub['id']; ?>)" class="btn-icon delete" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        <span>Mostrando página <?php echo $page; ?> de <?php echo $total_pages; ?></span>
                    </div>
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&tipo=<?php echo $tipoFilter; ?>&posicion=<?php echo $posicionFilter; ?>&activo=<?php echo $activoFilter; ?>">
                                        <i class="fas fa-chevron-left"></i> Anterior
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&tipo=<?php echo $tipoFilter; ?>&posicion=<?php echo $posicionFilter; ?>&activo=<?php echo $activoFilter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&tipo=<?php echo $tipoFilter; ?>&posicion=<?php echo $posicionFilter; ?>&activo=<?php echo $activoFilter; ?>">
                                        Siguiente <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<!-- Botón tema claro/oscuro -->
<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Función para cambiar tema
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
    
    // Al cargar la página, ajustar el icono según el tema guardado
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

    function deletePublicidad(id) {
        Swal.fire({
            title: '¿Eliminar publicidad?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/dashboard/admin/delete-publicidad.php',
                    method: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            Swal.fire('Eliminado', 'Publicidad eliminada correctamente', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', r.message || 'Error al eliminar', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo eliminar la publicidad', 'error');
                    }
                });
            }
        });
    }
</script>
</body>
</html>