<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\memberships.php
 * GESTIÓN DE MEMBRESÍAS - Panel de Administración
 * MODIFICADO: Agregado tema claro/oscuro (como en audit.php)
 * MODIFICADO: Eliminados scripts duplicados (jQuery y Bootstrap ya están en sidebar)
 * MODIFICADO: Eliminado botón de tema duplicado (ya existe en sidebar)
 * MODIFICADO: Corregida ruta del endpoint AJAX
 * MODIFICADO: Corregida inclusión del sidebar (mismo formato que users.php)
 */

session_start();
require_once '../../config/database.php';
require_once __DIR__ . '/../../includes/audit-log.php';

// Verificar que $pdo existe
if (!isset($pdo) || $pdo === null) {
    die("Error: No se pudo establecer conexión con la base de datos");
}

require_once __DIR__ . '/../../includes/admin-auth.php';

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];

// Verificar permisos específicos para membresías
$hasPermission = false;
if ($admin['role'] === 'superadmin' || $admin['role'] === 'ingeniero' || $admin['role'] === 'contador') {
    $hasPermission = true;
}

if (!$hasPermission) {
    die("Error: No tienes permisos para gestionar membresías.");
}

// Inicializar auditoría para el administrador
$audit = new AuditLog($pdo, $admin['id'], $admin['email'], $admin['role']);

// Obtener el tema del administrador (como en audit.php)
$theme = $_COOKIE['admin_theme'] ?? 'light';

