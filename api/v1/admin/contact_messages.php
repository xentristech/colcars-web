<?php
/**
 * API para gestión de mensajes de contacto y copias de seguridad
 * Ruta: C:\ServidorWeb\htdocs\easycarluxury\api\v1\admin\contact_messages.php
 * Métodos soportados: POST (create), GET (list/get), PUT (update), DELETE (delete/backup_delete)
 * Acciones especiales via POST: export, backup_create, backup_list
 * 
 * CORREGIDO: 
 * - Rutas de inclusión usando __DIR__ para evitar errores de ruta
 * - Se agregó session_start() para permitir autenticación desde AJAX
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responder a preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// INICIAR SESIÓN PARA VERIFICAR AUTENTICACIÓN (CORRECCIÓN CRÍTICA)
// ============================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CORRECCIÓN DE RUTAS - USO DE __DIR__
// ============================================
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/admin-auth.php';

// Obtener conexión a la base de datos
$database = Database::getInstance();
$pdo = $database->getConnection();

// Verificar autenticación para todas las acciones excepto 'create'
$admin = null;
$action = null;

// Determinar la acción
$input = json_decode(file_get_contents('php://input'), true);
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? null;
} else if ($input) {
    $action = $input['action'] ?? null;
}

// Para acciones administrativas, verificar autenticación
$adminActions = ['list', 'get', 'update', 'delete', 'export', 'backup_create', 'backup_list', 'backup_delete'];
if ($action && in_array($action, $adminActions)) {
    $adminAuth = new AdminAuth($pdo);
    $admin = $adminAuth->verifyAdmin();
    
    if (!$admin) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado. Debes iniciar sesión como administrador.']);
        exit();
    }
}

// Directorio para copias de seguridad - CORREGIDO con __DIR__
$backupDir = __DIR__ . '/../../../dashboard/admin/uploads/mensajes-contacto';

// Crear directorio si no existe
if (!is_dir($backupDir)) {
    mkdir($backupDir, 0777, true);
}

/**
 * Función para validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Función para registrar acciones en auditoría
 */
function logContactAction($pdo, $adminId, $action, $details, $ipAddress, $userAgent) {
    $stmt = $pdo->prepare("
        INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address, user_agent, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $adminId,
        $action,
        'contact_messages',
        null,
        json_encode($details, JSON_UNESCAPED_UNICODE),
        $ipAddress,
        $userAgent
    ]);
}

/**
 * Función para obtener IP del cliente
 */
function getClientIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

$ipAddress = getClientIP();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

