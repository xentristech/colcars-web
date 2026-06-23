<?php
/**
 * TEST COMPLETO PARA DIAGNÓSTICO DE ELIMINACIÓN
 * Este archivo prueba paso a paso la eliminación de usuarios
 */

// ============================================
// CONFIGURACIÓN INICIAL
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

// ============================================
// FUNCIÓN PARA RESPONDER
// ============================================
function responder($success, $message, $data = null, $error = null) {
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($error !== null) {
        $response['error'] = $error;
    }
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

// ============================================
// PRUEBA 1: VERIFICAR ARCHIVOS
// ============================================
$testResults = [];
$testResults[] = [
    'test' => '1. Verificando archivos necesarios',
    'status' => 'iniciando'
];

$requiredFiles = [
    '../../config/database.php',
    '../../includes/auth.php',
    '../../includes/admin-auth.php',
    '../../includes/audit-log.php'
];

$allFilesExist = true;
foreach ($requiredFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    $exists = file_exists($fullPath);
    $testResults[] = [
        'test' => '   Archivo: ' . $file,
        'status' => $exists ? '✅ OK' : '❌ FALTA',
        'path' => $fullPath
    ];
    if (!$exists) $allFilesExist = false;
}

if (!$allFilesExist) {
    responder(false, 'Faltan archivos necesarios', $testResults);
}

// ============================================
// PRUEBA 2: INCLUIR ARCHIVOS Y CONECTAR BD
// ============================================
$testResults[] = [
    'test' => '2. Incluyendo archivos y conectando a BD',
    'status' => 'iniciando'
];

try {
    require_once '../../config/database.php';
    $testResults[] = [
        'test' => '   database.php',
        'status' => '✅ OK'
    ];
} catch (Exception $e) {
    responder(false, 'Error al incluir database.php', $testResults, $e->getMessage());
}

try {
    require_once '../../includes/auth.php';
    $testResults[] = [
        'test' => '   auth.php',
        'status' => '✅ OK'
    ];
} catch (Exception $e) {
    responder(false, 'Error al incluir auth.php', $testResults, $e->getMessage());
}

try {
    require_once '../../includes/admin-auth.php';
    $testResults[] = [
        'test' => '   admin-auth.php',
        'status' => '✅ OK'
    ];
} catch (Exception $e) {
    responder(false, 'Error al incluir admin-auth.php', $testResults, $e->getMessage());
}

try {
    require_once '../../includes/audit-log.php';
    $testResults[] = [
        'test' => '   audit-log.php',
        'status' => '✅ OK'
    ];
} catch (Exception $e) {
    responder(false, 'Error al incluir audit-log.php', $testResults, $e->getMessage());
}

// Verificar conexión a BD
if (!isset($pdo) || !$pdo) {
    responder(false, 'No se pudo conectar a la base de datos', $testResults);
}
$testResults[] = [
    'test' => '   Conexión a BD',
    'status' => '✅ OK'
];

// ============================================
// PRUEBA 3: VERIFICAR CLASES
// ============================================
$testResults[] = [
    'test' => '3. Verificando clases',
    'status' => 'iniciando'
];

$classes = ['Authentication', 'AdminAuth', 'AuditLog'];
foreach ($classes as $class) {
    $exists = class_exists($class);
    $testResults[] = [
        'test' => '   Clase ' . $class,
        'status' => $exists ? '✅ OK' : '❌ FALTA'
    ];
    if (!$exists) {
        responder(false, 'Clase ' . $class . ' no encontrada', $testResults);
    }
}

// ============================================
// PRUEBA 4: VERIFICAR SUPERADMIN
// ============================================
$testResults[] = [
    'test' => '4. Buscando superadmin en la BD',
    'status' => 'iniciando'
];

try {
    $stmt = $pdo->query("SELECT u.*, r.nombre as role_name FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre = 'superadmin' LIMIT 1");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        $testResults[] = [
            'test' => '   Superadmin encontrado',
            'status' => '✅ OK',
            'data' => [
                'id' => $admin['id'],
                'email' => $admin['email'],
                'role' => $admin['role_name']
            ]
        ];
    } else {
        responder(false, 'No se encontró ningún superadmin en la BD', $testResults);
    }
} catch (Exception $e) {
    responder(false, 'Error al buscar superadmin', $testResults, $e->getMessage());
}

