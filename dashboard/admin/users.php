<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\users.php
 * GESTIÓN DE USUARIOS - Panel de Administración
 * MODIFICADO: Paginación centrada con botones de adelante y atrás
 * FIX: Eliminación de usuarios con SweetAlert2
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

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

// Inicializar auditoría para el administrador
$audit = new AuditLog($pdo, $admin['id'], $admin['email'], $admin['role']);

// Determinar qué tab está activo
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'todos';

// Obtener parámetros de paginación
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
$current_offset = ($current_page - 1) * $current_limit;
$current_search = trim($_GET['search'] ?? '');
$current_roleFilter = $_GET['role_filter'] ?? '';

// ============================================
// FUNCIÓN PARA OBTENER USUARIOS SEGÚN EL TAB
// ============================================
function getUsersByTab($pdo, $tab, $page, $limit, $offset, $search, $roleFilter) {
    $where = [];
    $params = [];
    
    // Condiciones según el tab
    switch($tab) {
        case 'todos':
            // Todos los usuarios (activos e inactivos)
            break;
        case 'free':
            $where[] = "u.tipo_cuenta = 'free'";
            $where[] = "u.activo = 1";
            break;
        case 'pro':
            $where[] = "u.tipo_cuenta = 'pro'";
            $where[] = "u.activo = 1";
            break;
        case 'premium':
            $where[] = "u.tipo_cuenta = 'premium'";
            $where[] = "u.activo = 1";
            break;
        case 'elite':
            $where[] = "u.tipo_cuenta = 'elite'";
            $where[] = "u.activo = 1";
            break;
        case 'sistema':
            $where[] = "r.nombre IN ('superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor')";
            $where[] = "u.activo = 1";
            break;
        case 'activos':
            $where[] = "u.activo = 1";
            break;
        case 'inactivos':
            $where[] = "u.activo = 0";
            break;
        default:
            $where[] = "u.activo = 1";
            break;
    }
    
    // Búsqueda
    if (!empty($search)) {
        $where[] = "(u.nombre_completo LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.id = ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = is_numeric($search) ? (int)$search : 0;
    }
    
    // Filtro por rol
    if (!empty($roleFilter)) {
        $where[] = "r.nombre = ?";
        $params[] = $roleFilter;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Count total
    $countQuery = "SELECT COUNT(*) as total FROM usuarios u JOIN roles r ON u.rol_id = r.id $whereClause";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($total / $limit);
    
    // Get users
    $query = "SELECT u.id, u.nombre_completo as full_name, u.email, u.username, u.telefono as phone, 
                r.nombre as role, u.tipo_cuenta as membership_tier, 
                CASE WHEN u.activo = 1 THEN 'active' ELSE 'inactive' END as status,
                u.created_at, u.ultimo_acceso as last_login,
                u.email_verificado, u.tema_oscuro
                FROM usuarios u 
                JOIN roles r ON u.rol_id = r.id 
                $whereClause 
                ORDER BY u.created_at DESC 
                LIMIT $limit OFFSET $offset";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return ['users' => $users, 'total' => $total, 'totalPages' => $totalPages];
}

// Procesar acciones POST para auditoría (crear, editar, eliminar, toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        // Registrar creación de usuario en auditoría
        $audit->registerCreate('usuario', $_POST['user_id'] ?? 0, [
            'nombre' => $_POST['full_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'username' => $_POST['username'] ?? '',
            'rol_id' => $_POST['role_id'] ?? '',
            'tipo_cuenta' => $_POST['membership_tier'] ?? ''
        ], 'Usuario creado por administrador');
    }
    
    if ($action === 'edit_user') {
        // Registrar edición de usuario en auditoría
        $oldData = $_POST['old_data'] ?? null;
        $newData = [
            'nombre' => $_POST['full_name'] ?? '',
            'email' => $_POST['email'] ?? '',
            'username' => $_POST['username'] ?? '',
            'rol_id' => $_POST['role_id'] ?? '',
            'tipo_cuenta' => $_POST['membership_tier'] ?? ''
        ];
        if ($oldData) {
            $oldData = json_decode($oldData, true);
        }
        $audit->registerUpdate('usuario', $_POST['user_id'] ?? 0, $oldData, $newData, 'Usuario editado por administrador');
    }
    
    if ($action === 'toggle_user_status') {
        $newStatus = $_POST['active'] ?? 0;
        $statusText = $newStatus == 1 ? 'activado' : 'desactivado';
        $audit->register(($newStatus == 1 ? 'ACTIVATE' : 'DEACTIVATE'), 'usuario', $_POST['user_id'] ?? 0, 
                        ['activo' => ($newStatus == 1 ? 0 : 1)], ['activo' => $newStatus], 
                        null, "Usuario $statusText por administrador");
    }
    
    if ($action === 'delete_user_permanently') {
        $audit->registerDelete('usuario', $_POST['user_id'] ?? 0, $_POST['user_data'] ?? null, 
        'Usuario eliminado permanentemente por superadmin');
    }
}

