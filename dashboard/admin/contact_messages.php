<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\contact_messages.php
 * 
 * Panel de administración para gestionar mensajes de contacto
 * MODIFICADO: Unificado con la estructura de audit.php (sidebar, footer, tema oscuro/claro, responsive)
 * MODIFICADO: Se mantiene toda la funcionalidad original (responder, editar, eliminar, backup, exportar)
 * MODIFICADO: CORREGIDO - Se agregó verificación de existencia de get_message.php y manejo de errores AJAX
 * MODIFICADO: CORREGIDO - Se agregó e.stopPropagation() a los botones para evitar modales duplicados
 * MODIFICADO: Se eliminó el evento .message-row.click() que causaba la apertura de dos modales
 * MODIFICADO: NUEVA TABLA - Se agregó tabla de backups de archivos en carpeta uploads/mensajes
 * MODIFICADO: CORREGIDO - Error de auditoría: valores ENUM válidos para la columna 'accion'
 */

// Iniciar sesión
session_start();

// Verificar si el usuario está logueado y tiene rol de administrador (superadmin, ingeniero, contador, técnico, asesor)
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// Verificar rol de administrador (rol_id 1,2,3,4,5)
$rolPermitido = in_array($_SESSION['rol_id'], [1, 2, 3, 4, 5]);
if (!$rolPermitido) {
    header('Location: /dashboard/user/');
    exit;
}

require_once '../../config/database.php';
$database = Database::getInstance();
$pdo = $database->getConnection();

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

// Configuración de paginación
$itemsPorPagina = 20;
$paginaActual = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($paginaActual - 1) * $itemsPorPagina;

// Filtros
$filtroStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filtroBusqueda = isset($_GET['search']) ? trim($_GET['search']) : '';
$filtroFecha = isset($_GET['fecha']) ? $_GET['fecha'] : '';

// ==========================================
// DIRECTORIO DE BACKUPS DE ARCHIVOS
// ==========================================
$backupArchivosDir = __DIR__ . '/uploads/mensajes/';

// Crear directorio si no existe
if (!file_exists($backupArchivosDir)) {
    mkdir($backupArchivosDir, 0777, true);
}

// Función para crear backup de archivos (exportar mensajes a JSON)
function crearBackupArchivos($pdo, $directorio) {
    // Obtener todos los mensajes
    $sql = "SELECT id, nombre_completo, email, telefono, whatsapp, mensaje, respuesta, respondido_por, fecha_respuesta, status, ip_address, user_agent, created_at, updated_at FROM contact_messages ORDER BY id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Obtener estadísticas para incluir en el backup
    $statsSql = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN status = 'respondido' THEN 1 ELSE 0 END) as respondidos,
        SUM(CASE WHEN status = 'archivado' THEN 1 ELSE 0 END) as archivados,
        SUM(CASE WHEN status = 'eliminado' THEN 1 ELSE 0 END) as eliminados
        FROM contact_messages";
    $stats = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC);
    
    // Crear estructura del backup
    $backupData = [
        'info' => [
            'fecha_backup' => date('Y-m-d H:i:s'),
            'usuario_backup' => $_SESSION['nombre_completo'] ?? $_SESSION['email'] ?? 'Administrador',
            'usuario_id' => $_SESSION['usuario_id'] ?? null,
            'total_registros' => $stats['total'],
            'estadisticas' => $stats,
            'tipo_backup' => 'contact_messages_full_export'
        ],
        'mensajes' => $mensajes
    ];
    
    // Generar nombre de archivo
    $fecha = date('Y-m-d_H-i-s');
    $filename = "backup_mensajes_{$fecha}.json";
    $filepath = $directorio . $filename;
    
    // Guardar archivo JSON
    if (file_put_contents($filepath, json_encode($backupData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        // Registrar en log de auditoría - Usando 'CREATE' en lugar del valor no válido para ENUM
        $detalles = "Backup archivo creado: " . $filename . " | Total registros: " . $stats['total'];
        registrarAuditoria($pdo, 'CREATE', 'contact_messages_backup', null, $detalles);
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath, 'size' => filesize($filepath)];
    }
    
    return ['success' => false, 'error' => 'No se pudo escribir el archivo'];
}

// Función para eliminar archivo de backup
function eliminarBackupArchivo($directorio, $filename, $pdo) {
    $filepath = $directorio . $filename;
    
    // Validar que el archivo esté dentro del directorio permitido (seguridad)
    $realPath = realpath($filepath);
    $realDir = realpath($directorio);
    
    if ($realPath === false || strpos($realPath, $realDir) !== 0) {
        return ['success' => false, 'error' => 'Ruta de archivo no válida'];
    }
    
    if (file_exists($filepath) && is_file($filepath)) {
        if (unlink($filepath)) {
            // Registrar en log de auditoría - Usando 'DELETE' en lugar del valor no válido para ENUM
            registrarAuditoria($pdo, 'DELETE', 'contact_messages_backup', null, "Backup archivo eliminado: " . $filename);
            return ['success' => true, 'filename' => $filename];
        } else {
            return ['success' => false, 'error' => 'No se pudo eliminar el archivo'];
        }
    }
    
    return ['success' => false, 'error' => 'Archivo no encontrado'];
}