// ============================================
// MANEJAR SOLICITUDES SEGÚN MÉTODO Y ACCIÓN
// ============================================

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
            // Acción: create (crear nuevo mensaje desde formulario público)
            if ($action === 'create') {
                // Validar campos requeridos
                $errors = [];
                if (empty($input['nombre_completo'])) $errors[] = 'El nombre completo es obligatorio.';
                if (empty($input['email'])) $errors[] = 'El email es obligatorio.';
                if (!empty($input['email']) && !isValidEmail($input['email'])) $errors[] = 'El email no es válido.';
                if (empty($input['asunto'])) $errors[] = 'El asunto es obligatorio.';
                if (empty($input['mensaje'])) $errors[] = 'El mensaje es obligatorio.';
                
                if (!empty($errors)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'errors' => $errors]);
                    exit();
                }
                
                // Insertar mensaje
                $stmt = $pdo->prepare("
                    INSERT INTO contact_messages_new (nombre, email, telefono, asunto, mensaje, estado_leido, estado_respondido, ip_usuario, user_agent, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, 0, 0, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $input['nombre_completo'],
                    $input['email'],
                    $input['telefono'] ?? '',
                    $input['asunto'],
                    $input['mensaje'],
                    $ipAddress,
                    $userAgent
                ]);
                
                $messageId = $pdo->lastInsertId();
                
                // Registrar en auditoría (sin admin_id porque es público)
                $auditStmt = $pdo->prepare("
                    INSERT INTO auditoria (usuario_email, accion, tabla_afectada, registro_id, ip_address, user_agent, created_at)
                    VALUES (?, 'CREATE', 'contact_messages_new', ?, ?, ?, NOW())
                ");
                $auditStmt->execute([
                    $input['email'],
                    $messageId,
                    $ipAddress,
                    $userAgent
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Mensaje enviado correctamente. Te responderemos a la brevedad.',
                    'id' => $messageId
                ]);
                exit();
            }
            
            // Acción: export (exportar mensajes a CSV/Excel/PDF)
            if ($action === 'export') {
                $format = $input['format'] ?? 'excel';
                $filters = $input['filters'] ?? [];
                
                // Construir consulta con filtros
                $sql = "SELECT * FROM contact_messages_new WHERE 1=1";
                $params = [];
                
                if (!empty($filters['search'])) {
                    $sql .= " AND (nombre LIKE ? OR email LIKE ? OR asunto LIKE ? OR mensaje LIKE ?)";
                    $searchTerm = "%" . $filters['search'] . "%";
                    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                }
                if (isset($filters['estado_leido']) && $filters['estado_leido'] !== '') {
                    $sql .= " AND estado_leido = ?";
                    $params[] = (int)$filters['estado_leido'];
                }
                if (isset($filters['estado_respondido']) && $filters['estado_respondido'] !== '') {
                    $sql .= " AND estado_respondido = ?";
                    $params[] = (int)$filters['estado_respondido'];
                }
                if (!empty($filters['date_from'])) {
                    $sql .= " AND DATE(fecha_creacion) >= ?";
                    $params[] = $filters['date_from'];
                }
                if (!empty($filters['date_to'])) {
                    $sql .= " AND DATE(fecha_creacion) <= ?";
                    $params[] = $filters['date_to'];
                }
                
                $sql .= " ORDER BY fecha_creacion DESC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Preparar datos para exportación
                $exportData = [];
                foreach ($messages as $msg) {
                    $exportData[] = [
                        'ID' => $msg['id'],
                        'Nombre' => $msg['nombre'],
                        'Email' => $msg['email'],
                        'Teléfono' => $msg['telefono'],
                        'Asunto' => $msg['asunto'],
                        'Mensaje' => $msg['mensaje'],
                        'Leído' => $msg['estado_leido'] ? 'Sí' : 'No',
                        'Respondido' => $msg['estado_respondido'] ? 'Sí' : 'No',
                        'Fecha' => $msg['fecha_creacion']
                    ];
                }
                
                // Registrar exportación en auditoría
                if ($admin) {
                    logContactAction($pdo, $admin['id'], 'EXPORT', [
                        'format' => $format,
                        'filters' => $filters,
                        'record_count' => count($exportData)
                    ], $ipAddress, $userAgent);
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $exportData,
                    'count' => count($exportData),
                    'format' => $format
                ]);
                exit();
            }
            
            // Acción: backup_create (crear copia de seguridad)
            if ($action === 'backup_create') {
                // Obtener todos los mensajes
                $stmt = $pdo->query("SELECT * FROM contact_messages_new ORDER BY id");
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // CORRECCIÓN: usar 'full_name' en lugar de 'nombre_completo'
                $adminName = $admin['full_name'] ?? $admin['nombre_completo'] ?? 'Administrador';
                
                $backupData = [
                    'created_at' => date('Y-m-d H:i:s'),
                    'created_by' => $adminName,
                    'total_records' => count($messages),
                    'messages' => $messages
                ];
                
                $filename = 'backup_mensajes_' . date('Y-m-d_H-i-s') . '.json';
                $filepath = $backupDir . '/' . $filename;
                
                file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                
                // Verificar si la tabla contact_backups_log existe
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE 'contact_backups_log'");
                    if ($checkTable->rowCount() > 0) {
                        $stmt = $pdo->prepare("
                            INSERT INTO contact_backups_log (nombre_archivo, ruta_completa, tamanio_bytes, registros_incluidos, fecha_backup, creado_por, ip_creacion)
                            VALUES (?, ?, ?, ?, NOW(), ?, ?)
                        ");
                        $stmt->execute([
                            $filename,
                            $filepath,
                            filesize($filepath),
                            count($messages),
                            $adminName,  // CORRECCIÓN: usar $adminName
                            $ipAddress
                        ]);
                    }
                } catch (PDOException $e) {
                    // Tabla no existe, solo registramos en auditoría
                }
                
                // Registrar en auditoría
                logContactAction($pdo, $admin['id'], 'BACKUP_CREATE', [
                    'filename' => $filename,
                    'total_records' => count($messages),
                    'file_size' => filesize($filepath)
                ], $ipAddress, $userAgent);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Copia de seguridad creada exitosamente.',
                    'filename' => $filename,
                    'total_records' => count($messages)
                ]);
                exit();
            }
            
            // Acción: backup_list (listar copias de seguridad)
            if ($action === 'backup_list') {
                $backups = [];
                // Leer archivos del directorio
                if (is_dir($backupDir)) {
                    $files = scandir($backupDir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                            $filepath = $backupDir . '/' . $file;
                            $fileContent = file_get_contents($filepath);
                            $backupData = json_decode($fileContent, true);
                            $backups[] = [
                                'id' => null,
                                'nombre_archivo' => $file,
                                'ruta_completa' => $filepath,
                                'tamanio_bytes' => filesize($filepath),
                                'registros_incluidos' => $backupData['total_records'] ?? 0,
                                'fecha_backup' => $backupData['created_at'] ?? date('Y-m-d H:i:s', filemtime($filepath)),
                                'creado_por' => $backupData['created_by'] ?? 'Desconocido'
                            ];
                        }
                    }
                    // Ordenar por fecha descendente
                    usort($backups, function($a, $b) {
                        return strtotime($b['fecha_backup']) - strtotime($a['fecha_backup']);
                    });
                }
                
                echo json_encode([
                    'success' => true,
                    'backups' => $backups
                ]);
                exit();
            }
            
            // Acción: backup_delete (eliminar copia de seguridad)
            if ($action === 'backup_delete') {
                $filename = $input['filename'] ?? null;
                
                if (!$filename) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'Nombre de archivo no proporcionado.']);
                    exit();
                }
                
                $filepath = $backupDir . '/' . $filename;
                
                // Verificar que el archivo existe y está en el directorio seguro
                if (file_exists($filepath) && strpos($filepath, $backupDir) === 0) {
                    unlink($filepath);
                    
                    // Registrar en auditoría
                    logContactAction($pdo, $admin['id'], 'BACKUP_DELETE', [
                        'filename' => $filename
                    ], $ipAddress, $userAgent);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Copia de seguridad eliminada exitosamente.'
                    ]);
                } else {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Archivo no encontrado.']);
                }
                exit();
            }
            
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida para método POST.']);
            break;
            
        case 'GET':
            // Acción: list (listar todos los mensajes)
            if ($action === 'list') {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
                $offset = ($page - 1) * $limit;
                
                // Filtros
                $estado_leido = $_GET['estado_leido'] ?? null;
                $estado_respondido = $_GET['estado_respondido'] ?? null;
                $search = $_GET['search'] ?? null;
                
                $sql = "SELECT * FROM contact_messages_new WHERE 1=1";
                $countSql = "SELECT COUNT(*) as total FROM contact_messages_new WHERE 1=1";
                $params = [];
                
                if ($estado_leido !== null && $estado_leido !== '') {
                    $sql .= " AND estado_leido = ?";
                    $countSql .= " AND estado_leido = ?";
                    $params[] = (int)$estado_leido;
                }
                if ($estado_respondido !== null && $estado_respondido !== '') {
                    $sql .= " AND estado_respondido = ?";
                    $countSql .= " AND estado_respondido = ?";
                    $params[] = (int)$estado_respondido;
                }
                if ($search) {
                    $sql .= " AND (nombre LIKE ? OR email LIKE ? OR asunto LIKE ? OR mensaje LIKE ?)";
                    $countSql .= " AND (nombre LIKE ? OR email LIKE ? OR asunto LIKE ? OR mensaje LIKE ?)";
                    $searchTerm = "%$search%";
                    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
                }
                
                // Obtener total
                $countStmt = $pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Registrar visualización en auditoría
                logContactAction($pdo, $admin['id'], 'VIEW_LIST', [
                    'filters' => [
                        'estado_leido' => $estado_leido,
                        'estado_respondido' => $estado_respondido,
                        'search' => $search
                    ],
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total
                ], $ipAddress, $userAgent);
                
                echo json_encode([
                    'success' => true,
                    'messages' => $messages,
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]);
                exit();
            }
            
            // Acción: get (obtener un mensaje específico)
            if ($action === 'get') {
                $id = $_GET['id'] ?? null;
                
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID de mensaje no proporcionado.']);
                    exit();
                }
                
                $stmt = $pdo->prepare("SELECT * FROM contact_messages_new WHERE id = ?");
                $stmt->execute([$id]);
                $message = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$message) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado.']);
                    exit();
                }
                
                // Marcar como leído si no lo está
                if ($message['estado_leido'] == 0) {
                    $updateStmt = $pdo->prepare("UPDATE contact_messages_new SET estado_leido = 1 WHERE id = ?");
                    $updateStmt->execute([$id]);
                    $message['estado_leido'] = 1;
                }
                
                echo json_encode(['success' => true, 'message' => $message]);
                exit();
            }
            
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida para método GET.']);
            break;
            
        case 'PUT':
            // Acción: update (actualizar mensaje)
            if ($action === 'update') {
                $id = $input['id'] ?? null;
                
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID de mensaje no proporcionado.']);
                    exit();
                }
                
                // Verificar que el mensaje existe
                $checkStmt = $pdo->prepare("SELECT * FROM contact_messages_new WHERE id = ?");
                $checkStmt->execute([$id]);
                $oldMessage = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$oldMessage) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado.']);
                    exit();
                }
                
                // Construir UPDATE dinámico
                $updateFields = [];
                $updateParams = [];
                
                $allowedFields = ['nombre', 'email', 'telefono', 'asunto', 'mensaje', 'estado_leido', 'estado_respondido', 'respuesta_admin'];
                
                foreach ($allowedFields as $field) {
                    if (isset($input[$field])) {
                        $updateFields[] = "$field = ?";
                        $updateParams[] = $input[$field];
                    }
                }
                
                if (empty($updateFields)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'No hay campos para actualizar.']);
                    exit();
                }
                
                $updateParams[] = $id;
                $sql = "UPDATE contact_messages_new SET " . implode(', ', $updateFields) . ", fecha_actualizacion = NOW() WHERE id = ?";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($updateParams);
                
                // Registrar en auditoría
                logContactAction($pdo, $admin['id'], 'UPDATE', [
                    'message_id' => $id,
                    'old_data' => $oldMessage,
                    'new_data' => $input
                ], $ipAddress, $userAgent);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Mensaje actualizado correctamente.'
                ]);
                exit();
            }
            
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida para método PUT.']);
            break;
            
        case 'DELETE':
            // Acción: delete (eliminar mensaje)
            if ($action === 'delete') {
                $id = $_GET['id'] ?? null;
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$id && $input) {
                    $id = $input['id'] ?? null;
                }
                
                if (!$id) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'error' => 'ID de mensaje no proporcionado.']);
                    exit();
                }
                
                // Verificar que el mensaje existe
                $checkStmt = $pdo->prepare("SELECT * FROM contact_messages_new WHERE id = ?");
                $checkStmt->execute([$id]);
                $message = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$message) {
                    http_response_code(404);
                    echo json_encode(['success' => false, 'error' => 'Mensaje no encontrado.']);
                    exit();
                }
                
                // Eliminar mensaje
                $stmt = $pdo->prepare("DELETE FROM contact_messages_new WHERE id = ?");
                $stmt->execute([$id]);
                
                // Registrar en auditoría
                logContactAction($pdo, $admin['id'], 'DELETE', [
                    'message_id' => $id,
                    'deleted_data' => $message
                ], $ipAddress, $userAgent);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Mensaje eliminado correctamente.'
                ]);
                exit();
            }
            
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no válida para método DELETE.']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Método no permitido.']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>