// Obtener datos según el tab activo
$userData = getUsersByTab($pdo, $active_tab, $current_page, $current_limit, $current_offset, $current_search, $current_roleFilter);

// Estadísticas para los tabs
$stats = [
    'todos' => $pdo->query("SELECT COUNT(*) as count FROM usuarios")->fetch(PDO::FETCH_ASSOC)['count'],
    'free' => $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE tipo_cuenta = 'free' AND activo = 1")->fetch(PDO::FETCH_ASSOC)['count'],
    'pro' => $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE tipo_cuenta = 'pro' AND activo = 1")->fetch(PDO::FETCH_ASSOC)['count'],
    'premium' => $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE tipo_cuenta = 'premium' AND activo = 1")->fetch(PDO::FETCH_ASSOC)['count'],
    'elite' => $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE tipo_cuenta = 'elite' AND activo = 1")->fetch(PDO::FETCH_ASSOC)['count'],
    'sistema' => $pdo->query("SELECT COUNT(*) as count FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre IN ('superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor') AND u.activo = 1")->fetch(PDO::FETCH_ASSOC)['count'],
    'activos' => $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE activo = 1")->fetch(PDO::FETCH_ASSOC)['count'],
    'inactivos' => $pdo->query("SELECT COUNT(*) as count FROM usuarios WHERE activo = 0")->fetch(PDO::FETCH_ASSOC)['count']
];