// ============================================
// PRUEBA 5: VERIFICAR USUARIOS DISPONIBLES
// ============================================
$testResults[] = [
    'test' => '5. Listando usuarios disponibles para eliminar',
    'status' => 'iniciando'
];

try {
    $stmt = $pdo->query("SELECT u.id, u.email, u.nombre_completo, r.nombre as role_name 
                         FROM usuarios u 
                         JOIN roles r ON u.rol_id = r.id 
                         WHERE r.nombre != 'superadmin' 
                         AND u.id != " . $admin['id'] . "
                         LIMIT 10");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($usuarios) > 0) {
        $testResults[] = [
            'test' => '   Usuarios disponibles',
            'status' => '✅ OK',
            'data' => $usuarios
        ];
    } else {
        $testResults[] = [
            'test' => '   Usuarios disponibles',
            'status' => '⚠️ ADVERTENCIA',
            'data' => 'No hay usuarios para eliminar (todos son superadmin)'
        ];
    }
} catch (Exception $e) {
    responder(false, 'Error al listar usuarios', $testResults, $e->getMessage());
}

// ============================================
// PRUEBA 6: PROBAR ELIMINACIÓN DIRECTA (SIN AUTENTICACIÓN)
// ============================================
$testResults[] = [
    'test' => '6. Probando eliminación directa (sin autenticación)',
    'status' => 'iniciando'
];

