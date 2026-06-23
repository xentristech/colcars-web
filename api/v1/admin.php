<?php
// ========== BLOQUE DE DIAGNÓSTICO (PRIMERO - ANTES DE CUALQUIER require_once) ==========
// Activar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Verificar que podemos escribir en el log
error_log("=== ADMIN.PHP DIAGNÓSTICO INICIADO ===");

// Verificar que los archivos existen ANTES de requerirlos
$requiredFiles = [
    '../../config/database.php',
    '../../config/config.php',
    '../../includes/auth.php',
    '../../includes/admin-auth.php',
    '../../includes/audit-log.php'
];

foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (!file_exists($fullPath)) {
        $errorMsg = "ERROR: Archivo no existe: " . $fullPath;
        error_log($errorMsg);
        http_response_code(500);
        echo json_encode(['error' => $errorMsg, 'file' => $file, 'full_path' => $fullPath]);
        exit;
    }
    error_log("Archivo encontrado: " . $fullPath);
}

error_log("TODOS los archivos existen. Continuando con require_once...");

// Probar que podemos incluir database.php antes de hacerlo
$testIncludePath = __DIR__ . '/../../config/database.php';
if (file_exists($testIncludePath)) {
    error_log("Ruta absoluta de database.php: " . $testIncludePath);
} else {
    error_log("Ruta absoluta NO existe: " . $testIncludePath);
}

error_log("=== FIN BLOQUE DIAGNÓSTICO ===");
// ========== FIN BLOQUE DIAGNÓSTICO ==========

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin-auth.php';
require_once '../../includes/audit-log.php';

// ========== SEGUNDO BLOQUE DE DIAGNÓSTICO (DESPUÉS DE require_once) ==========
// Verificar conexión a base de datos
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'pdo_exists' => isset($pdo),
        'pdo_valid' => $pdo ? true : false
    ]);
    exit;
}

// Probar conexión con una consulta simple
try {
    $stmt = $pdo->query("SELECT 1 as test");
    $result = $stmt->fetch();
    error_log("DB Connection test: SUCCESS - " . print_r($result, true));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database query failed',
        'message' => $e->getMessage()
    ]);
    exit;
}

// Verificar que las clases existen
$requiredClasses = ['Authentication', 'AdminAuth', 'AuditLog'];
foreach ($requiredClasses as $class) {
    if (!class_exists($class)) {
        http_response_code(500);
        echo json_encode([
            'error' => "Class $class not found",
            'message' => "La clase $class no está definida. Verificar que el archivo correspondiente se cargó correctamente."
        ]);
        exit;
    }
}
error_log("Todas las clases necesarias existen");
// ========== FIN SEGUNDO BLOQUE DIAGNÓSTICO ==========

$auth = new Authentication($pdo);
$adminAuth = new AdminAuth($pdo);

// Verify admin access
$admin = $adminAuth->verifyAdmin();

// Inicializar auditoría para el administrador
$audit = new AuditLog($pdo, $admin['id'], $admin['email'], $admin['role']);

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

// Helper function for validation
function validateRequiredFields($fields, $data) {
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing field: $field"]);
            return false;
        }
    }
    return true;
}

// Helper function for email validation
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid email format']);
        return false;
    }
    return true;
}

// Helper function for pagination
function getPaginationParams() {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}

