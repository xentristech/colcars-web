<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\system-config.php
 * CONFIGURACIÓN DEL SISTEMA - Panel de Administración
 * MODIFICADO: Usa CDN en lugar de archivos locales
 * MODIFICADO: Eliminados scripts duplicados (jQuery y Bootstrap ya están en sidebar)
 * MODIFICADO: Eliminado CSS local innecesario
 * MODIFICADO: Rutas absolutas corregidas (sin /easycarluxury/)
 * MODIFICADO: Añadido tema claro/oscuro (como en categorias.php)
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';
require_once __DIR__ . '/../../includes/audit-log.php';

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

$audit = null;
if ($admin && isset($admin['id'])) {
    $audit = new AuditLog($pdo, $admin['id'], $admin['email'] ?? '', $admin['role'] ?? 'superadmin');
}

$isSuperAdmin = false;
if ($admin && isset($admin['rol_id'])) {
    $isSuperAdmin = ($admin['rol_id'] == 1);
} elseif ($admin && isset($admin['role'])) {
    $isSuperAdmin = ($admin['role'] === 'superadmin');
}

if (!$isSuperAdmin) {
    if ($audit) {
        $audit->register('ACCESS_DENIED', 'system_config', null, null, null, 'Intento de acceso denegado a configuración del sistema');
    }
    header('Location: index.php');
    exit;
}

// Procesar POST y redirigir (PRG)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $redirect = true;
    $actionType = '';
    
    if (isset($_POST['update_config'])) {
        $configs = [
            'site_name', 'site_description', 'contact_email', 'support_phone',
            'maintenance_mode', 'max_publications_free', 'max_publications_pro',
            'max_publications_premium', 'max_publications_elite', 'commission_rate',
            'dian_environment', 'google_analytics_id', 'facebook_pixel_id',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption'
        ];
        
        $stmt = $pdo->prepare("
            INSERT INTO system_config (config_key, config_value, updated_by, updated_at) 
            VALUES (:key, :value, :admin, NOW())
            ON DUPLICATE KEY UPDATE 
            config_value = VALUES(config_value), 
            updated_by = VALUES(updated_by), 
            updated_at = VALUES(updated_at)
        ");
        
        foreach ($configs as $config) {
            $value = $_POST[$config] ?? '';
            if ($config === 'smtp_password' && empty($value)) {
                continue;
            }
            $stmt->execute([':key' => $config, ':value' => $value, ':admin' => $admin['id']]);
        }
        
        if ($audit) {
            $audit->register('UPDATE', 'system_config', null, null, null, 'Configuración del sistema actualizada');
        }
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Configuración actualizada exitosamente"];
        $actionType = 'update_config';
    }
    
    if (isset($_POST['clear_cache'])) {
        $cacheDir = __DIR__ . '/../../cache/';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        if ($audit) {
            $audit->register('CLEAR_CACHE', 'system', null, null, null, 'Caché del sistema limpiada');
        }
        $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Caché limpiado exitosamente"];
        $actionType = 'clear_cache';
    }
    
    if (isset($_POST['create_backup'])) {
        require_once '../../includes/backup.php';
        $backup = new DatabaseBackup($pdo);
        $result = $backup->createBackup();
        
        if ($result['success']) {
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Backup creado: " . $result['filename']];
            if ($audit) {
                $audit->register('CREATE_BACKUP', 'database', null, null, null, "Backup creado: {$result['filename']}");
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'error', 'text' => "Error al crear backup: " . $result['error']];
        }
        $actionType = 'create_backup';
    }
    
    // Redirigir para evitar reenvío del POST
    header('Location: system-config.php?action=' . urlencode($actionType));
    exit;
}

// Recuperar mensaje flash después de redirección
$flashMessage = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : null;
unset($_SESSION['flash_message']);

// Cargar configuraciones para mostrar en el formulario
$configs = $pdo->query("SELECT config_key, config_value FROM system_config")->fetchAll(PDO::FETCH_KEY_PAIR);

