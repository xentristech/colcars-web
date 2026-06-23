<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\index.php
 * DASHBOARD ADMINISTRADOR - CON TEMA CLARO/OSCURO
 * MODIFICADO: Se agregó soporte para tema claro/oscuro desde audit.php
 * ESTRUCTURA MÓVIL: Basada en users.php
 */

session_start();
require_once '../../config/database.php';
require_once __DIR__ . '/../../includes/admin-auth.php';

// Obtener conexión
$database = Database::getInstance();
$pdo = $database->getConnection();

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

// Store admin info in session for sidebar
$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

$stats = $adminAuth->getDashboardStats();

// ============================================
// PAGINACIÓN PARA USUARIOS RECIENTES
// ============================================
$user_page = isset($_GET['user_page']) ? (int)$_GET['user_page'] : 1;
$user_per_page = isset($_GET['user_per_page']) ? (int)$_GET['user_per_page'] : 10;
$user_offset = ($user_page - 1) * $user_per_page;

// Validar valores permitidos para registros por página de usuarios
$allowed_user_per_page = [10, 25, 50, 100, 200, 500];
if (!in_array($user_per_page, $allowed_user_per_page)) {
    $user_per_page = 10;
}

// Get total count of users
$totalUsersQuery = "SELECT COUNT(*) as total FROM usuarios u WHERE u.activo = 1";
$totalUsersResult = $pdo->query($totalUsersQuery);
$totalUsersRows = $totalUsersResult->fetch(PDO::FETCH_ASSOC)['total'];
$totalUserPages = ceil($totalUsersRows / $user_per_page);

// Get recent users con paginación
$query = "SELECT u.id, u.nombre_completo as full_name, u.email, r.nombre as role, u.tipo_cuenta as membership_tier, u.created_at, u.activo as status 
            FROM usuarios u
            JOIN roles r ON u.rol_id = r.id
            WHERE u.activo = 1
            ORDER BY u.created_at DESC 
            LIMIT :offset, :per_page";
$stmt = $pdo->prepare($query);
$stmt->bindParam(':offset', $user_offset, PDO::PARAM_INT);
$stmt->bindParam(':per_page', $user_per_page, PDO::PARAM_INT);
$stmt->execute();
$recentUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ============================================
// PAGINACIÓN PARA ACTIVIDAD RECIENTE
// ============================================
$audit_page = isset($_GET['audit_page']) ? (int)$_GET['audit_page'] : 1;
$audit_per_page = isset($_GET['audit_per_page']) ? (int)$_GET['audit_per_page'] : 10;
$audit_offset = ($audit_page - 1) * $audit_per_page;

// Validar valores permitidos para registros por página de auditoría
$allowed_audit_per_page = [10, 25, 50, 100, 200, 500];
if (!in_array($audit_per_page, $allowed_audit_per_page)) {
    $audit_per_page = 10;
}

// Get total count of audit logs
$totalAuditQuery = "SELECT COUNT(*) as total FROM auditoria a";
$totalAuditResult = $pdo->query($totalAuditQuery);
$totalAuditRows = $totalAuditResult->fetch(PDO::FETCH_ASSOC)['total'];
$totalAuditPages = ceil($totalAuditRows / $audit_per_page);

// Get recent audit logs con paginación
$recentLogs = [];
try {
    $query = "SELECT a.*, u.nombre_completo as admin_name 
                FROM auditoria a
                LEFT JOIN usuarios u ON a.usuario_id = u.id
                ORDER BY a.created_at DESC 
                LIMIT :offset, :per_page";
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':offset', $audit_offset, PDO::PARAM_INT);
    $stmt->bindParam(':per_page', $audit_per_page, PDO::PARAM_INT);
    $stmt->execute();
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentLogs = [];
}

// Get sales by membership tier
$query = "SELECT m.name as tier, COUNT(p.id) as sales_count, COALESCE(SUM(p.amount), 0) as total_revenue
            FROM memberships m
            LEFT JOIN payments p ON p.membership_id = m.id AND p.status = 'completed'
            GROUP BY m.id";
