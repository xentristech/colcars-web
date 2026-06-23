<?php
/**
 * Colcars - Administración de Publicaciones (Admin)
 * Ruta: /dashboard/admin/admin-publications.php
 * Permite: Ver, filtrar, editar, eliminar, activar/desactivar publicaciones de todos los usuarios
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación
requireAuth();

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /easycarluxury/login');
    exit;
}

$db = Database::getInstance();
$current_user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

if (!$current_user || !is_array($current_user)) {
    session_destroy();
    header('Location: /easycarluxury/login');
    exit;
}

// Verificar que sea administrador (rol_id 1 = superadmin)
if (($current_user['rol_id'] ?? 0) != 1) {
    header('Location: /easycarluxury/dashboard/user/index.php');
    exit;
}

// Variables de filtrado
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$statusFilter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');
$user_filter = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;

// Construir consulta WHERE
$where = "1=1";
$params = [];

if ($statusFilter === 'activos') {
    $where .= " AND p.status = 'active'";
} elseif ($statusFilter === 'inactivos') {
    $where .= " AND p.status = 'inactive'";
}

if (!empty($search)) {
    $where .= " AND (p.titulo LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($user_filter && $user_filter > 0) {
    $where .= " AND p.usuario_id = ?";
    $params[] = $user_filter;
}

// Obtener total de publicaciones
$totalStmt = $db->query("SELECT COUNT(*) as total FROM publicaciones p WHERE $where", $params);
$totalRow = $totalStmt ? $totalStmt->fetch(PDO::FETCH_ASSOC) : ['total' => 0];
$total_count = $totalRow['total'];
$total_pages = ceil($total_count / $per_page);

// Obtener publicaciones con datos del usuario
$paramsForQuery = $params;
$paramsForQuery[] = $per_page;
$paramsForQuery[] = $offset;

$sql = "SELECT p.*, 
               c.nombre as categoria_nombre,
               u.nombre_completo as usuario_nombre,
               u.email as usuario_email,
               u.tipo_cuenta as usuario_tipo_cuenta
        FROM publicaciones p
        JOIN categorias c ON p.categoria_id = c.id
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $db->query($sql, $paramsForQuery);
$publicaciones = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

// Obtener imagen principal para cada publicación
if (!empty($publicaciones) && is_array($publicaciones)) {
    foreach ($publicaciones as $key => $pub) {
        $imagen = $db->getOne("SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = ? AND is_primary = 1 LIMIT 1", [$pub['id']]);
        $publicaciones[$key]['imagen_principal'] = $imagen ? $imagen['image_path'] : null;
        
        $comentarios = $db->getOne("SELECT COUNT(*) as total FROM comentarios WHERE publicacion_id = ? AND visible = 1", [$pub['id']]);
        $publicaciones[$key]['comentarios_count'] = $comentarios ? $comentarios['total'] : 0;
    }
}

// Obtener lista de usuarios para el filtro
$usuarios = $db->getAll("SELECT id, nombre_completo, email, tipo_cuenta FROM usuarios ORDER BY nombre_completo ASC");

$theme = $_COOKIE['user_theme'] ?? ($current_user['tema_oscuro'] ? 'dark' : 'light');
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Publicaciones - Colcars Admin</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
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
            margin-top: 0;
        }

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

        /* Modo oscuro - inputs y selects */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select,
        [data-theme="dark"] .input-group-text {
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

        [data-theme="dark"] .table {
            color: #ffffff !important;
        }

        [data-theme="dark"] .table td,
        [data-theme="dark"] .table th {
            border-color: #3a3a4e !important;
        }

        [data-theme="dark"] .table-striped > tbody > tr:nth-of-type(odd) > * {
            background-color: rgba(102, 126, 234, 0.1);
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }

        [data-theme="dark"] .text-muted {
            color: #c0c0c0 !important;
        }

        /* Cards y elementos */
        .filter-card, .stats-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .publication-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .publication-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--card-shadow);
        }

        .publication-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
        }

        .badge-container {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-bottom: 10px;
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
            transition: transform 0.3s;
        }

        .btn-theme:hover {
            transform: scale(1.05);
        }

        .user-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            color: white;
            display: inline-block;
        }

        .membership-free { background: #6c757d; }
        .membership-pro { background: #0d6efd; }
        .membership-premium { background: #fd7e14; }
        .membership-elite { background: #ffc107; color: #000; }
        .membership-sistema { background: #dc3545; }

        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
                padding-top: 70px !important;
            }
            
            .publication-img {
                width: 100%;
                height: 150px;
            }
            
            .action-buttons, .badge-container {
                justify-content: flex-start;
            }
            
            .btn-theme {
                bottom: 70px;
                right: 15px;
                width: 45px;
                height: 45px;
            }
        }

        @media (max-width: 992px) {
            .main-content {
                padding-top: 70px !important;
            }
        }
    </style>
</head>
<body>

    <button class="btn-theme" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>

    <div class="dashboard-wrapper">
        <!-- Sidebar de administración -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
        </div>

        <div class="content-column">
            <div class="main-content">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2><i class="fas fa-list"></i> Gestionar Publicaciones</h2>
                    <div class="d-flex gap-2">
                        <a href="/dashboard/admin/new-publication.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Nueva Publicación
                        </a>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="filter-card">
                    <form method="GET" action="" id="filterForm">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label"><i class="fas fa-user"></i> Usuario</label>
                                <select name="user_id" class="form-select select2" id="userSelect">
                                    <option value="">-- Todos los usuarios --</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?php echo $usuario['id']; ?>" <?php echo ($user_filter == $usuario['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($usuario['nombre_completo']); ?> 
                                            (<?php echo strtoupper($usuario['tipo_cuenta']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><i class="fas fa-filter"></i> Estado</label>
                                <select name="status" class="form-select">
                                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>Todas</option>
                                    <option value="activos" <?php echo $statusFilter === 'activos' ? 'selected' : ''; ?>>Activas</option>
                                    <option value="inactivos" <?php echo $statusFilter === 'inactivos' ? 'selected' : ''; ?>>Inactivas</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label"><i class="fas fa-search"></i> Buscar</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" placeholder="Título o descripción..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="btn btn-secondary w-100">
                                        <i class="fas fa-sync-alt"></i> Refrescar
                                    </a>
                                    <?php if ($user_filter): ?>
                                        <a href="?status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>" class="btn btn-outline-danger w-100">
                                            <i class="fas fa-times"></i> Limpiar
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Estadísticas rápidas -->
                <div class="stats-card">
                    <div class="row text-center">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="p-2">
                                <i class="fas fa-car fa-2x text-primary"></i>
                                <h4 class="mt-2 mb-0"><?php echo number_format($total_count); ?></h4>
                                <small class="text-muted">Total publicaciones</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="p-2">
                                <i class="fas fa-check-circle fa-2x text-success"></i>
                                <h4 class="mt-2 mb-0"><?php 
                                    $activeCount = $db->getOne("SELECT COUNT(*) as total FROM publicaciones WHERE status = 'active'");
                                    echo number_format($activeCount['total'] ?? 0);
                                ?></h4>
                                <small class="text-muted">Publicaciones activas</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="p-2">
                                <i class="fas fa-users fa-2x text-info"></i>
                                <h4 class="mt-2 mb-0"><?php 
                                    $userCount = $db->getOne("SELECT COUNT(DISTINCT usuario_id) as total FROM publicaciones");
                                    echo number_format($userCount['total'] ?? 0);
                                ?></h4>
                                <small class="text-muted">Usuarios con publicaciones</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Listado de publicaciones -->
                <?php if (empty($publicaciones) || !is_array($publicaciones) || count($publicaciones) === 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-car fa-4x text-muted mb-3"></i>
                        <h5>No hay publicaciones</h5>
                        <p class="text-muted">No se encontraron publicaciones con los filtros aplicados.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($publicaciones as $pub): ?>
                        <div class="publication-card">
                            <div class="row g-0">
                                <div class="col-md-2">
                                    <?php if (!empty($pub['imagen_principal'])): ?>
                                        <img src="<?php echo htmlspecialchars($pub['imagen_principal']); ?>"
                                            class="publication-img w-100" style="height: 120px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="publication-img w-100 bg-secondary d-flex align-items-center justify-content-center" style="height: 120px;">
                                            <i class="fas fa-image fa-3x text-white-50"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-10">
                                    <div class="p-3">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h5><?php echo htmlspecialchars($pub['titulo']); ?></h5>
                                                <div class="mb-2">
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($pub['categoria_nombre']); ?></span>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($pub['estado_articulo'] ?? 'usado'); ?></span>
                                                    <span class="user-badge">
                                                        <i class="fas fa-user"></i> <?php echo htmlspecialchars($pub['usuario_nombre']); ?>
                                                    </span>
                                                    <span class="badge membership-<?php echo $pub['usuario_tipo_cuenta']; ?>">
                                                        <?php echo strtoupper($pub['usuario_tipo_cuenta']); ?>
                                                    </span>
                                                </div>
                                                <div class="row text-muted small">
                                                    <div class="col-md-3"><i class="fas fa-eye"></i> <?php echo number_format($pub['visitas'] ?? 0); ?> visitas</div>
                                                    <div class="col-md-3"><i class="fas fa-heart"></i> <?php echo number_format($pub['likes'] ?? 0); ?> likes</div>
                                                    <div class="col-md-3"><i class="fas fa-comment"></i> <?php echo $pub['comentarios_count'] ?? 0; ?> comentarios</div>
                                                    <div class="col-md-3"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($pub['created_at'])); ?></div>
                                                </div>
                                                <div class="mt-2 small">
                                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($pub['ubicacion'] ?? 'No especificada'); ?>
                                                    <i class="fas fa-tag ms-2"></i> <?php echo htmlspecialchars($pub['color'] ?? 'N/A'); ?>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                                <div class="badge-container">
                                                    <?php if ($pub['destacado']): ?>
                                                        <span class="badge bg-warning"><i class="fas fa-star"></i> Destacado</span>
                                                    <?php endif; ?>
                                                    <?php if ($pub['solo_premium_elite']): ?>
                                                        <span class="badge bg-purple"><i class="fas fa-gem"></i> Solo Premium/Elite</span>
                                                    <?php endif; ?>
                                                    <span class="badge bg-<?php echo $pub['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $pub['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </div>
                                                <div class="action-buttons">
                                                    <a href="/public/catalog/detail.php?id=<?php echo $pub['id']; ?>"
                                                        target="_blank" class="btn btn-sm btn-outline-info">
                                                        <i class="fas fa-eye"></i> Ver
                                                    </a>
                                                    <a href="/dashboard/admin/admin-edit-publication.php?id=<?php echo $pub['id']; ?>"
                                                        class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-edit"></i> Editar
                                                    </a>
                                                    <button onclick="toggleStatus(<?php echo $pub['id']; ?>, '<?php echo $pub['status']; ?>')"
                                                        class="btn btn-sm btn-outline-warning">
                                                        <i class="fas fa-<?php echo $pub['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                    <button onclick="deletePublication(<?php echo $pub['id']; ?>)"
                                                        class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="mt-2">
                                                    <strong class="text-primary fs-5"><?php echo formatMoney($pub['precio']); ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                        <nav class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="dashboard-footer" style="background: var(--bg-secondary); border-top: 1px solid var(--border-color); padding: 15px 0; text-align: center; font-size: 0.75rem;">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <p class="mb-0 text-muted">&copy; <?php echo date('Y'); ?> Colcars - Todos los derechos reservados.</p>
                    <p class="mb-0 small"><a href="/easycarluxury/terms" class="text-decoration-none">Términos y condiciones</a> | <a href="/easycarluxury/privacy" class="text-decoration-none">Política de privacidad</a></p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <style>
        .membership-free { background: #6c757d; }
        .membership-pro { background: #0d6efd; }
        .membership-premium { background: #fd7e14; }
        .membership-elite { background: #ffc107; color: #000; }
        .membership-sistema { background: #dc3545; }
        .bg-purple { background: #6f42c1; }
        
        .dashboard-footer a {
            color: #667eea;
        }
        
        [data-theme="dark"] .dashboard-footer a {
            color: #a0c4ff;
        }
    </style>

    <script>
        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Seleccionar usuario...',
                allowClear: true
            });
            
            $('#userSelect').on('change', function() {
                $('#filterForm').submit();
            });
        });

        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({
                url: '/api/v1/users/settings.php',
                method: 'POST',
                data: { theme: newTheme },
                error: function() { console.log('Theme preference not saved'); }
            });
        }

        function toggleStatus(id, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activar' : 'desactivar';
            const actionText = action === 'activar' ? 'visible' : 'oculta';
            
            Swal.fire({
                title: `¿${action === 'activar' ? 'Activar' : 'Desactivar'} publicación?`,
                text: `La publicación quedará ${actionText}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Sí, ${action}`,
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/dashboard/admin/update-status.php',
                        method: 'POST',
                        data: {
                            id: id,
                            status: newStatus,
                            csrf_token: '<?php echo $csrf_token; ?>'
                        },
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                Swal.fire('Éxito', `Publicación ${action}da correctamente`, 'success');
                                location.reload();
                            } else {
                                Swal.fire('Error', r.message || 'Error al cambiar el estado', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo cambiar el estado', 'error');
                        }
                    });
                }
            });
        }

        function deletePublication(id) {
            Swal.fire({
                title: '¿Eliminar publicación?',
                text: 'Esta acción no se puede deshacer. Todos los datos asociados se perderán.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/dashboard/admin/delete-publication.php',
                        method: 'POST',
                        data: {
                            id: id,
                            csrf_token: '<?php echo $csrf_token; ?>'
                        },
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                Swal.fire('Eliminado', 'Publicación eliminada correctamente', 'success');
                                location.reload();
                            } else {
                                Swal.fire('Error', r.message || 'Error al eliminar', 'error');
                            }
                        },
                        error: function() {
                            Swal.fire('Error', 'No se pudo eliminar la publicación', 'error');
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>