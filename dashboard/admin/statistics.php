<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\statistics.php
 * ESTADÍSTICAS GLOBALES - Panel de Administración
 * MODIFICADO: Usa CDN en lugar de archivos locales
 * MODIFICADO: Eliminados scripts duplicados (jQuery y Bootstrap ya están en sidebar)
 * MODIFICADO: Rutas absolutas corregidas (sin /easycarluxury/)
 * MODIFICADO: Añadido tema claro/oscuro (como en categorias.php)
 * MODIFICADO: En modo oscuro, líneas de diagramas y textos de tablas en blanco
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/audit-log.php';

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

// ============================================
// ASIGNAR VARIABLES PARA EL SIDEBAR
// ============================================
$admin['full_name'] = $admin['full_name'] ?? $_SESSION['admin_name'] ?? $admin['nombre_completo'] ?? 'Administrador';
$admin['role'] = $admin['role'] ?? $_SESSION['admin_role'] ?? 'admin';

$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];
$_SESSION['nombre_completo'] = $admin['full_name'];
$_SESSION['role'] = $admin['role'];

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

$audit = new AuditLog($pdo, $admin['id'], $admin['email'], $admin['role']);
$audit->registerPageView('/dashboard/admin/statistics.php', 'Visualización de estadísticas globales');

$period = $_GET['period'] ?? 'month';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

if (!in_array($period, ['day', 'week', 'month', 'year'])) {
    $period = 'month';
}

$available_years = [2024, 2025, 2026, 2027, 2028, 2029, 2030];

if (!in_array($year, $available_years)) {
    $year = date('Y');
}

$months = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

switch ($period) {
    case 'day':
        $dateCondition = "DATE(created_at) = CURDATE()";
        $groupBy = "HOUR(created_at)";
        $label = "Hora";
        break;
    case 'week':
        $dateCondition = "YEARWEEK(created_at) = YEARWEEK(CURDATE())";
        $groupBy = "DATE(created_at)";
        $label = "Día";
        break;
    case 'month':
        $dateCondition = "MONTH(created_at) = $month AND YEAR(created_at) = $year";
        $groupBy = "DAY(created_at)";
        $label = "Día";
        break;
    case 'year':
        $dateCondition = "YEAR(created_at) = $year";
        $groupBy = "MONTH(created_at)";
        $label = "Mes";
        break;
    default:
        $dateCondition = "MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
        $groupBy = "DAY(created_at)";
}

// ============================================
// CONSULTAS PARA ESTADÍSTICAS - VERSIÓN CORREGIDA
// ============================================

$query = "SELECT 
            DATE_FORMAT(u.created_at, '%Y-%m') as period,
            DATE_FORMAT(u.created_at, '%b %Y') as period_label,
            u.tipo_cuenta as membership_type,
            COUNT(*) as count
            FROM usuarios u
            WHERE u.activo IS NOT NULL
            GROUP BY period, period_label, u.tipo_cuenta
            ORDER BY period ASC, 
                FIELD(u.tipo_cuenta, 'free', 'pro', 'premium', 'elite')";
$usersByMembership = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$groupedChartData = [];
$periods = [];
$membershipTypes = ['free', 'pro', 'premium', 'elite'];
$membershipLabels = ['Free', 'Pro', 'Premium', 'Elite'];
$membershipColors = ['rgb(52, 152, 219)', 'rgb(46, 204, 113)', 'rgb(241, 196, 15)', 'rgb(231, 76, 60)'];

foreach ($usersByMembership as $row) {
    if (!in_array($row['period_label'], $periods)) {
        $periods[] = $row['period_label'];
    }
}

foreach ($periods as $periodName) {
    foreach ($membershipTypes as $type) {
        $groupedChartData[$periodName][$type] = 0;
    }
}

foreach ($usersByMembership as $row) {
    if (isset($groupedChartData[$row['period_label']][$row['membership_type']])) {
        $groupedChartData[$row['period_label']][$row['membership_type']] = (int)$row['count'];
    }
}