$salesByTier = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Dashboard Admin - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ============================================
           VARIABLES DE TEMA CLARO (por defecto)
           ============================================ */
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

        /* CONTENEDOR PRINCIPAL CON FLEX (como users.php) */
        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* SIDEBAR - OCUPA SU ANCHO FIJO */
        .sidebar-column {
            flex-shrink: 0;
        }

        /* CONTENIDO PRINCIPAL (como users.php) - MEJORADO PARA MÓVILES */
        .admin-main {
            flex: 1;
            width: 100%;
            padding: 20px 25px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow-x: visible;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
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

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 8px 18px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
        }

        .btn-sm {
            padding: 5px 12px;
            font-size: 0.75rem;
        }

        .btn-outline-primary {
            background: transparent;
            border: 1px solid #c8a86b;
            color: #c8a86b;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-primary:hover {
            background: #c8a86b;
            color: white;
        }

        /* Stats grid - más compacto */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: transform 0.3s;
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.3rem;
        }

        .bg-primary { background: linear-gradient(135deg, #1a5276, #2980b9); }
        .bg-success { background: linear-gradient(135deg, #1f8a4c, #27ae60); }
        .bg-info { background: linear-gradient(135deg, #0e6b8c, #17a2b8); }
        .bg-warning { background: linear-gradient(135deg, #d4a017, #f39c12); }

        .stat-info h3 {
            font-size: 1.5rem;
            margin: 0;
            color: var(--text-primary);
        }

        .stat-info p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .stat-info small {
            font-size: 0.7rem;
        }

        .text-success {
            color: #27ae60 !important;
        }

        .text-warning {
            color: #f39c12 !important;
        }

        /* Charts */
        .charts-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .chart-card, .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border: 1px solid var(--border-color);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header h3 {
            font-size: 1rem;
            margin: 0;
            color: var(--text-primary);
        }

        canvas {
            max-height: 250px;
            width: 100%;
        }

        /* Tables - MEJORADO PARA SCROLL EN MÓVILES */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            margin-bottom: 15px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 600px;
        }

        .admin-table th, .admin-table td {
            padding: 10px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .admin-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            font-size: 0.8rem;
            color: var(--text-primary);
        }

        .admin-table tr:hover {
            background: var(--table-hover);
        }

        .badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .badge-gold {
            background: linear-gradient(135deg, #d4af37, #c8a86b);
            color: #1a1a2e;
        }

        .badge-primary {
            background: #2980b9;
            color: white;
        }

        .badge-info {
            background: #17a2b8;
            color: white;
        }

        .badge-secondary {
            background: #95a5a6;
            color: white;
        }

        .badge-warning {
            background: #f39c12;
            color: white;
        }

        .badge-danger {
            background: #e74c3c;
            color: white;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            white-space: nowrap;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive, .status-suspended {
            background: #f8d7da;
            color: #721c24;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.3s;
            font-size: 0.9rem;
        }

        .btn-icon.edit { color: #3498db; }
        .btn-icon.suspend { color: #f39c12; }
        .btn-icon.delete { color: #e74c3c; }

        .btn-icon:hover {
            transform: scale(1.1);
            background: rgba(0,0,0,0.05);
        }

        /* Audit timeline */
        .audit-timeline {
            width: 100%;
        }

        .audit-item {
            display: flex;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .audit-item:last-child {
            border-bottom: none;
        }

        .audit-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #c8a86b;
            font-size: 0.8rem;
            flex-shrink: 0;
        }

        .audit-content {
            flex: 1;
        }

        .audit-header {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 3px;
            font-size: 0.8rem;
        }

        .audit-action {
            color: #c8a86b;
            font-weight: 500;
        }

        .audit-time {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }

        /* Estilos para la paginación (como users.php) */
        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid var(--border-color);
        }

        .per-page-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .per-page-selector label {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin: 0;
        }

        .per-page-selector select {
            padding: 5px 10px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .pagination {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin: 0;
        }

        .pagination .page-item {
            list-style: none;
        }

        .pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.75rem;
            transition: all 0.3s;
            background: var(--card-bg);
        }

        .pagination .page-link:hover {
            background: #c8a86b;
            border-color: #c8a86b;
            color: white;
        }

        .pagination .active .page-link {
            background: #c8a86b;
            border-color: #c8a86b;
            color: white;
        }

        .pagination .disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .audit-section {
            margin-top: 30px;
        }

        /* Estilos para buscadores */
        .search-input {
            padding: 6px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.75rem;
            width: 250px;
            transition: all 0.3s;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .search-input:focus {
            outline: none;
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
        }

        .table-container {
            position: relative;
        }

        .no-results {
            text-align: center;
            padding: 30px;
            color: var(--text-secondary);
            font-size: 0.85rem;
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

        /* ============================================
           RESPONSIVE: MEJORADO PARA MÓVILES
           ============================================ */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
        }

        @media (max-width: 992px) {
            .admin-main {
                padding: 20px 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
            }
            
            .charts-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .pagination-container {
                flex-direction: column;
                align-items: center;
            }

            .search-input {
                width: 100%;
                max-width: 300px;
            }
        }

        @media (max-width: 768px) {
            .admin-main {
                padding: 15px 15px;
            }
            
            .admin-header {
                flex-direction: column;
                text-align: center;
                padding: 15px;
            }
            
            .header-title h1 {
                font-size: 1.2rem;
            }
            
            .stat-card {
                padding: 12px;
            }
            
            .stat-icon {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
            
            .stat-info h3 {
                font-size: 1.2rem;
            }
            
            .admin-table th, 
            .admin-table td {
                padding: 8px 6px;
                font-size: 0.7rem;
            }
            
            .action-buttons {
                gap: 3px;
            }
            
            .btn-icon {
                padding: 3px;
                font-size: 0.8rem;
            }
            
            .pagination .page-link {
                min-width: 28px;
                height: 28px;
                font-size: 0.7rem;
            }
            
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-header > div {
                width: 100%;
            }
            
            .search-input {
                width: 100%;
                max-width: none;
            }
        }

        @media (max-width: 576px) {
            .admin-main {
                padding: 12px 12px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .stat-card {
                padding: 10px;
            }
            
            .stat-icon {
                width: 35px;
                height: 35px;
                font-size: 0.9rem;
            }
            
            .stat-info h3 {
                font-size: 1.1rem;
            }
            
            .stat-info p, .stat-info small {
                font-size: 0.7rem;
            }
            
            .admin-table {
                min-width: 550px;
            }
            
            .admin-table th, 
            .admin-table td {
                padding: 6px 4px;
                font-size: 0.65rem;
            }
            
            .badge, .status-badge {
                padding: 2px 6px;
                font-size: 0.6rem;
            }
            
            .per-page-selector select {
                padding: 4px 8px;
                font-size: 0.7rem;
            }
            
            .pagination .page-link {
                min-width: 24px;
                height: 24px;
                font-size: 0.65rem;
                padding: 0 6px;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <!-- Sidebar Column (como users.php) ojo maricones hay uno de ustedes que borra la barra cada rato-->
    <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>
    
    <!-- Main Content (como users.php) -->
    <main class="admin-main">
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p>Bienvenido, <?php echo htmlspecialchars($admin['full_name']); ?></p>
            </div>
            <div class="header-actions">
                <button class="btn-primary" id="refreshData">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-primary">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_users']); ?></h3>
                    <p>Usuarios Totales</p>
                    <small class="text-success">
                        <i class="fas fa-arrow-up"></i> +<?php echo $stats['new_users_this_month']; ?> este mes
                    </small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-success">
                    <i class="fas fa-tags"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['total_publications']); ?></h3>
                    <p>Publicaciones Activas</p>
                    <small class="text-warning">
                        <i class="fas fa-clock"></i> <?php echo $stats['pending_reviews']; ?> por revisar
                    </small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-info">
                    <i class="fas fa-crown"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['active_subscriptions']); ?></h3>
                    <p>Suscripciones Activas</p>
                    <small>Membresías vigentes</small>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-warning">
                    <i class="fas fa-dollar-sign"></i>
                </div>
                <div class="stat-info">
                    <h3>$ <?php echo number_format($stats['revenue_this_month'], 0, ',', '.'); ?></h3>
                    <p>Ingresos del Mes</p>
                    <small>En facturas completadas</small>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="charts-row">
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-pie"></i> Usuarios por Rol</h3>
                </div>
                <div class="card-body">
                    <canvas id="usersByRoleChart"></canvas>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Ventas por Membresía</h3>
                </div>
                <div class="card-body">
                    <canvas id="salesByTierChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Users Table CON PAGINACIÓN Y BUSCADOR -->
        <div class="data-card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus"></i> Usuarios Recientes</h3>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="text" id="searchUsersInput" class="search-input" placeholder="🔍 Buscar por nombre, email o rol...">
                    <a href="users.php" class="btn-outline-primary">Ver todos</a>
                </div>
            </div>
            <div class="card-body table-responsive">
                <div class="table-container">
                    <table class="admin-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Membresía</th>
                                <th>Registro</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <?php foreach ($recentUsers as $user): ?>
                            <tr data-search="<?php echo strtolower($user['full_name'] . ' ' . $user['email'] . ' ' . $user['role']); ?>">
                                <td>#<?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $user['role'] === 'superadmin' ? 'danger' : 
                                            ($user['role'] === 'ingeniero' ? 'warning' : 
                                            ($user['role'] === 'contador' ? 'info' : 
                                            ($user['role'] === 'tecnico' ? 'primary' : 'secondary'))); 
                                    ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $user['membership_tier'] === 'elite' ? 'gold' : 
                                            ($user['membership_tier'] === 'premium' ? 'primary' : 
                                            ($user['membership_tier'] === 'pro' ? 'info' : 'secondary')); 
                                    ?>">
                                        <?php echo ucfirst($user['membership_tier']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $user['status'] ? 'active' : 'inactive'; ?>">
                                        <?php echo $user['status'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn-icon edit" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button class="btn-icon suspend" onclick="toggleUserStatus(<?php echo $user['id']; ?>, <?php echo $user['status']; ?>)" title="Cambiar estado">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                        <?php if ($admin['role'] === 'superadmin' && $user['role'] !== 'superadmin'): ?>
                                        <button class="btn-icon delete" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div id="usersNoResults" class="no-results" style="display: none;">No se encontraron usuarios</div>
                </div>
            </div>
            
            <!-- PAGINACIÓN PARA USUARIOS -->
            <div class="pagination-container" id="usersPagination">
                <div class="per-page-selector">
                    <label>Mostrar:</label>
                    <select id="userPerPageSelect" onchange="changeUserPerPage()">
                        <option value="10" <?php echo $user_per_page == 10 ? 'selected' : ''; ?>>10 registros</option>
                        <option value="25" <?php echo $user_per_page == 25 ? 'selected' : ''; ?>>25 registros</option>
                        <option value="50" <?php echo $user_per_page == 50 ? 'selected' : ''; ?>>50 registros</option>
                        <option value="100" <?php echo $user_per_page == 100 ? 'selected' : ''; ?>>100 registros</option>
                        <option value="200" <?php echo $user_per_page == 200 ? 'selected' : ''; ?>>200 registros</option>
                        <option value="500" <?php echo $user_per_page == 500 ? 'selected' : ''; ?>>500 registros</option>
                    </select>
                </div>
                
                <ul class="pagination">
                    <li class="page-item <?php echo $user_page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?user_page=<?php echo $user_page - 1; ?>&user_per_page=<?php echo $user_per_page; ?>&audit_page=<?php echo $audit_page; ?>&audit_per_page=<?php echo $audit_per_page; ?>" aria-label="Anterior">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    
                    <?php
                    $start_user_page = max(1, $user_page - 2);
                    $end_user_page = min($totalUserPages, $user_page + 2);
                    
                    if ($start_user_page > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?user_page=1&user_per_page=' . $user_per_page . '&audit_page=' . $audit_page . '&audit_per_page=' . $audit_per_page . '">1</a></li>';
                        if ($start_user_page > 2) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                    }
                    
                    for ($i = $start_user_page; $i <= $end_user_page; $i++) {
                        $active = $i == $user_page ? 'active' : '';
                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="?user_page=' . $i . '&user_per_page=' . $user_per_page . '&audit_page=' . $audit_page . '&audit_per_page=' . $audit_per_page . '">' . $i . '</a></li>';
                    }
                    
                    if ($end_user_page < $totalUserPages) {
                        if ($end_user_page < $totalUserPages - 1) {
                            echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        echo '<li class="page-item"><a class="page-link" href="?user_page=' . $totalUserPages . '&user_per_page=' . $user_per_page . '&audit_page=' . $audit_page . '&audit_per_page=' . $audit_per_page . '">' . $totalUserPages . '</a></li>';
                    }
                    ?>
                    
                    <li class="page-item <?php echo $user_page >= $totalUserPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?user_page=<?php echo $user_page + 1; ?>&user_per_page=<?php echo $user_per_page; ?>&audit_page=<?php echo $audit_page; ?>&audit_per_page=<?php echo $audit_per_page; ?>" aria-label="Siguiente">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                
                <div class="info">
                    <small>Mostrando <?php echo count($recentUsers); ?> de <?php echo $totalUsersRows; ?> registros</small>
                </div>
            </div>
        </div>
        
        <!-- Recent Audit Logs CON PAGINACIÓN Y BUSCADOR -->
        <?php if (!empty($recentLogs)): ?>
        <div class="data-card audit-section">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Actividad Reciente</h3>
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="text" id="searchAuditInput" class="search-input" placeholder="🔍 Buscar por usuario, acción o tabla...">
                    <a href="audit.php" class="btn-outline-primary">Ver historial completo</a>
                </div>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <div class="audit-timeline" id="auditTimeline">
                        <?php foreach ($recentLogs as $log): ?>
                        <div class="audit-item" data-search="<?php echo strtolower(($log['admin_name'] ?? 'Sistema') . ' ' . ($log['accion'] ?? '') . ' ' . ($log['tabla_afectada'] ?? '')); ?>">
                            <div class="audit-icon">
                                <i class="fas fa-user-edit"></i>
                            </div>
                            <div class="audit-content">
                                <div class="audit-header">
                                    <strong><?php echo htmlspecialchars($log['admin_name'] ?? 'Sistema'); ?></strong>
                                    <span class="audit-action"><?php echo $log['accion'] ?? 'acción'; ?></span>
                                    <span class="audit-target"><?php echo ucfirst($log['tabla_afectada'] ?? ''); ?></span>
                                </div>
                                <div class="audit-time">
                                    <i class="far fa-clock"></i> <?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="auditNoResults" class="no-results" style="display: none;">No se encontraron registros de actividad</div>
                </div>
                
                <!-- PAGINACIÓN PARA AUDITORÍA -->
                <div class="pagination-container" id="auditPagination">
                    <div class="per-page-selector">
                        <label>Mostrar:</label>
                        <select id="auditPerPageSelect" onchange="changeAuditPerPage()">
                            <option value="10" <?php echo $audit_per_page == 10 ? 'selected' : ''; ?>>10 registros</option>
                            <option value="25" <?php echo $audit_per_page == 25 ? 'selected' : ''; ?>>25 registros</option>
                            <option value="50" <?php echo $audit_per_page == 50 ? 'selected' : ''; ?>>50 registros</option>
                            <option value="100" <?php echo $audit_per_page == 100 ? 'selected' : ''; ?>>100 registros</option>
                            <option value="200" <?php echo $audit_per_page == 200 ? 'selected' : ''; ?>>200 registros</option>
                            <option value="500" <?php echo $audit_per_page == 500 ? 'selected' : ''; ?>>500 registros</option>
                        </select>
                    </div>
                    
                    <ul class="pagination">
                        <li class="page-item <?php echo $audit_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?audit_page=<?php echo $audit_page - 1; ?>&audit_per_page=<?php echo $audit_per_page; ?>&user_page=<?php echo $user_page; ?>&user_per_page=<?php echo $user_per_page; ?>" aria-label="Anterior">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                        
                        <?php
                        $start_audit_page = max(1, $audit_page - 2);
                        $end_audit_page = min($totalAuditPages, $audit_page + 2);
                        
                        if ($start_audit_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?audit_page=1&audit_per_page=' . $audit_per_page . '&user_page=' . $user_page . '&user_per_page=' . $user_per_page . '">1</a></li>';
                            if ($start_audit_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start_audit_page; $i <= $end_audit_page; $i++) {
                            $active = $i == $audit_page ? 'active' : '';
                            echo '<li class="page-item ' . $active . '"><a class="page-link" href="?audit_page=' . $i . '&audit_per_page=' . $audit_per_page . '&user_page=' . $user_page . '&user_per_page=' . $user_per_page . '">' . $i . '</a></li>';
                        }
                        
                        if ($end_audit_page < $totalAuditPages) {
                            if ($end_audit_page < $totalAuditPages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?audit_page=' . $totalAuditPages . '&audit_per_page=' . $audit_per_page . '&user_page=' . $user_page . '&user_per_page=' . $user_per_page . '">' . $totalAuditPages . '</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo $audit_page >= $totalAuditPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?audit_page=<?php echo $audit_page + 1; ?>&audit_per_page=<?php echo $audit_per_page; ?>&user_page=<?php echo $user_page; ?>&user_per_page=<?php echo $user_per_page; ?>" aria-label="Siguiente">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                    
                    <div class="info">
                        <small>Mostrando <?php echo count($recentLogs); ?> de <?php echo $totalAuditRows; ?> registros</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Botón para cambiar tema claro/oscuro -->
<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<!-- Footer -->
<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Función para cambiar tema claro/oscuro
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
        
        // Redibujar gráficos después de cambiar el tema
        redrawCharts();
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

    // Variables globales para los gráficos
    let usersByRoleChartInstance = null;
    let salesByTierChartInstance = null;
    
    // Users by role chart
    const usersByRoleCtx = document.getElementById('usersByRoleChart').getContext('2d');
    const usersByRoleData = <?php 
        $roles = ['superadmin' => 0, 'ingeniero' => 0, 'contador' => 0, 'tecnico' => 0, 'asesor' => 0, 'usuario' => 0];
        foreach ($stats['users_by_role'] as $roleStat) {
            $roleKey = strtolower($roleStat['role']);
            if (isset($roles[$roleKey])) $roles[$roleKey] = $roleStat['count'];
            else $roles['usuario'] += $roleStat['count'];
        }
        echo json_encode(array_values($roles));
    ?>;
    
    function initUsersByRoleChart() {
        if (usersByRoleChartInstance) {
            usersByRoleChartInstance.destroy();
        }
        usersByRoleChartInstance = new Chart(usersByRoleCtx, {
            type: 'pie',
            data: {
                labels: ['Superadmin', 'Ingeniero', 'Contador', 'Técnico', 'Asesor', 'Usuario'],
                datasets: [{
                    data: usersByRoleData,
                    backgroundColor: ['#e17055', '#00cec9', '#0984e3', '#fdcb6e', '#6c5ce7', '#a29bfe'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 9 } }
                    }
                }
            }
        });
    }
    
    // Sales by tier chart
    const salesByTierCtx = document.getElementById('salesByTierChart').getContext('2d');
    const salesByTierData = <?php echo json_encode($salesByTier); ?>;
    
    function initSalesByTierChart() {
        if (salesByTierChartInstance) {
            salesByTierChartInstance.destroy();
        }
        salesByTierChartInstance = new Chart(salesByTierCtx, {
            type: 'bar',
            data: {
                labels: salesByTierData.map(item => item.tier || 'N/A'),
                datasets: [{
                    label: 'Ventas',
                    data: salesByTierData.map(item => item.sales_count),
                    backgroundColor: '#0984e3',
                    borderRadius: 6
                }, {
                    label: 'Ingresos (COP)',
                    data: salesByTierData.map(item => item.total_revenue),
                    backgroundColor: '#00b894',
                    borderRadius: 6,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Número de ventas',
                            font: { size: 10 }
                        }
                    },
                    y1: {
                        position: 'right',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Ingresos (COP)',
                            font: { size: 10 }
                        },
                        ticks: {
                            callback: function(value) {
                                return '$ ' + value.toLocaleString('es-CO');
                            }
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: { font: { size: 9 } }
                    }
                }
            }
        });
    }
    
    // Función para redibujar los gráficos
    function redrawCharts() {
        setTimeout(function() {
            if (usersByRoleChartInstance) {
                usersByRoleChartInstance.destroy();
                initUsersByRoleChart();
            }
            if (salesByTierChartInstance) {
                salesByTierChartInstance.destroy();
                initSalesByTierChart();
            }
        }, 400);
    }
    
    // Función para filtrar usuarios
    function filterUsers() {
        const searchTerm = document.getElementById('searchUsersInput').value.toLowerCase();
        const rows = document.querySelectorAll('#usersTableBody tr');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const searchData = row.getAttribute('data-search') || '';
            if (searchData.includes(searchTerm) || searchTerm === '') {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const noResultsDiv = document.getElementById('usersNoResults');
        if (visibleCount === 0) {
            noResultsDiv.style.display = 'block';
        } else {
            noResultsDiv.style.display = 'none';
        }
    }
    
    // Función para filtrar auditoría
    function filterAudit() {
        const searchTerm = document.getElementById('searchAuditInput').value.toLowerCase();
        const items = document.querySelectorAll('#auditTimeline .audit-item');
        let visibleCount = 0;
        
        items.forEach(item => {
            const searchData = item.getAttribute('data-search') || '';
            if (searchData.includes(searchTerm) || searchTerm === '') {
                item.style.display = '';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        
        const noResultsDiv = document.getElementById('auditNoResults');
        if (visibleCount === 0) {
            noResultsDiv.style.display = 'block';
        } else {
            noResultsDiv.style.display = 'none';
        }
    }
    
    // Inicializar gráficos
    initUsersByRoleChart();
    initSalesByTierChart();
    
    // Event listeners para buscadores
    document.getElementById('searchUsersInput').addEventListener('keyup', filterUsers);
    document.getElementById('searchAuditInput').addEventListener('keyup', filterAudit);
    
    // Refresh data
    $('#refreshData').click(function() {
        location.reload();
    });
    
    // Change per page for users
    function changeUserPerPage() {
        const perPage = document.getElementById('userPerPageSelect').value;
        window.location.href = '?user_page=1&user_per_page=' + perPage + '&audit_page=<?php echo $audit_page; ?>&audit_per_page=<?php echo $audit_per_page; ?>';
    }
    
    // Change per page for audit
    function changeAuditPerPage() {
        const perPage = document.getElementById('auditPerPageSelect').value;
        window.location.href = '?audit_page=1&audit_per_page=' + perPage + '&user_page=<?php echo $user_page; ?>&user_per_page=<?php echo $user_per_page; ?>';
    }
    
    // Escuchar cambios de tamaño del sidebar para redibujar gráficos
    const sidebarObserver = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.attributeName === 'class') {
                redrawCharts();
            }
        });
    });
    
    const sidebarColumn = document.querySelector('.sidebar-column');
    if (sidebarColumn) {
        sidebarObserver.observe(sidebarColumn, { attributes: true });
    }
    
    // Escuchar cambios de tamaño de ventana
    window.addEventListener('resize', function() {
        redrawCharts();
    });
    
    // Toggle user status function
    function toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus == 1 ? 0 : 1;
        const action = newStatus == 1 ? 'activar' : 'desactivar';
        
        if (confirm(`¿Estás seguro de ${action} este usuario?`)) {
            $.ajax({
                url: '/easycarluxury/api/v1/admin.php',
                method: 'PUT',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('auth_token'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    action: 'toggle_user_status',
                    user_id: userId,
                    active: newStatus
                }),
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al cambiar el estado del usuario');
                }
            });
        }
    }
    
    // Delete user function
    function deleteUser(userId) {
        if (confirm('⚠️ ADVERTENCIA: Esta acción eliminará permanentemente al usuario y todos sus datos. ¿Estás seguro?')) {
            $.ajax({
                url: '/easycarluxury/api/v1/admin.php',
                method: 'DELETE',
                headers: {
                    'Authorization': 'Bearer ' + localStorage.getItem('auth_token'),
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    action: 'delete_user',
                    user_id: userId
                }),
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error al eliminar el usuario');
                }
            });
        }
    }
</script>
</body>
</html>