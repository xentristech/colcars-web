<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\mass-communications.php
 * COMUNICACIONES MASIVAS - Panel de Administración
 * MODIFICADO: Usa CDN en lugar de archivos locales
 * MODIFICADO: Eliminados scripts duplicados (jQuery y Bootstrap ya están en sidebar)
 * MODIFICADO: Eliminada función toggleAdminSidebar duplicada
 * MODIFICADO: Rutas absolutas corregidas (sin /easycarluxury/)
 * MODIFICADO: Añadido tema claro/oscuro (como en categorias.php)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

// Handle communication sending
$result = null;
$smtpError = null;

// Obtener configuración SMTP guardada o usar valores por defecto
$smtpConfig = [
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => '',
    'password' => '',
    'encryption' => 'tls',
    'from_email' => '',
    'from_name' => 'Easy Car Luxury'
];

// =====================================================
// CARGA DE CONFIGURACIÓN SMTP DESDE BASE DE DATOS (CORREGIDO)
// =====================================================
try {
    // Verificar si la tabla existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'system_config'")->rowCount() > 0;

    if ($tableExists) {
        // Verificar si las columnas existen
        $columns = $pdo->query("SHOW COLUMNS FROM system_config")->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('config_key', $columns) && in_array('config_value', $columns)) {
            $stmt = $pdo->prepare("SELECT config_key, config_value FROM system_config WHERE config_key LIKE 'smtp_%'");
            $stmt->execute();
            $dbConfig = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Asignar valores explícitamente
            foreach ($dbConfig as $row) {
                $key = $row['config_key'];
                $value = $row['config_value'];

                switch ($key) {
                    case 'smtp_host':
                        $smtpConfig['host'] = $value;
                        break;
                    case 'smtp_port':
                        $smtpConfig['port'] = (int) $value;
                        break;
                    case 'smtp_username':
                        $smtpConfig['username'] = $value;
                        break;
                    case 'smtp_password':
                        $smtpConfig['password'] = $value;
                        break;
                    case 'smtp_encryption':
                        $smtpConfig['encryption'] = $value;
                        break;
                    case 'smtp_from_email':
                        $smtpConfig['from_email'] = $value;
                        break;
                    case 'smtp_from_name':
                        $smtpConfig['from_name'] = $value;
                        break;
                }
            }
        }
    }
} catch (Exception $e) {
    error_log("Error cargando configuración SMTP: " . $e->getMessage());
}

// Guardar configuración SMTP si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    try {
        $smtpKeys = [
            'smtp_host' => $_POST['smtp_host'],
            'smtp_port' => $_POST['smtp_port'],
            'smtp_username' => $_POST['smtp_username'],
            'smtp_password' => $_POST['smtp_password'],
            'smtp_encryption' => $_POST['smtp_encryption'],
            'smtp_from_email' => $_POST['smtp_from_email'],
            'smtp_from_name' => $_POST['smtp_from_name']
        ];

        foreach ($smtpKeys as $key => $value) {
            $stmt = $pdo->prepare("INSERT INTO system_config (config_key, config_value, config_type) 
                                VALUES (?, ?, 'text')
                                ON DUPLICATE KEY UPDATE config_value = ?");
            $stmt->execute([$key, $value, $value]);
        }

        $smtpConfig = array_merge($smtpConfig, $smtpKeys);
        $smtpError = "✅ Configuración SMTP guardada correctamente";

    } catch (Exception $e) {
        $smtpError = "❌ Error al guardar configuración: " . $e->getMessage();
        error_log("Error guardando SMTP: " . $e->getMessage());
    }
}