// ============================================
// ENDPOINT AJAX PARA OBTENER MEMBRESÍA
// ============================================
if (isset($_GET['action']) && $_GET['action'] === 'get' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    $id = (int)$_GET['id'];
    
    try {
        $query = "SELECT * FROM memberships WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $id]);
        $membership = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($membership) {
            echo json_encode(['success' => true, 'membership' => $membership]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Membresía no encontrada con ID: ' . $id]);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
    }
    exit;
}

// Obtener parámetros de paginación y búsqueda
$tab_actual = isset($_GET['tab']) ? $_GET['tab'] : 'activas';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$current_offset = ($current_page - 1) * $current_limit;
$current_search = trim($_GET['search'] ?? '');

if ($current_page < 1) $current_page = 1;
if ($current_limit < 1) $current_limit = 20;

// Procesar acciones POST con auditoría
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $duration_days = (int)($_POST['duration_days'] ?? 30);
        $max_publications = (int)($_POST['max_publications'] ?? 0);
        $has_invoicing = isset($_POST['has_invoicing']) ? 1 : 0;
        $has_statistics = isset($_POST['has_statistics']) ? 1 : 0;
        $has_support_priority = isset($_POST['has_support_priority']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if (empty($name)) {
            $message = 'El nombre de la membresía es obligatorio.';
            $messageType = 'danger';
        } else {
            $query = "INSERT INTO memberships (name, description, price, duration_days, max_publications, 
                    has_invoicing, has_statistics, has_support_priority, is_featured, sort_order, active) 
                    VALUES (:name, :description, :price, :duration_days, :max_publications, 
                    :has_invoicing, :has_statistics, :has_support_priority, :is_featured, :sort_order, :active)";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':duration_days' => $duration_days,
                ':max_publications' => $max_publications,
                ':has_invoicing' => $has_invoicing,
                ':has_statistics' => $has_statistics,
                ':has_support_priority' => $has_support_priority,
                ':is_featured' => $is_featured,
                ':sort_order' => $sort_order,
                ':active' => $active
            ]);
            
            $membershipId = $pdo->lastInsertId();
            
            // REGISTRAR EN AUDITORÍA: Creación de membresía
            $audit->registerCreate('membresia', $membershipId, [
                'name' => $name,
                'price' => $price,
                'duration_days' => $duration_days,
                'max_publications' => $max_publications,
                'has_invoicing' => $has_invoicing,
                'has_statistics' => $has_statistics,
                'has_support_priority' => $has_support_priority,
                'is_featured' => $is_featured,
                'sort_order' => $sort_order,
                'active' => $active
            ], "Membresía '$name' creada por administrador");
            
            $message = 'Membresía creada exitosamente.';
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $duration_days = (int)($_POST['duration_days'] ?? 30);
        $max_publications = (int)($_POST['max_publications'] ?? 0);
        $has_invoicing = isset($_POST['has_invoicing']) ? 1 : 0;
        $has_statistics = isset($_POST['has_statistics']) ? 1 : 0;
        $has_support_priority = isset($_POST['has_support_priority']) ? 1 : 0;
        $is_featured = isset($_POST['is_featured']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $active = isset($_POST['active']) ? 1 : 0;
        
        if ($id <= 0) {
            $message = 'ID de membresía inválido.';
            $messageType = 'danger';
        } elseif (empty($name)) {
            $message = 'El nombre de la membresía es obligatorio.';
            $messageType = 'danger';
        } else {
            // Obtener datos antiguos para auditoría
            $oldQuery = "SELECT name, description, price, duration_days, max_publications, 
                        has_invoicing, has_statistics, has_support_priority, is_featured, sort_order, active 
                        FROM memberships WHERE id = :id";
            $oldStmt = $pdo->prepare($oldQuery);
            $oldStmt->execute([':id' => $id]);
            $oldData = $oldStmt->fetch(PDO::FETCH_ASSOC);
            
            $query = "UPDATE memberships SET 
                    name = :name, 
                    description = :description, 
                    price = :price, 
                    duration_days = :duration_days, 
                    max_publications = :max_publications, 
                    has_invoicing = :has_invoicing, 
                    has_statistics = :has_statistics, 
                    has_support_priority = :has_support_priority, 
                    is_featured = :is_featured, 
                    sort_order = :sort_order, 
                    active = :active 
                    WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':price' => $price,
                ':duration_days' => $duration_days,
                ':max_publications' => $max_publications,
                ':has_invoicing' => $has_invoicing,
                ':has_statistics' => $has_statistics,
                ':has_support_priority' => $has_support_priority,
                ':is_featured' => $is_featured,
                ':sort_order' => $sort_order,
                ':active' => $active,
                ':id' => $id
            ]);
            
            // Obtener datos nuevos para auditoría
            $newQuery = "SELECT name, description, price, duration_days, max_publications, 
                        has_invoicing, has_statistics, has_support_priority, is_featured, sort_order, active 
                        FROM memberships WHERE id = :id";
            $newStmt = $pdo->prepare($newQuery);
            $newStmt->execute([':id' => $id]);
            $newData = $newStmt->fetch(PDO::FETCH_ASSOC);
            
            // REGISTRAR EN AUDITORÍA: Actualización de membresía
            $audit->registerUpdate('membresia', $id, $oldData, $newData, "Membresía '$name' editada por administrador");
            
            $message = 'Membresía actualizada exitosamente.';
            $messageType = 'success';
        }
    }
    
    elseif ($action === 'toggle_status') {
        $id = (int)($_POST['id'] ?? 0);
        $currentStatus = (int)($_POST['current_status'] ?? 0);
        $newStatus = $currentStatus == 1 ? 0 : 1;
        
        // Obtener nombre de la membresía para auditoría
        $nameQuery = "SELECT name FROM memberships WHERE id = :id";
        $nameStmt = $pdo->prepare($nameQuery);
        $nameStmt->execute([':id' => $id]);
        $membershipName = $nameStmt->fetch(PDO::FETCH_ASSOC)['name'];
        
        $query = "UPDATE memberships SET active = :active WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':active' => $newStatus, ':id' => $id]);
        
        $actionType = ($newStatus == 1) ? 'ACTIVATE' : 'DEACTIVATE';
        $statusText = ($newStatus == 1) ? 'activada' : 'desactivada';
        
        // REGISTRAR EN AUDITORÍA: Cambio de estado de membresía
        $audit->register($actionType, 'membresia', $id, 
                        ['active' => $currentStatus], ['active' => $newStatus], 
                        null, "Membresía '$membershipName' $statusText por administrador");
        
        $message = $newStatus == 1 ? 'Membresía activada exitosamente.' : 'Membresía desactivada exitosamente.';
        $messageType = 'success';
    }
    
    elseif ($action === 'toggle_featured') {
        $id = (int)($_POST['id'] ?? 0);
        $currentFeatured = (int)($_POST['current_featured'] ?? 0);
        $newFeatured = $currentFeatured == 1 ? 0 : 1;
        
        // Obtener nombre de la membresía para auditoría
        $nameQuery = "SELECT name FROM memberships WHERE id = :id";
        $nameStmt = $pdo->prepare($nameQuery);
        $nameStmt->execute([':id' => $id]);
        $membershipName = $nameStmt->fetch(PDO::FETCH_ASSOC)['name'];
        
        $query = "UPDATE memberships SET is_featured = :is_featured WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':is_featured' => $newFeatured, ':id' => $id]);
        
        $actionType = ($newFeatured == 1) ? 'FEATURE' : 'UNFEATURE';
        $statusText = ($newFeatured == 1) ? 'destacada' : 'no destacada';
        
        // REGISTRAR EN AUDITORÍA: Cambio de estado destacado de membresía
        $audit->register($actionType, 'membresia', $id, 
                        ['is_featured' => $currentFeatured], ['is_featured' => $newFeatured], 
                        null, "Membresía '$membershipName' marcada como $statusText por administrador");
        
        $message = $newFeatured == 1 ? 'Membresía destacada exitosamente.' : 'Membresía ya no es destacada.';
        $messageType = 'success';
    }
    
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        // Obtener datos de la membresía antes de eliminar
        $oldQuery = "SELECT * FROM memberships WHERE id = :id";
        $oldStmt = $pdo->prepare($oldQuery);
        $oldStmt->execute([':id' => $id]);
        $membershipToDelete = $oldStmt->fetch(PDO::FETCH_ASSOC);
        
        // Verificar si hay usuarios con esta membresía
        $checkQuery = "SELECT COUNT(*) as count FROM user_memberships WHERE membership_id = :membership_id";
        $checkStmt = $pdo->prepare($checkQuery);
        $checkStmt->execute([':membership_id' => $id]);
        $hasUsers = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
        
        if ($hasUsers) {
            $message = 'No se puede eliminar la membresía porque tiene usuarios asociados.';
            $messageType = 'danger';
        } else {
            $query = "DELETE FROM memberships WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([':id' => $id]);
            
            // REGISTRAR EN AUDITORÍA: Eliminación de membresía
            $audit->registerDelete('membresia', $id, $membershipToDelete, 
                                "Membresía '{$membershipToDelete['name']}' eliminada por administrador");
            
            $message = 'Membresía eliminada exitosamente.';
            $messageType = 'success';
        }
    }
}