$backupDir = __DIR__ . '/../../backups/';
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . 'backup_*.sql.gz');
    rsort($files);
    $backups = array_slice($files, 0, 10);
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Configuración del Sistema - Easy Car Luxury</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    
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
        
        .form-select, .form-control {
            border-radius: 8px;
            border: 1px solid var(--input-border);
            padding: 8px 12px;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        
        .form-select:focus, .form-control:focus {
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
            outline: none;
        }
        
        .form-label {
            color: var(--text-primary);
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
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
        }
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-warning {
            background: #ffc107;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            color: #1a1a2e;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-warning:hover {
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: #17a2b8;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-info:hover {
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: #dc3545;
            border: none;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
        }
        
        [data-theme="dark"] .btn-warning {
            background: #d4a000;
            color: #1a1a2e;
        }
        
        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
            border: none;
            color: white;
        }
        
        .btn-sm {
            padding: 4px 8px;
            font-size: 0.75rem;
            border-radius: 4px;
        }
        
        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .admin-table th,
        .admin-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .admin-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.85rem;
        }
        
        .admin-table tr:hover {
            background: var(--table-hover);
        }
        
        .mt-3 {
            margin-top: 1rem;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-wrapper .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--text-secondary);
            background: transparent;
            border: none;
            padding: 0;
            z-index: 10;
        }
        
        .password-wrapper .toggle-password:hover {
            color: #c8a86b;
        }
        
        .password-wrapper .form-control {
            padding-right: 40px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
        }
        
        [data-theme="dark"] .alert-success {
            background-color: #1a4a2a;
            color: #ccffcc;
            border-color: #2a6a3a;
        }
        
        [data-theme="dark"] .alert-error {
            background-color: #5a1a1a;
            color: #ffcccc;
            border-color: #8b3a3a;
        }
        
        [data-theme="dark"] .text-muted {
            color: var(--text-secondary) !important;
        }
        
        /* Ajustes responsivos para móvil */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 80px !important;
                padding: 60px 15px 15px;
            }
            .data-card .card-body .row .btn {
                margin-bottom: 12px;
            }
            .table-responsive {
                overflow-x: scroll !important;
                -webkit-overflow-scrolling: touch;
                display: block;
                width: 100%;
                padding-bottom: 10px;
            }
            .admin-table {
                width: 800px !important;
                min-width: 800px !important;
                max-width: none !important;
                display: table !important;
                border-collapse: collapse;
            }
            .admin-table th,
            .admin-table td {
                padding: 8px 10px;
                font-size: 0.75rem;
                vertical-align: middle;
            }
            .admin-table td:last-child {
                white-space: normal;
                min-width: 200px;
            }
            .btn-sm {
                padding: 4px 6px;
                font-size: 0.7rem;
                margin: 2px 2px;
                display: inline-block;
            }
            .table-responsive::-webkit-scrollbar {
                height: 6px;
            }
            .table-responsive::-webkit-scrollbar-track {
                background: #f1f1f1;
                border-radius: 4px;
            }
            .table-responsive::-webkit-scrollbar-thumb {
                background: #c8a86b;
                border-radius: 4px;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }
        
        @media (max-width: 576px) {
            .admin-table th,
            .admin-table td {
                padding: 6px 8px;
                font-size: 0.7rem;
            }
            .btn-sm {
                padding: 3px 5px;
                font-size: 0.65rem;
            }
        }
        
        code {
            color: var(--text-primary);
            background: var(--bg-primary);
            padding: 2px 4px;
            border-radius: 4px;
        }
        
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="admin-container">

            <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

        
        <main class="admin-main">
            <div class="admin-header">
                <div class="header-title">
                    <h1><i class="fas fa-cog"></i> Configuración del Sistema</h1>
                    <p>Gestiona la configuración global de la plataforma</p>
                </div>
            </div>
            
            <?php if ($flashMessage): ?>
                <div class="alert alert-<?php echo $flashMessage['type'] === 'error' ? 'error' : $flashMessage['type']; ?> alert-dismissible fade show">
                    <?php echo htmlspecialchars($flashMessage['text']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="row" id="configForm">
                <div class="col-md-6">
                    <div class="data-card">
                        <div class="card-header">
                            <h3><i class="fas fa-globe"></i> Configuración General</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Nombre del sitio</label>
                                <input type="text" class="form-control" name="site_name" value="<?php echo htmlspecialchars($configs['site_name'] ?? 'Easy Car Luxury'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="site_description" rows="3"><?php echo htmlspecialchars($configs['site_description'] ?? 'Plataforma de compra y venta de vehículos de lujo'); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email de contacto</label>
                                <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($configs['contact_email'] ?? 'contacto@easycarluxury.com'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono de soporte</label>
                                <input type="text" class="form-control" name="support_phone" value="<?php echo htmlspecialchars($configs['support_phone'] ?? '+57 300 000 0000'); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Modo mantenimiento</label>
                                <select class="form-select" name="maintenance_mode">
                                    <option value="0" <?php echo ($configs['maintenance_mode'] ?? '0') == '0' ? 'selected' : ''; ?>>Desactivado</option>
                                    <option value="1" <?php echo ($configs['maintenance_mode'] ?? '0') == '1' ? 'selected' : ''; ?>>Activado</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-card mt-3">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Límites y Comisiones</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label class="form-label">Publicaciones Free</label>
                                    <input type="number" class="form-control" name="max_publications_free" value="<?php echo $configs['max_publications_free'] ?? 1; ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Publicaciones Pro</label>
                                    <input type="number" class="form-control" name="max_publications_pro" value="<?php echo $configs['max_publications_pro'] ?? 5; ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Publicaciones Premium</label>
                                    <input type="number" class="form-control" name="max_publications_premium" value="<?php echo $configs['max_publications_premium'] ?? 20; ?>">
                                </div>
                                <div class="col-6 mb-3">
                                    <label class="form-label">Publicaciones Elite</label>
                                    <input type="number" class="form-control" name="max_publications_elite" value="<?php echo $configs['max_publications_elite'] ?? 100; ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Comisión por venta (%)</label>
                                <input type="number" step="0.01" class="form-control" name="commission_rate" value="<?php echo $configs['commission_rate'] ?? 5; ?>">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="data-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-simple"></i> Analytics y Tracking</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Google Analytics ID</label>
                                <input type="text" class="form-control" name="google_analytics_id" placeholder="G-XXXXXXXXXX" value="<?php echo htmlspecialchars($configs['google_analytics_id'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Facebook Pixel ID</label>
                                <input type="text" class="form-control" name="facebook_pixel_id" placeholder="XXXXXXXXXXXXXXXXX" value="<?php echo htmlspecialchars($configs['facebook_pixel_id'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-card mt-3">
                        <div class="card-header">
                            <h3><i class="fas fa-envelope"></i> Configuración SMTP</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Host SMTP</label>
                                <input type="text" class="form-control" name="smtp_host" value="<?php echo htmlspecialchars($configs['smtp_host'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Puerto SMTP</label>
                                <input type="number" class="form-control" name="smtp_port" value="<?php echo $configs['smtp_port'] ?? 587; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Usuario SMTP</label>
                                <input type="text" class="form-control" name="smtp_username" value="<?php echo htmlspecialchars($configs['smtp_username'] ?? ''); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña SMTP</label>
                                <div class="password-wrapper">
                                    <input type="password" class="form-control" id="smtp_password" name="smtp_password" placeholder="Dejar vacío para no cambiar" value="<?php echo htmlspecialchars($configs['smtp_password'] ?? ''); ?>">
                                    <button type="button" class="toggle-password" data-target="smtp_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Encriptación</label>
                                <select class="form-select" name="smtp_encryption">
                                    <option value="tls" <?php echo ($configs['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                    <option value="ssl" <?php echo ($configs['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                    <option value="none" <?php echo ($configs['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>Ninguna</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="data-card mt-3">
                        <div class="card-header">
                            <h3><i class="fas fa-file-invoice"></i> Facturación DIAN</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Ambiente DIAN</label>
                                <select class="form-select" name="dian_environment">
                                    <option value="test" <?php echo ($configs['dian_environment'] ?? 'test') === 'test' ? 'selected' : ''; ?>>Pruebas (Habilitación)</option>
                                    <option value="production" <?php echo ($configs['dian_environment'] ?? '') === 'production' ? 'selected' : ''; ?>>Producción</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-12">
                    <div class="data-card mt-3">
                        <div class="card-header">
                            <h3><i class="fas fa-database"></i> Mantenimiento</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <button type="submit" name="update_config" class="btn btn-primary w-100" id="btnSaveConfig">
                                        <i class="fas fa-save"></i> Guardar Configuración
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="clear_cache" class="btn btn-warning w-100" id="btnClearCache">
                                        <i class="fas fa-trash-alt"></i> Limpiar Caché
                                    </button>
                                </div>
                                <div class="col-md-4">
                                    <button type="submit" name="create_backup" class="btn btn-info w-100" id="btnCreateBackup">
                                        <i class="fas fa-database"></i> Crear Backup
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            
            <div class="data-card mt-3">
                <div class="card-header">
                    <h3><i class="fas fa-archive"></i> Backups Disponibles</h3>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Archivo</th>
                                    <th>Fecha</th>
                                    <th>Tamaño</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                <?php $filename = basename($backup); ?>
                                <tr>
                                    <td><?php echo $filename; ?></td>
                                    <td><?php echo date('d/m/Y H:i:s', filemtime($backup)); ?></td>
                                    <td><?php echo round(filesize($backup) / 1024 / 1024, 2); ?> MB</div>
                                    <td>
                                        <a href="/backups/<?php echo $filename; ?>" class="btn btn-sm btn-primary btn-download" data-filename="<?php echo $filename; ?>">
                                            <i class="fas fa-download"></i> Descargar
                                        </a>
                                        <button class="btn btn-sm btn-danger btn-restore" data-filename="<?php echo $filename; ?>">
                                            <i class="fas fa-undo"></i> Restaurar
                                        </button>
                                        <button class="btn btn-sm btn-warning btn-delete" data-filename="<?php echo $filename; ?>">
                                            <i class="fas fa-trash-alt"></i> Eliminar
                                        </button>
                                     </div>
                                  </tr>
                                <?php endforeach; ?>
                                <?php if (empty($backups)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">No hay backups disponibles</div>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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

        $(document).ready(function() {
            var formSubmitted = false;
            
            // Función showAlert con SweetAlert2 y tema
            function showAlert(icon, title, message) {
                showSwalWithTheme({
                    icon: icon,
                    title: title,
                    text: message,
                    confirmButtonColor: '#c8a86b',
                    confirmButtonText: 'Aceptar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        formSubmitted = false;
                    }
                });
            }
            
            function showConfirm(icon, title, message, confirmText, callback) {
                showSwalWithTheme({
                    icon: icon,
                    title: title,
                    text: message,
                    showCancelButton: true,
                    confirmButtonColor: '#c8a86b',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: confirmText || 'Sí, continuar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        callback();
                    }
                });
            }
            
            function showLoading(title) {
                Swal.fire({
                    title: title || 'Procesando...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            }
            
            $('#btnSaveConfig').on('click', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                showConfirm('question', 'Guardar Configuración', '¿Estás seguro de que deseas guardar los cambios?', 'Sí, guardar', function() {
                    formSubmitted = true;
                    showLoading('Guardando configuración...');
                    $('<input>').attr({ type: 'hidden', name: 'update_config', value: '1' }).appendTo('#configForm');
                    $('#configForm').off('submit').submit();
                });
                return false;
            });
            
            $('#btnClearCache').on('click', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                showConfirm('warning', 'Limpiar Caché', '¿Estás seguro de que deseas limpiar la caché del sistema?', 'Sí, limpiar', function() {
                    formSubmitted = true;
                    showLoading('Limpiando caché...');
                    $('<input>').attr({ type: 'hidden', name: 'clear_cache', value: '1' }).appendTo('#configForm');
                    $('#configForm').off('submit').submit();
                });
                return false;
            });
            
            $('#btnCreateBackup').on('click', function(e) {
                if (formSubmitted) {
                    e.preventDefault();
                    return false;
                }
                e.preventDefault();
                showConfirm('info', 'Crear Backup', '¿Deseas crear una copia de seguridad de la base de datos?', 'Sí, crear backup', function() {
                    formSubmitted = true;
                    showLoading('Creando backup...');
                    $('<input>').attr({ type: 'hidden', name: 'create_backup', value: '1' }).appendTo('#configForm');
                    $('#configForm').off('submit').submit();
                });
                return false;
            });
            
            $('.btn-restore').on('click', function() {
                var filename = $(this).data('filename');
                var $button = $(this);
                showSwalWithTheme({
                    icon: 'warning',
                    title: 'Restaurar Backup',
                    html: '⚠️ ADVERTENCIA: Restaurar un backup sobrescribirá TODA la base de datos actual.<br><br>Esta acción NO se puede deshacer.<br><br><strong>¿Restaurar backup ' + filename + '?</strong>',
                    showCancelButton: true,
                    confirmButtonColor: '#c8a86b',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, restaurar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $button.html('<i class="fas fa-spinner fa-spin"></i>');
                        $button.prop('disabled', true);
                        Swal.fire({
                            title: 'Restaurando backup...',
                            allowOutsideClick: false,
                            showConfirmButton: false,
                            didOpen: () => {
                                Swal.showLoading();
                                window.location.href = '/api/v1/admin-advanced.php?action=restore_backup&file=' + encodeURIComponent(filename);
                            }
                        });
                    }
                });
            });
            
            $('.btn-delete').on('click', function() {
                var filename = $(this).data('filename');
                var $button = $(this);
                showSwalWithTheme({
                    icon: 'warning',
                    title: 'Eliminar Backup',
                    text: '¿Estás seguro de que deseas eliminar el backup ' + filename + '? Esta acción no se puede deshacer.',
                    showCancelButton: true,
                    confirmButtonColor: '#c8a86b',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $button.html('<i class="fas fa-spinner fa-spin"></i>');
                        $button.prop('disabled', true);
                        $.ajax({
                            url: '/api/v1/admin-advanced.php?action=delete_backup&file=' + encodeURIComponent(filename),
                            method: 'GET',
                            dataType: 'json',
                            success: function(response) {
                                if (response.success) {
                                    showSwalWithTheme({ icon: 'success', title: 'Eliminado', text: response.message, confirmButtonColor: '#c8a86b' }).then(() => { location.reload(); });
                                } else {
                                    showSwalWithTheme({ icon: 'error', title: 'Error', text: response.error, confirmButtonColor: '#c8a86b' });
                                    $button.html('<i class="fas fa-trash-alt"></i> Eliminar');
                                    $button.prop('disabled', false);
                                }
                            },
                            error: function() {
                                showSwalWithTheme({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor', confirmButtonColor: '#c8a86b' });
                                $button.html('<i class="fas fa-trash-alt"></i> Eliminar');
                                $button.prop('disabled', false);
                            }
                        });
                    }
                });
            });
            
            $('.btn-download').on('click', function(e) {
                e.preventDefault();
                var filename = $(this).data('filename');
                var downloadUrl = $(this).attr('href');
                showSwalWithTheme({
                    icon: 'info',
                    title: 'Descargar Backup',
                    text: '¿Deseas descargar el backup ' + filename + '?',
                    showCancelButton: true,
                    confirmButtonColor: '#c8a86b',
                    cancelButtonColor: '#dc3545',
                    confirmButtonText: 'Sí, descargar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = downloadUrl;
                    }
                });
            });
            
            $('.toggle-password').on('click', function() {
                var targetId = $(this).data('target');
                var input = $('#' + targetId);
                var icon = $(this).find('i');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    icon.removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    icon.removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            $('[data-bs-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>