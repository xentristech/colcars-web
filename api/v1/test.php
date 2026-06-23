<?php
// ============================================
// ARCHIVO DE PRUEBA PARA DIAGNÓSTICO
// ============================================

// Activar errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar respuesta JSON
header('Content-Type: application/json');

// Array para almacenar resultados de las pruebas
$results = [
    'test_name' => 'Diagnóstico API',
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// ============================================
// PRUEBA 1: Verificar que el archivo se ejecuta
// ============================================
$results['tests'][] = [
    'name' => 'Archivo ejecutándose',
    'status' => 'SUCCESS',
    'message' => 'El archivo test.php se está ejecutando correctamente'
];

// ============================================
// PRUEBA 2: Verificar paths de includes
// ============================================
$pathsToCheck = [
    '../../config/database.php',
    '../../config/config.php',
    '../../includes/auth.php',
    '../../includes/admin-auth.php',
    '../../includes/audit-log.php'
];

$pathResults = [];
$allPathsExist = true;

foreach ($pathsToCheck as $path) {
    $fullPath = __DIR__ . '/' . $path;
    $exists = file_exists($fullPath);
    $pathResults[] = [
        'path' => $path,
        'full_path' => $fullPath,
        'exists' => $exists
    ];
    if (!$exists) {
        $allPathsExist = false;
    }
}

$results['tests'][] = [
    'name' => 'Verificación de archivos includes',
    'status' => $allPathsExist ? 'SUCCESS' : 'FAILED',
    'details' => $pathResults,
    'message' => $allPathsExist ? 'Todos los archivos existen' : 'Algunos archivos no existen'
];

// ============================================
// PRUEBA 3: Probar include de database.php
// ============================================
try {
    require_once '../../config/database.php';
    $results['tests'][] = [
        'name' => 'Include database.php',
        'status' => 'SUCCESS',
        'message' => 'database.php incluido correctamente'
    ];
} catch (Exception $e) {
    $results['tests'][] = [
        'name' => 'Include database.php',
        'status' => 'ERROR',
        'message' => 'Error al incluir database.php: ' . $e->getMessage()
    ];
}

// ============================================
// PRUEBA 4: Verificar conexión a BD
// ============================================
if (isset($pdo) && $pdo) {
    try {
        $stmt = $pdo->query("SELECT 1 as test");
        $result = $stmt->fetch();
        $results['tests'][] = [
            'name' => 'Conexión a base de datos',
            'status' => 'SUCCESS',
            'message' => 'Conexión exitosa',
            'data' => $result
        ];
    } catch (PDOException $e) {
        $results['tests'][] = [
            'name' => 'Conexión a base de datos',
            'status' => 'ERROR',
            'message' => 'Error en consulta: ' . $e->getMessage()
        ];
    }
} else {
    $results['tests'][] = [
        'name' => 'Conexión a base de datos',
        'status' => 'ERROR',
        'message' => 'No se pudo conectar a la base de datos. $pdo no está disponible.'
    ];
}

// ============================================
// PRUEBA 5: Verificar clase Authentication
// ============================================
if (isset($pdo)) {
    try {
        // Intentar incluir auth.php
        if (file_exists(__DIR__ . '/../../includes/auth.php')) {
            require_once __DIR__ . '/../../includes/auth.php';
            
            // Verificar qué clases existen
            $classes = get_declared_classes();
            $authClasses = array_filter($classes, function($class) {
                return stripos($class, 'auth') !== false;
            });
            
            if (class_exists('Authentication')) {
                $auth = new Authentication($pdo);
                $results['tests'][] = [
                    'name' => 'Clase Authentication',
                    'status' => 'SUCCESS',
                    'message' => 'Clase Authentication existe y se instanció correctamente',
                    'class' => 'Authentication'
                ];
            } elseif (class_exists('Auth')) {
                $auth = new Auth($pdo);
                $results['tests'][] = [
                    'name' => 'Clase Auth',
                    'status' => 'SUCCESS',
                    'message' => 'Clase Auth existe y se instanció correctamente',
                    'class' => 'Auth'
                ];
            } else {
                $results['tests'][] = [
                    'name' => 'Clase de autenticación',
                    'status' => 'ERROR',
                    'message' => 'No se encontró la clase Authentication ni Auth',
                    'available_classes' => $authClasses
                ];
            }
        } else {
            $results['tests'][] = [
                'name' => 'Clase de autenticación',
                'status' => 'ERROR',
                'message' => 'El archivo auth.php no existe en la ruta esperada'
            ];
        }
    } catch (Exception $e) {
        $results['tests'][] = [
            'name' => 'Clase de autenticación',
            'status' => 'ERROR',
            'message' => 'Error al cargar auth.php: ' . $e->getMessage()
        ];
    }
}

// ============================================
// PRUEBA 6: Verificar clase AdminAuth
// ============================================
try {
    if (file_exists(__DIR__ . '/../../includes/admin-auth.php')) {
        require_once __DIR__ . '/../../includes/admin-auth.php';
        
        if (class_exists('AdminAuth')) {
            $adminAuth = new AdminAuth($pdo);
            $results['tests'][] = [
                'name' => 'Clase AdminAuth',
                'status' => 'SUCCESS',
                'message' => 'AdminAuth existe y se instanció correctamente'
            ];
        } else {
            $results['tests'][] = [
                'name' => 'Clase AdminAuth',
                'status' => 'ERROR',
                'message' => 'La clase AdminAuth no existe'
            ];
        }
    } else {
        $results['tests'][] = [
            'name' => 'Clase AdminAuth',
            'status' => 'ERROR',
            'message' => 'El archivo admin-auth.php no existe'
        ];
    }
} catch (Exception $e) {
    $results['tests'][] = [
        'name' => 'Clase AdminAuth',
        'status' => 'ERROR',
        'message' => 'Error: ' . $e->getMessage()
    ];
}

// ============================================
// PRUEBA 7: Verificar método verifyAdmin
// ============================================
if (class_exists('AdminAuth') && isset($adminAuth)) {
    try {
        // Intentar obtener admin desde un token simulado
        // Esto fallará si no hay token, pero verificamos que el método existe
        if (method_exists($adminAuth, 'verifyAdmin')) {
            $results['tests'][] = [
                'name' => 'Método verifyAdmin',
                'status' => 'SUCCESS',
                'message' => 'El método verifyAdmin existe en AdminAuth'
            ];
        } else {
            $results['tests'][] = [
                'name' => 'Método verifyAdmin',
                'status' => 'ERROR',
                'message' => 'El método verifyAdmin no existe en AdminAuth'
            ];
        }
        
        // Verificar otros métodos comunes
        $methods = get_class_methods($adminAuth);
        $results['tests'][] = [
            'name' => 'Métodos disponibles en AdminAuth',
            'status' => 'INFO',
            'methods' => $methods
        ];
    } catch (Exception $e) {
        $results['tests'][] = [
            'name' => 'Método verifyAdmin',
            'status' => 'ERROR',
            'message' => 'Error al verificar el método: ' . $e->getMessage()
        ];
    }
}

// ============================================
// PRUEBA 8: Verificar tabla de usuarios
// ============================================
if (isset($pdo) && $pdo) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
        if ($stmt->rowCount() > 0) {
            $results['tests'][] = [
                'name' => 'Tabla usuarios',
                'status' => 'SUCCESS',
                'message' => 'La tabla usuarios existe'
            ];
            
            // Obtener estructura de la tabla
            $stmt = $pdo->query("DESCRIBE usuarios");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results['tests'][] = [
                'name' => 'Estructura tabla usuarios',
                'status' => 'INFO',
                'columns' => array_column($columns, 'Field')
            ];
        } else {
            $results['tests'][] = [
                'name' => 'Tabla usuarios',
                'status' => 'ERROR',
                'message' => 'La tabla usuarios no existe'
            ];
        }
    } catch (PDOException $e) {
        $results['tests'][] = [
            'name' => 'Tabla usuarios',
            'status' => 'ERROR',
            'message' => 'Error al verificar tabla: ' . $e->getMessage()
        ];
    }
}

