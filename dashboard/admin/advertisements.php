<?php
/**
 * Archivo: /easycarluxury/dashboard/admin/advertisements.php
 * Gestión de publicidad (anuncios) para el panel de administración.
 * Versión responsiva corregida para móvil.
 * MODIFICADO: Tema claro/oscuro añadido
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

// Handle advertisement operations
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $target_dir = "../../uploads/ads/";
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $image_path = '';
                if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $_FILES['image']['name']);
                    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                    if (in_array($extension, $allowed)) {
                        $target_file = $target_dir . $filename;
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                            $image_path = '/uploads/ads/' . $filename;
                        }
                    }
                }

                $query = "INSERT INTO advertisements 
                          (title, description, image_path, link_url, position, start_date, end_date, target_role, status, created_by) 
                          VALUES 
                          (:title, :description, :image_path, :link_url, :position, :start_date, :end_date, :target_role, :status, :created_by)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':title' => $_POST['title'],
                    ':description' => $_POST['description'],
                    ':image_path' => $image_path,
                    ':link_url' => $_POST['link_url'],
                    ':position' => $_POST['position'],
                    ':start_date' => $_POST['start_date'],
                    ':end_date' => $_POST['end_date'],
                    ':target_role' => $_POST['target_role'],
                    ':status' => $_POST['status'],
                    ':created_by' => $admin['id']
                ]);

                $adminAuth->logAction($admin['id'], 'create_advertisement', 'ad', $pdo->lastInsertId(), json_encode(['title' => $_POST['title']]));
                $message = "Publicidad creada exitosamente";
                break;

            case 'toggle_status':
                $query = "UPDATE advertisements SET status = :status WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':status' => $_POST['status'], ':id' => $_POST['id']]);

                $adminAuth->logAction($admin['id'], 'toggle_ad_status', 'ad', $_POST['id'], json_encode(['new_status' => $_POST['status']]));
                $message = "Estado actualizado";
                break;

            case 'delete':
                // Get image path to delete file
                $query = "SELECT image_path FROM advertisements WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $_POST['id']]);
                $ad = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($ad && $ad['image_path']) {
                    $file_path = "../../" . ltrim($ad['image_path'], '/');
                    if (file_exists($file_path)) {
                        unlink($file_path);
                    }
                }

                $query = "DELETE FROM advertisements WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $_POST['id']]);

                $adminAuth->logAction($admin['id'], 'delete_advertisement', 'ad', $_POST['id']);
                $message = "Publicidad eliminada";
                break;
        }
    }
}

// Get all advertisements
$query = "SELECT a.*, u.nombre_completo as creator_name 
          FROM advertisements a
          LEFT JOIN usuarios u ON a.created_by = u.id
          ORDER BY a.created_at DESC";
$ads = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
                SUM(CASE WHEN position = 'home_top' THEN 1 ELSE 0 END) as home_top,
                SUM(CASE WHEN position = 'home_bottom' THEN 1 ELSE 0 END) as home_bottom,
                SUM(CASE WHEN position = 'sidebar' THEN 1 ELSE 0 END) as sidebar
               FROM advertisements";
$stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Publicidad - Easy Car Luxury</title>
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            overflow-x: hidden;
        }

        /* ============================================
           ESTILOS GENERALES - PREVENIR DESBORDES
           ============================================ */
        .admin-container {
            display: flex !important;
            min-height: 100vh;
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* Columna de contenido - ocupa el 100% restante sin espacios */
        .content-column {
            flex: 1;
            background: var(--bg-primary);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Contenido principal - con margenes de 20px a todos los lados */
        .admin-main {
            width: 100%;
            max-width: 100%;
            padding: 20px;
            margin: 0;
            box-sizing: border-box;
        }

        /* Ajustes para los elementos internos */
        .stats-mini,
        .ads-grid {
            width: 100%;
            padding: 0;
            margin: 0;
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

        /* Grid de estadísticas */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-mini {
            background: var(--card-bg);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border-color);
        }

        .stat-mini .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #c8a86b;
        }

        .stat-mini .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Grid de anuncios - responsivo */
        .ads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        /* Tarjeta de anuncio */
        .ad-card {
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--border-color);
        }

        .ad-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }

        .ad-image {
            position: relative;
            height: 180px;
            background: var(--bg-primary);
            overflow: hidden;
        }

        .ad-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .ad-image .no-image {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--text-secondary);
        }

        .ad-image .no-image i {
            font-size: 3rem;
            margin-bottom: 10px;
        }

        .ad-status-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fff;
        }

        .ad-status-badge.status-active {
            background: #27ae60;
        }

        .ad-status-badge.status-inactive {
            background: #e74c3c;
        }

        .ad-info {
            padding: 15px;
        }

        .ad-info h3 {
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .ad-description {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .ad-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 10px;
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .ad-meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .ad-meta a {
            color: #c8a86b;
            text-decoration: none;
        }

        .ad-meta a:hover {
            text-decoration: underline;
        }

        .ad-dates {
            font-size: 0.75rem;
            color: var(--text-secondary);
            margin-bottom: 12px;
        }

        .ad-actions {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon.edit {
            background: #3498db;
            color: #fff;
        }

        .btn-icon.edit:hover {
            background: #2980b9;
        }

        .btn-icon.suspend {
            background: #e67e22;
            color: #fff;
        }

        .btn-icon.suspend:hover {
            background: #d35400;
        }

        .btn-icon.activate {
            background: #27ae60;
            color: #fff;
        }

        .btn-icon.activate:hover {
            background: #229954;
        }

        .btn-icon.delete {
            background: #e74c3c;
            color: #fff;
        }

        .btn-icon.delete:hover {
            background: #c0392b;
        }

        .ad-creator {
            display: flex;
            justify-content: space-between;
            font-size: 0.7rem;
            color: var(--text-secondary);
            padding-top: 8px;
            border-top: 1px solid var(--border-color);
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

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-label {
            color: var(--text-primary);
        }

        [data-theme="dark"] .text-muted {
            color: var(--text-secondary) !important;
        }

        /* ============================================
            RESPONSIVE: Ajustes para móvil
           ============================================ */
        @media (max-width: 992px) {
            .admin-container {
                display: block !important;
            }
            .content-column {
                width: 100% !important;
                flex: none !important;
            }
            .admin-main {
                width: 100% !important;
                max-width: 100% !important;
                padding: 70px 15px 15px 15px !important;
                box-sizing: border-box !important;
            }
        }

        @media (max-width: 768px) {
            body {
                overflow-x: hidden;
            }
            .stats-mini {
                grid-template-columns: 1fr !important;
                gap: 10px;
            }
            .stat-mini {
                padding: 12px 15px;
                justify-content: space-between;
            }
            .stat-mini .stat-number {
                font-size: 1.2rem;
            }
            .stat-mini .stat-label {
                font-size: 0.8rem;
            }
            .ads-grid {
                gap: 15px;
                grid-template-columns: 1fr;
            }
            .ad-image {
                height: 160px;
            }
            .ad-info h3 {
                font-size: 1rem;
            }
            .ad-meta {
                flex-direction: column;
                gap: 6px;
            }
            .ad-actions {
                justify-content: center;
                flex-wrap: wrap;
            }
            .ad-creator {
                flex-direction: column;
                text-align: center;
                gap: 4px;
            }
            .admin-header {
                flex-direction: column;
                text-align: center;
                gap: 12px;
            }
            .admin-header .header-title h1 {
                font-size: 1.3rem;
            }
            .admin-header .header-title p {
                font-size: 0.8rem;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }

        @media (max-width: 480px) {
            .admin-main {
                padding: 70px 10px 10px 10px !important;
            }
            .stats-mini {
                gap: 8px;
            }
            .stat-mini {
                padding: 10px 12px;
            }
            .stat-mini .stat-number {
                font-size: 1rem;
            }
            .ads-grid {
                gap: 12px;
            }
            .ad-image {
                height: 140px;
            }
            .btn-icon {
                width: 28px;
                height: 28px;
            }
        }

        /* SweetAlert2 en modo oscuro */
        [data-theme="dark"] .swal2-popup {
            background: #16213e;
            color: #ffffff;
        }

        .swal2-container {
            z-index: 99999 !important;
        }
    </style>
</head>

<body>
    <div class="admin-container">
        <?php include '../includes/admin-sidebar.php'; ?>
        <div class="content-column">
            <main class="admin-main">
                <div class="admin-header">
                    <div class="header-title">
                        <h1><i class="fas fa-ad"></i> Gestión de Publicidad</h1>
                        <p>Administra banners y anuncios en la plataforma</p>
                    </div>
                    <div class="header-actions">
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdModal">
                            <i class="fas fa-plus"></i> Nueva Publicidad
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-mini">
                    <div class="stat-mini">
                        <span class="stat-label">Total Anuncios</span>
                        <span class="stat-number"><?php echo $stats['total']; ?></span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-label">Activos</span>
                        <span class="stat-number"><?php echo $stats['active']; ?></span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-label">Inactivos</span>
                        <span class="stat-number"><?php echo $stats['inactive']; ?></span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-label">Home Top</span>
                        <span class="stat-number"><?php echo $stats['home_top']; ?></span>
                    </div>
                    <div class="stat-mini">
                        <span class="stat-label">Sidebar</span>
                        <span class="stat-number"><?php echo $stats['sidebar']; ?></span>
                    </div>
                </div>

                <!-- Ads Grid -->
                <div class="ads-grid">
                    <?php foreach ($ads as $ad): ?>
                        <div class="ad-card">
                            <div class="ad-image">
                                <?php if ($ad['image_path']): ?>
                                    <img src="<?php echo $ad['image_path']; ?>"
                                        alt="<?php echo htmlspecialchars($ad['title']); ?>">
                                <?php else: ?>
                                    <div class="no-image">
                                        <i class="fas fa-image"></i>
                                        <span>Sin imagen</span>
                                    </div>
                                <?php endif; ?>
                                <div class="ad-status-badge status-<?php echo $ad['status']; ?>">
                                    <?php echo $ad['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                </div>
                            </div>
                            <div class="ad-info">
                                <h3><?php echo htmlspecialchars($ad['title']); ?></h3>
                                <p class="ad-description">
                                    <?php echo htmlspecialchars(substr($ad['description'], 0, 100)); ?>
                                </p>
                                <div class="ad-meta">
                                    <span><i class="fas fa-link"></i> <a
                                            href="<?php echo htmlspecialchars($ad['link_url']); ?>"
                                            target="_blank">Enlace</a></span>
                                    <span><i class="fas fa-map-marker-alt"></i>
                                        <?php echo str_replace('_', ' ', ucfirst($ad['position'])); ?></span>
                                    <span><i class="fas fa-users"></i> <?php echo ucfirst($ad['target_role']); ?></span>
                                </div>
                                <div class="ad-dates">
                                    <i class="far fa-calendar"></i>
                                    <?php echo date('d/m/Y', strtotime($ad['start_date'])); ?> -
                                    <?php echo date('d/m/Y', strtotime($ad['end_date'])); ?>
                                </div>
                                <div class="ad-actions">
                                    <button class="btn-icon edit" onclick="editAd(<?php echo $ad['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button
                                        class="btn-icon <?php echo $ad['status'] === 'active' ? 'suspend' : 'activate'; ?>"
                                        onclick="toggleAdStatus(<?php echo $ad['id']; ?>, '<?php echo $ad['status']; ?>')">
                                        <i
                                            class="fas fa-<?php echo $ad['status'] === 'active' ? 'ban' : 'check-circle'; ?>"></i>
                                    </button>
                                    <button class="btn-icon delete" onclick="deleteAd(<?php echo $ad['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                                <div class="ad-creator">
                                    <small>Creado por:
                                        <?php echo htmlspecialchars($ad['creator_name'] ?? 'Sistema'); ?></small>
                                    <small><?php echo date('d/m/Y', strtotime($ad['created_at'])); ?></small>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (empty($ads)): ?>
                        <div class="text-center p-5" style="color: var(--text-secondary);">
                            <i class="fas fa-ad fa-3x mb-3"></i>
                            <p>No hay publicidades creadas</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdModal">Crear
                                primera
                                publicidad</button>
                        </div>
                    <?php endif; ?>
                </div>

            </main>
        </div>
    </div>

    <!-- Botón para cambiar tema claro/oscuro -->
    <button class="btn-theme" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>

    <!-- Create Advertisement Modal -->
    <div class="modal fade" id="createAdModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus"></i> Nueva Publicidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Título *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">URL de destino *</label>
                                <input type="url" class="form-control" name="link_url" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Imagen *</label>
                                <input type="file" class="form-control" name="image" accept="image/*" required>
                                <small class="text-muted">Formatos: JPG, PNG, GIF, WEBP. Tamaño recomendado:
                                    1200x400px</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Posición *</label>
                                <select class="form-select" name="position" required>
                                    <option value="home_top">Home - Top (banner principal)</option>
                                    <option value="home_bottom">Home - Bottom (parte inferior)</option>
                                    <option value="sidebar">Sidebar (barra lateral)</option>
                                    <option value="catalog_top">Catálogo - Top</option>
                                    <option value="catalog_bottom">Catálogo - Bottom</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha inicio *</label>
                                <input type="date" class="form-control" name="start_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha fin *</label>
                                <input type="date" class="form-control" name="end_date" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Audiencia objetivo</label>
                                <select class="form-select" name="target_role">
                                    <option value="all">Todos los usuarios</option>
                                    <option value="user">Solo usuarios normales</option>
                                    <option value="admin">Solo administradores</option>
                                    <option value="premium">Solo miembros Premium/Elite</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" name="status">
                                    <option value="inactive">Inactivo</option>
                                    <option value="active">Activo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Crear Publicidad</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Advertisement Modal -->
    <div class="modal fade" id="editAdModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Editar Publicidad</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="advertisements-edit.php">
                    <div class="modal-body" id="editAdContent">
                        <!-- Loaded via AJAX -->
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        const token = localStorage.getItem('auth_token');
        
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

        function editAd(adId) {
            $.ajax({
                url: '/easycarluxury/api/v1/admin-advanced.php?action=get_ad&id=' + adId,
                method: 'GET',
                headers: {
                    'Authorization': 'Bearer ' + token
                },
                success: function (response) {
                    if (response.success) {
                        const ad = response.data;
                        let html = `
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="id" value="${ad.id}">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Título *</label>
                                    <input type="text" class="form-control" name="title" value="${escapeHtml(ad.title)}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">URL de destino *</label>
                                    <input type="url" class="form-control" name="link_url" value="${escapeHtml(ad.link_url)}" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control" name="description" rows="2">${escapeHtml(ad.description || '')}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Imagen actual</label><br>
                                    ${ad.image_path ? `<img src="${ad.image_path}" style="max-width: 100%; max-height: 150px;" class="mb-2"><br>` : ''}
                                    <input type="file" class="form-control" name="image" accept="image/*">
                                    <small class="text-muted">Dejar vacío para mantener la imagen actual</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Posición *</label>
                                    <select class="form-select" name="position" required>
                                        <option value="home_top" ${ad.position === 'home_top' ? 'selected' : ''}>Home - Top</option>
                                        <option value="home_bottom" ${ad.position === 'home_bottom' ? 'selected' : ''}>Home - Bottom</option>
                                        <option value="sidebar" ${ad.position === 'sidebar' ? 'selected' : ''}>Sidebar</option>
                                        <option value="catalog_top" ${ad.position === 'catalog_top' ? 'selected' : ''}>Catálogo - Top</option>
                                        <option value="catalog_bottom" ${ad.position === 'catalog_bottom' ? 'selected' : ''}>Catálogo - Bottom</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fecha inicio *</label>
                                    <input type="date" class="form-control" name="start_date" value="${ad.start_date}" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Fecha fin *</label>
                                    <input type="date" class="form-control" name="end_date" value="${ad.end_date}" required>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Audiencia objetivo</label>
                                    <select class="form-select" name="target_role">
                                        <option value="all" ${ad.target_role === 'all' ? 'selected' : ''}>Todos los usuarios</option>
                                        <option value="user" ${ad.target_role === 'user' ? 'selected' : ''}>Solo usuarios normales</option>
                                        <option value="admin" ${ad.target_role === 'admin' ? 'selected' : ''}>Solo administradores</option>
                                        <option value="premium" ${ad.target_role === 'premium' ? 'selected' : ''}>Solo miembros Premium/Elite</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Estado</label>
                                    <select class="form-select" name="status">
                                        <option value="active" ${ad.status === 'active' ? 'selected' : ''}>Activo</option>
                                        <option value="inactive" ${ad.status === 'inactive' ? 'selected' : ''}>Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        `;
                        $('#editAdContent').html(html);
                        $('#editAdModal').modal('show');
                    }
                }
            });
        }

        function toggleAdStatus(adId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            showSwalWithTheme({
                title: `${newStatus === 'active' ? 'Activar' : 'Desactivar'} publicidad`,
                text: `¿Estás seguro de ${newStatus === 'active' ? 'activar' : 'desactivar'} esta publicidad?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#c8a86b',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('<form>', {
                        method: 'POST',
                        action: ''
                    }).append($('<input>', { name: 'action', value: 'toggle_status' }))
                        .append($('<input>', { name: 'id', value: adId }))
                        .append($('<input>', { name: 'status', value: newStatus }))
                        .appendTo('body')
                        .submit();
                }
            });
        }

        function deleteAd(adId) {
            showSwalWithTheme({
                title: 'Eliminar publicidad',
                text: '¿Estás seguro de eliminar esta publicidad permanentemente? Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('<form>', {
                        method: 'POST',
                        action: ''
                    }).append($('<input>', { name: 'action', value: 'delete' }))
                        .append($('<input>', { name: 'id', value: adId }))
                        .appendTo('body')
                        .submit();
                }
            });
        }

        function escapeHtml(str) {
            if (!str) return '';
            return str.replace(/[&<>]/g, function (m) {
                if (m === '&') return '&amp;';
                if (m === '<') return '&lt;';
                if (m === '>') return '&gt;';
                return m;
            });
        }
    </script>

    <?php include __DIR__ . '/../includes/admin-footer.php'; ?>
</body>
</html>