// Verificar si la petición es DELETE
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $userId = $input['user_id'] ?? null;
    $action = $input['action'] ?? '';
    
    $testResults[] = [
        'test' => '   Método DELETE recibido',
        'status' => '✅ OK',
        'data' => [
            'action' => $action,
            'user_id' => $userId
        ]
    ];
    
    if ($action === 'delete_user_permanently' && $userId) {
        $testResults[] = [
            'test' => '   Intentando eliminar usuario ID: ' . $userId,
            'status' => 'iniciando'
        ];
        
        try {
            // Verificar que el usuario existe
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmt->execute([$userId]);
            $userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$userToDelete) {
                $testResults[] = [
                    'test' => '   Usuario no encontrado',
                    'status' => '❌ ERROR',
                    'data' => 'El usuario con ID ' . $userId . ' no existe'
                ];
                responder(false, 'Usuario no encontrado', $testResults);
            }
            
            $testResults[] = [
                'test' => '   Usuario encontrado',
                'status' => '✅ OK',
                'data' => [
                    'id' => $userToDelete['id'],
                    'email' => $userToDelete['email'],
                    'nombre' => $userToDelete['nombre_completo'],
                    'rol_id' => $userToDelete['rol_id']
                ]
            ];
            
            // Verificar que no sea superadmin
            if ($userToDelete['rol_id'] == 1) {
                $testResults[] = [
                    'test' => '   Verificando rol',
                    'status' => '❌ ERROR',
                    'data' => 'No se puede eliminar a un superadmin'
                ];
                responder(false, 'No se puede eliminar a un superadmin', $testResults);
            }
            
            $testResults[] = [
                'test' => '   Verificando rol',
                'status' => '✅ OK',
                'data' => 'El usuario NO es superadmin, puede ser eliminado'
            ];
            
            // ==== INTENTAR ELIMINAR ====
            $pdo->beginTransaction();
            
            // Eliminar registros relacionados
            $relatedTables = ['publicaciones', 'payments', 'favoritos', 'messages', 'user_memberships'];
            $deletedCount = [];
            
            foreach ($relatedTables as $table) {
                // Verificar si la tabla existe
                $checkTable = $pdo->query("SHOW TABLES LIKE '$table'");
                if ($checkTable->rowCount() > 0) {
                    // Determinar nombre de la columna
                    if ($table === 'messages') {
                        $pdo->prepare("DELETE FROM messages WHERE sender_id = ? OR receiver_id = ?")->execute([$userId, $userId]);
                        $count = $pdo->prepare("SELECT ROW_COUNT() as count")->fetch()['count'] ?? 0;
                    } else {
                        $column = ($table === 'payments' || $table === 'user_memberships') ? 'user_id' : 'usuario_id';
                        $stmt = $pdo->prepare("DELETE FROM $table WHERE $column = ?");
                        $stmt->execute([$userId]);
                        $count = $stmt->rowCount();
                    }
                    $deletedCount[$table] = $count;
                }
            }
            
            // Eliminar el usuario
            $deleteStmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $deleteStmt->execute([$userId]);
            $rowsDeleted = $deleteStmt->rowCount();
            
            $pdo->commit();
            
            $testResults[] = [
                'test' => '   Eliminación completada',
                'status' => '✅ ÉXITO',
                'data' => [
                    'usuario_eliminado' => $rowsDeleted > 0,
                    'registros_relacionados_eliminados' => $deletedCount
                ]
            ];
            
            responder(true, 'Usuario eliminado correctamente', $testResults);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $testResults[] = [
                'test' => '   Error en eliminación',
                'status' => '❌ ERROR',
                'data' => $e->getMessage()
            ];
            responder(false, 'Error al eliminar usuario', $testResults, $e->getMessage());
        }
    } else {
        $testResults[] = [
            'test' => '   Acción no válida',
            'status' => '⚠️ ADVERTENCIA',
            'data' => 'Se esperaba action=delete_user_permanently'
        ];
        responder(false, 'Acción no válida', $testResults);
    }
} else {
    // Si es GET, mostrar instrucciones
    $testResults[] = [
        'test' => '   Método actual: ' . $_SERVER['REQUEST_METHOD'],
        'status' => 'ℹ️ INFO',
        'data' => 'Para probar la eliminación, envía una petición DELETE con: {"action":"delete_user_permanently","user_id":ID}'
    ];
    
    // Mostrar resumen
    $testResults[] = [
        'test' => '📋 RESUMEN DEL TEST',
        'status' => 'COMPLETADO',
        'data' => [
            'total_pruebas' => count($testResults),
            'archivos' => '✅ OK',
            'base_datos' => '✅ OK',
            'clases' => '✅ OK',
            'superadmin' => '✅ OK',
            'usuarios_disponibles' => count($usuarios ?? []),
            'instrucciones' => 'Para probar la eliminación, copia y pega el código JavaScript que aparece abajo en la consola (F12)'
        ]
    ];
    
    // Instrucciones para JavaScript
    $testResults[] = [
        'test' => '🔧 CÓDIGO PARA PROBAR DESDE LA CONSOLA',
        'status' => 'INSTRUCCIONES',
        'data' => [
            'codigo' => "
// Copia y pega esto en la consola (F12)
const userId = " . ($usuarios[0]['id'] ?? '2') . "; // Cambia por un ID válido

fetch('/api/v1/test_completo.php', {
    method: 'DELETE',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        action: 'delete_user_permanently',
        user_id: userId
    })
})
.then(r => r.json())
.then(d => {
    console.log('RESULTADO:', d);
    if (d.success) {
        console.log('✅ ELIMINACIÓN EXITOSA!');
    } else {
        console.error('❌ ERROR:', d.error || d.message);
    }
})
.catch(e => console.error('Error de conexión:', e));
"
        ]
    ];
    
    echo json_encode($testResults, JSON_PRETTY_PRINT);
}