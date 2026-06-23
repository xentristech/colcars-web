<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\audit.php
 * REGISTRO DE AUDITORÍA - Panel de Administración
 * MODIFICADO: Usa CDN en lugar de archivos locales
 * MODIFICADO: Eliminados scripts duplicados (jQuery y Bootstrap ya están en sidebar)
 * MODIFICADO: Rutas absolutas corregidas (sin /easycarluxury/)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';

// Obtener la conexión PDO correctamente
$database = Database::getInstance();
$pdo = $database->getConnection();

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

// Pagination and filters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 30;
$offset = ($page - 1) * $limit;
$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build query
$whereConditions = ["1=1"];
$params = [];

if ($actionFilter) {
    $whereConditions[] = "a.action = :action";
    $params[':action'] = $actionFilter;
}

if ($userFilter) {
    $whereConditions[] = "a.admin_id = :user_id";
    $params[':user_id'] = $userFilter;
}

if ($dateFrom) {
    $whereConditions[] = "DATE(a.created_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}

if ($dateTo) {
    $whereConditions[] = "DATE(a.created_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}

$whereClause = implode(" AND ", $whereConditions);

// Get distinct actions for filter
$actionsQuery = "SELECT DISTINCT action FROM admin_audit_log ORDER BY action";
$actions = $pdo->query($actionsQuery)->fetchAll(PDO::FETCH_COLUMN);

// Get admins for filter
$adminsQuery = "SELECT id, nombre_completo as full_name FROM usuarios WHERE rol_id IN (1, 2, 3, 4, 5) ORDER BY nombre_completo";
$admins = $pdo->query($adminsQuery)->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM admin_audit_log a WHERE $whereClause";
$countStmt = $pdo->prepare($countQuery);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$totalLogs = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($totalLogs / $limit);

// Get audit logs
$query = "SELECT a.*, u.nombre_completo as admin_name, u.email as admin_email
        FROM admin_audit_log a
        JOIN usuarios u ON a.admin_id = u.id
        WHERE $whereClause
        ORDER BY a.created_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría - Colcars</title>
    <!-- Ruta: /dashboard/admin/audit.php -->
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS - CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
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
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            z-index: -100;
        }

        .sidebar-column {
            flex-shrink: 0;
            
        }

        .admin-main {
            flex: 1;
            width: auto;
            padding: 15px 15px;
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

        .filters-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .filters-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: inline-block;
        }

        .form-select, .form-control {
            padding: 6px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.8rem;
            min-width: 150px;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: #c8a86b;
        }

        .stats-mini {
            display: flex;
            gap: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 10px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .stat-mini {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-mini .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .stat-mini .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #c8a86b;
        }

        .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .card-body {
            padding: 0;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
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

        .audit-time {
            white-space: nowrap;
        }

        .admin-cell strong {
            display: block;
            font-size: 0.8rem;
            color: var(--text-primary);
        }

        .admin-cell small {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }

        .action-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
        }

        .action-create { background: #d4edda; color: #155724; }
        .action-update { background: #fff3cd; color: #856404; }
        .action-delete { background: #f8d7da; color: #721c24; }
        .action-login { background: #d1ecf1; color: #0c5460; }
        .action-logout { background: #e2e3e5; color: #383d41; }
        .action-suspend { background: #f8d7da; color: #721c24; }
        .action-restore { background: #d4edda; color: #155724; }
        .action-upgrade { background: #d4edda; color: #155724; }
        .action-downgrade { background: #fff3cd; color: #856404; }
        .action-activate { background: #d4edda; color: #155724; }
        .action-deactivate { background: #f8d7da; color: #721c24; }
        .action-feature { background: #fff3cd; color: #856404; }
        .action-unfeature { background: #e2e3e5; color: #383d41; }
        .action-impersonate { background: #d1ecf1; color: #0c5460; }
        .action-reset_password { background: #fff3cd; color: #856404; }
        .action-soft_delete { background: #f8d7da; color: #721c24; }

        .target-info strong {
            display: block;
            font-size: 0.75rem;
            color: var(--text-primary);
        }

        .target-info small {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }

        .audit-details-cell .btn-details {
            background: none;
            border: none;
            color: #c8a86b;
            cursor: pointer;
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .audit-details-cell .btn-details:hover {
            background: var(--table-hover);
            color: #a07e4a;
        }

        .ip-address {
            font-size: 0.7rem;
            background: var(--bg-primary);
            padding: 2px 6px;
            border-radius: 4px;
            color: var(--text-primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.8rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
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

        .btn-success {
            background: #28a745;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn-export {
            background: #28a745;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 0.75rem;
            margin-left: 5px;
            transition: all 0.3s;
        }

        .btn-export:hover {
            background: #1e7e34;
            transform: translateY(-1px);
        }

        .btn-export i {
            margin-right: 4px;
        }

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

        .page-link:hover, .page-item.active .page-link {
            background: #c8a86b;
            color: white;
            border-color: #c8a86b;
        }

        .limit-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-primary);
        }

        .limit-selector select {
            padding: 5px 8px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.75rem;
            background: var(--input-bg);
            color: var(--text-primary);
            cursor: pointer;
        }

        .text-center {
            text-align: center;
        }

        .text-muted {
            color: var(--text-secondary) !important;
        }

        /* Estilos para el modal de detalles legibles */
        .details-container {
            font-size: 0.85rem;
            line-height: 1.5;
            color: var(--text-primary);
        }
        .details-section {
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
        }
        .details-label {
            font-weight: bold;
            color: #c8a86b;
            display: inline-block;
            min-width: 110px;
        }
        .details-value {
            color: var(--text-primary);
        }
        .details-subsection {
            margin-top: 8px;
            margin-left: 15px;
            padding-left: 10px;
            border-left: 3px solid #c8a86b;
        }
        .details-subsection-title {
            font-weight: bold;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        .details-list {
            list-style: none;
            padding-left: 0;
            margin-bottom: 0;
        }
        .details-list li {
            padding: 2px 0;
            font-size: 0.8rem;
        }
        .details-list li strong {
            color: var(--text-secondary);
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
           RESPONSIVE: Ajustes solicitados
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px;
            }
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group select,
            .filter-group input,
            .filter-group button {
                width: 100%;
            }
            .header-actions .btn-export {
                margin-bottom: 8px !important;
            }
            .filter-group .btn-secondary {
                margin-top: 10px !important;
            }
        }

        @media (max-width: 768px) {
            .admin-table {
                min-width: 800px;
            }
            .pagination-container {
                flex-direction: column;
                align-items: center;
            }
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
    <div class="admin-container">
        <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header">
                <div class="header-title">
                    <h1><i class="fas fa-history"></i> Registro de Auditoría</h1>
                    <p>Historial completo de acciones administrativas</p>
                </div>
                <div class="header-actions">
                    <div style="display: inline-flex; gap: 5px;">
                        <button class="btn-export" id="exportCsvBtn">
                            <i class="fas fa-file-csv"></i> CSV
                        </button>
                        <button class="btn-export" id="exportExcelBtn">
                            <i class="fas fa-file-excel"></i> Excel
                        </button>
                        <button class="btn-export" id="exportPdfBtn" style="background-color: red;">
                            <i class="fas fa-file-pdf"></i> PDF
                        </button>
                    </div>
                    <button class="btn btn-danger" id="clearOldLogs" style="margin-left: 10px;">
                        <i class="fas fa-trash"></i> Limpiar logs antiguos
                    </button>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-card">
                <form method="GET" class="filters-form" id="filterForm">
                    <div class="filter-group">
                        <select name="action" class="form-select">
                            <option value="">Todas las acciones</option>
                            <?php foreach ($actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $actionFilter === $action ? 'selected' : ''; ?>>
                                    <?php echo str_replace('_', ' ', ucfirst($action)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <select name="user_id" class="form-select">
                            <option value="">Todos los administradores</option>
                            <?php foreach ($admins as $adminUser): ?>
                                <option value="<?php echo $adminUser['id']; ?>" <?php echo $userFilter == $adminUser['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($adminUser['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <input type="date" name="date_from" class="form-control" placeholder="Desde" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    <div class="filter-group">
                        <input type="date" name="date_to" class="form-control" placeholder="Hasta" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <a href="audit.php" class="btn btn-secondary">Limpiar</a>
                    </div>
                </form>
            </div>
            
            <!-- Statistics -->
            <div class="stats-mini">
                <div class="stat-mini">
                    <span class="stat-label">Total Registros</span>
                    <span class="stat-number"><?php echo number_format($totalLogs); ?></span>
                </div>
                <div class="stat-mini">
                    <span class="stat-label">Mostrando</span>
                    <span class="stat-number"><?php echo min($limit, $totalLogs); ?></span>
                </div>
            </div>
            
            <!-- Audit Logs Table -->
            <div class="data-card">
                <div class="card-body table-responsive">
                    <table class="admin-table audit-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Fecha/Hora</th>
                                <th>Administrador</th>
                                <th>Acción</th>
                                <th>Target</th>
                                <th>Detalles</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($auditLogs) > 0): ?>
                                <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td><?php echo $log['id']; ?></td>
                                    <td class="audit-time">
                                        <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="admin-cell">
                                            <strong><?php echo htmlspecialchars($log['admin_name']); ?></strong>
                                            <small><?php echo htmlspecialchars($log['admin_email']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="action-badge action-<?php echo str_replace('_', '-', strtolower($log['action'])); ?>">
                                            <?php echo str_replace('_', ' ', ucfirst($log['action'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['target_type'] && $log['target_id']): ?>
                                            <div class="target-info">
                                                <strong><?php echo ucfirst($log['target_type']); ?></strong>
                                                <small>#<?php echo $log['target_id']; ?></small>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="audit-details-cell">
                                        <?php if ($log['details']): ?>
                                            <button class="btn-details" onclick='showDetails(<?php echo json_encode($log['details'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'>
                                                <i class="fas fa-eye"></i> Ver detalles
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code class="ip-address"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></code>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center">No se encontraron registros de auditoría</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Paginación -->
            <div class="pagination-container">
                <div class="limit-selector">
                    <span>Mostrar:</span>
                    <select id="limit_select" onchange="changeLimit(this.value)">
                        <option value="10" <?php echo $limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $limit == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="30" <?php echo $limit == 30 ? 'selected' : ''; ?>>30</option>
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                    <span>registros por página</span>
                </div>
                
                <?php if ($totalPages > 1): ?>
                <nav>
                    <ul class="pagination">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="#" onclick="changePage(<?php echo $page - 1; ?>); return false;">Anterior</a>
                        </li>
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($totalPages, $page + 2);
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>';
                            if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="#" onclick="changePage(<?php echo $i; ?>); return false;"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; 
                        if ($end_page < $totalPages) {
                            if ($end_page < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' . $totalPages . '); return false;">' . $totalPages . '</a></li>';
                        }
                        ?>
                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="#" onclick="changePage(<?php echo $page + 1; ?>); return false;">Siguiente</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <!-- Botón para cambiar tema claro/oscuro -->
    <button class="btn-theme" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2 px-3" style="background: #c8a86b; color: white;">
                    <h5 class="modal-title"><i class="fas fa-info-circle"></i> Detalles de la Acción</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3" id="detailsModalBody">
                    <div id="detailsContent" class="details-container"></div>
                </div>
                <div class="modal-footer py-2 px-3">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
            <!-- Footer -->
            <?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>
    <!-- SweetAlert2 JS - CDN (única librería externa, jQuery y Bootstrap ya están en sidebar) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        const token = localStorage.getItem('auth_token');
        
        // Función para mostrar SweetAlert2 con el tema adecuado
        function showSwalWithTheme(options) {
            const theme = document.documentElement.getAttribute('data-theme') || 'light';
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
        
        function changePage(page) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('page', page);
            window.location.href = '?' + urlParams.toString();
        }
        
        function changeLimit(limit) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('limit', limit);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        }
        
        function exportAudit(format) {
            const params = new URLSearchParams(window.location.search);
            let url = 'export-audit.php?format=' + format;
            if (params.get('action')) url += '&action=' + encodeURIComponent(params.get('action'));
            if (params.get('user_id')) url += '&user_id=' + encodeURIComponent(params.get('user_id'));
            if (params.get('date_from')) url += '&date_from=' + encodeURIComponent(params.get('date_from'));
            if (params.get('date_to')) url += '&date_to=' + encodeURIComponent(params.get('date_to'));
            window.location.href = url;
        }
        
        // Función para parsear strings JSON dentro del objeto
        function parseDeep(obj) {
            if (typeof obj === 'string') {
                try {
                    const parsed = JSON.parse(obj);
                    return parseDeep(parsed);
                } catch(e) {
                    return obj;
                }
            } else if (Array.isArray(obj)) {
                return obj.map(item => parseDeep(item));
            } else if (typeof obj === 'object' && obj !== null) {
                const newObj = {};
                for (const key in obj) {
                    newObj[key] = parseDeep(obj[key]);
                }
                return newObj;
            }
            return obj;
        }
        
        // Traducir nombres de acciones a español legible
        function translateAction(action) {
            const actions = {
                'CREATE': 'Creación',
                'UPDATE': 'Actualización',
                'DELETE': 'Eliminación',
                'LOGIN': 'Inicio de sesión',
                'LOGOUT': 'Cierre de sesión',
                'SUSPEND': 'Suspensión',
                'RESTORE': 'Restauración',
                'UPGRADE': 'Mejora de membresía',
                'DOWNGRADE': 'Degradación de membresía',
                'ACTIVATE': 'Activación',
                'DEACTIVATE': 'Desactivación',
                'FEATURE': 'Marcar como destacado',
                'UNFEATURE': 'Quitar destacado',
                'IMPERSONATE': 'Suplantación de identidad',
                'RESET_PASSWORD': 'Restablecimiento de contraseña',
                'SOFT_DELETE': 'Desactivación de cuenta',
                'VIEW': 'Visualización',
                'READ': 'Lectura'
            };
            return actions[action] || action;
        }
        
        // Traducir tipos de target
        function translateTargetType(targetType) {
            const types = {
                'usuario': 'Usuario',
                'categoria': 'Categoría',
                'membresia': 'Membresía',
                'publicacion': 'Publicación',
                'page': 'Página',
                'session': 'Sesión'
            };
            return types[targetType] || targetType;
        }
        
        // Formatear objeto como lista HTML legible
        function formatObjectAsList(obj, indent = 0) {
            if (obj === null || obj === undefined) return '<span class="text-muted">—</span>';
            if (typeof obj !== 'object') return String(obj);
            
            let html = '<ul class="details-list" style="margin-left: ' + (indent * 15) + 'px;">';
            for (const [key, value] of Object.entries(obj)) {
                const displayKey = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                if (typeof value === 'object' && value !== null) {
                    html += `<li><strong>${displayKey}:</strong> ${formatObjectAsList(value, indent + 1)}</li>`;
                } else {
                    let displayValue = String(value);
                    if (value === 1 || value === true) displayValue = 'Sí';
                    if (value === 0 || value === false) displayValue = 'No';
                    html += `<li><strong>${displayKey}:</strong> ${displayValue}</li>`;
                }
            }
            html += '</ul>';
            return html;
        }
        
        function showDetails(detailsData) {
            let details = detailsData;
            
            if (typeof details === 'string') {
                try {
                    details = JSON.parse(details);
                } catch(e) {
                    details = details;
                }
            }
            
            details = parseDeep(details);
            
            let html = '';
            
            if (details.action) {
                html += `<div class="details-section">
                            <span class="details-label"><i class="fas fa-tag"></i> Acción:</span>
                            <span class="details-value">${translateAction(details.action)}</span>
                         </div>`;
            }
            
            if (details.target_type) {
                html += `<div class="details-section">
                            <span class="details-label"><i class="fas fa-cube"></i> Tipo:</span>
                            <span class="details-value">${translateTargetType(details.target_type)}</span>
                         </div>`;
            }
            
            if (details.target_id) {
                html += `<div class="details-section">
                            <span class="details-label"><i class="fas fa-hashtag"></i> ID:</span>
                            <span class="details-value">#${details.target_id}</span>
                         </div>`;
            }
            
            if (details.page) {
                let pageName = details.page.replace('/easycarluxury/', '').replace(/\?.*$/, '');
                html += `<div class="details-section">
                            <span class="details-label"><i class="fas fa-file-alt"></i> Página:</span>
                            <span class="details-value">${pageName}</span>
                         </div>`;
            }
            
            if (details.additional_info) {
                html += `<div class="details-section">
                            <span class="details-label"><i class="fas fa-comment"></i> Información:</span>
                            <span class="details-value">${escapeHtml(details.additional_info)}</span>
                         </div>`;
            }
            
            if (details.timestamp) {
                html += `<div class="details-section">
                            <span class="details-label"><i class="fas fa-calendar"></i> Fecha/Hora:</span>
                            <span class="details-value">${details.timestamp}</span>
                         </div>`;
            }
            
            if (details.old_data && Object.keys(details.old_data).length > 0) {
                html += `<div class="details-subsection">
                            <div class="details-subsection-title"><i class="fas fa-arrow-left"></i> Datos anteriores:</div>
                            ${formatObjectAsList(details.old_data)}
                         </div>`;
            }
            
            if (details.new_data && Object.keys(details.new_data).length > 0) {
                html += `<div class="details-subsection">
                            <div class="details-subsection-title"><i class="fas fa-arrow-right"></i> Datos nuevos:</div>
                            ${formatObjectAsList(details.new_data)}
                         </div>`;
            }
            
            if (html === '') {
                html = '<p class="text-muted text-center">No hay detalles adicionales para esta acción.</p>';
            }
            
            document.getElementById('detailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }
        
        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function(m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
        
        document.getElementById('exportCsvBtn').addEventListener('click', function() {
            exportAudit('csv');
        });
        
        document.getElementById('exportExcelBtn').addEventListener('click', function() {
            exportAudit('excel');
        });
        
        document.getElementById('exportPdfBtn').addEventListener('click', function() {
            exportAudit('pdf');
        });
        
        document.getElementById('clearOldLogs').addEventListener('click', function() {
            showSwalWithTheme({
                title: '¿Eliminar logs antiguos?',
                text: 'Esta acción eliminará todos los registros de auditoría mayores a 90 días. No se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/api/v1/admin.php',
                        method: 'DELETE',
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Content-Type': 'application/json'
                        },
                        data: JSON.stringify({
                            action: 'clear_old_audit_logs',
                            days: 90
                        }),
                        success: function(response) {
                            if (response.success) {
                                showSwalWithTheme({
                                    title: '¡Eliminados!',
                                    text: response.deleted_count + ' logs eliminados correctamente',
                                    icon: 'success',
                                    confirmButtonColor: '#c8a86b'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                showSwalWithTheme({
                                    title: 'Error',
                                    text: response.message || 'Error al eliminar logs',
                                    icon: 'error',
                                    confirmButtonColor: '#c8a86b'
                                });
                            }
                        },
                        error: function() {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'Error de conexión al eliminar logs',
                                icon: 'error',
                                confirmButtonColor: '#c8a86b'
                            });
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>