// ============================================
// PRUEBA 9: Verificar token en headers (si existe)
// ============================================
$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? 'No header';

$results['tests'][] = [
    'name' => 'Headers recibidos',
    'status' => 'INFO',
    'headers' => [
        'authorization' => $authHeader,
        'content_type' => $headers['Content-Type'] ?? 'No content-type',
        'all_headers' => array_keys($headers)
    ]
];

// ============================================
// PRUEBA 10: Simular petición DELETE
// ============================================
$results['tests'][] = [
    'name' => 'Simulación de DELETE request',
    'status' => 'INFO',
    'message' => 'Para probar DELETE, envía: method=DELETE, body={"action":"delete_user_permanently","user_id":"1"}',
    'suggested_test' => "curl -X DELETE 'https://colcars.com/api/v1/admin.php' -H 'Authorization: Bearer TU_TOKEN' -d '{\"action\":\"delete_user_permanently\",\"user_id\":1}'"
];

// ============================================
// PRUEBA 11: Verificar que el archivo admin.php existe
// ============================================
$adminPhpPath = __DIR__ . '/admin.php';
if (file_exists($adminPhpPath)) {
    $results['tests'][] = [
        'name' => 'Archivo admin.php',
        'status' => 'SUCCESS',
        'message' => 'El archivo admin.php existe en la ruta esperada',
        'path' => $adminPhpPath
    ];
} else {
    $results['tests'][] = [
        'name' => 'Archivo admin.php',
        'status' => 'ERROR',
        'message' => 'El archivo admin.php NO existe en la ruta esperada',
        'path' => $adminPhpPath
    ];
}