$datasets = [];
foreach ($membershipTypes as $index => $type) {
    $data = [];
    foreach ($periods as $periodName) {
        $data[] = $groupedChartData[$periodName][$type];
    }
    $datasets[] = [
        'label' => $membershipLabels[$index],
        'data' => $data,
        'backgroundColor' => $membershipColors[$index],
        'borderColor' => $membershipColors[$index],
        'borderWidth' => 1
    ];
}

$membershipCosts = [
    'free' => 20000,
    'pro' => 75000,
    'premium' => 150000,
    'elite' => 300000
];

$membershipSummary = [];
$totalUsers = 0;
$totalRevenue = 0;

foreach ($membershipCosts as $type => $cost) {
    $query_count = "SELECT COUNT(*) as count FROM usuarios WHERE tipo_cuenta = :type AND activo = 1";
    $stmt = $pdo->prepare($query_count);
    $stmt->execute([':type' => $type]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $total = $count * $cost;
    
    $membershipSummary[] = [
        'type' => ucfirst($type),
        'users' => $count,
        'cost' => $cost,
        'total' => $total
    ];
    
    $totalUsers += $count;
    $totalRevenue += $total;
}

$selectedYear = $year;
$monthlyRevenue = array_fill(1, 12, 0);

try {
    $query = "SELECT MONTH(payment_date) as month_num, SUM(amount) as total 
              FROM payments 
              WHERE YEAR(payment_date) = :year AND status = 'completed'
              GROUP BY MONTH(payment_date)
              ORDER BY month_num";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':year' => $selectedYear]);
    $revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($revenueData as $row) {
        $monthlyRevenue[(int)$row['month_num']] = (float)$row['total'];
    }
} catch (Exception $e) {
    error_log("Error en consulta de ingresos: " . $e->getMessage());
}

$revenueMonths = array_values($months);
$revenueValues = array_values($monthlyRevenue);
$maxRevenue = max($revenueValues);
$minY = 75000;
$maxY = max($maxRevenue, $minY + 10000);

$publications = [];
try {
    $query = "SELECT DATE_FORMAT(created_at, '$groupBy') as period, COUNT(*) as count 
                FROM publicaciones 
                WHERE $dateCondition AND status = 'active'
                GROUP BY period ORDER BY period";
    $publications = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error en publicaciones: " . $e->getMessage());
}

$topTiers = [];
try {
    $query = "SELECT m.name, COUNT(p.id) as sales, SUM(p.amount) as revenue
                FROM payments p
                JOIN memberships m ON p.membership_id = m.id
                WHERE p.status = 'completed'
                GROUP BY m.id
                ORDER BY revenue DESC";
    $topTiers = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error en top tiers: " . $e->getMessage());
}

// ============================================
// CONSULTA CORREGIDA - Crecimiento Acumulado
// ============================================
$query = "SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as new_users,
            (SELECT COUNT(*) FROM usuarios u2 WHERE DATE_FORMAT(u2.created_at, '%Y-%m') <= month AND u2.activo IS NOT NULL) as cumulative
            FROM usuarios u1
            WHERE u1.activo IS NOT NULL
            GROUP BY month
            ORDER BY month ASC";
$userGrowth = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT 
            DATE(created_at) as date,
            SUM(CASE WHEN table_name = 'usuarios' THEN 1 ELSE 0 END) as new_users,
            SUM(CASE WHEN table_name = 'publicaciones' THEN 1 ELSE 0 END) as new_publications,
            SUM(CASE WHEN table_name = 'payments' THEN 1 ELSE 0 END) as payments
            FROM (
            SELECT created_at, 'usuarios' as table_name FROM usuarios WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL
            SELECT created_at, 'publicaciones' FROM publicaciones WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL
            SELECT payment_date as created_at, 'payments' FROM payments WHERE payment_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND status = 'completed'
            ) as activity
            GROUP BY DATE(created_at)
            ORDER BY date DESC
            LIMIT 10";
$activity = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

if (empty($activity)) {
    $query = "SELECT 
                DATE(created_at) as date,
                SUM(CASE WHEN table_name = 'usuarios' THEN 1 ELSE 0 END) as new_users,
                SUM(CASE WHEN table_name = 'publicaciones' THEN 1 ELSE 0 END) as new_publications,
                SUM(CASE WHEN table_name = 'payments' THEN 1 ELSE 0 END) as payments
                FROM (
                SELECT created_at, 'usuarios' as table_name FROM usuarios
                UNION ALL
                SELECT created_at, 'publicaciones' FROM publicaciones
                UNION ALL
                SELECT payment_date as created_at, 'payments' FROM payments WHERE status = 'completed'
                ) as activity
                GROUP BY DATE(created_at)
                ORDER BY date DESC
                LIMIT 10";
    $activity = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Estadísticas Globales - Easy Car Luxury</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS - CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Chart.js - CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
            --chart-text-color: #1a1a2e;
            --chart-grid-color: rgba(0, 0, 0, 0.1);
            --chart-ticks-color: #1a1a2e;
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
            --chart-text-color: #ffffff;
            --chart-grid-color: rgba(255, 255, 255, 0.1);
            --chart-ticks-color: #ffffff;
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

        .filters-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .filters-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }

        .filter-group select {
            padding: 8px 15px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 0.85rem;
            background: var(--input-bg);
            color: var(--text-primary);
            cursor: pointer;
            min-width: 160px;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
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
            font-weight: 500;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
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

        .btn-info {
            background: #17a2b8;
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

        .charts-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .chart-card .card-header {
            padding: 15px 20px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .chart-card .card-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .chart-card .card-header h3 i {
            color: #c8a86b;
            margin-right: 8px;
        }

        .chart-card .card-body {
            padding: 20px;
        }

        /* Estilos para los gráficos en modo oscuro */
        [data-theme="dark"] canvas {
            filter: brightness(0.9);
        }
        
        /* Forzar colores de texto en gráficos Chart.js */
        [data-theme="dark"] .chartjs-render-monitor {
            color: var(--chart-text-color) !important;
        }

        .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-top: 20px;
            border: 1px solid var(--border-color);
        }

        .data-card .card-header {
            padding: 15px 20px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .data-card .card-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .data-card .card-header h3 i {
            color: #c8a86b;
            margin-right: 8px;
        }

        .data-card .card-body {
            padding: 20px;
        }

        .summary-table-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            margin-top: 20px;
            border: 1px solid var(--border-color);
        }

        .summary-table-container .card-header {
            padding: 15px 20px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .summary-table-container .card-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--text-primary);
        }

        .summary-table-container .card-header h3 i {
            color: #c8a86b;
            margin-right: 8px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table th,
        .summary-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.85rem;
            color: var(--text-primary);
        }

        .summary-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
        }

        .summary-table tr:hover {
            background: var(--table-hover);
        }

        .text-right {
            text-align: right;
        }

        .font-bold {
            font-weight: bold;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        canvas {
            max-height: 350px;
            width: 100%;
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

        /* SweetAlert2 en modo oscuro */
        [data-theme="dark"] .swal2-popup {
            background: #16213e;
            color: #ffffff;
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        /* Modal en modo oscuro (por si se necesita) */
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

        /* Responsive */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px;
            }
            .charts-row {
                grid-template-columns: 1fr;
            }
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group select {
                width: 100%;
            }
            .summary-table-container .card-header {
                flex-direction: column;
                align-items: stretch;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .summary-table {
                font-size: 0.75rem;
            }
            .summary-table th,
            .summary-table td {
                padding: 8px 10px;
            }
            .header-actions .btn {
                font-size: 0.7rem;
                padding: 4px 10px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
            <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

        <main class="admin-main">
            <div class="admin-header">
                <div class="header-title">
                    <h1><i class="fas fa-chart-line"></i> Estadísticas Globales</h1>
                    <p>Métricas y análisis de la plataforma desde el inicio de operaciones</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-success" id="exportUsersBtn"><i class="fas fa-users"></i> Usuarios</button>
                    <button class="btn btn-success" id="exportPaymentsBtn"><i class="fas fa-credit-card"></i> Pagos</button>
                    <button class="btn btn-success" id="exportPublicationsBtn"><i class="fas fa-newspaper"></i> Publicaciones</button>
                    <button class="btn btn-info" id="generatePDFBtn"><i class="fas fa-file-pdf"></i> Reporte PDF</button>
                </div>
            </div>
            
            <div class="filters-container">
                <form method="GET" class="filters-form" id="filterForm">
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-alt"></i> Período</label>
                        <select name="period" class="form-select">
                            <option value="">Seleccione un período</option>
                            <option value="day" <?php echo $period === 'day' ? 'selected' : ''; ?>>Último día</option>
                            <option value="week" <?php echo $period === 'week' ? 'selected' : ''; ?>>Última semana</option>
                            <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Este mes</option>
                            <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Este año</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Año</label>
                        <select name="year" class="form-select">
                            <option value="">Seleccione un año</option>
                            <?php foreach ($available_years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-week"></i> Mes</label>
                        <select name="month" class="form-select">
                            <option value="">Seleccione un mes</option>
                            <?php foreach ($months as $num => $name): ?>
                            <option value="<?php echo $num; ?>" <?php echo $month == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-chart-line"></i> Aplicar filtros</button>
                    </div>
                </form>
            </div>
            
            <div class="charts-row">
                <div class="chart-card">
                    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Registros de Usuarios por Membresía</h3></div>
                    <div class="card-body"><canvas id="registrationsGroupedChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="card-header"><h3><i class="fas fa-dollar-sign"></i> Ingresos (COP)</h3></div>
                    <div class="card-body"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
            
            <div class="charts-row">
                <div class="chart-card">
                    <div class="card-header"><h3><i class="fas fa-chart-pie"></i> Ventas por Membresía</h3></div>
                    <div class="card-body"><canvas id="topTiersChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="card-header"><h3><i class="fas fa-chart-line"></i> Crecimiento Acumulado</h3></div>
                    <div class="card-body"><canvas id="growthChart"></canvas></div>
                </div>
            </div>
            
            <div class="data-card">
                <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Actividad de la Plataforma</h3></div>
                <div class="card-body"><canvas id="activityChart"></canvas></div>
            </div>
            
            <div class="summary-table-container">
                <div class="card-header">
                    <h3><i class="fas fa-table"></i> Resumen de Membresías</h3>
                    <div class="btn-group">
                        <button class="btn btn-success btn-sm" id="exportSummaryCsvBtn"><i class="fas fa-file-csv"></i> Exportar CSV</button>
                        <button class="btn btn-success btn-sm" id="exportSummaryExcelBtn"><i class="fas fa-file-excel"></i> Exportar Excel</button>
                        <button class="btn btn-danger btn-sm" id="exportSummaryPdfBtn"><i class="fas fa-file-pdf"></i> Exportar PDF</button>
                    </div>
                </div>
                <div class="card-body" style="padding: 0; overflow-x: auto;">
                    <table class="summary-table" id="membershipSummaryTable">
                        <thead>
                            <tr><th>Membresía</th><th class="text-right">Usuarios</th><th class="text-right">Costo por Usuario</th><th class="text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($membershipSummary as $item): ?>
                            <tr><td><?php echo $item['type']; ?></td><td class="text-right"><?php echo number_format($item['users']); ?></td><td class="text-right">$ <?php echo number_format($item['cost'], 0, ',', '.'); ?></td><td class="text-right">$ <?php echo number_format($item['total'], 0, ',', '.'); ?></td></tr>
                            <?php endforeach; ?>
                            <tr class="font-bold" style="background: var(--table-hover);"><td>Totales</div><td class="text-right"><?php echo number_format($totalUsers); ?></td><td class="text-right"></td><td class="text-right">$ <?php echo number_format($totalRevenue, 0, ',', '.'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Botón tema claro/oscuro -->
    <button class="btn-theme" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>
    
    <?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>
    
    <!-- jQuery, Bootstrap y SweetAlert2 JS - CDN -->
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
            
            // Reaplicar configuraciones de los gráficos después de cambiar el tema
            setTimeout(function() {
                location.reload();
            }, 100);
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

        if (typeof Chart === 'undefined') {
            console.error('Chart.js no se cargó correctamente');
            document.querySelectorAll('.chart-card .card-body, .data-card .card-body').forEach(function(el) {
                if (el.querySelector('canvas')) {
                    el.innerHTML = '<div class="alert alert-danger text-center">Error: Chart.js no se cargó correctamente. Verifica la conexión a Internet.</div>';
                }
            });
        } else {
            const periods = <?php echo json_encode($periods); ?>;
            const datasets = <?php echo json_encode($datasets); ?>;
            const isDarkMode = document.documentElement.getAttribute('data-theme') === 'dark';
            const textColor = isDarkMode ? '#ffffff' : '#1a1a2e';
            const gridColor = isDarkMode ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
            
            if (periods.length > 0) {
                const groupedCtx = document.getElementById('registrationsGroupedChart').getContext('2d');
                new Chart(groupedCtx, {
                    type: 'bar',
                    data: { labels: periods, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        plugins: {
                            legend: { position: 'top', labels: { font: { size: 10, color: textColor }, boxWidth: 12, padding: 8, color: textColor } },
                            tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + context.parsed.y + ' usuarios'; } } }
                        },
                        scales: {
                            x: { title: { display: true, text: 'Mes', font: { size: 11, color: textColor }, color: textColor }, ticks: { font: { size: 9, color: textColor } }, grid: { color: gridColor } },
                            y: { title: { display: true, text: 'Cantidad de usuarios', font: { size: 11, color: textColor } }, beginAtZero: true, ticks: { font: { size: 9, color: textColor } }, grid: { color: gridColor } }
                        }
                    }
                });
            } else {
                const container = document.getElementById('registrationsGroupedChart').parentNode;
                document.getElementById('registrationsGroupedChart').style.display = 'none';
                container.innerHTML = '<div class="text-center text-muted p-4">No hay datos de usuarios disponibles</div>';
            }
            
            const revenueMonths = <?php echo json_encode($revenueMonths); ?>;
            const revenueValues = <?php echo json_encode($revenueValues); ?>;
            const minY = <?php echo $minY; ?>;
            const maxY = <?php echo $maxY; ?>;
            
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'bar',
                data: {
                    labels: revenueMonths,
                    datasets: [{
                        label: 'Ingresos (COP) - Año <?php echo $selectedYear; ?>',
                        data: revenueValues,
                        backgroundColor: 'rgba(155, 89, 182, 0.8)',
                        borderColor: 'rgb(155, 89, 182)',
                        borderWidth: 1,
                        borderRadius: 8,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        tooltip: { callbacks: { label: function(context) { let value = context.parsed.y; return value === 0 ? 'Sin ingresos registrados' : 'Ingresos: $ ' + value.toLocaleString('es-CO'); } } },
                        legend: { position: 'top', labels: { font: { size: 11, color: textColor } } }
                    },
                    scales: {
                        y: {
                            min: minY,
                            suggestedMax: maxY,
                            title: { display: true, text: 'Ingresos en Pesos Colombianos (COP)', font: { size: 11, weight: 'bold', color: textColor } },
                            ticks: { callback: function(value) { return '$ ' + value.toLocaleString('es-CO'); }, stepSize: 50000, color: textColor },
                            grid: { drawBorder: true, color: gridColor }
                        },
                        x: {
                            title: { display: true, text: 'Mes del año', font: { size: 11, weight: 'bold', color: textColor } },
                            ticks: { font: { size: 10, rotation: 0, color: textColor } },
                            grid: { display: false }
                        }
                    },
                    layout: { padding: { top: 20, bottom: 10, left: 10, right: 10 } },
                    animation: { duration: 1000, easing: 'easeOutQuart' },
                    interaction: { mode: 'index', intersect: false }
                }
            });
            
            <?php if (count($topTiers) > 0): ?>
            const topTiersLabels = <?php echo json_encode(array_column($topTiers, 'name')); ?>;
            const topTiersData = <?php echo json_encode(array_column($topTiers, 'revenue')); ?>;
            const topTiersCtx = document.getElementById('topTiersChart').getContext('2d');
            new Chart(topTiersCtx, {
                type: 'pie',
                data: { labels: topTiersLabels, datasets: [{ data: topTiersData, backgroundColor: ['rgb(52, 152, 219)', 'rgb(46, 204, 113)', 'rgb(241, 196, 15)', 'rgb(231, 76, 60)'] }] },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: true, 
                    plugins: { 
                        tooltip: { callbacks: { label: function(context) { return context.label + ': $ ' + context.parsed.toLocaleString('es-CO'); } } }, 
                        legend: { position: 'right', labels: { color: textColor } } 
                    } 
                }
            });
            <?php else: ?>
            const topTiersContainer = document.getElementById('topTiersChart').parentNode;
            document.getElementById('topTiersChart').style.display = 'none';
            topTiersContainer.innerHTML = '<div class="text-center text-muted p-4">No hay datos de membresías disponibles</div>';
            <?php endif; ?>
            
            <?php if (count($userGrowth) > 0): ?>
            const growthLabels = <?php echo json_encode(array_column($userGrowth, 'month')); ?>;
            const growthNewUsers = <?php echo json_encode(array_column($userGrowth, 'new_users')); ?>;
            const growthCumulative = <?php echo json_encode(array_column($userGrowth, 'cumulative')); ?>;
            const growthCtx = document.getElementById('growthChart').getContext('2d');
            new Chart(growthCtx, {
                type: 'line',
                data: {
                    labels: growthLabels,
                    datasets: [
                        { label: 'Nuevos usuarios', data: growthNewUsers, borderColor: 'rgb(52, 152, 219)', backgroundColor: 'rgba(52, 152, 219, 0.1)', borderWidth: 2, tension: 0.4, fill: false, pointBackgroundColor: 'rgb(52, 152, 219)', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6 },
                        { label: 'Usuarios acumulados', data: growthCumulative, borderColor: 'rgb(231, 76, 60)', backgroundColor: 'rgba(231, 76, 60, 0.1)', borderWidth: 2, tension: 0.4, fill: true, pointBackgroundColor: 'rgb(231, 76, 60)', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6 }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top', labels: { usePointStyle: true, boxWidth: 10, color: textColor } },
                        tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + context.parsed.y.toLocaleString('es-CO') + ' usuarios'; } } }
                    },
                    scales: {
                        y: { beginAtZero: true, title: { display: true, text: 'Cantidad de usuarios', font: { size: 11, color: textColor } }, ticks: { callback: function(value) { return value.toLocaleString('es-CO'); }, color: textColor }, grid: { color: gridColor } },
                        x: { title: { display: true, text: 'Mes', font: { size: 11, color: textColor } }, ticks: { color: textColor }, grid: { color: gridColor } }
                    }
                }
            });
            <?php else: ?>
            const growthContainer = document.getElementById('growthChart').parentNode;
            document.getElementById('growthChart').style.display = 'none';
            growthContainer.innerHTML = '<div class="text-center text-muted p-4">No hay datos de crecimiento disponibles</div>';
            <?php endif; ?>
            
            <?php if (count($activity) > 0): ?>
            const activityLabels = <?php echo json_encode(array_column($activity, 'date')); ?>;
            const activityNewUsers = <?php echo json_encode(array_column($activity, 'new_users')); ?>;
            const activityPublications = <?php echo json_encode(array_column($activity, 'new_publications')); ?>;
            const activityPayments = <?php echo json_encode(array_column($activity, 'payments')); ?>;
            const activityCtx = document.getElementById('activityChart').getContext('2d');
            new Chart(activityCtx, {
                type: 'bar',
                data: {
                    labels: activityLabels,
                    datasets: [
                        { label: 'Nuevos usuarios', data: activityNewUsers, backgroundColor: 'rgb(52, 152, 219)', borderRadius: 4 },
                        { label: 'Publicaciones', data: activityPublications, backgroundColor: 'rgb(46, 204, 113)', borderRadius: 4 },
                        { label: 'Pagos', data: activityPayments, backgroundColor: 'rgb(241, 196, 15)', borderRadius: 4 }
                    ]
                },
                options: { 
                    responsive: true, 
                    maintainAspectRatio: true, 
                    plugins: { 
                        legend: { position: 'top', labels: { color: textColor } } 
                    }, 
                    scales: { 
                        y: { beginAtZero: true, title: { display: true, text: 'Cantidad', color: textColor }, ticks: { color: textColor }, grid: { color: gridColor } }, 
                        x: { title: { display: true, text: 'Fecha', color: textColor }, ticks: { color: textColor }, grid: { color: gridColor } } 
                    } 
                }
            });
            <?php else: ?>
            const activityContainer = document.getElementById('activityChart').parentNode;
            document.getElementById('activityChart').style.display = 'none';
            activityContainer.innerHTML = '<div class="text-center text-muted p-4">No hay datos de actividad disponibles</div>';
            <?php endif; ?>
        }
        
        function exportSummaryTable(format) {
            window.location.href = 'export-membership-summary.php?format=' + format;
        }
        
        if (document.getElementById('exportSummaryCsvBtn')) { document.getElementById('exportSummaryCsvBtn').addEventListener('click', function() { exportSummaryTable('csv'); }); }
        if (document.getElementById('exportSummaryExcelBtn')) { document.getElementById('exportSummaryExcelBtn').addEventListener('click', function() { exportSummaryTable('excel'); }); }
        if (document.getElementById('exportSummaryPdfBtn')) { document.getElementById('exportSummaryPdfBtn').addEventListener('click', function() { exportSummaryTable('pdf'); }); }
        
        function exportReport(type) {
            const period = document.querySelector('select[name="period"]')?.value || '<?php echo $period; ?>';
            const year = document.querySelector('select[name="year"]')?.value || '<?php echo $year; ?>';
            const month = document.querySelector('select[name="month"]')?.value || '<?php echo $month; ?>';
            window.location.href = '/api/v1/admin-advanced.php?action=export_report&type=' + type + '&period=' + period + '&year=' + year + '&month=' + month;
        }
        
        function generatePDFReport() {
            const period = document.querySelector('select[name="period"]')?.value || '<?php echo $period; ?>';
            const year = document.querySelector('select[name="year"]')?.value || '<?php echo $year; ?>';
            const month = document.querySelector('select[name="month"]')?.value || '<?php echo $month; ?>';
            window.location.href = '/api/v1/admin-advanced.php?action=pdf_report&period=' + period + '&year=' + year + '&month=' + month;
        }
        
        if (document.getElementById('exportUsersBtn')) { document.getElementById('exportUsersBtn').addEventListener('click', function() { exportReport('users'); }); }
        if (document.getElementById('exportPaymentsBtn')) { document.getElementById('exportPaymentsBtn').addEventListener('click', function() { exportReport('payments'); }); }
        if (document.getElementById('exportPublicationsBtn')) { document.getElementById('exportPublicationsBtn').addEventListener('click', function() { exportReport('publications'); }); }
        if (document.getElementById('generatePDFBtn')) { document.getElementById('generatePDFBtn').addEventListener('click', function() { generatePDFReport(); }); }
        
        // Función para mostrar mensajes de éxito/error con SweetAlert2
        function showSuccess(message) {
            showSwalWithTheme({
                title: '¡Éxito!',
                text: message,
                icon: 'success',
                confirmButtonColor: '#c8a86b',
                timer: 3000,
                showConfirmButton: true
            });
        }
        
        function showError(message) {
            showSwalWithTheme({
                title: 'Error',
                text: message,
                icon: 'error',
                confirmButtonColor: '#dc3545',
                timer: 4000,
                showConfirmButton: true
            });
        }
        
        function showWarning(message) {
            showSwalWithTheme({
                title: 'Advertencia',
                text: message,
                icon: 'warning',
                confirmButtonColor: '#c8a86b',
                timer: 3000,
                showConfirmButton: true
            });
        }
        
        function showInfo(message) {
            showSwalWithTheme({
                title: 'Información',
                text: message,
                icon: 'info',
                confirmButtonColor: '#c8a86b',
                timer: 3000,
                showConfirmButton: true
            });
        }
    </script>
</body>
</html>