// Obtener lista de roles para el filtro
$rolesList = $pdo->query("SELECT nombre FROM roles ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - Colcars</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            --table-header-bg: #f8f9fa;
            --table-header-color: #333;
            --tab-bg: #f8f9fa;
            --tab-border: #dee2e6;
            --filter-bar-bg: #ffffff;
            --modal-bg: #ffffff;
            --modal-header-bg: #c8a86b;
            --modal-header-color: white;
            --modal-border: #dee2e6;
            --alert-danger-bg: #f8d7da;
            --alert-danger-text: #721c24;
            --alert-danger-border: #f5c6cb;
            --alert-success-bg: #d4edda;
            --alert-success-text: #155724;
            --alert-success-border: #c3e6cb;
            --alert-warning-bg: #fff3cd;
            --alert-warning-text: #856404;
            --alert-warning-border: #ffeeba;
            --swal2-bg: #ffffff;
            --swal2-text: #1a1a2e;
            --swal2-input-bg: #ffffff;
            --swal2-input-border: #dddddd;
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
            --table-header-bg: #0f1535;
            --table-header-color: #ffffff;
            --tab-bg: #0f1535;
            --tab-border: #2a2a3e;
            --filter-bar-bg: #16213e;
            --modal-bg: #16213e;
            --modal-header-bg: #0f1535;
            --modal-header-color: #c8a86b;
            --modal-border: #2a2a3e;
            --alert-danger-bg: #2d1a1a;
            --alert-danger-text: #f8d7da;
            --alert-danger-border: #721c24;
            --alert-success-bg: #1a2d1a;
            --alert-success-text: #d4edda;
            --alert-success-border: #155724;
            --alert-warning-bg: #2d2d1a;
            --alert-warning-text: #fff3cd;
            --alert-warning-border: #856404;
            --swal2-bg: #16213e;
            --swal2-text: #ffffff;
            --swal2-input-bg: #222F58;
            --swal2-input-border: #4a4a5e;
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

        .admin-main {
            flex: 1;
            width: 100%;
            padding: 2px 5px;
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

        .section-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
            width: 100%;
        }

        /* Estilos para los tabs dentro de la tabla */
        .table-tabs {
            display: flex;
            gap: 4px;
            flex-wrap: wrap;
            padding: 10px 15px;
            background: var(--tab-bg);
            border-bottom: 1px solid var(--tab-border);
        }

        .tab-btn {
            padding: 6px 12px;
            background: var(--card-bg);
            border: 1px solid var(--tab-border);
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .tab-btn i {
            margin-right: 4px;
            font-size: 0.65rem;
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
            padding: 1px 5px;
            font-size: 0.6rem;
            margin-left: 4px;
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
            background: var(--filter-bar-bg);
            border-bottom: 1px solid var(--border-color);
        }

        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .table-wrapper {
            padding: 0 2px;
            overflow-x: visible;
            width: 100%;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-primary);
            vertical-align: middle;
        }

        /* Anchos específicos para cada columna */
        .admin-table th:nth-child(1),
        .admin-table td:nth-child(1) { width: 3%; }  /* ID */
        
        .admin-table th:nth-child(2),
        .admin-table td:nth-child(2) { width: 25%; } /* Usuario */
        
        .admin-table th:nth-child(3),
        .admin-table td:nth-child(3) { width: 16%; } /* Contacto */
        
        .admin-table th:nth-child(4),
        .admin-table td:nth-child(4) { width: 8%; } /* Rol */
        
        .admin-table th:nth-child(5),
        .admin-table td:nth-child(5) { width: 8%; }  /* Membresía */
        
        .admin-table th:nth-child(6),
        .admin-table td:nth-child(6) { width: 6%; }  /* Estado */
        
        .admin-table th:nth-child(7),
        .admin-table td:nth-child(7) { width: 8%; } /* Registro */
        
        .admin-table th:nth-child(8),
        .admin-table td:nth-child(8) { width: 12%; } /* Último acceso */
        
        .admin-table th:nth-child(9),
        .admin-table td:nth-child(9) { width: 14%; } /* Acciones */

        .admin-table th {
            background: var(--table-header-bg);
            font-weight: 600;
            color: var(--table-header-color);
            font-size: 0.75rem;
            position: sticky;
            top: 0;
        }

        .admin-table tr:hover {
            background: var(--table-hover);
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 0.85rem;
            flex-shrink: 0;
        }

        .user-info strong {
            display: block;
            font-size: 0.85rem;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-info small {
            font-size: 0.7rem;
            color: var(--text-secondary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 600;
            display: inline-block;
            white-space: nowrap;
        }

        .badge-danger { background: #dc3545; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-primary { background: #007bff; color: white; }
        .badge-secondary { background: #6c757d; color: white; }
        .badge-free { background: #6c757d; color: white; }
        .badge-pro { background: #17a2b8; color: white; }
        .badge-premium { background: #007bff; color: white; }
        .badge-elite { background: linear-gradient(135deg, #d4af37, #c8a86b); color: #333; }

        .status-badge {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 500;
            display: inline-block;
            white-space: nowrap;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }

        [data-theme="dark"] .status-active {
            background: #1a2d1a;
            color: #d4edda;
        }

        [data-theme="dark"] .status-inactive {
            background: #2d1a1a;
            color: #f8d7da;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
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
            text-decoration: none;
            background: var(--bg-primary);
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .btn-icon:hover {
            transform: translateY(-2px);
        }

        .btn-icon.edit:hover { background: #007bff; color: white; }
        .btn-icon.view:hover { background: #17a2b8; color: white; }
        .btn-icon.login:hover { background: #28a745; color: white; }
        .btn-icon.suspend:hover { background: #ffc107; color: #333; }
        .btn-icon.delete:hover { background: #dc3545; color: white; }

        .pagination-container {
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 15px;
            border-top: 1px solid var(--border-color);
            background: var(--card-bg);
        }

        .pagination-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .pagination {
            display: flex;
            gap: 5px;
            list-style: none;
            flex-wrap: wrap;
            margin: 0;
            padding: 0;
            justify-content: center;
        }

        .page-link {
            padding: 8px 14px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s;
            font-size: 0.8rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-weight: 500;
        }

        .page-link:hover {
            background: #c8a86b;
            color: white;
            border-color: #c8a86b;
            transform: translateY(-2px);
        }

        .page-item.active .page-link {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            color: white;
            border-color: #c8a86b;
            box-shadow: 0 2px 8px rgba(200,168,107,0.3);
        }

        .page-item.disabled .page-link {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
            transform: none;
        }

        .limit-selector {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.8rem;
            color: var(--text-primary);
            background: var(--bg-primary);
            padding: 6px 12px;
            border-radius: 8px;
        }

        .limit-selector select {
            padding: 5px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.75rem;
            background: var(--input-bg);
            color: var(--text-primary);
            cursor: pointer;
        }

        .page-info {
            font-size: 0.75rem;
            color: var(--text-secondary);
            background: var(--bg-primary);
            padding: 5px 12px;
            border-radius: 20px;
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

        .btn-success {
            background: #28a745;
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

        .search-input {
            padding: 5px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            width: 250px;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-select {
            padding: 5px 10px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .text-center {
            text-align: center;
        }

        .modal-content {
            background: var(--modal-bg);
            border: 1px solid var(--modal-border);
        }

        .modal-header {
            background: var(--modal-header-bg);
            color: var(--modal-header-color);
            border-bottom-color: var(--modal-border);
        }

        .modal-header .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }

        [data-theme="dark"] .modal-header .btn-close {
            filter: invert(0);
            opacity: 0.8;
        }

        .modal-footer {
            border-top-color: var(--modal-border);
        }

        .modal-title {
            color: var(--modal-header-color);
        }

        .alert-danger {
            background-color: var(--alert-danger-bg);
            color: var(--alert-danger-text);
            border-color: var(--alert-danger-border);
        }

        .alert-success {
            background-color: var(--alert-success-bg);
            color: var(--alert-success-text);
            border-color: var(--alert-success-border);
        }

        .alert-warning {
            background-color: var(--alert-warning-bg);
            color: var(--alert-warning-text);
            border-color: var(--alert-warning-border);
        }

        [data-theme="dark"] .swal2-popup {
            background: var(--swal2-bg);
            color: var(--swal2-text);
        }

        [data-theme="dark"] .swal2-title {
            color: var(--swal2-text);
        }

        [data-theme="dark"] .swal2-html-container {
            color: var(--text-secondary);
        }

        [data-theme="dark"] .swal2-input,
        [data-theme="dark"] .swal2-select,
        [data-theme="dark"] .swal2-textarea {
            background: var(--swal2-input-bg);
            color: var(--swal2-text);
            border-color: var(--swal2-input-border);
        }

        [data-theme="dark"] .swal2-input:focus,
        [data-theme="dark"] .swal2-select:focus,
        [data-theme="dark"] .swal2-textarea:focus {
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
        }

        [data-theme="dark"] .swal2-validation-message {
            background: var(--alert-danger-bg);
            color: var(--alert-danger-text);
        }

        .form-label {
            color: var(--text-secondary);
        }

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

        @media (max-width: 1200px) {
            .admin-table {
                table-layout: auto;
                min-width: 1000px;
            }
            .table-wrapper {
                overflow-x: auto;
            }
        }

        @media (max-width: 992px) {
            .admin-main {
                padding: 100px 15px 15px;
            }
            .search-input {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .admin-main {
                padding: 100px 10px 10px;
            }
            .pagination-container {
                gap: 12px;
                padding: 15px;
            }
            .tab-btn {
                padding: 4px 8px;
                font-size: 0.65rem;
            }
            .tab-badge {
                font-size: 0.55rem;
            }
            .btn-export {
                padding: 4px 8px;
                font-size: 0.7rem;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            .page-link {
                padding: 6px 10px;
                font-size: 0.7rem;
            }
            .pagination {
                gap: 3px;
            }
        }

        .compact-swal .swal2-popup {
            padding: 2rem 2.5rem !important;
            font-size: 0.75rem !important;
            margin: 0 auto !important;
        }
        .compact-swal .swal2-html-container {
            margin: 0.8rem 0 !important;
            padding: 0 !important;
        }
        .compact-swal .swal2-actions {
            margin-top: 0.8rem !important;
        }
        .compact-swal .swal2-title {
            padding: 0.5rem 0 !important;
            font-size: 1rem !important;
        }
        .compact-swal .form-label {
            font-size: 0.7rem !important;
            margin-bottom: 5px !important;
        }
        .compact-swal .mb-2 {
            margin-bottom: 0.5rem !important;
        }
        .compact-swal input, .compact-swal select {
            padding: 6px 10px !important;
            font-size: 0.75rem !important;
            min-height: 32px !important;
            width: 100% !important;
        }
    </style>
</head>
<body>

<div class="admin-container">
    <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="admin-main">
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-users"></i> Gestión de Usuarios</h1>
                <p>Administra todos los usuarios de la plataforma</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-primary" id="showCreateUserModal">
                    <i class="fas fa-user-plus"></i> Nuevo Usuario
                </button>
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
            </div>
        </div>
        
        <div class="section-container">
            <div class="table-tabs">
                <button class="tab-btn <?php echo $active_tab == 'todos' ? 'active' : ''; ?>" data-tab="todos">
                    <i class="fas fa-users"></i> Todos
                    <span class="tab-badge"><?php echo $stats['todos']; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab == 'free' ? 'active' : ''; ?>" data-tab="free">
                    <i class="fas fa-user"></i> Free
                    <span class="tab-badge"><?php echo $stats['free']; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab == 'pro' ? 'active' : ''; ?>" data-tab="pro">
                    <i class="fas fa-star"></i> Pro
                    <span class="tab-badge"><?php echo $stats['pro']; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab == 'premium' ? 'active' : ''; ?>" data-tab="premium">
                    <i class="fas fa-gem"></i> Premium
                    <span class="tab-badge"><?php echo $stats['premium']; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab == 'elite' ? 'active' : ''; ?>" data-tab="elite">
                    <i class="fas fa-crown"></i> Elite
                    <span class="tab-badge"><?php echo $stats['elite']; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab == 'sistema' ? 'active' : ''; ?>" data-tab="sistema">
                    <i class="fas fa-building"></i> Sistema
                    <span class="tab-badge"><?php echo $stats['sistema']; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab == 'activos' ? 'active' : ''; ?>" data-tab="activos">
                    <i class="fas fa-check-circle"></i> Activos
                    <span class="tab-badge"><?php echo $stats['activos']; ?></span>
                </button>
                <button class="tab-btn <?php echo $active_tab == 'inactivos' ? 'active' : ''; ?>" data-tab="inactivos">
                    <i class="fas fa-trash-alt"></i> Inactivos
                    <span class="tab-badge"><?php echo $stats['inactivos']; ?></span>
                </button>
            </div>
            
            <div class="filter-bar">
                <form method="GET" class="filter-form" id="filterForm">
                    <input type="hidden" name="tab" value="<?php echo $active_tab; ?>">
                    <input type="hidden" name="page" value="1">
                    <input type="text" name="search" class="search-input" placeholder="Buscar por nombre, email, usuario o ID..." value="<?php echo htmlspecialchars($current_search); ?>">
                    <select name="role_filter" class="form-select" style="width: auto;">
                        <option value="">Todos los roles</option>
                        <?php foreach ($rolesList as $role): ?>
                            <option value="<?php echo $role['nombre']; ?>" <?php echo $current_roleFilter === $role['nombre'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst($role['nombre']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="?tab=<?php echo $active_tab; ?>" class="btn btn-secondary">Limpiar</a>
                </form>
            </div>
            
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Contacto</th>
                            <th>Rol</th>
                            <th>Membresía</th>
                            <th>Estado</th>
                            <th>Registro</th>
                            <th>Último acceso</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($userData['users']) > 0): ?>
                            <?php foreach ($userData['users'] as $user): ?>
                                <tr data-user-id="<?php echo $user['id']; ?>" data-user-role="<?php echo $user['role']; ?>">
                                    <td>#<?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar">
                                                <?php echo strtoupper(substr($user['full_name'] ?? $user['username'], 0, 1)); ?>
                                            </div>
                                            <div class="user-info">
                                                <strong><?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></strong>
                                                <small><?php echo htmlspecialchars($user['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                                        <small><?php echo htmlspecialchars($user['username']); ?></small>
                                    </td>
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
                                        <span class="badge badge-<?php echo $user['membership_tier']; ?>">
                                            <?php echo ucfirst($user['membership_tier'] ?? 'free'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $user['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                            <?php echo $user['status'] == 'active' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                                    <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="edit-user.php?id=<?php echo $user['id']; ?>" class="btn-icon edit" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button class="btn-icon view" onclick="viewUser(<?php echo $user['id']; ?>)" title="Ver detalles">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn-icon login" onclick="loginAsUser(<?php echo $user['id']; ?>)" title="Iniciar sesión como este usuario">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['status'] == 'active'): ?>
                                                <button class="btn-icon suspend" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'active')" title="Desactivar">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-icon suspend" onclick="toggleUserStatus(<?php echo $user['id']; ?>, 'inactive')" title="Activar">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($admin['role'] === 'superadmin' && $user['role'] !== 'superadmin'): ?>
                                                <button class="btn-icon delete" onclick="deleteUser(<?php echo $user['id']; ?>)" title="Eliminar permanentemente">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">No se encontraron usuarios</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="pagination-container">
                <?php if ($userData['totalPages'] > 1): ?>
                <div class="pagination-wrapper">
                    <ul class="pagination">
                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="#" onclick="changePage(<?php echo $current_page - 1; ?>); return false;">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        </li>
                        
                        <?php 
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($userData['totalPages'], $current_page + 2);
                        
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="#" onclick="changePage(1); return false;">1</a></li>';
                            if ($start_page > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $current_page ? 'active' : ''; ?>">
                                <a class="page-link" href="#" onclick="changePage(<?php echo $i; ?>); return false;"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; 
                        
                        if ($end_page < $userData['totalPages']) {
                            if ($end_page < $userData['totalPages'] - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="#" onclick="changePage(' . $userData['totalPages'] . '); return false;">' . $userData['totalPages'] . '</a></li>';
                        }
                        ?>
                        
                        <li class="page-item <?php echo $current_page >= $userData['totalPages'] ? 'disabled' : ''; ?>">
                            <a class="page-link" href="#" onclick="changePage(<?php echo $current_page + 1; ?>); return false;">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </div>
                <?php endif; ?>
                
                <div class="limit-selector">
                    <span><i class="fas fa-list-ul"></i> Mostrar:</span>
                    <select id="limit_select" onchange="changeLimit(this.value)">
                        <option value="10" <?php echo $current_limit == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="20" <?php echo $current_limit == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="50" <?php echo $current_limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $current_limit == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo $current_limit == 200 ? 'selected' : ''; ?>>200</option>
                    </select>
                    <span>registros por página</span>
                </div>
                
                <div class="page-info">
                    <i class="fas fa-database"></i> Mostrando <?php echo count($userData['users']); ?> de <?php echo $userData['total']; ?> usuarios | 
                    <i class="fas fa-file"></i> Página <?php echo $current_page; ?> de <?php echo $userData['totalPages']; ?>
                </div>
            </div>
        </div>
    </main>
</div>

<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<!-- SweetAlert2 - CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>

<script>
    const token = localStorage.getItem('auth_token');
    
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
        
        document.querySelectorAll('.tab-btn').forEach(function(tab) {
            tab.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.set('tab', tabName);
                urlParams.set('page', 1);
                window.location.href = '?' + urlParams.toString();
            });
        });
        
        document.getElementById('exportCsvBtn').addEventListener('click', function(e) {
            e.preventDefault();
            exportUsers('csv');
        });
        
        document.getElementById('exportExcelBtn').addEventListener('click', function(e) {
            e.preventDefault();
            exportUsers('excel');
        });
        
        document.getElementById('exportPdfBtn').addEventListener('click', function(e) {
            e.preventDefault();
            exportUsers('pdf');
        });
        
        const showCreateBtn = document.getElementById('showCreateUserModal');
        if (showCreateBtn) {
            showCreateBtn.addEventListener('click', function() {
                ensureCreateUserModal();
                document.getElementById('createFullName').value = '';
                document.getElementById('createEmail').value = '';
                document.getElementById('createUsername').value = '';
                document.getElementById('createPhone').value = '';
                document.getElementById('createRole').value = '6';
                document.getElementById('createMembershipTier').value = 'free';
                document.getElementById('createPassword').value = '';
                document.getElementById('createConfirmPassword').value = '';
                document.getElementById('createUserError').style.display = 'none';
                new bootstrap.Modal(document.getElementById('createUserModal')).show();
            });
        }
    });
    
    function exportUsers(format) {
        const tab = '<?php echo $active_tab; ?>';
        const search = '<?php echo htmlspecialchars($current_search); ?>';
        const roleFilter = '<?php echo htmlspecialchars($current_roleFilter); ?>';
        
        let url = 'export-users.php?format=' + format + '&tab=' + tab;
        if (search) url += '&search=' + encodeURIComponent(search);
        if (roleFilter) url += '&role_filter=' + encodeURIComponent(roleFilter);
        
        window.location.href = url;
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
    
    function ensureViewModal() {
        if (!document.getElementById('viewUserModal')) {
            const modalHtml = `
                <div class="modal fade" id="viewUserModal" tabindex="-1" data-bs-backdrop="static">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">
                            <div class="modal-header py-2 px-3">
                                <h6 class="modal-title"><i class="fas fa-user me-1"></i> Detalles del Usuario</h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-3">
                                <div id="viewUserLoading" class="text-center py-3">
                                    <div class="spinner-border spinner-border-sm text-secondary"></div>
                                    <p class="small mt-2 mb-0">Cargando...</p>
                                </div>
                                <div id="viewUserContent" style="display: none;"></div>
                            </div>
                            <div class="modal-footer py-2 px-3">
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
    }
    
    function viewUser(userId) {
        ensureViewModal();
        document.getElementById('viewUserLoading').style.display = 'block';
        document.getElementById('viewUserContent').style.display = 'none';
        new bootstrap.Modal(document.getElementById('viewUserModal')).show();
        
        $.ajax({
            url: '/api/v1/admin.php?action=get_user_details&user_id=' + userId,
            method: 'GET',
            headers: { 'Authorization': 'Bearer ' + token },
            success: function(response) {
                document.getElementById('viewUserLoading').style.display = 'none';
                if (response.success) {
                    const user = response.data;
                    let statusBadge = user.activo == 1 ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>';
                    let verifiedBadge = user.email_verificado == 1 ? '<span class="badge bg-success">Verificado</span>' : '<span class="badge bg-warning">No verificado</span>';
                    
                    document.getElementById('viewUserContent').innerHTML = `
                        <div class="text-center mb-3">
                            <div style="width: 60px; height: 60px; background: linear-gradient(135deg, #c8a86b, #a07e4a); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto; color: white; font-size: 1.5rem; font-weight: bold;">${(user.nombre_completo || user.username).charAt(0).toUpperCase()}</div>
                            <h5 class="mt-2 mb-0">${escapeHtml(user.nombre_completo || user.username)}</h5>
                            <p class="text-muted small mb-0">${escapeHtml(user.email)}</p>
                        </div>
                        <div class="row g-2">
                            <div class="col-6"><strong>ID:</strong> #${user.id}</div>
                            <div class="col-6"><strong>Usuario:</strong> ${escapeHtml(user.username)}</div>
                            <div class="col-6"><strong>Teléfono:</strong> ${escapeHtml(user.telefono || 'N/A')}</div>
                            <div class="col-6"><strong>Rol:</strong> ${escapeHtml(user.role_name || 'usuario')}</div>
                            <div class="col-6"><strong>Membresía:</strong> ${escapeHtml(user.tipo_cuenta || 'free')}</div>
                            <div class="col-6"><strong>Estado:</strong> ${statusBadge}</div>
                            <div class="col-6"><strong>Email verificado:</strong> ${verifiedBadge}</div>
                            <div class="col-6"><strong>Registro:</strong> ${new Date(user.created_at).toLocaleDateString('es-CO')}</div>
                            <div class="col-12"><strong>Último acceso:</strong> ${user.ultimo_acceso ? new Date(user.ultimo_acceso).toLocaleString('es-CO') : 'Nunca'}</div>
                        </div>
                    `;
                    document.getElementById('viewUserContent').style.display = 'block';
                } else {
                    document.getElementById('viewUserContent').innerHTML = '<div class="alert alert-danger small mb-0">Error al cargar los detalles</div>';
                    document.getElementById('viewUserContent').style.display = 'block';
                }
            },
            error: function() {
                document.getElementById('viewUserLoading').style.display = 'none';
                document.getElementById('viewUserContent').innerHTML = '<div class="alert alert-danger small mb-0">Error al cargar los detalles</div>';
                document.getElementById('viewUserContent').style.display = 'block';
            }
        });
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
    
    // ============================================
    // FUNCIONES PARA ELIMINAR USUARIOS CON SWEETALERT2
    // ============================================
    
    function deleteUser(userId) {
        // Obtener el rol del usuario desde la fila de la tabla
        const row = document.querySelector(`tr[data-user-id="${userId}"]`);
        if (row) {
            const role = row.getAttribute('data-user-role');
            if (role === 'superadmin') {
                Swal.fire({
                    icon: 'warning',
                    title: 'No se puede eliminar',
                    text: 'No puedes eliminar a un Superadmin.',
                    confirmButtonColor: '#c8a86b'
                });
                return;
            }
        }
        
        // Mostrar diálogo de confirmación
        Swal.fire({
            title: '¿Eliminar usuario?',
            text: "Esta acción eliminará al usuario de forma permanente. No se puede deshacer.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                // Mostrar loading
                Swal.fire({
                    title: 'Eliminando usuario...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                // Realizar la petición DELETE
                fetch('/api/v1/admin.php', {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ 
                        action: 'delete_user_permanently', 
                        user_id: userId 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Eliminado!',
                            text: 'El usuario ha sido eliminado permanentemente.',
                            confirmButtonColor: '#c8a86b',
                            timer: 1500
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Error al eliminar el usuario',
                            confirmButtonColor: '#c8a86b'
                        });
                    }
                })
                .catch(() => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error de conexión',
                        text: 'No se pudo eliminar el usuario. Intenta nuevamente.',
                        confirmButtonColor: '#c8a86b'
                    });
                });
            }
        });
    }
    
    function loginAsUser(userId) {
        Swal.fire({
            title: 'Iniciar sesión como usuario',
            text: 'Verás exactamente lo que este usuario ve en el sistema.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#c8a86b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Iniciando sesión...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '/api/v1/admin.php',
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                    data: JSON.stringify({ action: 'impersonate_user', user_id: userId }),
                    success: function(response) {
                        if (response.success) {
                            localStorage.setItem('auth_token', response.token);
                            window.location.href = '/dashboard/user/index.php';
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Error al iniciar sesión',
                                confirmButtonColor: '#c8a86b'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo iniciar sesión como el usuario.',
                            confirmButtonColor: '#c8a86b'
                        });
                    }
                });
            }
        });
    }
    
    function toggleUserStatus(userId, currentStatus) {
        const newStatus = currentStatus === 'active' ? 0 : 1;
        const action = newStatus === 1 ? 'activar' : 'desactivar';
        const actionText = newStatus === 1 ? 'Activar' : 'Desactivar';
        
        Swal.fire({
            title: actionText + ' usuario',
            text: `El usuario quedará ${newStatus === 1 ? 'activo' : 'inactivo'}. ¿Estás seguro?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus === 1 ? '#28a745' : '#ffc107',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, ' + action,
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '/api/v1/admin.php',
                    method: 'PUT',
                    headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                    data: JSON.stringify({ action: 'toggle_user_status', user_id: userId, active: newStatus }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: `Usuario ${newStatus === 1 ? 'activado' : 'desactivado'}`,
                                text: `El usuario ha sido ${newStatus === 1 ? 'activado' : 'desactivado'} correctamente.`,
                                confirmButtonColor: '#c8a86b',
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Error al cambiar estado',
                                confirmButtonColor: '#c8a86b'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo cambiar el estado del usuario.',
                            confirmButtonColor: '#c8a86b'
                        });
                    }
                });
            }
        });
    }
    
    function ensureCreateUserModal() {
        if (!document.getElementById('createUserModal')) {
            const modalHtml = `
                <div class="modal fade" id="createUserModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-lg">
                        <div class="modal-content">
                            <div class="modal-header py-2 px-3">
                                <h6 class="modal-title"><i class="fas fa-user-plus me-1"></i> Crear Usuario</h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body p-3">
                                <div id="createUserError" class="alert alert-danger small py-1 px-2 mb-2" style="display: none;"></div>
                                <form id="createUserForm">
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Nombre completo *</label>
                                        <input type="text" id="createFullName" class="form-control form-control-sm" placeholder="Nombre completo">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Email *</label>
                                        <input type="email" id="createEmail" class="form-control form-control-sm" placeholder="Email">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Usuario *</label>
                                        <input type="text" id="createUsername" class="form-control form-control-sm" placeholder="Usuario">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Teléfono</label>
                                        <input type="text" id="createPhone" class="form-control form-control-sm" placeholder="Teléfono">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Rol *</label>
                                        <select id="createRole" class="form-select form-select-sm">
                                            <option value="6">Usuario</option>
                                            <option value="5">Asesor</option>
                                            <option value="4">Técnico</option>
                                            <option value="3">Contador</option>
                                            <option value="2">Ingeniero</option>
                                            <?php if ($admin['role'] === 'superadmin'): ?>
                                            <option value="1">Superadmin</option>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Membresía</label>
                                        <select id="createMembershipTier" class="form-select form-select-sm">
                                            <option value="free">Free</option>
                                            <option value="pro">Pro</option>
                                            <option value="premium">Premium</option>
                                            <option value="elite">Elite</option>
                                        </select>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Contraseña *</label>
                                        <input type="password" id="createPassword" class="form-control form-control-sm" placeholder="Contraseña">
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label small fw-bold">Confirmar contraseña *</label>
                                        <input type="password" id="createConfirmPassword" class="form-control form-control-sm" placeholder="Confirmar contraseña">
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer py-2 px-3">
                                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-sm" id="saveUserBtn" style="background: #c8a86b; border-color: #c8a86b; color: white;">Crear Usuario</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHtml);
        }
    }
    
    document.addEventListener('click', function(e) {
        if (e.target.id === 'saveUserBtn') {
            const fullName = document.getElementById('createFullName').value.trim();
            const email = document.getElementById('createEmail').value.trim();
            const username = document.getElementById('createUsername').value.trim();
            const phone = document.getElementById('createPhone').value.trim();
            const role = document.getElementById('createRole').value;
            const membershipTier = document.getElementById('createMembershipTier').value;
            const password = document.getElementById('createPassword').value;
            const confirmPassword = document.getElementById('createConfirmPassword').value;
            const errorDiv = document.getElementById('createUserError');
            
            if (!fullName || !email || !username || !password) {
                errorDiv.textContent = 'Por favor complete todos los campos obligatorios';
                errorDiv.style.display = 'block';
                setTimeout(() => errorDiv.style.display = 'none', 3000);
                return;
            }
            if (password !== confirmPassword) {
                errorDiv.textContent = 'Las contraseñas no coinciden';
                errorDiv.style.display = 'block';
                setTimeout(() => errorDiv.style.display = 'none', 3000);
                return;
            }
            if (password.length < 6) {
                errorDiv.textContent = 'La contraseña debe tener al menos 6 caracteres';
                errorDiv.style.display = 'block';
                setTimeout(() => errorDiv.style.display = 'none', 3000);
                return;
            }
            
            errorDiv.style.display = 'none';
            const saveBtn = document.getElementById('saveUserBtn');
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creando...';
            
            $.ajax({
                url: '/api/v1/admin.php',
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                data: JSON.stringify({
                    action: 'create_user',
                    full_name: fullName,
                    email: email,
                    username: username,
                    phone: phone,
                    role_id: role,
                    membership_tier: membershipTier,
                    password: password
                }),
                success: function(response) {
                    if (response.success) {
                        const modal = bootstrap.Modal.getInstance(document.getElementById('createUserModal'));
                        if (modal) modal.hide();
                        location.reload();
                    } else {
                        errorDiv.textContent = response.message || 'Error al crear el usuario';
                        errorDiv.style.display = 'block';
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = 'Crear Usuario';
                        setTimeout(() => errorDiv.style.display = 'none', 3000);
                    }
                },
                error: function(xhr) {
                    errorDiv.textContent = xhr.responseJSON?.message || 'Error al crear el usuario';
                    errorDiv.style.display = 'block';
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = 'Crear Usuario';
                    setTimeout(() => errorDiv.style.display = 'none', 3000);
                }
            });
        }
    });
</script>
</body>
</html>