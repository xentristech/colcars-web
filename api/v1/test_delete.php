<?php
// ============================================
// TEST DE ELIMINACIÓN DIRECTA (SIN AUTENTICACIÓN)
// ============================================

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// Incluir archivos
require_once '../../config/database.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin-auth.php';
require_once '../../includes/audit-log.php';

// Crear instancias
$auth = new Authentication($pdo);
$adminAuth = new AdminAuth($pdo);

// Obtener un admin real de la base de datos (el primero que encuentre)
$stmt = $pdo->query("SELECT u.*, r.nombre as role_name FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre = 'superadmin' LIMIT 1");
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    echo json_encode(['error' => 'No se encontró un superadmin en la base de datos']);
    exit;
}

$result = [
    'test' => 'DELETE User Test (Directo)',
    'timestamp' => date('Y-m-d H:i:s'),
    'admin_found' => [
        'id' => $admin['id'],
        'email' => $admin['email'],
        'role' => $admin['role_name']
    ]
];

// Verificar que el método DELETE está disponible
$result['method'] = $_SERVER['REQUEST_METHOD'];

// Obtener el body de la petición
$input = json_decode(file_get_contents('php://input'), true);
$result['input_received'] = $input;

// Simular la acción DELETE
$action = $input['action'] ?? '';
$userId = $input['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'DELETE' && $action === 'delete_user_permanently') {
    
    if (!$userId) {
        echo json_encode(['error' => 'user_id required']);
        exit;
    }
    
    // Verificar que el usuario existe
    $userStmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $userStmt->execute([$userId]);
    $userToDelete = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userToDelete) {
        $result['error'] = 'Usuario no encontrado';
        echo json_encode($result);
        exit;
    }
    
    $result['user_to_delete'] = [
        'id' => $userToDelete['id'],
        'email' => $userToDelete['email'],
        'nombre' => $userToDelete['nombre_completo']
    ];
    
    // Verificar que no sea superadmin
    if ($userToDelete['rol_id'] == $admin['rol_id']) {
        $result['error'] = 'No se puede eliminar a otro superadmin';
        echo json_encode($result);
        exit;
    }
    
    // Probar eliminación (soft delete primero)
    try {
        $pdo->beginTransaction();
        
        // Soft delete
        $updateStmt = $pdo->prepare("UPDATE usuarios SET activo = 0, deleted_at = NOW() WHERE id = ?");
        $updateResult = $updateStmt->execute([$userId]);
        $affectedRows = $updateStmt->rowCount();
        
        $pdo->commit();
        
        $result['soft_delete'] = [
            'success' => $updateResult,
            'affected_rows' => $affectedRows,
            'query' => "UPDATE usuarios SET activo = 0, deleted_at = NOW() WHERE id = $userId"
        ];
        
        // Si funciona el soft delete, probar el DELETE permanente
        if ($updateResult) {
            $pdo->beginTransaction();
            
            // Primero eliminar registros relacionados (ejemplo)
            $tables = ['publicaciones', 'payments', 'favoritos', 'messages'];
            $deletedRelated = [];
            
            foreach ($tables as $table) {
                // Verificar si la tabla tiene columna user_id o usuario_id
                $cols = $pdo->query("SHOW COLUMNS FROM $table")->fetchAll(PDO::FETCH_COLUMN);
                $colName = in_array('user_id', $cols) ? 'user_id' : (in_array('usuario_id', $cols) ? 'usuario_id' : null);
                
                if ($colName) {
                    $delStmt = $pdo->prepare("DELETE FROM $table WHERE $colName = ?");
                    $delStmt->execute([$userId]);
                    $deletedRelated[$table] = $delStmt->rowCount();
                }
            }
            
            // Eliminar usuario permanentemente
            $deleteStmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $deleteResult = $deleteStmt->execute([$userId]);
            $permanentDeleted = $deleteStmt->rowCount();
            
            $pdo->commit();
            
            $result['permanent_delete'] = [
                'success' => $deleteResult,
                'affected_rows' => $permanentDeleted,
                'related_deleted' => $deletedRelated,
                'query' => "DELETE FROM usuarios WHERE id = $userId"
            ];
        }
        
        $result['status'] = 'SUCCESS';
        $result['message'] = 'Prueba completada exitosamente';
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $result['status'] = 'ERROR';
        $result['error'] = 'Error en la prueba: ' . $e->getMessage();
        $result['trace'] = $e->getTraceAsString();
    }
} else {
    $result['status'] = 'WARNING';
    $result['message'] = 'No se recibió una petición DELETE con action=delete_user_permanently';
    $result['how_to_test'] = 'Usa: curl -X DELETE "https://colcars.com/api/v1/test_delete.php" -d "{\"action\":\"delete_user_permanently\",\"user_id\":1}"';
}

echo json_encode($result, JSON_PRETTY_PRINT);
?>