// Función para listar archivos de backup
function listarBackupsArchivos($directorio) {
    $backups = [];
    if (file_exists($directorio)) {
        $archivos = scandir($directorio);
        foreach ($archivos as $archivo) {
            if (preg_match('/^backup_mensajes_.+\.json$/', $archivo)) {
                $rutaCompleta = $directorio . $archivo;
                $backups[] = [
                    'nombre' => $archivo,
                    'ruta' => $rutaCompleta,
                    'tamaño' => filesize($rutaCompleta),
                    'fecha' => date('Y-m-d H:i:s', filemtime($rutaCompleta))
                ];
            }
        }
        // Ordenar por fecha descendente (más reciente primero)
        usort($backups, function($a, $b) {
            return strtotime($b['fecha']) - strtotime($a['fecha']);
        });
    }
    return $backups;
}

// ==========================================
// PROCESAR ACCIONES POST
// ==========================================
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = isset($_POST['accion']) ? $_POST['accion'] : '';
    
    // RESPONDER MENSAJE
    if ($accion === 'responder' && isset($_POST['id']) && isset($_POST['respuesta'])) {
        $id = intval($_POST['id']);
        $respuesta = trim($_POST['respuesta']);
        $adminId = $_SESSION['usuario_id'];
        
        if (!empty($respuesta)) {
            $sql = "UPDATE contact_messages SET respuesta = :respuesta, respondido_por = :respondido_por, fecha_respuesta = NOW(), status = 'respondido', updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([':respuesta' => $respuesta, ':respondido_por' => $adminId, ':id' => $id])) {
                $mensaje = 'Respuesta guardada correctamente.';
                
                // Obtener email del usuario para enviar notificación
                $emailSql = "SELECT email, nombre_completo FROM contact_messages WHERE id = :id";
                $emailStmt = $pdo->prepare($emailSql);
                $emailStmt->execute([':id' => $id]);
                $usuario = $emailStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario) {
                    // Aquí se podría enviar un correo de notificación
                    error_log("Respuesta enviada a: " . $usuario['email'] . " sobre mensaje ID: " . $id);
                }
            } else {
                $error = 'Error al guardar la respuesta.';
                error_log("Error al guardar respuesta para mensaje ID: " . $id);
            }
        } else {
            $error = 'La respuesta no puede estar vacía.';
        }
    }
    
    // EDITAR MENSAJE
    elseif ($accion === 'editar' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        $nombre_completo = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $telefono = trim($_POST['telefono']);
        $whatsapp = trim($_POST['whatsapp']);
        $mensaje_texto = trim($_POST['mensaje']);
        $status = $_POST['status'];
        
        if (empty($nombre_completo) || empty($email) || empty($telefono) || empty($mensaje_texto)) {
            $error = 'Todos los campos obligatorios deben estar llenos.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El correo electrónico no es válido.';
        } else {
            $sql = "UPDATE contact_messages SET nombre_completo = :nombre_completo, email = :email, telefono = :telefono, whatsapp = :whatsapp, mensaje = :mensaje, status = :status, updated_at = NOW() WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute([
                ':nombre_completo' => $nombre_completo,
                ':email' => $email,
                ':telefono' => $telefono,
                ':whatsapp' => $whatsapp ?: null,
                ':mensaje' => $mensaje_texto,
                ':status' => $status,
                ':id' => $id
            ])) {
                $mensaje = 'Mensaje editado correctamente.';
            } else {
                $error = 'Error al editar el mensaje.';
                error_log("Error al editar mensaje ID: " . $id);
            }
        }
    }
    
    // ELIMINAR MENSAJE (individual)
    elseif ($accion === 'eliminar' && isset($_POST['id'])) {
        $id = intval($_POST['id']);
        
        // Verificar si se requiere backup antes de eliminar
        $hacerBackup = isset($_POST['hacer_backup']) && $_POST['hacer_backup'] == '1';
        
        if ($hacerBackup) {
            // Crear backup de la tabla antes de eliminar
            $backupFile = crearBackupTabla($pdo, 'contact_messages');
            if ($backupFile) {
                $mensaje = "Backup creado: " . basename($backupFile) . ". ";
            } else {
                $error = "No se pudo crear el backup. ";
            }
        }
        
        // Eliminar el mensaje (soft delete - cambiar status a 'eliminado')
        $sql = "UPDATE contact_messages SET status = 'eliminado', updated_at = NOW() WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute([':id' => $id])) {
            $mensaje = ($mensaje ?: '') . 'Mensaje eliminado correctamente.';
        } else {
            $error = 'Error al eliminar el mensaje.';
            error_log("Error al eliminar mensaje ID: " . $id);
        }
    }
    
    // ELIMINAR MÚLTIPLES MENSAJES
    elseif ($accion === 'eliminar_multiples' && isset($_POST['ids']) && is_array($_POST['ids'])) {
        $ids = array_map('intval', $_POST['ids']);
        $hacerBackup = isset($_POST['hacer_backup']) && $_POST['hacer_backup'] == '1';
        
        if ($hacerBackup) {
            $backupFile = crearBackupTabla($pdo, 'contact_messages');
            if ($backupFile) {
                $mensaje = "Backup creado: " . basename($backupFile) . ". ";
            } else {
                $error = "No se pudo crear el backup. ";
            }
        }
        
        if (count($ids) > 0) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE contact_messages SET status = 'eliminado', updated_at = NOW() WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);
            if ($stmt->execute($ids)) {
                $mensaje = ($mensaje ?: '') . count($ids) . ' mensajes eliminados correctamente.';
            } else {
                $error = 'Error al eliminar los mensajes.';
                error_log("Error al eliminar múltiples mensajes: " . implode(',', $ids));
            }
        }
    }
    
    // CREAR BACKUP DE LA TABLA (SQL)
    elseif ($accion === 'crear_backup') {
        $backupFile = crearBackupTabla($pdo, 'contact_messages');
        if ($backupFile) {
            $mensaje = "Backup SQL creado exitosamente: " . basename($backupFile);
        } else {
            $error = "Error al crear el backup SQL. Verifica los permisos de escritura en la carpeta de backups.";
        }
    }
    
    // CREAR BACKUP DE ARCHIVOS (JSON)
    elseif ($accion === 'crear_backup_archivo') {
        $resultado = crearBackupArchivos($pdo, $backupArchivosDir);
        if ($resultado['success']) {
            $sizeKB = round($resultado['size'] / 1024, 2);
            $mensaje = "Backup de archivo creado exitosamente: " . $resultado['filename'] . " ({$sizeKB} KB)";
        } else {
            $error = "Error al crear el backup de archivo: " . ($resultado['error'] ?? 'Error desconocido');
        }
    }
    
    // ELIMINAR BACKUP DE ARCHIVO
    elseif ($accion === 'eliminar_backup_archivo' && isset($_POST['archivo'])) {
        $archivo = basename(trim($_POST['archivo'])); // Sanitizar nombre
        if (empty($archivo)) {
            $error = 'Nombre de archivo inválido';
        } else {
            $resultado = eliminarBackupArchivo($backupArchivosDir, $archivo, $pdo);
            if ($resultado['success']) {
                $mensaje = "Backup eliminado: " . $archivo;
            } else {
                $error = "Error al eliminar backup: " . ($resultado['error'] ?? 'Error desconocido');
            }
        }
    }
    
    // EXPORTAR A CSV
    elseif ($accion === 'exportar_csv') {
        exportarACSV($pdo, $filtroStatus, $filtroBusqueda, $filtroFecha);
        exit;
    }
}