// Probar conexión SMTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_smtp'])) {
    $testMail = new PHPMailer(true);
    try {
        $testMail->isSMTP();
        $testMail->Host = $_POST['test_host'];
        $testMail->SMTPAuth = true;
        $testMail->Username = $_POST['test_username'];
        $testMail->Password = $_POST['test_password'];
        $testMail->SMTPSecure = $_POST['test_encryption'];
        $testMail->Port = $_POST['test_port'];
        $testMail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $testMail->setFrom($_POST['test_from_email'], $_POST['test_from_name']);
        $testMail->addAddress($_POST['test_from_email'], 'Test');
        $testMail->Subject = 'Prueba de conexión SMTP - Easy Car Luxury';
        $testMail->Body = 'Esta es una prueba de que la configuración SMTP funciona correctamente.';
        $testMail->AltBody = 'Prueba de conexión SMTP';

        $testMail->send();
        $smtpError = "✅ Prueba exitosa! El correo se envió correctamente a {$_POST['test_from_email']}";
    } catch (Exception $e) {
        $smtpError = "❌ Error en la prueba: " . $testMail->ErrorInfo;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_communication'])) {
    $type = $_POST['type'];
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $target = $_POST['target'];

    // Get target users
    $whereClause = "1=1";

    switch ($target) {
        case 'free_users':
            $whereClause .= " AND tipo_cuenta = 'free'";
            break;
        case 'pro_users':
            $whereClause .= " AND tipo_cuenta = 'pro'";
            break;
        case 'premium_users':
            $whereClause .= " AND tipo_cuenta = 'premium'";
            break;
        case 'elite_users':
            $whereClause .= " AND tipo_cuenta = 'elite'";
            break;
        case 'sistema_users':
            $whereClause .= " AND tipo_cuenta = 'sistema'";
            break;
        case 'administracion_users':
            $whereClause .= " AND categoria_usuario = 'administracion'";
            break;
        case 'active_users':
            $whereClause .= " AND activo = 1";
            break;
        case 'inactive_users':
            $whereClause .= " AND activo = 0";
            break;
        case 'all_users':
        default:
            break;
    }

    $query = "SELECT id, nombre_completo, email, telefono FROM usuarios WHERE $whereClause";
    $users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

    $successCount = 0;
    $failCount = 0;
    $errorDetails = [];

    foreach ($users as $user) {
        if ($type === 'email') {
            $mail = new PHPMailer(true);

            try {
                $mail->isSMTP();
                $mail->Host = $smtpConfig['host'];
                $mail->SMTPAuth = true;
                $mail->Username = $smtpConfig['username'];
                $mail->Password = $smtpConfig['password'];
                $mail->SMTPSecure = $smtpConfig['encryption'];
                $mail->Port = $smtpConfig['port'];

                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
                $mail->addAddress($user['email'], $user['nombre_completo']);

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->CharSet = 'UTF-8';

                $emailBody = "
                    <html>
                    <head><title>$subject</title></head>
                    <body>
                        <h2>Easy Car Luxury</h2>
                        <p>Hola {$user['nombre_completo']},</p>
                        <div>" . nl2br(htmlspecialchars($message)) . "</div>
                        <hr>
                        <small>Este es un mensaje automático, por favor no responder.</small>
                    </body>
                    </html>
                ";

                $mail->Body = $emailBody;
                $mail->AltBody = strip_tags($message);

                $mail->send();
                $successCount++;

                usleep(500000);

            } catch (Exception $e) {
                $failCount++;
                $errorDetails[] = $user['email'] . ": " . $mail->ErrorInfo;
                error_log("Error PHPMailer a {$user['email']}: " . $mail->ErrorInfo);
            }
        } elseif ($type === 'whatsapp') {
            $successCount++;
        }
    }

    $query = "INSERT INTO mass_communications 
            (admin_id, type, subject, message, target, recipients_count, sent_count, failed_count, created_at) 
            VALUES 
            (:admin_id, :type, :subject, :message, :target, :recipients, :sent, :failed, NOW())";
    $stmt = $pdo->prepare($query);
    $stmt->execute([
        ':admin_id' => $admin['id'],
        ':type' => $type,
        ':subject' => $subject,
        ':message' => $message,
        ':target' => $target,
        ':recipients' => count($users),
        ':sent' => $successCount,
        ':failed' => $failCount
    ]);

    if (method_exists($adminAuth, 'logAction')) {
        $adminAuth->logAction(
            $admin['id'],
            'mass_communication',
            'communication',
            $pdo->lastInsertId(),
            json_encode(['type' => $type, 'target' => $target, 'recipients' => count($users)])
        );
    }

    $result = [
        'success' => true,
        'sent' => $successCount,
        'failed' => $failCount,
        'total' => count($users),
        'errors' => $errorDetails
    ];
}

// Get communication history
$query = "SELECT c.*, u.nombre_completo as admin_name 
            FROM mass_communications c
            JOIN usuarios u ON c.admin_id = u.id
            ORDER BY c.created_at DESC
            LIMIT 50";