switch ($method) {
    case 'GET':
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'get_user_details':
                $userId = $_GET['user_id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }
                
                if (!is_numeric($userId)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid user_id format']);
                    exit;
                }
                
                $query = "SELECT u.*, r.nombre as role_name,
                            (SELECT COUNT(*) FROM publicaciones WHERE usuario_id = u.id AND status = 'active') as publications_count,
                            (SELECT COUNT(*) FROM payments WHERE user_id = u.id AND status = 'completed') as payments_count,
                            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE user_id = u.id AND status = 'completed') as total_spent
                            FROM usuarios u 
                            LEFT JOIN roles r ON u.rol_id = r.id 
                            WHERE u.id = :id AND u.activo = 1";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    unset($user['password_hash']);
                    echo json_encode(['success' => true, 'data' => $user]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
                break;
                
            case 'get_user_stats':
                $userId = $_GET['user_id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }
                
                if (!is_numeric($userId)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid user_id format']);
                    exit;
                }
                
                $query = "SELECT 
                            (SELECT COUNT(*) FROM publicaciones WHERE usuario_id = :id AND status = 'active') as publications_count,
                            (SELECT COUNT(*) FROM payments WHERE user_id = :id AND status = 'completed') as payments_count,
                            (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE user_id = :id AND status = 'completed') as total_spent,
                            (SELECT COUNT(*) FROM favorites WHERE user_id = :id) as favorites_count,
                            (SELECT COUNT(*) FROM messages WHERE sender_id = :id OR receiver_id = :id) as messages_count
                            FROM DUAL";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $userId]);
                $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'data' => $stats]);
                break;
                
            case 'export_users':
                $role = $_GET['role'] ?? '';
                $status = $_GET['status'] ?? '';
                $search = $_GET['search'] ?? '';
                $format = $_GET['format'] ?? 'csv';
                
                $whereConditions = [];
                $params = [];
                
                if ($search) {
                    $whereConditions[] = "(u.nombre_completo LIKE :search OR u.email LIKE :search OR u.username LIKE :search)";
                    $params[':search'] = "%$search%";
                }
                if ($role) {
                    $whereConditions[] = "r.nombre = :role";
                    $params[':role'] = $role;
                }
                if ($status === 'active') {
                    $whereConditions[] = "u.activo = 1";
                } elseif ($status === 'inactive') {
                    $whereConditions[] = "u.activo = 0";
                } else {
                    $whereConditions[] = "1=1";
                }
                
                $whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";
                
                $query = "SELECT u.id, u.nombre_completo, u.email, u.username, u.telefono, r.nombre as role, 
                            u.tipo_cuenta, u.activo as status, u.created_at, u.ultimo_acceso 
                            FROM usuarios u 
                            JOIN roles r ON u.rol_id = r.id 
                            $whereClause 
                            ORDER BY u.created_at DESC";
                $stmt = $pdo->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if ($format === 'json') {
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="usuarios_export_' . date('Y-m-d') . '.json"');
                    echo json_encode(['users' => $users, 'export_date' => date('Y-m-d H:i:s'), 'total' => count($users)]);
                } else {
                    header('Content-Type: text/csv; charset=UTF-8');
                    header('Content-Disposition: attachment; filename="usuarios_export_' . date('Y-m-d') . '.csv"');
                    
                    $output = fopen('php://output', 'w');
                    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
                    fputcsv($output, ['ID', 'Nombre', 'Email', 'Usuario', 'Teléfono', 'Rol', 'Membresía', 'Estado', 'Fecha Registro', 'Último Acceso']);
                    
                    foreach ($users as $user) {
                        fputcsv($output, [
                            $user['id'],
                            $user['nombre_completo'],
                            $user['email'],
                            $user['username'],
                            $user['telefono'] ?? 'N/A',
                            $user['role'],
                            $user['tipo_cuenta'],
                            $user['status'] == 1 ? 'Activo' : 'Inactivo',
                            $user['created_at'],
                            $user['ultimo_acceso'] ?? 'Nunca'
                        ]);
                    }
                    fclose($output);
                }
                exit;
                break;
                
            case 'list_users':
                $search = $_GET['search'] ?? '';
                $role = $_GET['role'] ?? '';
                $status = $_GET['status'] ?? '';
                $sortBy = $_GET['sort_by'] ?? 'created_at';
                $sortOrder = $_GET['sort_order'] ?? 'DESC';
                $pagination = getPaginationParams();
                
                $allowedSortFields = ['id', 'nombre_completo', 'email', 'created_at', 'ultimo_acceso'];
                $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'created_at';
                $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
                
                $whereConditions = ["u.activo = 1"];
                $params = [];
                
                if ($search) {
                    $whereConditions[] = "(u.nombre_completo LIKE :search OR u.email LIKE :search OR u.username LIKE :search)";
                    $params[':search'] = "%$search%";
                }
                if ($role) {
                    $whereConditions[] = "r.nombre = :role";
                    $params[':role'] = $role;
                }
                if ($status === 'active') {
                    $whereConditions[] = "u.activo = 1";
                } elseif ($status === 'inactive') {
                    $whereConditions[] = "u.activo = 0";
                }
                
                $whereClause = implode(" AND ", $whereConditions);
                
                // Get total count
                $countQuery = "SELECT COUNT(*) as total FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE $whereClause";
                $countStmt = $pdo->prepare($countQuery);
                foreach ($params as $key => $value) {
                    $countStmt->bindValue($key, $value);
                }
                $countStmt->execute();
                $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Get paginated results
                $query = "SELECT u.id, u.nombre_completo, u.email, u.username, u.telefono, r.nombre as role, 
                            u.tipo_cuenta, u.activo as status, u.created_at, u.ultimo_acceso,
                            (SELECT COUNT(*) FROM publicaciones WHERE usuario_id = u.id AND status = 'active') as publications_count
                            FROM usuarios u 
                            JOIN roles r ON u.rol_id = r.id 
                            WHERE $whereClause 
                            ORDER BY $sortBy $sortOrder 
                            LIMIT :limit OFFSET :offset";
                $stmt = $pdo->prepare($query);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $pagination['limit'], PDO::PARAM_INT);
                $stmt->bindValue(':offset', $pagination['offset'], PDO::PARAM_INT);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Remove sensitive data
                foreach ($users as &$user) {
                    unset($user['password_hash']);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $users,
                    'pagination' => [
                        'current_page' => $pagination['page'],
                        'per_page' => $pagination['limit'],
                        'total' => (int)$total,
                        'last_page' => ceil($total / $pagination['limit'])
                    ]
                ]);
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;
        
    case 'POST':
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'create_user':
                if (!validateRequiredFields(['full_name', 'email', 'password', 'role_id'], $input)) {
                    exit;
                }
                
                if (!validateEmail($input['email'])) {
                    exit;
                }
                
                if (strlen($input['password']) < 6) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Password must be at least 6 characters']);
                    exit;
                }
                
                // Check if email exists
                $query = "SELECT id FROM usuarios WHERE email = :email";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':email' => $input['email']]);
                if ($stmt->fetch()) {
                    http_response_code(409);
                    echo json_encode(['error' => 'Email already exists']);
                    exit;
                }
                
                // Check if username exists (if provided)
                if (!empty($input['username'])) {
                    $query = "SELECT id FROM usuarios WHERE username = :username";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':username' => $input['username']]);
                    if ($stmt->fetch()) {
                        http_response_code(409);
                        echo json_encode(['error' => 'Username already exists']);
                        exit;
                    }
                }
                
                $hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);
                
                $pdo->beginTransaction();
                try {
                    $query = "INSERT INTO usuarios (nombre_completo, email, username, telefono, password_hash, rol_id, tipo_cuenta, activo, created_at) 
                            VALUES (:nombre_completo, :email, :username, :telefono, :password_hash, :rol_id, :tipo_cuenta, 1, NOW())";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':nombre_completo' => $input['full_name'],
                        ':email' => $input['email'],
                        ':username' => $input['username'] ?? $input['email'],
                        ':telefono' => $input['phone'] ?? null,
                        ':password_hash' => $hashedPassword,
                        ':rol_id' => $input['role_id'],
                        ':tipo_cuenta' => $input['membership_tier'] ?? 'free'
                    ]);
                    
                    $userId = $pdo->lastInsertId();
                    
                    $audit->registerCreate('usuario', $userId, [
                        'full_name' => $input['full_name'],
                        'email' => $input['email'],
                        'username' => $input['username'] ?? $input['email'],
                        'role_id' => $input['role_id'],
                        'membership_tier' => $input['membership_tier'] ?? 'free'
                    ], 'Usuario creado por administrador');
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'user_id' => $userId, 'message' => 'User created successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to create user: ' . $e->getMessage()]);
                }
                break;
                
            case 'impersonate_user':
                $userId = $input['user_id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }
                
                if (!is_numeric($userId)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid user_id format']);
                    exit;
                }
                
                $query = "SELECT u.*, r.nombre as role_name FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                
                if ($user['role_name'] === 'superadmin' && $admin['role'] !== 'superadmin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot impersonate superadmin']);
                    exit;
                }
                
                $token = $auth->generateToken($user);
                
                $audit->register('IMPERSONATE', 'usuario', $userId, null, null, null, 
                                "Administrador {$admin['email']} inició sesión como usuario {$user['email']}");
                
                echo json_encode(['success' => true, 'token' => $token, 'user' => [
                    'id' => $user['id'],
                    'name' => $user['nombre_completo'],
                    'email' => $user['email'],
                    'role' => $user['role_name']
                ]]);
                break;
                
            case 'send_test_email':
                $userId = $input['user_id'] ?? null;
                $emailType = $input['email_type'] ?? 'test';
                
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }
                
                $query = "SELECT email, nombre_completo FROM usuarios WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user) {
                    $audit->register('TEST_EMAIL', 'usuario', $userId, null, null, null, 
                                    "Email de prueba ($emailType) enviado a {$user['email']}");
                    echo json_encode(['success' => true, 'message' => 'Test email queued successfully']);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                }
                break;
                
            case 'bulk_action':
                $action_type = $input['action_type'] ?? '';
                $user_ids = $input['user_ids'] ?? [];
                
                if (!$action_type || empty($user_ids) || !is_array($user_ids)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid bulk action parameters']);
                    exit;
                }
                
                if (!in_array($action_type, ['activate', 'deactivate', 'delete'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid action type']);
                    exit;
                }
                
                $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
                
                $pdo->beginTransaction();
                try {
                    if ($action_type === 'activate') {
                        $query = "UPDATE usuarios SET activo = 1 WHERE id IN ($placeholders)";
                        $message = "Usuarios activados masivamente";
                    } elseif ($action_type === 'deactivate') {
                        $query = "UPDATE usuarios SET activo = 0 WHERE id IN ($placeholders)";
                        $message = "Usuarios desactivados masivamente";
                    } else {
                        $query = "UPDATE usuarios SET activo = 0 WHERE id IN ($placeholders)";
                        $message = "Usuarios eliminados (soft delete) masivamente";
                    }
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($user_ids);
                    $affected = $stmt->rowCount();
                    
                    $audit->register('BULK_' . strtoupper($action_type), 'usuarios', null, 
                                    ['user_ids' => $user_ids], ['action' => $action_type], 
                                    null, "$message: $affected usuarios afectados");
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'affected' => $affected, 'message' => $message]);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['error' => 'Bulk action failed: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;
        
    case 'PUT':
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'update_user':
                $userId = $input['user_id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }
                
                // Verify user exists
                $verifyQuery = "SELECT id, rol_id FROM usuarios WHERE id = :id";
                $verifyStmt = $pdo->prepare($verifyQuery);
                $verifyStmt->execute([':id' => $userId]);
                if (!$verifyStmt->fetch()) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                
                // Get old user data for audit
                $oldUserQuery = "SELECT nombre_completo, email, username, telefono, rol_id, tipo_cuenta, activo FROM usuarios WHERE id = :id";
                $oldStmt = $pdo->prepare($oldUserQuery);
                $oldStmt->execute([':id' => $userId]);
                $oldUser = $oldStmt->fetch(PDO::FETCH_ASSOC);
                
                $updateFields = [];
                $params = [':id' => $userId];
                
                if (isset($input['full_name']) && !empty($input['full_name'])) {
                    $updateFields[] = "nombre_completo = :nombre_completo";
                    $params[':nombre_completo'] = $input['full_name'];
                }
                if (isset($input['email']) && !empty($input['email'])) {
                    if (!validateEmail($input['email'])) {
                        exit;
                    }
                    $updateFields[] = "email = :email";
                    $params[':email'] = $input['email'];
                }
                if (isset($input['username']) && !empty($input['username'])) {
                    $updateFields[] = "username = :username";
                    $params[':username'] = $input['username'];
                }
                if (isset($input['phone'])) {
                    $updateFields[] = "telefono = :telefono";
                    $params[':telefono'] = $input['phone'];
                }
                if (isset($input['role_id']) && is_numeric($input['role_id'])) {
                    $updateFields[] = "rol_id = :rol_id";
                    $params[':rol_id'] = $input['role_id'];
                }
                if (isset($input['membership_tier'])) {
                    $updateFields[] = "tipo_cuenta = :tipo_cuenta";
                    $params[':tipo_cuenta'] = $input['membership_tier'];
                }
                if (isset($input['activo']) && in_array($input['activo'], [0, 1])) {
                    $updateFields[] = "activo = :activo";
                    $params[':activo'] = $input['activo'];
                }
                if (isset($input['password']) && !empty($input['password'])) {
                    if (strlen($input['password']) < 6) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Password must be at least 6 characters']);
                        exit;
                    }
                    $updateFields[] = "password_hash = :password_hash";
                    $params[':password_hash'] = password_hash($input['password'], PASSWORD_DEFAULT);
                }
                
                if (empty($updateFields)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'No fields to update']);
                    exit;
                }
                
                $query = "UPDATE usuarios SET " . implode(", ", $updateFields) . " WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                
                // Get new user data for audit
                $newUserQuery = "SELECT nombre_completo, email, username, telefono, rol_id, tipo_cuenta, activo FROM usuarios WHERE id = :id";
                $newStmt = $pdo->prepare($newUserQuery);
                $newStmt->execute([':id' => $userId]);
                $newUser = $newStmt->fetch(PDO::FETCH_ASSOC);
                
                $audit->registerUpdate('usuario', $userId, $oldUser, $newUser, 'Usuario editado por administrador');
                
                echo json_encode(['success' => true, 'message' => 'Usuario actualizado exitosamente']);
                break;
                
            case 'toggle_user_status':
                $userId = $input['user_id'] ?? null;
                $active = $input['active'] ?? null;
                
                if (!$userId || !in_array($active, [0, 1], true)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid parameters']);
                    exit;
                }
                
                // Check if user exists and is not superadmin if demoting
                $checkQuery = "SELECT u.activo, r.nombre as role_name FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = :id";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([':id' => $userId]);
                $userCheck = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userCheck) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                
                if ($userCheck['role_name'] === 'superadmin' && $admin['role'] !== 'superadmin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot modify superadmin status']);
                    exit;
                }
                
                $oldStatus = $userCheck['activo'];
                
                $query = "UPDATE usuarios SET activo = :activo WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':activo' => $active, ':id' => $userId]);
                
                $actionType = ($active == 1) ? 'ACTIVATE' : 'DEACTIVATE';
                $statusText = ($active == 1) ? 'activado' : 'desactivado';
                $audit->register($actionType, 'usuario', $userId, 
                                ['activo' => $oldStatus], ['activo' => $active], 
                                null, "Usuario $statusText por administrador");
                
                echo json_encode(['success' => true, 'message' => "Usuario $statusText exitosamente"]);
                break;
                
            case 'reset_password':
                $userId = $input['user_id'] ?? null;
                $newPassword = $input['password'] ?? null;
                
                if (!$userId || !$newPassword) {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID and password required']);
                    exit;
                }
                
                if (strlen($newPassword) < 6) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Password must be at least 6 characters']);
                    exit;
                }
                
                // Check if user exists
                $checkQuery = "SELECT email FROM usuarios WHERE id = :id";
                $checkStmt = $pdo->prepare($checkQuery);
                $checkStmt->execute([':id' => $userId]);
                if (!$checkStmt->fetch()) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $query = "UPDATE usuarios SET password_hash = :password_hash WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':password_hash' => $hashedPassword, ':id' => $userId]);
                
                $audit->register('RESET_PASSWORD', 'usuario', $userId, null, null, null, 
                                'Contraseña restablecida por administrador');
                
                echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
                break;
                
            case 'update_membership':
                $userId = $input['user_id'] ?? null;
                $membershipTier = $input['membership_tier'] ?? null;
                $expiryDate = $input['expiry_date'] ?? null;
                
                if (!$userId || !$membershipTier) {
                    http_response_code(400);
                    echo json_encode(['error' => 'User ID and membership tier required']);
                    exit;
                }
                
                $validTiers = ['free', 'basic', 'premium', 'enterprise'];
                if (!in_array($membershipTier, $validTiers)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid membership tier']);
                    exit;
                }
                
                $pdo->beginTransaction();
                try {
                    // Update user membership tier
                    $query = "UPDATE usuarios SET tipo_cuenta = :tipo_cuenta WHERE id = :id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([':tipo_cuenta' => $membershipTier, ':id' => $userId]);
                    
                    // Update or create membership record
                    if ($expiryDate) {
                        $checkQuery = "SELECT id FROM user_memberships WHERE user_id = :user_id";
                        $checkStmt = $pdo->prepare($checkQuery);
                        $checkStmt->execute([':user_id' => $userId]);
                        
                        if ($checkStmt->fetch()) {
                            $updateQuery = "UPDATE user_memberships SET membership_tier = :tier, expiry_date = :expiry_date WHERE user_id = :user_id";
                            $updateStmt = $pdo->prepare($updateQuery);
                            $updateStmt->execute([':tier' => $membershipTier, ':expiry_date' => $expiryDate, ':user_id' => $userId]);
                        } else {
                            $insertQuery = "INSERT INTO user_memberships (user_id, membership_tier, expiry_date, created_at) VALUES (:user_id, :tier, :expiry_date, NOW())";
                            $insertStmt = $pdo->prepare($insertQuery);
                            $insertStmt->execute([':user_id' => $userId, ':tier' => $membershipTier, ':expiry_date' => $expiryDate]);
                        }
                    }
                    
                    $audit->register('UPDATE_MEMBERSHIP', 'usuario', $userId, null, 
                                    ['membership_tier' => $membershipTier, 'expiry_date' => $expiryDate], 
                                    null, "Membresía actualizada a $membershipTier por administrador");
                    
                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Membership updated successfully']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to update membership: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;
        
    case 'DELETE':
        $action = $input['action'] ?? '';
        
        switch ($action) {
            case 'delete_user':
                $userId = $input['user_id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }
                
                // Get user info before deletion
                $query = "SELECT u.id, r.nombre as role_name, u.email, u.nombre_completo FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$user) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                
                if ($user['role_name'] === 'superadmin' && $admin['role'] !== 'superadmin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot delete superadmin']);
                    exit;
                }
                
                // CORREGIDO: Eliminado deleted_at
                $query = "UPDATE usuarios SET activo = 0 WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $userId]);
                
                $audit->register('SOFT_DELETE', 'usuario', $userId, null, ['activo' => 0], null, 
                                "Usuario {$user['email']} ({$user['nombre_completo']}) desactivado por administrador");
                
                echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
                break;
                
            case 'delete_user_permanently':
                if ($admin['role'] !== 'superadmin') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only superadmin can permanently delete users']);
                    exit;
                }
                
                $userId = $input['user_id'] ?? null;
                if (!$userId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'user_id required']);
                    exit;
                }
                
                // Get user data before deletion
                $userQuery = "SELECT u.id, r.nombre as role_name, u.email, u.nombre_completo, u.username FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = :id";
                $userStmt = $pdo->prepare($userQuery);
                $userStmt->execute([':id' => $userId]);
                $userToDelete = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$userToDelete) {
                    http_response_code(404);
                    echo json_encode(['error' => 'User not found']);
                    exit;
                }
                
                if ($userToDelete['role_name'] === 'superadmin' && $userToDelete['id'] != $admin['id']) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Cannot delete another superadmin']);
                    exit;
                }
                
                $pdo->beginTransaction();
                try {
                    // Delete related records
                    $tables = [
                        'publicaciones' => 'usuario_id',
                        'payments' => 'user_id',
                        'user_memberships' => 'user_id',
                        'favorites' => 'user_id',  // <--- CORREGIDO: favoritos → favorites, usuario_id → user_id
                        'dian_transactions' => 'user_id',
                        'audit_logs' => 'user_id'
                    ];
                    
                    foreach ($tables as $table => $column) {
                        // Verificar si la tabla existe antes de intentar eliminarla
                        $checkTable = $pdo->query("SHOW TABLES LIKE '$table'");
                        if ($checkTable->rowCount() > 0) {
                            $deleteQuery = "DELETE FROM $table WHERE $column = :user_id";
                            $deleteStmt = $pdo->prepare($deleteQuery);
                            $deleteStmt->execute([':user_id' => $userId]);
                        }
                    }
                    
                    // Delete messages
                    $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$userId, $userId]);
                    
                    // Delete user
                    $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$userId]);
                    
                    $pdo->commit();
                    
                    $audit->registerDelete('usuario', $userId, $userToDelete, 
                    "Usuario {$userToDelete['email']} ({$userToDelete['nombre_completo']}) eliminado permanentemente por superadmin");
                    
                    echo json_encode(['success' => true, 'message' => 'User permanently deleted']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to delete user: ' . $e->getMessage()]);
                }
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid action']);
                break;
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>