// ==========================================
// FUNCIONES AUXILIARES
// ==========================================

function crearBackupTabla($pdo, $tabla) {
    $backupDir = '../../backups/';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0777, true);
    }
    
    $fecha = date('Y-m-d_H-i-s');
    $backupFile = $backupDir . "backup_{$tabla}_{$fecha}.sql";
    
    // Obtener estructura de la tabla
    $estructura = $pdo->query("SHOW CREATE TABLE {$tabla}")->fetch(PDO::FETCH_ASSOC);
    
    // Obtener datos de la tabla
    $datos = $pdo->query("SELECT * FROM {$tabla} WHERE status != 'eliminado'")->fetchAll(PDO::FETCH_ASSOC);
    
    $sql = "-- Backup de tabla {$tabla}\n";
    $sql .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- Generado por: " . ($_SESSION['nombre_completo'] ?? 'Administrador') . "\n\n";
    $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n\n";
    $sql .= $estructura['Create Table'] . ";\n\n";
    
    if (count($datos) > 0) {
        $sql .= "INSERT INTO `{$tabla}` (`" . implode('`, `', array_keys($datos[0])) . "`) VALUES\n";
        $valores = [];
        foreach ($datos as $fila) {
            $filaValores = [];
            foreach ($fila as $valor) {
                if ($valor === null) {
                    $filaValores[] = "NULL";
                } else {
                    $filaValores[] = "'" . addslashes($valor) . "'";
                }
            }
            $valores[] = "(" . implode(', ', $filaValores) . ")";
        }
        $sql .= implode(",\n", $valores) . ";\n";
    }
    
    if (file_put_contents($backupFile, $sql)) {
        // Registrar en log de auditoría
        registrarAuditoria($pdo, 'CREATE', $tabla, null, "Backup SQL creado: " . basename($backupFile));
        return $backupFile;
    }
    
    return false;
}

