<?php
/**
 * Colcars - Gestión de Publicidad (Banners y Videos)
 * Administrador puede crear/editar publicidad para mostrar en detail.php
 * MODIFICADO: Tema claro/oscuro completo, sidebar responsivo
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación
$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /login');
    exit;
}

$db = Database::getInstance();
$admin = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

if (!$admin || !is_array($admin)) {
    session_destroy();
    header('Location: /login');
    exit;
}

// Verificar que sea administrador (rol_id = 1 o tipo_cuenta = 'admin')
if ($admin['rol_id'] != 1 && $admin['tipo_cuenta'] !== 'admin' && $admin['rol'] !== 'admin') {
    header('Location: /dashboard/user/index.php');
    exit;
}

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

// Crear carpeta de uploads si no existe
$upload_banner_dir = __DIR__ . '/uploads/publicidad/banners/';
$upload_video_dir = __DIR__ . '/uploads/publicidad/videos/';

if (!is_dir($upload_banner_dir)) {
    mkdir($upload_banner_dir, 0777, true);
}
if (!is_dir($upload_video_dir)) {
    mkdir($upload_video_dir, 0777, true);
}

// Obtener datos para edición
$edit_mode = false;
$edit_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$edit_data = null;

if ($edit_id > 0) {
    $edit_data = $db->getOne("SELECT * FROM publicidad WHERE id = ?", [$edit_id]);
    if ($edit_data) {
        $edit_mode = true;
    }
}

// Procesar formulario
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $tipo = $_POST['tipo'] ?? 'banner';
        $link_url = trim($_POST['link_url'] ?? '');
        $posicion = $_POST['posicion'] ?? 'banner_principal';
        $activo = isset($_POST['activo']) ? 1 : 0;
        $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
        $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
        $orden = intval($_POST['orden'] ?? 0);
        $video_embed = trim($_POST['video_embed'] ?? '');
        
        if (empty($titulo)) {
            throw new Exception('El título es requerido');
        }
        
        $archivo_url = null;
        
        // Procesar subida de archivo
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['archivo'];
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if ($tipo === 'banner') {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_ext, $allowed)) {
                    throw new Exception('Formato no permitido para banner. Use: JPG, PNG, GIF, WEBP');
                }
                $file_name = 'banner_' . time() . '_' . uniqid() . '.' . $file_ext;
                $target_dir = $upload_banner_dir;
                $url_path = '/easycarluxury/dashboard/admin/uploads/publicidad/banners/' . $file_name;
            } else {
                $allowed = ['mp4', 'webm', 'ogg'];
                if (!in_array($file_ext, $allowed)) {
                    throw new Exception('Formato no permitido para video. Use: MP4, WEBM, OGG');
                }
                $file_name = 'video_' . time() . '_' . uniqid() . '.' . $file_ext;
                $target_dir = $upload_video_dir;
                $url_path = '/easycarluxury/dashboard/admin/uploads/publicidad/videos/' . $file_name;
            }
            
            $target_file = $target_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $archivo_url = $url_path;
            } else {
                throw new Exception('Error al subir el archivo');
            }
        } elseif ($edit_mode && !empty($_POST['archivo_url_existente'])) {
            $archivo_url = $_POST['archivo_url_existente'];
        } elseif ($tipo === 'video' && !empty($video_embed)) {
            $archivo_url = null;
        } elseif (!$edit_mode) {
            throw new Exception('Debe seleccionar un archivo');
        }
        
        if ($edit_mode) {
            $sql = "UPDATE publicidad SET 
                        titulo = :titulo,
                        descripcion = :descripcion,
                        tipo = :tipo,
                        archivo_url = :archivo_url,
                        video_embed = :video_embed,
                        link_url = :link_url,
                        posicion = :posicion,
                        activo = :activo,
                        fecha_inicio = :fecha_inicio,
                        fecha_fin = :fecha_fin,
                        orden = :orden,
                        updated_at = NOW()
                    WHERE id = :id";
            
            $params = [
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':tipo' => $tipo,
                ':archivo_url' => $archivo_url,
                ':video_embed' => $video_embed,
                ':link_url' => $link_url,
                ':posicion' => $posicion,
                ':activo' => $activo,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin,
                ':orden' => $orden,
                ':id' => $edit_id
            ];
            
            $db->query($sql, $params);
            $success = 'Publicidad actualizada exitosamente';
        } else {
            $sql = "INSERT INTO publicidad (
                        titulo, descripcion, tipo, archivo_url, video_embed, link_url,
                        posicion, activo, fecha_inicio, fecha_fin, orden, created_by, created_at
                    ) VALUES (
                        :titulo, :descripcion, :tipo, :archivo_url, :video_embed, :link_url,
                        :posicion, :activo, :fecha_inicio, :fecha_fin, :orden, :created_by, NOW()
                    )";
            
            $params = [
                ':titulo' => $titulo,
                ':descripcion' => $descripcion,
                ':tipo' => $tipo,
                ':archivo_url' => $archivo_url,
                ':video_embed' => $video_embed,
                ':link_url' => $link_url,
                ':posicion' => $posicion,
                ':activo' => $activo,
                ':fecha_inicio' => $fecha_inicio,
                ':fecha_fin' => $fecha_fin,
                ':orden' => $orden,
                ':created_by' => $user_id
            ];
            
            $db->query($sql, $params);
            $success = 'Publicidad creada exitosamente';
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$publicidades = $db->getAll("SELECT * FROM publicidad ORDER BY posicion, orden, created_at DESC");
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo $edit_mode ? 'Editar Publicidad' : 'Nueva Publicidad'; ?> - Colcars Admin</title>
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

        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .sidebar-column {
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .admin-main {
            flex: 1;
            width: auto;
            padding: 15px 15px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow-x: hidden;
        }

        .admin-main > * {
            max-width: 100%;
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

        /* Form Container */
        .form-container {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .form-section {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .section-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c8a86b;
            display: inline-block;
            color: var(--text-primary);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .form-control, .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            color: var(--text-primary);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
        }

        .form-control:focus, .form-select:focus {
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
            background: var(--input-bg);
            color: var(--text-primary);
            outline: none;
        }

        textarea.form-control {
            resize: vertical;
        }

        .required-field::after {
            content: '*';
            color: #dc3545;
            margin-left: 4px;
        }

        .form-check-input {
            background-color: var(--input-bg);
            border-color: var(--input-border);
        }

        .form-check-input:checked {
            background-color: #c8a86b;
            border-color: #c8a86b;
        }

        .form-check-label {
            color: var(--text-primary);
        }

        /* Buttons */
        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.85rem;
        }

        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
        }

        .btn-outline-primary {
            border: 1px solid #c8a86b;
            background: transparent;
            color: #c8a86b;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-primary:hover {
            background: #c8a86b;
            color: white;
        }

        .btn-outline-danger {
            border: 1px solid #dc3545;
            background: transparent;
            color: #dc3545;
            padding: 4px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.75rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-outline-danger:hover {
            background: #dc3545;
            color: white;
        }

        /* Table styles */
        .table-responsive {
            overflow-x: auto;
            border-radius: 12px;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
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

        /* Badges */
        .badge-activo {
            background: #28a745;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-inactivo {
            background: #dc3545;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-banner {
            background: #17a2b8;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        .badge-video {
            background: #e74c3c;
            color: white;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            display: inline-block;
        }

        /* Preview images */
        .preview-img {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
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

        /* Alertas */
        .alert {
            border-radius: 12px;
            margin-bottom: 20px;
        }

        [data-theme="dark"] .alert-success {
            background-color: #1a4a2a;
            color: #ccffcc;
            border-color: #2a6a3a;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #5a1a1a;
            color: #ffcccc;
            border-color: #8b3a3a;
        }

        /* SweetAlert2 en modo oscuro */
        [data-theme="dark"] .swal2-popup {
            background: #16213e;
            color: #ffffff;
        }

        .swal2-container {
            z-index: 99999 !important;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px !important;
            }
        }

        @media (max-width: 768px) {
            .admin-table {
                min-width: 600px;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            .form-container {
                padding: 15px;
            }
            .form-section {
                padding: 15px;
            }
            .preview-img {
                width: 50px;
                height: 50px;
            }
        }

        @media (max-width: 480px) {
            .admin-main {
                padding: 60px 8px 8px 8px !important;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <div class="sidebar-column">
        <?php include __DIR__ . '/../../dashboard/includes/admin-sidebar.php'; ?>
    </div>
    <main class="admin-main">
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-ad"></i> <?php echo $edit_mode ? 'Editar Publicidad' : 'Nueva Publicidad'; ?></h1>
                <p>Administra banners y anuncios en la plataforma</p>
            </div>
            <div class="header-actions">
                <a href="/dashboard/admin/publications.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" enctype="multipart/form-data" id="publicidadForm">
                <div class="form-section">
                    <h5 class="section-title"><i class="fas fa-info-circle"></i> Datos de la Publicidad</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-field">Título</label>
                            <input type="text" name="titulo" class="form-control" required 
                                   value="<?php echo $edit_data ? htmlspecialchars($edit_data['titulo']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="2"><?php echo $edit_data ? htmlspecialchars($edit_data['descripcion']) : ''; ?></textarea>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-field">Tipo</label>
                            <select name="tipo" class="form-select" id="tipoSelect" required>
                                <option value="banner" <?php echo ($edit_data && $edit_data['tipo'] == 'banner') ? 'selected' : ''; ?>>Banner (Imagen JPG/PNG/GIF)</option>
                                <option value="video" <?php echo ($edit_data && $edit_data['tipo'] == 'video') ? 'selected' : ''; ?>>Video (MP4 o YouTube/Vimeo)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required-field">Posición</label>
                            <select name="posicion" class="form-select" required>
                                <option value="banner_principal" <?php echo ($edit_data && $edit_data['posicion'] == 'banner_principal') ? 'selected' : ''; ?>>Banner Principal (centro de detail.php)</option>
                                <option value="video_espacio" <?php echo ($edit_data && $edit_data['posicion'] == 'video_espacio') ? 'selected' : ''; ?>>Espacio de Video (dentro de tabla)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">URL de destino (al hacer clic)</label>
                            <input type="url" name="link_url" class="form-control" 
                                   placeholder="https://ejemplo.com"
                                   value="<?php echo $edit_data ? htmlspecialchars($edit_data['link_url']) : ''; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Orden</label>
                            <input type="number" name="orden" class="form-control" value="<?php echo $edit_data ? $edit_data['orden'] : '0'; ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Activo</label>
                            <div class="form-check mt-2">
                                <input type="checkbox" name="activo" class="form-check-input" value="1" <?php echo (!$edit_data || $edit_data['activo'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label">Publicación activa</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-section" id="bannerSection">
                    <h5 class="section-title"><i class="fas fa-image"></i> Banner (Imagen)</h5>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Imagen del Banner (JPG, PNG, GIF animado, WEBP)</label>
                            <input type="file" name="archivo" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp" id="bannerFile">
                            <?php if ($edit_data && $edit_data['tipo'] == 'banner' && $edit_data['archivo_url']): ?>
                                <div class="mt-2">
                                    <img src="<?php echo $edit_data['archivo_url']; ?>" class="preview-img">
                                    <input type="hidden" name="archivo_url_existente" value="<?php echo htmlspecialchars($edit_data['archivo_url']); ?>">
                                    <small class="text-muted d-block">Imagen actual. Sube una nueva para reemplazar.</small>
                                </div>
                            <?php endif; ?>
                            <small class="text-muted">Tamaño recomendado: 1200x400px</small>
                        </div>
                    </div>
                </div>

                <div class="form-section" id="videoSection" style="display: none;">
                    <h5 class="section-title"><i class="fas fa-video"></i> Video</h5>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Subir archivo de video (MP4, WEBM, OGG)</label>
                            <input type="file" name="archivo" class="form-control" accept="video/mp4,video/webm, video/ogg" id="videoFile">
                            <?php if ($edit_data && $edit_data['tipo'] == 'video' && $edit_data['archivo_url'] && !$edit_data['video_embed']): ?>
                                <div class="mt-2">
                                    <video src="<?php echo $edit_data['archivo_url']; ?>" style="max-height: 100px;" controls></video>
                                    <input type="hidden" name="archivo_url_existente" value="<?php echo htmlspecialchars($edit_data['archivo_url']); ?>">
                                    <small class="text-muted d-block">Video actual. Sube uno nuevo para reemplazar.</small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-12 mb-3">
                            <label class="form-label">O URL de YouTube/Vimeo (embed)</label>
                            <input type="text" name="video_embed" class="form-control" 
                                   placeholder="https://www.youtube.com/embed/VIDEO_ID"
                                   value="<?php echo $edit_data ? htmlspecialchars($edit_data['video_embed']) : ''; ?>">
                            <small class="text-muted">Ejemplo: https://www.youtube.com/embed/dQw4w9WgXcQ</small>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5 class="section-title"><i class="fas fa-calendar"></i> Fechas de Publicación</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de inicio</label>
                            <input type="date" name="fecha_inicio" class="form-control" 
                                   value="<?php echo $edit_data ? $edit_data['fecha_inicio'] : ''; ?>">
                            <small class="text-muted">Dejar vacío para inicio inmediato</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Fecha de fin</label>
                            <input type="date" name="fecha_fin" class="form-control" 
                                   value="<?php echo $edit_data ? $edit_data['fecha_fin'] : ''; ?>">
                            <small class="text-muted">Dejar vacío para sin fecha de expiración</small>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="/dashboard/admin/publications.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <?php echo $edit_mode ? 'Actualizar' : 'Crear Publicidad'; ?>
                    </button>
                </div>
            </form>
        </div>

        <?php if (!empty($publicidades)): ?>
        <div class="form-container mt-4">
            <h5 class="section-title"><i class="fas fa-list"></i> Publicidades Existentes</h5>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Tipo</th>
                            <th>Posición</th>
                            <th>Vista previa</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($publicidades as $pub): ?>
                        <tr>
                            <td><?php echo $pub['id']; ?></td>
                            <td><?php echo htmlspecialchars($pub['titulo']); ?></div>
                            <td>
                                <?php if ($pub['tipo'] == 'banner'): ?>
                                    <span class="badge-banner">Banner</span>
                                <?php else: ?>
                                    <span class="badge-video">Video</span>
                                <?php endif; ?>
                            </div>
                            <td>
                                <?php if ($pub['posicion'] == 'banner_principal'): ?>
                                    Banner Principal
                                <?php else: ?>
                                    Espacio Video
                                <?php endif; ?>
                            </div>
                            <td>
                                <?php if ($pub['tipo'] == 'banner' && $pub['archivo_url']): ?>
                                    <img src="<?php echo htmlspecialchars($pub['archivo_url']); ?>" class="preview-img">
                                <?php elseif ($pub['tipo'] == 'video' && $pub['video_embed']): ?>
                                    <i class="fab fa-youtube fa-2x" style="color: #ff0000;"></i>
                                <?php elseif ($pub['tipo'] == 'video' && $pub['archivo_url']): ?>
                                    <i class="fas fa-file-video fa-2x"></i>
                                <?php else: ?>
                                    <i class="fas fa-image fa-2x text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <td>
                                <?php if ($pub['activo'] == 1): ?>
                                    <span class="badge-activo">Activo</span>
                                <?php else: ?>
                                    <span class="badge-inactivo">Inactivo</span>
                                <?php endif; ?>
                            </div>
                            <td>
                                <a href="/dashboard/admin/new-publication.php?id=<?php echo $pub['id']; ?>" class="btn-outline-primary">
                                    <i class="fas fa-edit"></i> Editar
                                </a>
                                <button onclick="deletePublicidad(<?php echo $pub['id']; ?>)" class="btn-outline-danger">
                                    <i class="fas fa-trash"></i> Eliminar
                                </button>
                            </div>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<!-- Botón tema claro/oscuro -->
<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<?php include_once __DIR__ . '/../../dashboard/includes/admin-footer.php'; ?>

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

    function deletePublicidad(id) {
        Swal.fire({
            title: '¿Eliminar publicidad?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/dashboard/admin/delete-publicidad.php',
                    method: 'POST',
                    data: { id: id },
                    dataType: 'json',
                    success: function(r) {
                        if (r.success) {
                            Swal.fire('Eliminado', 'Publicidad eliminada correctamente', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', r.message || 'Error al eliminar', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'No se pudo eliminar la publicidad', 'error');
                    }
                });
            }
        });
    }

    document.getElementById('tipoSelect').addEventListener('change', function() {
        const tipo = this.value;
        if (tipo === 'banner') {
            document.getElementById('bannerSection').style.display = 'block';
            document.getElementById('videoSection').style.display = 'none';
        } else {
            document.getElementById('bannerSection').style.display = 'none';
            document.getElementById('videoSection').style.display = 'block';
        }
    });

    const tipoActual = document.getElementById('tipoSelect').value;
    if (tipoActual === 'banner') {
        document.getElementById('bannerSection').style.display = 'block';
        document.getElementById('videoSection').style.display = 'none';
    } else {
        document.getElementById('bannerSection').style.display = 'none';
        document.getElementById('videoSection').style.display = 'block';
    }
</script>

</body>
</html>