// ============================================
// PRUEBA 12: Información del servidor
// ============================================
$results['tests'][] = [
    'name' => 'Información del servidor',
    'status' => 'INFO',
    'server' => [
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'No disponible',
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'No disponible',
        'php_version' => phpversion(),
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? 'No disponible',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'No disponible',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'No disponible'
    ]
];

// ============================================
// RESULTADOS FINALES
// ============================================
$failedTests = array_filter($results['tests'], function($test) {
    return $test['status'] === 'ERROR' || $test['status'] === 'FAILED';
});

$results['summary'] = [
    'total_tests' => count($results['tests']),
    'success_count' => count(array_filter($results['tests'], function($t) { return $t['status'] === 'SUCCESS'; })),
    'error_count' => count(array_filter($results['tests'], function($t) { return $t['status'] === 'ERROR'; })),
    'failed_count' => count(array_filter($results['tests'], function($t) { return $t['status'] === 'FAILED'; })),
    'info_count' => count(array_filter($results['tests'], function($t) { return $t['status'] === 'INFO'; }))
];

if (count($failedTests) > 0) {
    $results['summary']['status'] = 'FAILED';
    $results['summary']['message'] = 'Se encontraron ' . count($failedTests) . ' problemas. Revisa los detalles arriba.';
} else {
    $results['summary']['status'] = 'PASSED';
    $results['summary']['message'] = 'Todas las pruebas pasaron correctamente.';
}

// Mostrar resultados en formato JSON bonito
echo json_encode($results, JSON_PRETTY_PRINT);

// ============================================
// LOG DE DEPURACIÓN ADICIONAL
// ============================================
// Guardar en archivo de log
$logFile = __DIR__ . '/test_debug.log';
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'summary' => $results['summary'],
    'failed_tests' => $failedTests
];
file_put_contents($logFile, print_r($logData, true), FILE_APPEND);

?>