function exportarACSV($pdo, $filtroStatus, $filtroBusqueda, $filtroFecha) {
    $sql = "SELECT id, nombre_completo, email, telefono, whatsapp, mensaje, respuesta, status, created_at, updated_at FROM contact_messages WHERE 1=1";
    $params = [];
    
    if (!empty($filtroStatus) && $filtroStatus !== 'todos') {
        $sql .= " AND status = :status";
        $params[':status'] = $filtroStatus;
    }
    
    if (!empty($filtroBusqueda)) {
        $sql .= " AND (nombre_completo LIKE :search OR email LIKE :search OR mensaje LIKE :search)";
        $params[':search'] = "%{$filtroBusqueda}%";
    }
    
    if (!empty($filtroFecha)) {
        $sql .= " AND DATE(created_at) = :fecha";
        $params[':fecha'] = $filtroFecha;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $filename = "contact_messages_" . date('Y-m-d_H-i-s') . ".csv";
    
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nombre', 'Email', 'Teléfono', 'WhatsApp', 'Mensaje', 'Respuesta', 'Estado', 'Fecha Creación', 'Fecha Actualización']);
    
    foreach ($datos as $fila) {
        fputcsv($output, [
            $fila['id'],
            $fila['nombre_completo'],
            $fila['email'],
            $fila['telefono'],
            $fila['whatsapp'] ?? '',
            $fila['mensaje'],
            $fila['respuesta'] ?? '',
            $fila['status'],
            $fila['created_at'],
            $fila['updated_at']
        ]);
    }
    
    fclose($output);
    registrarAuditoria($pdo, 'READ', 'contact_messages', null, "Exportados " . count($datos) . " registros a CSV");
}

function registrarAuditoria($pdo, $accion, $tabla, $registroId, $detalles) {
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $usuarioEmail = $_SESSION['email'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Asegurar que la acción sea válida para el ENUM de la tabla auditoria
    // Valores permitidos: CREATE, READ, UPDATE, DELETE, LOGIN, LOGOUT, SUSPEND, RESTORE, UPGRADE, DOWNGRADE
    $accionValida = $accion;
    $accionesPermitidas = ['CREATE', 'READ', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'SUSPEND', 'RESTORE', 'UPGRADE', 'DOWNGRADE'];
    
    if (!in_array($accion, $accionesPermitidas)) {
        // Si no es una acción válida, usar 'UPDATE' como fallback y guardar la acción real en detalles
        $detalles = "Acción original: {$accion} | " . $detalles;
        $accionValida = 'UPDATE';
    }
    
    $sql = "INSERT INTO auditoria (usuario_id, usuario_email, accion, tabla_afectada, registro_id, datos_nuevos, ip_address, user_agent, created_at) 
            VALUES (:usuario_id, :usuario_email, :accion, :tabla, :registro_id, :detalles, :ip, :user_agent, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':usuario_email' => $usuarioEmail,
        ':accion' => $accionValida,
        ':tabla' => $tabla,
        ':registro_id' => $registroId,
        ':detalles' => $detalles,
        ':ip' => $ip,
        ':user_agent' => $userAgent
    ]);
}

// ==========================================
// CONSULTAR MENSAJES CON FILTROS
// ==========================================

$sqlBase = "SELECT * FROM contact_messages WHERE 1=1";
$countBase = "SELECT COUNT(*) as total FROM contact_messages WHERE 1=1";
$params = [];

if (!empty($filtroStatus) && $filtroStatus !== 'todos') {
    $sqlBase .= " AND status = :status";
    $countBase .= " AND status = :status";
    $params[':status'] = $filtroStatus;
}

if (!empty($filtroBusqueda)) {
    $sqlBase .= " AND (nombre_completo LIKE :search OR email LIKE :search OR mensaje LIKE :search)";
    $countBase .= " AND (nombre_completo LIKE :search OR email LIKE :search OR mensaje LIKE :search)";
    $params[':search'] = "%{$filtroBusqueda}%";
}

if (!empty($filtroFecha)) {
    $sqlBase .= " AND DATE(created_at) = :fecha";
    $countBase .= " AND DATE(created_at) = :fecha";
    $params[':fecha'] = $filtroFecha;
}

// Obtener total de registros
$countStmt = $pdo->prepare($countBase);
$countStmt->execute($params);
$totalRegistros = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPaginas = ceil($totalRegistros / $itemsPorPagina);

// Obtener registros paginados
$sqlBase .= " ORDER BY 
    CASE WHEN status = 'pendiente' THEN 1
         WHEN status = 'respondido' THEN 2
         WHEN status = 'archivado' THEN 3
         ELSE 4 END,
    created_at DESC
    LIMIT :offset, :limit";
$params[':offset'] = $offset;
$params[':limit'] = $itemsPorPagina;

$stmt = $pdo->prepare($sqlBase);
foreach ($params as $key => $value) {
    if ($key === ':offset' || $key === ':limit') {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($key, $value);
    }
}
$stmt->execute();
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$statsSql = "SELECT 
    SUM(CASE WHEN status = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
    SUM(CASE WHEN status = 'respondido' THEN 1 ELSE 0 END) as respondidos,
    SUM(CASE WHEN status = 'archivado' THEN 1 ELSE 0 END) as archivados,
    SUM(CASE WHEN status = 'eliminado' THEN 1 ELSE 0 END) as eliminados,
    COUNT(*) as total
    FROM contact_messages";
$stats = $pdo->query($statsSql)->fetch(PDO::FETCH_ASSOC);

// Obtener listado de backups disponibles (SQL)
$backupDir = '../../backups/';
$backups = [];
if (file_exists($backupDir)) {
    $archivos = scandir($backupDir);
    foreach ($archivos as $archivo) {
        if (preg_match('/^backup_contact_messages_.+\.sql$/', $archivo)) {
            $backups[] = [
                'nombre' => $archivo,
                'ruta' => $backupDir . $archivo,
                'tamaño' => filesize($backupDir . $archivo),
                'fecha' => date('Y-m-d H:i:s', filemtime($backupDir . $archivo))
            ];
        }
    }
    rsort($backups);
}

// Obtener listado de backups de archivos (JSON)
$backupsArchivos = listarBackupsArchivos($backupArchivosDir);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mensajes de Contacto - Panel Admin</title>
    <!-- Ruta: /dashboard/admin/contact_messages.php -->
    
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

        .stats-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
            border: 1px solid var(--border-color);
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
        }
        
        .stats-card h3 {
            font-size: 2rem;
            margin-bottom: 5px;
            color: var(--text-primary);
        }
        
        .stats-card.pendientes { border-left: 4px solid #f39c12; }
        .stats-card.respondidos { border-left: 4px solid #27ae60; }
        .stats-card.archivados { border-left: 4px solid #3498db; }
        .stats-card.eliminados { border-left: 4px solid #e74c3c; }

        .filter-bar {
            background: var(--card-bg);
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .form-select, .form-control {
            padding: 6px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.8rem;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: #c8a86b;
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

        .btn-warning {
            background: #ffc107;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: #212529;
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

        .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
            margin-bottom: 20px;
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

        .badge-status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-pendiente { background: #f39c12; color: #fff; }
        .badge-respondido { background: #27ae60; color: #fff; }
        .badge-archivado { background: #3498db; color: #fff; }
        .badge-eliminado { background: #e74c3c; color: #fff; }

        .message-preview {
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table-actions {
            white-space: nowrap;
        }
        
        .table-actions button {
            margin: 0 2px;
            padding: 4px 8px;
            font-size: 0.7rem;
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

        .backup-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .backup-item {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .backup-item:hover {
            background: var(--table-hover);
        }

        .response-box {
            background: var(--bg-primary);
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        
        .response-box .response-text {
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #27ae60;
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

        [data-theme="dark"] .alert-success {
            background-color: #0f3b2c;
            color: #a5d6a5;
            border-color: #1e5a3a;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #3b1e1e;
            color: #f5a5a5;
            border-color: #5e2a2a;
        }

        [data-theme="dark"] .text-muted {
            color: var(--text-secondary) !important;
        }

        /* Estilos adicionales para la tabla de backups de archivos */
        .backup-card {
            margin-top: 30px;
        }
        
        .backup-card .table-actions {
            white-space: nowrap;
        }
        
        .backup-card .btn-sm {
            padding: 4px 8px;
            font-size: 0.7rem;
            margin: 0 2px;
        }
        
        .file-size {
            font-family: monospace;
            font-size: 0.75rem;
        }

        /* ============================================
           RESPONSIVE: Ajustes como audit.php
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px;
            }
            .filter-bar .row {
                flex-direction: column;
            }
            .filter-bar .col-md-3,
            .filter-bar .col-md-4,
            .filter-bar .col-md-2 {
                width: 100%;
                margin-bottom: 10px;
            }
            .filter-bar button {
                width: 100%;
            }
            .stats-card h3 {
                font-size: 1.5rem;
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
            .table-actions button {
                padding: 2px 5px;
                font-size: 0.7rem;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-actions {
                width: 100%;
                display: flex;
                justify-content: space-between;
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
                    <h1><i class="fas fa-envelope me-2"></i>Gestión de Mensajes de Contacto</h1>
                    <p>Administra y responde los mensajes recibidos desde el formulario de contacto</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#backupModal">
                        <i class="fas fa-database"></i> Backup SQL
                    </button>
                    <button class="btn btn-info" id="exportarBtn">
                        <i class="fas fa-file-excel"></i> Exportar CSV
                    </button>
                </div>
            </div>
            
            <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($mensaje); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Tarjetas de estadísticas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card pendientes">
                        <i class="fas fa-clock float-end fs-3 text-warning"></i>
                        <small class="text-muted">Pendientes</small>
                        <h3><?php echo $stats['pendientes']; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card respondidos">
                        <i class="fas fa-check-circle float-end fs-3 text-success"></i>
                        <small class="text-muted">Respondidos</small>
                        <h3><?php echo $stats['respondidos']; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card archivados">
                        <i class="fas fa-archive float-end fs-3 text-info"></i>
                        <small class="text-muted">Archivados</small>
                        <h3><?php echo $stats['archivados']; ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card eliminados">
                        <i class="fas fa-trash float-end fs-3 text-danger"></i>
                        <small class="text-muted">Eliminados</small>
                        <h3><?php echo $stats['eliminados']; ?></h3>
                    </div>
                </div>
            </div>
            
            <!-- Filtros -->
            <div class="filter-bar">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            <option value="todos" <?php echo $filtroStatus == 'todos' || empty($filtroStatus) ? 'selected' : ''; ?>>Todos</option>
                            <option value="pendiente" <?php echo $filtroStatus == 'pendiente' ? 'selected' : ''; ?>>Pendientes</option>
                            <option value="respondido" <?php echo $filtroStatus == 'respondido' ? 'selected' : ''; ?>>Respondidos</option>
                            <option value="archivado" <?php echo $filtroStatus == 'archivado' ? 'selected' : ''; ?>>Archivados</option>
                            <option value="eliminado" <?php echo $filtroStatus == 'eliminado' ? 'selected' : ''; ?>>Eliminados</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?php echo htmlspecialchars($filtroFecha); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Buscar</label>
                        <input type="text" name="search" class="form-control" placeholder="Nombre, email o mensaje..." value="<?php echo htmlspecialchars($filtroBusqueda); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Botones de acción masiva -->
            <div class="mb-3">
                <button class="btn btn-danger btn-sm" id="eliminarSeleccionadosBtn" disabled>
                    <i class="fas fa-trash"></i> Eliminar seleccionados
                </button>
                <label class="ms-3">
                    <input type="checkbox" id="backupAntesEliminar"> Hacer backup antes de eliminar
                </label>
            </div>
            
            <!-- Tabla de mensajes -->
            <div class="data-card">
                <div class="card-body table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th width="30"><input type="checkbox" id="seleccionarTodos"></th>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Mensaje</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Respuesta</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($mensajes) > 0): ?>
                                <?php foreach ($mensajes as $msg): ?>
                                <tr class="message-row" data-id="<?php echo $msg['id']; ?>">
                                    <td><input type="checkbox" class="seleccionar-msg" value="<?php echo $msg['id']; ?>"></td>
                                    <td><?php echo $msg['id']; ?></td>
                                    <td><?php echo htmlspecialchars($msg['nombre_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($msg['email']); ?></td>
                                    <td><?php echo htmlspecialchars($msg['telefono']); ?></td>
                                    <td class="message-preview"><?php echo htmlspecialchars(substr($msg['mensaje'], 0, 50)) . (strlen($msg['mensaje']) > 50 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="badge-status 
                                            <?php echo $msg['status'] == 'pendiente' ? 'badge-pendiente' : ($msg['status'] == 'respondido' ? 'badge-respondido' : ($msg['status'] == 'archivado' ? 'badge-archivado' : 'badge-eliminado')); ?>">
                                            <?php 
                                                echo $msg['status'] == 'pendiente' ? 'Pendiente' : 
                                                    ($msg['status'] == 'respondido' ? 'Respondido' : 
                                                    ($msg['status'] == 'archivado' ? 'Archivado' : 'Eliminado'));
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?></td>
                                    <td>
                                        <?php echo !empty($msg['respuesta']) ? '<i class="fas fa-check-circle text-success"></i>' : '<i class="fas fa-clock text-warning"></i>'; ?>
                                    </td>
                                    <td class="table-actions">
                                        <button class="btn btn-sm btn-info ver-msg" data-id="<?php echo $msg['id']; ?>" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary responder-msg" data-id="<?php echo $msg['id']; ?>" title="Responder">
                                            <i class="fas fa-reply"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning editar-msg" data-id="<?php echo $msg['id']; ?>" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn btn-sm btn-danger eliminar-msg" data-id="<?php echo $msg['id']; ?>" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-5">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">No hay mensajes para mostrar</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Paginación -->
            <?php if ($totalPaginas > 1): ?>
            <div class="pagination-container">
                <div class="limit-selector" style="display: none;">
                    <!-- Mantenido por compatibilidad pero oculto ya que usamos paginación fija -->
                </div>
                <nav>
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?php echo $paginaActual == 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $paginaActual-1; ?>&status=<?php echo urlencode($filtroStatus); ?>&search=<?php echo urlencode($filtroBusqueda); ?>&fecha=<?php echo urlencode($filtroFecha); ?>">
                                <i class="fas fa-chevron-left"></i> Anterior
                            </a>
                        </li>
                        <?php 
                        $start_page = max(1, $paginaActual - 2);
                        $end_page = min($totalPaginas, $paginaActual + 2);
                        if ($start_page > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1&status=' . urlencode($filtroStatus) . '&search=' . urlencode($filtroBusqueda) . '&fecha=' . urlencode($filtroFecha) . '">1</a></li>';
                            if ($start_page > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $paginaActual ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($filtroStatus); ?>&search=<?php echo urlencode($filtroBusqueda); ?>&fecha=<?php echo urlencode($filtroFecha); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; 
                        if ($end_page < $totalPaginas) {
                            if ($end_page < $totalPaginas - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPaginas . '&status=' . urlencode($filtroStatus) . '&search=' . urlencode($filtroBusqueda) . '&fecha=' . urlencode($filtroFecha) . '">' . $totalPaginas . '</a></li>';
                        }
                        ?>
                        <li class="page-item <?php echo $paginaActual == $totalPaginas ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $paginaActual+1; ?>&status=<?php echo urlencode($filtroStatus); ?>&search=<?php echo urlencode($filtroBusqueda); ?>&fecha=<?php echo urlencode($filtroFecha); ?>">
                                Siguiente <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
            <!-- ========================================== -->
            <!-- NUEVA TABLA: BACKUPS DE ARCHIVOS -->
            <!-- ========================================== -->
            <div class="data-card backup-card">
                <div class="card-header p-3" style="background: var(--bg-secondary); border-bottom: 1px solid var(--border-color);">
                    <h5 class="mb-0"><i class="fas fa-archive me-2"></i>Backups de Archivos (JSON)</h5>
                    <small class="text-muted">Ubicación: /dashboard/admin/uploads/mensajes/</small>
                </div>
                <div class="card-body p-3">
                    <div class="mb-3">
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="accion" value="crear_backup_archivo">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus-circle"></i> Crear nuevo backup de archivos
                            </button>
                        </form>
                        <span class="ms-2 text-muted small">
                            <i class="fas fa-info-circle"></i> Los backups se guardan en formato JSON con todos los mensajes
                        </span>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Nombre del archivo</th>
                                    <th>Tamaño</th>
                                    <th>Fecha de creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($backupsArchivos) > 0): ?>
                                    <?php foreach ($backupsArchivos as $bkp): ?>
                                    <tr>
                                        <td>
                                            <i class="fas fa-file-alt text-info me-2"></i>
                                            <?php echo htmlspecialchars($bkp['nombre']); ?>
                                        </td>
                                        <td class="file-size"><?php echo round($bkp['tamaño'] / 1024, 2); ?> KB</td>
                                        <td><?php echo $bkp['fecha']; ?></td>
                                        <td class="table-actions">
                                            <a href="uploads/mensajes/<?php echo urlencode($bkp['nombre']); ?>" class="btn btn-sm btn-success" download title="Descargar">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <button class="btn btn-sm btn-danger eliminar-backup-archivo" data-archivo="<?php echo htmlspecialchars($bkp['nombre']); ?>" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <i class="fas fa-folder-open fa-2x text-muted mb-2"></i>
                                            <p class="text-muted mb-0">No hay backups de archivos disponibles</p>
                                            <small class="text-muted">Haz clic en "Crear nuevo backup de archivos" para generar uno</small>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Botón para cambiar tema claro/oscuro -->
    <button class="btn-theme" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>
    
    <!-- Modal Ver Mensaje -->
    <div class="modal fade" id="verModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-envelope me-2"></i>Detalle del Mensaje</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="verModalBody">
                    <!-- Contenido dinámico -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Responder -->
    <div class="modal fade" id="responderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-reply-all me-2"></i>Responder Mensaje</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="responderForm">
                    <input type="hidden" name="accion" value="responder">
                    <input type="hidden" name="id" id="responderId">
                    <div class="modal-body">
                        <div id="mensajeOriginal"></div>
                        <div class="mb-3 mt-3">
                            <label class="form-label">Tu Respuesta:</label>
                            <textarea name="respuesta" id="respuestaTexto" class="form-control" rows="5" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">Enviar Respuesta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Editar -->
    <div class="modal fade" id="editarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Mensaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editarForm">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editarId">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" name="nombre_completo" id="editarNombre" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" id="editarEmail" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono *</label>
                                <input type="text" name="telefono" id="editarTelefono" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">WhatsApp</label>
                                <input type="text" name="whatsapp" id="editarWhatsapp" class="form-control">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Mensaje *</label>
                                <textarea name="mensaje" id="editarMensaje" class="form-control" rows="4" required></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Estado</label>
                                <select name="status" id="editarStatus" class="form-select">
                                    <option value="pendiente">Pendiente</option>
                                    <option value="respondido">Respondido</option>
                                    <option value="archivado">Archivado</option>
                                    <option value="eliminado">Eliminado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Modal Backup SQL -->
    <div class="modal fade" id="backupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-database me-2"></i>Backups SQL de Mensajes</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="accion" value="crear_backup">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus-circle"></i> Crear nuevo backup SQL ahora
                        </button>
                    </form>
                    
                    <hr>
                    
                    <h6><i class="fas fa-history"></i> Backups SQL disponibles:</h6>
                    <div class="backup-list">
                        <?php if (count($backups) > 0): ?>
                            <?php foreach ($backups as $bkp): ?>
                            <div class="backup-item">
                                <div>
                                    <i class="fas fa-file-archive text-info"></i>
                                    <strong><?php echo htmlspecialchars($bkp['nombre']); ?></strong><br>
                                    <small><?php echo $bkp['fecha']; ?> - <?php echo round($bkp['tamaño'] / 1024, 2); ?> KB</small>
                                </div>
                                <div>
                                    <a href="<?php echo $bkp['ruta']; ?>" class="btn btn-sm btn-success" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted">No hay backups SQL disponibles.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle"></i> Los backups SQL se guardan en la carpeta <strong>backups/</strong> del servidor.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Confirmar Eliminación de Mensaje -->
    <div class="modal fade" id="confirmarEliminarModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar este mensaje?</p>
                    <p class="text-muted small">Esta acción no se puede deshacer.</p>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="confirmBackupCheck">
                        <label class="form-check-label" for="confirmBackupCheck">
                            <i class="fas fa-database"></i> Hacer backup antes de eliminar
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarEliminarBtn">Eliminar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Confirmar Eliminación de Backup de Archivo -->
    <div class="modal fade" id="confirmarEliminarBackupArchivoModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Estás seguro de que deseas eliminar este backup de archivo?</p>
                    <p class="text-muted small">Esta acción no se puede deshacer y el archivo se eliminará permanentemente.</p>
                    <p><strong>Archivo:</strong> <span id="archivoAEliminar"></span></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" id="confirmarEliminarBackupArchivoBtn">Eliminar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>
    
    <!-- SweetAlert2 JS - CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        
        let eliminarId = null;
        let eliminarArchivo = null;
        
        // Ver detalle del mensaje (con manejo de errores y stopPropagation para evitar modales duplicados)
        $('.ver-msg').click(function(e) {
            e.stopPropagation(); // Evita que el evento se propague a la fila
            const id = $(this).data('id');
            const btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: 'get_message.php',
                method: 'GET',
                data: { id: id },
                success: function(data) {
                    $('#verModalBody').html(data);
                    $('#verModal').modal('show');
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    showSwalWithTheme({
                        title: 'Error',
                        text: 'No se pudo cargar el detalle del mensaje. ' + (xhr.responseJSON?.error || error),
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-eye"></i>');
                }
            });
        });
        
        // Responder mensaje (con manejo de errores y stopPropagation para evitar modales duplicados)
        $('.responder-msg').click(function(e) {
            e.stopPropagation(); // Evita que el evento se propague a la fila
            const id = $(this).data('id');
            const btn = $(this);
            $('#responderId').val(id);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: 'get_message.php',
                method: 'GET',
                data: { id: id, simple: 1 },
                dataType: 'json',
                success: function(msg) {
                    if (msg.error) {
                        showSwalWithTheme({
                            title: 'Error',
                            text: msg.error,
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                        return;
                    }
                    $('#mensajeOriginal').html(`
                        <div class="response-box">
                            <strong><i class="fas fa-user"></i> ${escapeHtml(msg.nombre_completo)}</strong><br>
                            <small><i class="fas fa-envelope"></i> ${escapeHtml(msg.email)} | <i class="fas fa-phone"></i> ${escapeHtml(msg.telefono)}</small>
                            <hr>
                            <p><strong>Mensaje original:</strong></p>
                            <div class="response-text">
                                ${escapeHtml(msg.mensaje).replace(/\n/g, '<br>')}
                            </div>
                            ${msg.respuesta ? `
                                <hr>
                                <p><strong>Respuesta anterior:</strong></p>
                                <div class="response-text" style="border-left-color: #3498db;">
                                    ${escapeHtml(msg.respuesta).replace(/\n/g, '<br>')}
                                </div>
                            ` : ''}
                        </div>
                    `);
                    $('#responderModal').modal('show');
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    showSwalWithTheme({
                        title: 'Error',
                        text: 'No se pudo cargar el mensaje para responder.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-reply"></i>');
                }
            });
        });
        
        // Editar mensaje (con manejo de errores y stopPropagation para evitar modales duplicados)
        $('.editar-msg').click(function(e) {
            e.stopPropagation(); // Evita que el evento se propague a la fila
            const id = $(this).data('id');
            const btn = $(this);
            $('#editarId').val(id);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
            
            $.ajax({
                url: 'get_message.php',
                method: 'GET',
                data: { id: id, simple: 1 },
                dataType: 'json',
                success: function(msg) {
                    if (msg.error) {
                        showSwalWithTheme({
                            title: 'Error',
                            text: msg.error,
                            icon: 'error',
                            confirmButtonText: 'Aceptar'
                        });
                        return;
                    }
                    $('#editarNombre').val(msg.nombre_completo);
                    $('#editarEmail').val(msg.email);
                    $('#editarTelefono').val(msg.telefono);
                    $('#editarWhatsapp').val(msg.whatsapp || '');
                    $('#editarMensaje').val(msg.mensaje);
                    $('#editarStatus').val(msg.status);
                    $('#editarModal').modal('show');
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    showSwalWithTheme({
                        title: 'Error',
                        text: 'No se pudo cargar el mensaje para editar.',
                        icon: 'error',
                        confirmButtonText: 'Aceptar'
                    });
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="fas fa-edit"></i>');
                }
            });
        });
        
        // Eliminar mensaje individual (con stopPropagation para evitar modales duplicados)
        $('.eliminar-msg').click(function(e) {
            e.stopPropagation(); // Evita que el evento se propague a la fila
            eliminarId = $(this).data('id');
            $('#confirmBackupCheck').prop('checked', false);
            $('#confirmarEliminarModal').modal('show');
        });
        
        $('#confirmarEliminarBtn').click(function() {
            const hacerBackup = $('#confirmBackupCheck').is(':checked') ? 1 : 0;
            
            $('<form method="POST">')
                .append($('<input>', { name: 'accion', value: 'eliminar', type: 'hidden' }))
                .append($('<input>', { name: 'id', value: eliminarId, type: 'hidden' }))
                .append($('<input>', { name: 'hacer_backup', value: hacerBackup, type: 'hidden' }))
                .appendTo('body')
                .submit();
        });
        
        // Eliminar backup de archivo
        $('.eliminar-backup-archivo').click(function(e) {
            e.stopPropagation();
            eliminarArchivo = $(this).data('archivo');
            $('#archivoAEliminar').text(eliminarArchivo);
            $('#confirmarEliminarBackupArchivoModal').modal('show');
        });
        
        $('#confirmarEliminarBackupArchivoBtn').click(function() {
            if (eliminarArchivo) {
                $('<form method="POST">')
                    .append($('<input>', { name: 'accion', value: 'eliminar_backup_archivo', type: 'hidden' }))
                    .append($('<input>', { name: 'archivo', value: eliminarArchivo, type: 'hidden' }))
                    .appendTo('body')
                    .submit();
            }
        });
        
        // Seleccionar todos
        $('#seleccionarTodos').change(function() {
            $('.seleccionar-msg').prop('checked', $(this).is(':checked'));
            actualizarBotonEliminar();
        });
        
        $('.seleccionar-msg').change(function() {
            actualizarBotonEliminar();
        });
        
        function actualizarBotonEliminar() {
            const seleccionados = $('.seleccionar-msg:checked').length;
            $('#eliminarSeleccionadosBtn').prop('disabled', seleccionados === 0);
            if (seleccionados > 0) {
                $('#eliminarSeleccionadosBtn').html(`<i class="fas fa-trash"></i> Eliminar seleccionados (${seleccionados})`);
            } else {
                $('#eliminarSeleccionadosBtn').html(`<i class="fas fa-trash"></i> Eliminar seleccionados`);
            }
        }
        
        $('#eliminarSeleccionadosBtn').click(function() {
            const ids = [];
            $('.seleccionar-msg:checked').each(function() {
                ids.push($(this).val());
            });
            
            if (ids.length === 0) return;
            
            const hacerBackup = $('#backupAntesEliminar').is(':checked') ? 1 : 0;
            
            $('<form method="POST">')
                .append($('<input>', { name: 'accion', value: 'eliminar_multiples', type: 'hidden' }))
                .append($('<input>', { name: 'hacer_backup', value: hacerBackup, type: 'hidden' }))
                .each(function() {
                    ids.forEach(id => {
                        $(this).append($('<input>', { name: 'ids[]', value: id, type: 'hidden' }));
                    });
                })
                .appendTo('body')
                .submit();
        });
        
        $('#exportarBtn').click(function() {
            const params = new URLSearchParams(window.location.search);
            params.set('accion', 'exportar_csv');
            window.location.href = '?' + params.toString();
        });
        
        function escapeHtml(text) {
            if (!text) return '';
            return text
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
        
        // NOTA: Se ha eliminado el evento de click en la fila (.message-row.click()) 
        // para evitar que se abran modales duplicados. Ahora SOLO los botones 
        // específicos abren sus respectivos modales.
    </script>
</body>
</html>