// ============================================
// FUNCIONES DE BÚSQUEDA NUEVAS (CORREGIDAS)
// ============================================

function getMembershipsByStatus($pdo, $status, $limit, $offset, $search) {
    $limit = (int)$limit;
    $offset = (int)$offset;
    
    if (!empty($search)) {
        $search_param = "%$search%";
        $sql = "SELECT * FROM memberships WHERE active = ? AND (name LIKE ? OR description LIKE ?) ORDER BY sort_order ASC, price ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $search_param, $search_param]);
    } else {
        $sql = "SELECT * FROM memberships WHERE active = ? ORDER BY sort_order ASC, price ASC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status]);
    }
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTotalMembershipsByStatus($pdo, $status, $search) {
    if (!empty($search)) {
        $search_param = "%$search%";
        $sql = "SELECT COUNT(*) as total FROM memberships WHERE active = ? AND (name LIKE ? OR description LIKE ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status, $search_param, $search_param]);
    } else {
        $sql = "SELECT COUNT(*) as total FROM memberships WHERE active = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$status]);
    }
    return $stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

// Obtener estadísticas para los tabs (con búsqueda incluida)
$stats_activas = getTotalMembershipsByStatus($pdo, 1, $current_search);
$stats_inactivas = getTotalMembershipsByStatus($pdo, 0, $current_search);

// Obtener membresías según el tab activo y búsqueda
$status_filtro = ($tab_actual === 'activas') ? 1 : 0;
$memberships = getMembershipsByStatus($pdo, $status_filtro, $current_limit, $current_offset, $current_search);
$total = ($tab_actual === 'activas') ? $stats_activas : $stats_inactivas;
$totalPages = ($total > 0) ? ceil($total / $current_limit) : 1;

// Estadísticas totales para las tarjetas
$stats = [
    'total' => $pdo->query("SELECT COUNT(*) as count FROM memberships")->fetch(PDO::FETCH_ASSOC)['count'],
    'active' => $pdo->query("SELECT COUNT(*) as count FROM memberships WHERE active = 1")->fetch(PDO::FETCH_ASSOC)['count'],
    'inactive' => $pdo->query("SELECT COUNT(*) as count FROM memberships WHERE active = 0")->fetch(PDO::FETCH_ASSOC)['count'],
    'featured' => $pdo->query("SELECT COUNT(*) as count FROM memberships WHERE is_featured = 1")->fetch(PDO::FETCH_ASSOC)['count']
];
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Membresías - Easy Car Luxury</title>
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        /* ============================================
           VARIABLES DE TEMA CLARO (como audit.php)
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
           VARIABLES DE TEMA OSCURO (como audit.php)
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

        .stats-cards {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px 20px;
            flex: 1;
            min-width: 150px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-card .stat-number {
            font-size: 1.8rem;
            font-weight: bold;
            color: #c8a86b;
        }

        .stat-card .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .stat-card i {
            font-size: 1.5rem;
            color: #c8a86b;
            margin-right: 10px;
        }

        .section-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
            width: 100%;
        }

        .membership-tabs {
            display: flex;
            gap: 4px;
            padding: 10px 20px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
        }

        .tab-btn {
            padding: 8px 20px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab-btn i {
            margin-right: 6px;
        }

        .tab-btn:hover {
            background: var(--table-hover);
            color: #c8a86b;
            border-color: #c8a86b;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            color: white;
            border-color: #c8a86b;
        }

        .tab-badge {
            display: inline-block;
            background: rgba(0,0,0,0.1);
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 0.65rem;
            margin-left: 6px;
        }

        .tab-btn.active .tab-badge {
            background: rgba(255,255,255,0.2);
        }

        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            padding: 15px 20px;
            background: var(--card-bg);
            border-bottom: 1px solid var(--border-color);
        }

        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            padding: 5px 10px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.8rem;
            width: 250px;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .search-input:focus {
            outline: none;
            border-color: #c8a86b;
        }

        .search-input::placeholder {
            color: var(--text-secondary);
        }

        .table-wrapper {
            padding: 0 20px;
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

        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-success {
            background: #28a745;
            color: white;
        }

        .badge-danger {
            background: #dc3545;
            color: white;
        }

        .badge-warning {
            background: #ffc107;
            color: #333;
        }

        .badge-info {
            background: #17a2b8;
            color: white;
        }

        .badge-featured {
            background: #f9ca24;
            color: #1a1a2e;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: nowrap;
        }

        .btn-icon {
            width: 30px;
            height: 30px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            background: var(--bg-primary);
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
        }

        .btn-icon.edit:hover {
            background: #007bff;
            color: white;
        }

        .btn-icon.delete:hover {
            background: #dc3545;
            color: white;
        }

        .btn-icon.status:hover {
            background: #ffc107;
            color: #333;
        }

        .btn-icon.featured:hover {
            background: #f9ca24;
            color: #333;
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

        .pagination-container {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 1px solid var(--border-color);
            background: var(--card-bg);
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

        .alert {
            margin: 15px 20px 0;
            padding: 10px 15px;
            border-radius: 8px;
            font-size: 0.8rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        [data-theme="dark"] .alert-success {
            background-color: #1a4a2a;
            color: #ccffcc;
            border-color: #2a6a3a;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #5a1a1a;
            color: #ffcccc;
            border-color: #8b3a3a;
        }

        .text-center {
            text-align: center;
        }

        .price-cell {
            font-weight: bold;
            color: #c8a86b;
        }

        .featured-star {
            color: #f9ca24;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--border-color);
        }

        /* Botón tema claro/oscuro (como audit.php) */
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

        /* Modal en modo oscuro (como audit.php) */
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

        [data-theme="dark"] .form-check-label {
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-check-input {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .form-check-input:checked {
            background-color: #667eea !important;
            border-color: #667eea !important;
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                padding: 80px 10px 10px;
            }
            .search-input {
                width: 100%;
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
            .stats-cards {
                flex-direction: column;
            }
            .stat-card {
                width: 100%;
            }
            .tab-btn {
                padding: 5px 12px;
                font-size: 0.7rem;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
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
                <h1><i class="fas fa-crown"></i> Gestión de Membresías</h1>
                <p>Administra los planes de membresía de la plataforma</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" id="showCreateModal">
                    <i class="fas fa-plus"></i> Nueva Membresía
                </button>
            </div>
        </div>
        
        <div class="stats-cards">
            <div class="stat-card">
                <div><i class="fas fa-chart-line"></i><span class="stat-number"><?php echo $stats['total']; ?></span></div>
                <div class="stat-label">Total Membresías</div>
            </div>
            <div class="stat-card">
                <div><i class="fas fa-check-circle"></i><span class="stat-number"><?php echo $stats['active']; ?></span></div>
                <div class="stat-label">Activas</div>
            </div>
            <div class="stat-card">
                <div><i class="fas fa-ban"></i><span class="stat-number"><?php echo $stats['inactive']; ?></span></div>
                <div class="stat-label">Inactivas</div>
            </div>
            <div class="stat-card">
                <div><i class="fas fa-star"></i><span class="stat-number"><?php echo $stats['featured']; ?></span></div>
                <div class="stat-label">Destacadas</div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        
        <div class="section-container">
            <div class="membership-tabs">
                <button class="tab-btn <?php echo $tab_actual == 'activas' ? 'active' : ''; ?>" data-tab="activas">
                    <i class="fas fa-check-circle"></i> Activas <span class="tab-badge"><?php echo $stats_activas; ?></span>
                </button>
                <button class="tab-btn <?php echo $tab_actual == 'inactivas' ? 'active' : ''; ?>" data-tab="inactivas">
                    <i class="fas fa-ban"></i> Inactivas <span class="tab-badge"><?php echo $stats_inactivas; ?></span>
                </button>
            </div>
            
            <div class="filter-bar">
                <form method="GET" class="filter-form" id="filterForm">
                    <input type="hidden" name="tab" id="tabInput" value="<?php echo $tab_actual; ?>">
                    <input type="text" name="search" class="search-input" placeholder="Buscar membresía..." value="<?php echo htmlspecialchars($current_search); ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Buscar</button>
                    <?php if (!empty($current_search)): ?>
                        <a href="?tab=<?php echo $tab_actual; ?>&page=1" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Duración</th><th>Publicaciones</th><th>Características</th><th>Orden</th><th>Destacada</th><th>Estado</th><th>Acciones</th></tr>
                    </thead>
                    <tbody>
                        <?php if (count($memberships) > 0): ?>
                            <?php foreach ($memberships as $membership): ?>
                                <tr id="fila_<?php echo $membership['id']; ?>">
                                    <td><?php echo $membership['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars(ucfirst($membership['name'])); ?></strong><?php if (!empty($membership['description'])): ?><br><small class="text-muted"><?php echo htmlspecialchars(substr($membership['description'], 0, 40)) . (strlen($membership['description']) > 40 ? '...' : ''); ?></small><?php endif; ?></td>
                                    <td class="price-cell"><?php if ($membership['price'] > 0): ?>$ <?php echo number_format($membership['price'], 0, ',', '.'); ?><?php else: ?>Gratis<?php endif; ?></td>
                                    <td><?php echo $membership['duration_days']; ?> días</td>
                                    <td><?php if ($membership['max_publications'] >= 999999): ?><span class="badge badge-info">Ilimitadas</span><?php else: ?><?php echo number_format($membership['max_publications']); ?><?php endif; ?></td>
                                    <td><?php if ($membership['has_invoicing']): ?><span class="badge badge-info" title="Facturación DIAN"><i class="fas fa-file-invoice"></i></span><?php endif; ?><?php if ($membership['has_statistics']): ?><span class="badge badge-info" title="Estadísticas"><i class="fas fa-chart-line"></i></span><?php endif; ?><?php if ($membership['has_support_priority']): ?><span class="badge badge-info" title="Soporte prioritario"><i class="fas fa-headset"></i></span><?php endif; ?></td>
                                    <td class="text-center"><?php echo $membership['sort_order']; ?></td>
                                    <td><?php if ($membership['is_featured'] == 1): ?><span class="badge badge-featured"><i class="fas fa-star"></i> Destacada</span><?php else: ?><span class="text-muted">-</span><?php endif; ?></td>
                                    <td><?php if ($membership['active'] == 1): ?><span class="badge badge-success">Activa</span><?php else: ?><span class="badge badge-danger">Inactiva</span><?php endif; ?></td>
                                    <td><div class="action-buttons"><button class="btn-icon edit" onclick="editMembership(<?php echo $membership['id']; ?>)" title="Editar"><i class="fas fa-edit"></i></button><button class="btn-icon featured" onclick="toggleFeatured(<?php echo $membership['id']; ?>, <?php echo $membership['is_featured']; ?>)" title="<?php echo $membership['is_featured'] == 1 ? 'Quitar destacada' : 'Destacar'; ?>"><i class="fas fa-star"></i></button><button class="btn-icon status" onclick="toggleStatus(<?php echo $membership['id']; ?>, <?php echo $membership['active']; ?>)" title="<?php echo $membership['active'] == 1 ? 'Desactivar' : 'Activar'; ?>"><i class="fas fa-<?php echo $membership['active'] == 1 ? 'ban' : 'check-circle'; ?>"></i></button><button class="btn-icon delete" onclick="deleteMembership(<?php echo $membership['id']; ?>)" title="Eliminar"><i class="fas fa-trash"></i></button></div></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="10" class="empty-state"><i class="fas fa-folder-open"></i><p>No hay membresías <?php echo $tab_actual == 'activas' ? 'activas' : 'inactivas'; ?>.</p><?php if (!empty($current_search)): ?><small>No se encontraron resultados para "<?php echo htmlspecialchars($current_search); ?>"</small><?php else: ?><small>Haz clic en "Nueva Membresía" para crear una.</small><?php endif; ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination-container">
                <div class="limit-selector"><span>Mostrar:</span><select id="limit_select" onchange="changeLimit(this.value)"><option value="10" <?php echo $current_limit == 10 ? 'selected' : ''; ?>>10</option><option value="20" <?php echo $current_limit == 20 ? 'selected' : ''; ?>>20</option><option value="50" <?php echo $current_limit == 50 ? 'selected' : ''; ?>>50</option><option value="100" <?php echo $current_limit == 100 ? 'selected' : ''; ?>>100</option></select><span>registros por página</span></div>
                <?php if ($totalPages > 1): ?>
                <nav><ul class="pagination"><li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="#" onclick="changePage(<?php echo $current_page - 1; ?>); return false;">Anterior</a></li><?php $start_page = max(1, $current_page - 2); $end_page = min($totalPages, $current_page + 2); if ($start_page > 1) { echo '<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>'; if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; } for ($i = $start_page; $i <= $end_page; $i++): ?><li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>"><a class="page-link" href="#" onclick="changePage(<?php echo $i; ?>); return false;"><?php echo $i; ?></a></li><?php endfor; if ($end_page < $totalPages) { if ($end_page < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>'; echo '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' . $totalPages . '); return false;">' . $totalPages . '</a></li>'; } ?><li class="page-item <?php echo $current_page >= $totalPages ? 'disabled' : ''; ?>"><a class="page-link" href="#" onclick="changePage(<?php echo $current_page + 1; ?>); return false;">Siguiente</a></li></ul></nav>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- Botón para cambiar tema claro/oscuro (como audit.php) -->
<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<!-- SweetAlert2 JS desde CDN (jQuery y Bootstrap ya están en sidebar) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    let editId = null;
    
    // Función para cambiar tema (como audit.php)
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
    
    // Al cargar la página, ajustar el icono según el tema guardado (como audit.php)
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
    
    function createModal() {
        const modalHtml = `
            <div class="modal fade" id="membershipModal" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header py-2 px-3" style="background: #c8a86b; color: white;">
                            <h6 class="modal-title" id="modalTitle"><i class="fas fa-crown me-1"></i> Nueva Membresía</h6>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form id="membershipForm" method="POST">
                            <input type="hidden" name="action" id="formAction" value="create">
                            <input type="hidden" name="id" id="membershipId" value="0">
                            <div class="modal-body p-3">
                                <div id="formError" class="alert alert-danger small py-1 px-2 mb-2" style="display: none;"></div>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Nombre *</label>
                                        <input type="text" name="name" id="membershipName" class="form-control form-control-sm" required>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Precio (COP)</label>
                                        <input type="number" name="price" id="membershipPrice" class="form-control form-control-sm" value="0">
                                        <small class="text-muted">0 = Gratis</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Duración (días)</label>
                                        <input type="number" name="duration_days" id="membershipDuration" class="form-control form-control-sm" value="30">
                                        <small class="text-muted">30 días = 1 mes</small>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Máx. Publicaciones</label>
                                        <input type="number" name="max_publications" id="membershipMaxPub" class="form-control form-control-sm" value="1">
                                        <small class="text-muted">Usar 999999 para ilimitadas</small>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold">Descripción</label>
                                    <textarea name="description" id="membershipDescription" class="form-control form-control-sm" rows="2"></textarea>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label small fw-bold">Orden</label>
                                        <input type="number" name="sort_order" id="membershipOrder" class="form-control form-control-sm" value="0">
                                        <small class="text-muted">Número menor = aparece primero</small>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="has_invoicing" id="membershipInvoicing" class="form-check-input" value="1">
                                            <label class="form-check-label small" for="membershipInvoicing">Facturación DIAN</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="has_statistics" id="membershipStats" class="form-check-input" value="1">
                                            <label class="form-check-label small" for="membershipStats">Estadísticas</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="has_support_priority" id="membershipSupport" class="form-check-input" value="1">
                                            <label class="form-check-label small" for="membershipSupport">Soporte Prioritario</label>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_featured" id="membershipFeatured" class="form-check-input" value="1">
                                            <label class="form-check-label small" for="membershipFeatured">Destacado</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input type="checkbox" name="active" id="membershipActive" class="form-check-input" value="1" checked>
                                        <label class="form-check-label small" for="membershipActive">Activo</label>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer py-2 px-3">
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-sm" style="background: #c8a86b; border-color: #c8a86b; color: white;">Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
        
        if (document.getElementById('membershipModal')) {
            document.getElementById('membershipModal').remove();
        }
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        document.getElementById('membershipForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const name = document.getElementById('membershipName').value.trim();
            if (!name) {
                const errorDiv = document.getElementById('formError');
                errorDiv.textContent = 'El nombre de la membresía es obligatorio.';
                errorDiv.style.display = 'block';
                setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
                return;
            }
            const formData = new FormData(this);
            fetch(window.location.href, { method: 'POST', body: formData }).then(() => { window.location.reload(); });
        });
    }
    
    function editMembership(id) {
        editId = id;
        showSwalWithTheme({ title: 'Cargando...', text: 'Obteniendo datos de la membresía', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });
        fetch('memberships.php?action=get&id=' + id).then(response => response.json()).then(data => {
            Swal.close();
            if (data.success) {
                createModal();
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-1"></i> Editar Membresía';
                document.getElementById('formAction').value = 'edit';
                document.getElementById('membershipId').value = data.membership.id;
                document.getElementById('membershipName').value = data.membership.name;
                document.getElementById('membershipPrice').value = data.membership.price;
                document.getElementById('membershipDuration').value = data.membership.duration_days;
                document.getElementById('membershipMaxPub').value = data.membership.max_publications;
                document.getElementById('membershipDescription').value = data.membership.description || '';
                document.getElementById('membershipOrder').value = data.membership.sort_order;
                document.getElementById('membershipInvoicing').checked = data.membership.has_invoicing == 1;
                document.getElementById('membershipStats').checked = data.membership.has_statistics == 1;
                document.getElementById('membershipSupport').checked = data.membership.has_support_priority == 1;
                document.getElementById('membershipFeatured').checked = data.membership.is_featured == 1;
                document.getElementById('membershipActive').checked = data.membership.active == 1;
                new bootstrap.Modal(document.getElementById('membershipModal')).show();
            } else {
                showSwalWithTheme({ title: 'Error', text: data.message || 'Error al cargar los datos de la membresía.', icon: 'error', confirmButtonColor: '#c8a86b' });
            }
        }).catch(error => { Swal.close(); showSwalWithTheme({ title: 'Error', text: 'Error de conexión: ' + error.message, icon: 'error', confirmButtonColor: '#c8a86b' }); });
    }
    
    function showFormError(message) {
        const errorDiv = document.getElementById('formError');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        setTimeout(() => { errorDiv.style.display = 'none'; }, 3000);
    }
    
    function toggleStatus(id, currentStatus) {
        const action = currentStatus == 1 ? 'desactivar' : 'activar';
        const newStatusText = currentStatus == 1 ? 'inactiva' : 'activa';
        showSwalWithTheme({ title: `${action === 'activar' ? 'Activar' : 'Desactivar'} membresía`, text: `¿Estás seguro de ${action} esta membresía? Pasará a estar ${newStatusText}.`, icon: 'question', showCancelButton: true, confirmButtonColor: '#c8a86b', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar' }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'toggle_status');
                formData.append('id', id);
                formData.append('current_status', currentStatus);
                fetch(window.location.href, { method: 'POST', body: formData }).then(() => { window.location.reload(); });
            }
        });
    }
    
    function toggleFeatured(id, currentFeatured) {
        const action = currentFeatured == 1 ? 'quitar destacada' : 'destacar';
        showSwalWithTheme({ title: `${currentFeatured == 1 ? 'Quitar destacada' : 'Destacar'} membresía`, text: `¿Estás seguro de ${action} esta membresía?`, icon: 'question', showCancelButton: true, confirmButtonColor: '#c8a86b', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, continuar', cancelButtonText: 'Cancelar' }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'toggle_featured');
                formData.append('id', id);
                formData.append('current_featured', currentFeatured);
                fetch(window.location.href, { method: 'POST', body: formData }).then(() => { window.location.reload(); });
            }
        });
    }
    
    function deleteMembership(id) {
        showSwalWithTheme({ title: 'Eliminar membresía', text: '¿Estás seguro de eliminar esta membresía? Si tiene usuarios asociados no podrá eliminarla.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc3545', cancelButtonColor: '#6c757d', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);
                fetch(window.location.href, { method: 'POST', body: formData }).then(() => { window.location.reload(); });
            }
        });
    }
    
    document.querySelectorAll('.tab-btn').forEach(function(tab) {
        tab.addEventListener('click', function() {
            const tabName = this.getAttribute('data-tab');
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('tab', tabName);
            urlParams.set('page', 1);
            window.location.href = '?' + urlParams.toString();
        });
    });
    
    document.getElementById('showCreateModal')?.addEventListener('click', function() {
        editId = null;
        createModal();
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-crown me-1"></i> Nueva Membresía';
        document.getElementById('formAction').value = 'create';
        document.getElementById('membershipId').value = '0';
        document.getElementById('membershipName').value = '';
        document.getElementById('membershipPrice').value = '0';
        document.getElementById('membershipDuration').value = '30';
        document.getElementById('membershipMaxPub').value = '1';
        document.getElementById('membershipDescription').value = '';
        document.getElementById('membershipOrder').value = '0';
        document.getElementById('membershipInvoicing').checked = false;
        document.getElementById('membershipStats').checked = false;
        document.getElementById('membershipSupport').checked = false;
        document.getElementById('membershipFeatured').checked = false;
        document.getElementById('membershipActive').checked = true;
        new bootstrap.Modal(document.getElementById('membershipModal')).show();
    });
</script>
</body>
</html>