$communications = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics for targeting
$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN tipo_cuenta = 'free' THEN 1 ELSE 0 END) as free,
                SUM(CASE WHEN tipo_cuenta = 'pro' THEN 1 ELSE 0 END) as pro,
                SUM(CASE WHEN tipo_cuenta = 'premium' THEN 1 ELSE 0 END) as premium,
                SUM(CASE WHEN tipo_cuenta = 'elite' THEN 1 ELSE 0 END) as elite,
                SUM(CASE WHEN tipo_cuenta = 'sistema' THEN 1 ELSE 0 END) as sistema,
                SUM(CASE WHEN categoria_usuario = 'administracion' THEN 1 ELSE 0 END) as administracion,
                SUM(CASE WHEN rol_id = 6 THEN 1 ELSE 0 END) as users,
                SUM(CASE WHEN rol_id IN (1, 2, 3, 4, 5) THEN 1 ELSE 0 END) as admins
                FROM usuarios";
$userStats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

if (!$userStats) {
    $userStats = [
        'total' => 0,
        'active' => 0,
        'inactive' => 0,
        'free' => 0,
        'pro' => 0,
        'premium' => 0,
        'elite' => 0,
        'sistema' => 0,
        'administracion' => 0,
        'users' => 0,
        'admins' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Comunicaciones Masivas - Easy Car Luxury</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS - CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
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
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        .sidebar-column {
            flex-shrink: 0;
        }

        .admin-main {
            flex: 1;
            width: auto;
            padding: 15px 20px;
            background: var(--bg-primary);
            margin-left: 0;
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

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
        }

        [data-theme="dark"] .form-label {
            color: var(--text-primary);
        }

        [data-theme="dark"] .text-muted {
            color: var(--text-secondary) !important;
        }

        .user-stats {
            padding: 10px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        .stat-row:last-child {
            border-bottom: none;
        }

        .communication-item {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 10px;
        }

        .communication-item:last-child {
            border-bottom: none;
        }

        .comm-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .comm-type {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
        }

        .comm-email {
            background: #e3f2fd;
            color: #1976d2;
        }

        [data-theme="dark"] .comm-email {
            background: #0d3b66;
            color: #7ec8e0;
        }

        .comm-whatsapp {
            background: #d4f8e8;
            color: #075e54;
        }

        [data-theme="dark"] .comm-whatsapp {
            background: #0a4d3e;
            color: #7ef5d0;
        }

        .comm-stats {
            display: flex;
            gap: 15px;
            margin-top: 5px;
            font-size: 0.8rem;
        }

        .comm-date {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .comm-subject {
            font-size: 0.85rem;
            color: var(--text-primary);
        }

        .comm-admin {
            margin-top: 5px;
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
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

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200, 168, 107, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .btn-outline-secondary {
            border-color: #c8a86b;
            color: #c8a86b;
            background: transparent;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-outline-secondary:hover {
            background: #c8a86b;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
        }

        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
        }

        .btn-info {
            background: #17a2b8;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .form-select,
        .form-control {
            border-radius: 8px;
            border: 1px solid var(--input-border);
            padding: 8px 12px;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-select:focus,
        .form-control:focus {
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200, 168, 107, 0.2);
            outline: none;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffecb5;
            border-radius: 8px;
            color: #856404;
        }

        [data-theme="dark"] .alert-warning {
            background: #3d2e00;
            border-color: #5c4600;
            color: #ffd966;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        [data-theme="dark"] .alert-success {
            background: #1a4a2a;
            border-color: #2a6a3a;
            color: #ccffcc;
        }

        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        [data-theme="dark"] .alert-danger {
            background: #5a1a1a;
            border-color: #8b3a3a;
            color: #ffcccc;
        }

        .alert-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }

        [data-theme="dark"] .alert-info {
            background: #0a4a54;
            border-color: #0d6d7a;
            color: #7fd9e8;
        }

        .error-list {
            max-height: 200px;
            overflow-y: auto;
            font-size: 0.8rem;
        }

        .modal-header {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            color: white;
            border-bottom: none;
        }

        .modal-header .btn-close {
            color: white;
            filter: brightness(0) invert(1);
        }

        /* Estilos para las tres columnas */
        .three-columns {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .col-statistics {
            flex: 1;
            min-width: 280px;
        }

        .col-history {
            flex: 1;
            min-width: 280px;
        }

        .col-comunication {
            flex: 1.2;
            min-width: 320px;
        }

        /* Fuerza tres columnas en una sola línea (desktop) */
        .three-columns {
            display: flex;
            flex-direction: row;
            gap: 20px;
            flex-wrap: nowrap;
            width: 100%;
        }
        
        .col-statistics,
        .col-history,
        .col-comunication {
            flex: 1;
            min-width: 0;
            width: auto;
        }
        
        .col-comunication {
            flex: 1.2;
        }

        /* Ajustes responsivos - en móviles se apilan y se agrega margen top */
        @media (max-width: 992px) {
            .three-columns {
                flex-wrap: wrap;
            }
            .col-statistics,
            .col-history,
            .col-comunication {
                min-width: 100%;
            }
            .admin-main {
                margin-top: 80px !important;
                padding: 60px 15px 15px;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }

        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .header-actions {
                width: 100%;
            }
            .btn-outline-secondary {
                width: 100%;
                text-align: center;
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
                    <h1><i class="fas fa-bullhorn"></i> Comunicaciones Masivas</h1>
                    <p>Envía emails o WhatsApp a grupos de usuarios</p>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal"
                        data-bs-target="#smtpModal">
                        <i class="fas fa-cog"></i> Configuración de Correo
                    </button>
                </div>
            </div>

            <?php if ($smtpError): ?>
                <div class="alert alert-<?php echo strpos($smtpError, '✅') !== false ? 'success' : (strpos($smtpError, '❌') !== false ? 'danger' : 'info'); ?> alert-dismissible fade show"
                    role="alert">
                    <i
                        class="fas <?php echo strpos($smtpError, '✅') !== false ? 'fa-check-circle' : (strpos($smtpError, '❌') !== false ? 'fa-exclamation-triangle' : 'fa-info-circle'); ?>"></i>
                    <?php echo $smtpError; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($result && isset($result['success']) && $result['success']): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i>
                    Comunicación enviada: <?php echo $result['sent']; ?> enviados, <?php echo $result['failed']; ?> fallidos
                    (Total: <?php echo $result['total']; ?>)
                    <?php if (!empty($result['errors'])): ?>
                        <div class="mt-2">
                            <strong>Errores:</strong>
                            <ul class="error-list">
                                <?php foreach (array_slice($result['errors'], 0, 5) as $error): ?>
                                    <li class="text-danger small"><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- TRES COLUMNAS: Estadísticas | Historial | Nueva Comunicación -->
            <div class="three-columns">
                <!-- Columna 1: Estadísticas de Usuarios -->
                <div class="col-statistics">
                    <div class="data-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-pie"></i> Estadísticas de Usuarios</h3>
                        </div>
                        <div class="card-body">
                            <div class="user-stats">
                                <div class="stat-row"><span>Total usuarios:</span><strong><?php echo number_format($userStats['total']); ?></strong></div>
                                <div class="stat-row"><span>✅ Usuarios activos:</span><strong><?php echo number_format($userStats['active']); ?></strong></div>
                                <div class="stat-row"><span>❌ Usuarios inactivos:</span><strong><?php echo number_format($userStats['inactive']); ?></strong></div>
                                <div class="stat-row"><span>🔓 Free:</span><strong><?php echo number_format($userStats['free']); ?></strong></div>
                                <div class="stat-row"><span>⭐ Pro:</span><strong><?php echo number_format($userStats['pro']); ?></strong></div>
                                <div class="stat-row"><span>💎 Premium:</span><strong><?php echo number_format($userStats['premium']); ?></strong></div>
                                <div class="stat-row"><span>👑 Elite:</span><strong><?php echo number_format($userStats['elite']); ?></strong></div>
                                <div class="stat-row"><span>🖥️ Sistema:</span><strong><?php echo number_format($userStats['sistema']); ?></strong></div>
                                <div class="stat-row"><span>📋 Administración:</span><strong><?php echo number_format($userStats['administracion']); ?></strong></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Columna 2: Historial de Envíos -->
                <div class="col-history">
                    <div class="data-card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Historial de Envíos</h3>
                        </div>
                        <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                            <?php if (!empty($communications)): ?>
                                <?php foreach ($communications as $comm): ?>
                                <div class="communication-item">
                                    <div class="comm-header">
                                        <span class="comm-type comm-<?php echo $comm['type']; ?>">
                                            <i class="fas fa-<?php echo $comm['type'] === 'email' ? 'envelope' : 'whatsapp'; ?>"></i>
                                            <?php echo strtoupper($comm['type']); ?>
                                        </span>
                                        <span class="comm-date"><?php echo date('d/m/Y H:i', strtotime($comm['created_at'])); ?></span>
                                    </div>
                                    <div class="comm-subject"><strong><?php echo htmlspecialchars($comm['subject']); ?></strong></div>
                                    <div class="comm-stats">
                                        <span><i class="fas fa-users"></i> <?php echo $comm['recipients_count']; ?></span>
                                        <span class="text-success"><i class="fas fa-check"></i> <?php echo $comm['sent_count']; ?></span>
                                        <span class="text-danger"><i class="fas fa-times"></i> <?php echo $comm['failed_count']; ?></span>
                                    </div>
                                    <div class="comm-admin"><small>Por: <?php echo htmlspecialchars($comm['admin_name']); ?></small></div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">No hay envíos registrados</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Columna 3: Nueva Comunicación -->
                <div class="col-comunication">
                    <div class="data-card">
                        <div class="card-header">
                            <h3><i class="fas fa-paper-plane"></i> Nueva Comunicación</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($smtpConfig['username']) || empty($smtpConfig['password'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Configuración SMTP incompleta!</strong> Por favor, configura tus credenciales de correo haciendo clic en el botón <strong>"Configuración de Correo"</strong> en la parte superior derecha.
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Tipo de comunicación *</label>
                                    <select class="form-select" name="type" required>
                                        <option value="email">Email</option>
                                        <option value="whatsapp" disabled>WhatsApp (Próximamente)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Asunto *</label>
                                    <input type="text" class="form-control" name="subject" required maxlength="200">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Mensaje *</label>
                                    <textarea class="form-control" name="message" rows="6" required></textarea>
                                    <small class="text-muted">Puedes usar HTML para dar formato al mensaje</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Audiencia objetivo *</label>
                                    <select class="form-select" name="target" id="targetSelect" required>
                                        <option value="free_users">🔓 Free (<?php echo number_format($userStats['free']); ?>)</option>
                                        <option value="pro_users">⭐ Pro (<?php echo number_format($userStats['pro']); ?>)</option>
                                        <option value="premium_users">💎 Premium (<?php echo number_format($userStats['premium']); ?>)</option>
                                        <option value="elite_users">👑 Elite (<?php echo number_format($userStats['elite']); ?>)</option>
                                        <option value="sistema_users">🖥️ Sistema (<?php echo number_format($userStats['sistema']); ?>)</option>
                                        <option value="administracion_users">📋 Administración (<?php echo number_format($userStats['administracion']); ?>)</option>
                                        <option value="active_users">✅ Usuarios activos (<?php echo number_format($userStats['active']); ?>)</option>
                                        <option value="inactive_users">❌ Usuarios inactivos (<?php echo number_format($userStats['inactive']); ?>)</option>
                                        <option value="all_users">🌐 Todos los usuarios (<?php echo number_format($userStats['total']); ?>)</option>
                                    </select>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Precaución:</strong> Esta acción enviará mensajes a <span id="recipientCount"><?php echo number_format($userStats['free']); ?></span> usuarios. 
                                    Asegúrate de que el mensaje sea apropiado.
                                </div>
                                
                                <button type="submit" name="send_communication" class="btn btn-primary" onclick="return confirm('¿Estás seguro de enviar este mensaje a todos los usuarios seleccionados?')" <?php echo (empty($smtpConfig['username']) || empty($smtpConfig['password'])) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane"></i> Enviar Comunicación
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Botón tema claro/oscuro -->
    <button class="btn-theme" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>

    <!-- Modal de Configuración SMTP -->
    <div class="modal fade" id="smtpModal" tabindex="-1" aria-labelledby="smtpModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="smtpModalLabel"><i class="fas fa-envelope"></i> Configuración de Correo
                        SMTP</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="smtpConfigForm" class="row g-3">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i>
                                Configura los datos de tu servidor SMTP para poder enviar correos electrónicos.
                                <br><small>Ejemplo para Gmail: smtp.gmail.com, puerto 587, TLS</small>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Servidor SMTP *</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                value="<?php echo htmlspecialchars($smtpConfig['host']); ?>" required>
                            <small class="text-muted">Ej: smtp.gmail.com</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Puerto *</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                value="<?php echo $smtpConfig['port']; ?>" required>
                            <small class="text-muted">587 o 465</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cifrado *</label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption" required>
                                <option value="tls" <?php echo $smtpConfig['encryption'] == 'tls' ? 'selected' : ''; ?>>
                                    TLS (recomendado)</option>
                                <option value="ssl" <?php echo $smtpConfig['encryption'] == 'ssl' ? 'selected' : ''; ?>>
                                    SSL</option>
                                <option value="">Sin cifrado</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <hr>
                            <h6><i class="fas fa-user"></i> Credenciales</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usuario SMTP *</label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                value="<?php echo htmlspecialchars($smtpConfig['username']); ?>"
                                placeholder="tuemail@gmail.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contraseña SMTP *</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                value="<?php echo htmlspecialchars($smtpConfig['password']); ?>" placeholder="••••••••"
                                required>
                            <small class="text-muted">Para Gmail con 2FA, usa una contraseña de aplicación</small>
                        </div>
                        <div class="col-md-12">
                            <hr>
                            <h6><i class="fas fa-envelope"></i> Datos del remitente</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email del remitente *</label>
                            <input type="email" class="form-control" id="smtp_from_email" name="smtp_from_email"
                                value="<?php echo htmlspecialchars($smtpConfig['from_email']); ?>"
                                placeholder="noreply@easycarluxury.com" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nombre del remitente *</label>
                            <input type="text" class="form-control" id="smtp_from_name" name="smtp_from_name"
                                value="<?php echo htmlspecialchars($smtpConfig['from_name']); ?>"
                                placeholder="Easy Car Luxury" required>
                        </div>
                    </form>

                    <hr>

                    <form method="POST" id="smtpTestForm" class="row g-3">
                        <h6><i class="fas fa-vial"></i> Probar conexión con los datos ingresados</h6>
                        <div class="col-md-12">
                            <div class="alert alert-secondary">
                                <i class="fas fa-lightbulb"></i>
                                Edita los valores arriba y estos se actualizarán automáticamente para la prueba.
                            </div>
                        </div>
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="test_host" name="test_host"
                                placeholder="Servidor SMTP" required>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" id="test_port" name="test_port"
                                placeholder="Puerto" required>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="test_encryption" name="test_encryption">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="test_username" name="test_username"
                                placeholder="Usuario" required>
                        </div>
                        <div class="col-md-6">
                            <input type="password" class="form-control" id="test_password" name="test_password"
                                placeholder="Contraseña" required>
                        </div>
                        <div class="col-md-6">
                            <input type="email" class="form-control" id="test_from_email" name="test_from_email"
                                placeholder="Email remitente" required>
                        </div>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="test_from_name" name="test_from_name"
                                placeholder="Nombre remitente" required>
                        </div>
                        <div class="col-12">
                            <button type="submit" name="test_smtp" class="btn btn-info"
                                onclick="return confirm('¿Enviar correo de prueba? Recibirás un email de confirmación.')">
                                <i class="fas fa-paper-plane"></i> Probar envío
                            </button>
                            <small class="text-muted ms-2">Enviará un correo de prueba a tu propio email</small>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" form="smtpConfigForm" name="save_smtp" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar configuración
                    </button>
                </div>
            </div>
        </div>
    </div>

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

        const userStats = <?php echo json_encode($userStats); ?>;

        $('#targetSelect').change(function () {
            const target = $(this).val();
            let count = 0;

            switch (target) {
                case 'free_users': count = userStats.free || 0; break;
                case 'pro_users': count = userStats.pro || 0; break;
                case 'premium_users': count = userStats.premium || 0; break;
                case 'elite_users': count = userStats.elite || 0; break;
                case 'sistema_users': count = userStats.sistema || 0; break;
                case 'administracion_users': count = userStats.administracion || 0; break;
                case 'active_users': count = userStats.active || 0; break;
                case 'inactive_users': count = userStats.inactive || 0; break;
                case 'all_users': count = userStats.total || 0; break;
                default: count = userStats.total || 0;
            }

            $('#recipientCount').text(count.toLocaleString());
        });

        // Sincronizar valores entre configuración y prueba
        function syncTestFields() {
            $('#test_host').val($('#smtp_host').val());
            $('#test_port').val($('#smtp_port').val());
            $('#test_encryption').val($('#smtp_encryption').val());
            $('#test_username').val($('#smtp_username').val());
            $('#test_password').val($('#smtp_password').val());
            $('#test_from_email').val($('#smtp_from_email').val());
            $('#test_from_name').val($('#smtp_from_name').val());
        }

        $('#smtpModal').on('show.bs.modal', function () {
            syncTestFields();
        });

        $('#smtp_host, #smtp_port, #smtp_encryption, #smtp_username, #smtp_password, #smtp_from_email, #smtp_from_name').on('input change', function () {
            syncTestFields();
        });

        $(document).ready(function () {
            syncTestFields();
        });
    </script>
</body>
</html>