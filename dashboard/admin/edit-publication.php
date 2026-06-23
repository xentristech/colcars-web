<?php
/**
 * EASY CAR LUXURY - EDITAR PUBLICACIÓN (ADMIN)
 * Ruta: C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\edit-publication.php
 * 
 * FUNCIONALIDAD:
 * - Permite al administrador editar CUALQUIER publicación (sin restricción de usuario_id)
 * - Mantiene todos los campos nuevos (identificación, números de serie, datos legales)
 * - Gestión completa de imágenes y documentos
 * - TEMA CLARO/OSCURO: Implementado como en audit.php
 * - ESTRUCTURA: Basada en users.php para responsive móvil
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar que el usuario es administrador
requireAuth();

// INICIALIZAR LA BASE DE DATOS PRIMERO
$db = Database::getInstance();

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
$current_user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

// Verificar rol de administrador
if (!$current_user || !in_array($current_user['rol_id'], [1, 2, 3, 4, 5, 7])) {
    header('Location: /easycarluxury/dashboard/user/index.php');
    exit;
}

// Definir constantes de ruta
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}
if (!defined('UPLOAD_ABSOLUTE_PATH')) {
    define('UPLOAD_ABSOLUTE_PATH', BASE_PATH . '/uploads/vehicles/');
}
if (!is_dir(UPLOAD_ABSOLUTE_PATH)) {
    mkdir(UPLOAD_ABSOLUTE_PATH, 0777, true);
}

// Obtener publicación por ID (SIN RESTRICCIÓN de usuario_id)
$publicacion_id = intval($_GET['id'] ?? 0);
$publicacion = $db->getOne("SELECT * FROM publicaciones WHERE id = ?", [$publicacion_id]);

if (!$publicacion) {
    header('Location: publications.php?error=not_found');
    exit;
}

// Obtener el usuario dueño de la publicación
$propietario = $db->getOne("SELECT id, nombre_completo, email, tipo_cuenta FROM usuarios WHERE id = ?", [$publicacion['usuario_id']]);

$error = '';
$success = '';

// Obtener imágenes
$imagenes = $db->getAll("SELECT * FROM imagenes_publicaciones WHERE publicacion_id = ? ORDER BY sort_order", [$publicacion_id]);

// Obtener documentos
$documentos = $db->getAll("SELECT * FROM documentacion_articulos WHERE publicacion_id = ?", [$publicacion_id]);

// Obtener categorías
$categorias = $db->getAll("SELECT * FROM categorias WHERE padre_id IS NULL OR padre_id = 0 ORDER BY nombre");
$subcategorias = $db->getAll("SELECT * FROM categorias WHERE padre_id IS NOT NULL AND padre_id != 0 ORDER BY nombre");

// Procesar actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        $update_data = [
            'titulo' => sanitize($_POST['titulo']),
            'categoria_id' => intval($_POST['categoria_id']),
            'descripcion' => sanitize($_POST['descripcion']),
            'precio' => floatval($_POST['precio']),
            'negociable' => isset($_POST['negociable']) ? 1 : 0,
            'estado_articulo' => $_POST['estado_articulo'],
            'year_fabricacion' => intval($_POST['year_fabricacion']),
            'kilometraje' => intval($_POST['kilometraje']),
            'color' => sanitize($_POST['color']),
            'ubicacion' => sanitize($_POST['ubicacion']),
            'solo_premium_elite' => isset($_POST['solo_premium_elite']) ? 1 : 0,
            'brand' => sanitize($_POST['brand'] ?? ''),
            'linea_modelo_comercial' => sanitize($_POST['linea_modelo_comercial'] ?? ''),
            'clase_vehiculo' => sanitize($_POST['clase_vehiculo'] ?? ''),
            'tipo_carroceria' => sanitize($_POST['tipo_carroceria'] ?? ''),
            'cilindrada' => intval($_POST['cilindrada'] ?? 0),
            'potencia_hp' => intval($_POST['potencia_hp'] ?? 0),
            'fuel_type' => sanitize($_POST['fuel_type'] ?? ''),
            'capacidad' => sanitize($_POST['capacidad'] ?? ''),
            'blindaje' => sanitize($_POST['blindaje'] ?? 'No'),
            'numero_motor' => sanitize($_POST['numero_motor'] ?? ''),
            'numero_chasis' => sanitize($_POST['numero_chasis'] ?? ''),
            'numero_vin' => sanitize($_POST['numero_vin'] ?? ''),
            'servicio' => sanitize($_POST['servicio'] ?? ''),
            'origen' => sanitize($_POST['origen'] ?? ''),
            'propietario_nombre' => sanitize($_POST['propietario_nombre'] ?? ''),
            'propietario_tipo_documento' => sanitize($_POST['propietario_tipo_documento'] ?? ''),
            'propietario_numero_documento' => sanitize($_POST['propietario_numero_documento'] ?? ''),
            'empresa_vinculadora_nombre' => sanitize($_POST['empresa_vinculadora_nombre'] ?? ''),
            'empresa_vinculadora_nit' => sanitize($_POST['empresa_vinculadora_nit'] ?? '')
        ];
        
        try {
            $sql = "UPDATE publicaciones SET 
                        titulo = :titulo,
                        categoria_id = :categoria_id,
                        descripcion = :descripcion,
                        precio = :precio,
                        negociable = :negociable,
                        estado_articulo = :estado_articulo,
                        year_fabricacion = :year_fabricacion,
                        kilometraje = :kilometraje,
                        color = :color,
                        ubicacion = :ubicacion,
                        solo_premium_elite = :solo_premium_elite,
                        brand = :brand,
                        linea_modelo_comercial = :linea_modelo_comercial,
                        clase_vehiculo = :clase_vehiculo,
                        tipo_carroceria = :tipo_carroceria,
                        cilindrada = :cilindrada,
                        potencia_hp = :potencia_hp,
                        fuel_type = :fuel_type,
                        capacidad = :capacidad,
                        blindaje = :blindaje,
                        numero_motor = :numero_motor,
                        numero_chasis = :numero_chasis,
                        numero_vin = :numero_vin,
                        servicio = :servicio,
                        origen = :origen,
                        propietario_nombre = :propietario_nombre,
                        propietario_tipo_documento = :propietario_tipo_documento,
                        propietario_numero_documento = :propietario_numero_documento,
                        empresa_vinculadora_nombre = :empresa_vinculadora_nombre,
                        empresa_vinculadora_nit = :empresa_vinculadora_nit
                    WHERE id = :id";
            
            $params = $update_data;
            $params['id'] = $publicacion_id;
            $db->query($sql, $params);
            
            // Agregar nuevas imágenes
            if (!empty($_FILES['nuevas_imagenes']['name'][0])) {
                $user_folder_name = 'user_' . $publicacion['usuario_id'];
                $sub_folder_name = 'publicaciones';
                $absolute_user_folder = BASE_PATH . '/uploads/vehicles/' . $user_folder_name . '/' . $sub_folder_name . '/';
                
                if (!is_dir($absolute_user_folder)) {
                    mkdir($absolute_user_folder, 0777, true);
                }
                
                $orden_actual = count($imagenes);
                
                foreach ($_FILES['nuevas_imagenes']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['nuevas_imagenes']['error'][$key] === UPLOAD_ERR_OK) {
                        $extension = strtolower(pathinfo($_FILES['nuevas_imagenes']['name'][$key], PATHINFO_EXTENSION));
                        $filename = 'pub_' . $publicacion_id . '_' . uniqid() . '.' . $extension;
                        $destination = $absolute_user_folder . $filename;
                        
                        if (move_uploaded_file($tmp_name, $destination)) {
                            $url = '/easycarluxury/uploads/vehicles/' . $user_folder_name . '/' . $sub_folder_name . '/' . $filename;
                            $db->insert('imagenes_publicaciones', [
                                'publicacion_id' => $publicacion_id,
                                'image_path' => $url,
                                'is_primary' => (empty($imagenes) && $key == 0) ? 1 : 0,
                                'sort_order' => $orden_actual + $key
                            ]);
                        }
                    }
                }
            }
            
            // Agregar nuevos documentos
            if (!empty($_FILES['nuevos_documentos']['name'][0])) {
                $user_folder_name = 'user_' . $publicacion['usuario_id'];
                $sub_folder_name = 'documentos';
                $absolute_docs_folder = BASE_PATH . '/uploads/vehicles/' . $user_folder_name . '/' . $sub_folder_name . '/';
                
                if (!is_dir($absolute_docs_folder)) {
                    mkdir($absolute_docs_folder, 0777, true);
                }
                
                foreach ($_FILES['nuevos_documentos']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['nuevos_documentos']['error'][$key] === UPLOAD_ERR_OK) {
                        $tipo = $_POST['nuevo_documento_tipo'][$key] ?? 'otros';
                        $extension = strtolower(pathinfo($_FILES['nuevos_documentos']['name'][$key], PATHINFO_EXTENSION));
                        $filename = 'doc_' . $publicacion_id . '_' . $tipo . '_' . uniqid() . '.' . $extension;
                        $destination = $absolute_docs_folder . $filename;
                        
                        if (move_uploaded_file($tmp_name, $destination)) {
                            $url = '/easycarluxury/uploads/vehicles/' . $user_folder_name . '/' . $sub_folder_name . '/' . $filename;
                            $db->insert('documentacion_articulos', [
                                'publicacion_id' => $publicacion_id,
                                'tipo_documento' => $tipo,
                                'url_documento' => $url,
                                'verificado' => 0
                            ]);
                        }
                    }
                }
            }
            
            logAudit($user_id, 'UPDATE', 'publicaciones', $publicacion_id, $publicacion, $update_data);
            $success = 'Publicación actualizada exitosamente';
            
            // Recargar datos
            $publicacion = $db->getOne("SELECT * FROM publicaciones WHERE id = ?", [$publicacion_id]);
            $imagenes = $db->getAll("SELECT * FROM imagenes_publicaciones WHERE publicacion_id = ? ORDER BY sort_order", [$publicacion_id]);
            $documentos = $db->getAll("SELECT * FROM documentacion_articulos WHERE publicacion_id = ?", [$publicacion_id]);
            
        } catch (Exception $e) {
            $error = 'Error al actualizar: ' . $e->getMessage();
            error_log("Publication update error: " . $e->getMessage());
        }
    }
}

// Eliminar imagen
if (isset($_GET['delete_image'])) {
    $img_id = intval($_GET['delete_image']);
    $img = $db->getOne("SELECT * FROM imagenes_publicaciones WHERE id = ? AND publicacion_id = ?", [$img_id, $publicacion_id]);
    if ($img) {
        $file_path = BASE_PATH . $img['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $db->delete('imagenes_publicaciones', 'id = ?', [$img_id]);
        header("Location: edit-publication.php?id=$publicacion_id");
        exit;
    }
}

// Eliminar documento
if (isset($_GET['delete_doc'])) {
    $doc_id = intval($_GET['delete_doc']);
    $doc = $db->getOne("SELECT * FROM documentacion_articulos WHERE id = ? AND publicacion_id = ?", [$doc_id, $publicacion_id]);
    if ($doc) {
        $file_path = BASE_PATH . $doc['url_documento'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $db->delete('documentacion_articulos', 'id = ?', [$doc_id]);
        header("Location: edit-publication.php?id=$publicacion_id");
        exit;
    }
}

$csrf_token = generateCSRFToken();

// Obtener el tema del administrador (usando admin_theme como en audit.php)
$theme = $_COOKIE['admin_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Publicación (Admin) - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        /* ============================================
           ESTILOS EXACTOS COPIADOS DE users.php
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

        /* .sidebar-column { flex-shrink: 0; } */

        /* ============================================
           MODIFICACIÓN: CONTENIDO MÁS ANCHO EN PC (como users.php)
           ============================================ */
        .admin-main {
            flex: 1;
            width: 100%;
            padding: 2px 5px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow-x: visible;
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

        .section-container {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
            width: 100%;
        }

        /* Tarjetas de formulario */
        .form-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .form-card h5 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #c8a86b;
            display: inline-block;
            color: var(--text-primary);
        }

        .form-card h5 i {
            color: #c8a86b;
            margin-right: 8px;
        }

        .owner-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 10px 20px;
            border-radius: 12px;
            display: inline-block;
            margin-bottom: 20px;
            color: white;
        }

        .owner-badge i, .owner-badge strong {
            color: white;
        }

        .existing-images {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .image-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid var(--border-color);
            background: var(--bg-primary);
        }

        .image-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .image-item .delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(220,53,69,0.9);
            color: white;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-size: 12px;
            transition: all 0.3s;
        }

        .image-item .delete-btn:hover {
            transform: scale(1.1);
            background: #dc3545;
        }

        .main-badge {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(40,167,69,0.9);
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 5px;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .form-control, .form-select {
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--text-primary);
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #c8a86b;
            outline: none;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
        }

        .form-check-label {
            color: var(--text-primary);
        }

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            display: inline-block;
            font-size: 0.85rem;
        }

        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
        }

        .btn-success {
            background: #28a745;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            color: white;
            font-size: 0.75rem;
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            padding: 4px 12px;
            border-radius: 6px;
            color: white;
            font-size: 0.75rem;
        }

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

        .alert {
            border-radius: 12px;
        }

        [data-theme="dark"] .alert-success {
            background-color: #1a3a2a;
            border-color: #28a745;
            color: #a5d6a5;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #3a1a1a;
            border-color: #dc3545;
            color: #d6a5a5;
        }

        .list-group-item {
            background: var(--bg-primary);
            border-color: var(--border-color);
            color: var(--text-primary);
        }

        [data-theme="dark"] .list-group-item {
            background: #1a1a2e;
        }

        [data-theme="dark"] .list-group-item a {
            color: #c8a86b;
        }

        [data-theme="dark"] .select2-container--bootstrap-5 .select2-selection {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .select2-dropdown {
            background-color: #16213e !important;
            border-color: #2a2a3e !important;
        }

        [data-theme="dark"] .select2-results__option {
            color: #ffffff !important;
        }

        [data-theme="dark"] .select2-results__option--highlighted {
            background-color: #c8a86b !important;
            color: #1a1a2e !important;
        }

        /* ============================================
           RESPONSIVE: EXACTAMENTE COMO users.php
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                padding: 100px 15px 15px;
            }
        }

        @media (max-width: 768px) {
            .admin-main {
                padding: 100px 10px 10px;
            }
            .image-item {
                width: 70px;
                height: 70px;
            }
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            .form-card {
                padding: 15px;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        .swal2-container {
            z-index: 99999 !important;
        }
    </style>
</head>
<body>

<!-- Botón para cambiar tema claro/oscuro (igual a users.php) -->
<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<!-- ESTRUCTURA EXACTA DE users.php -->
<div class="admin-container">
    <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="admin-main">
        <!-- HEADER -->
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-edit"></i> Editar Publicación</h1>
                <p>Administrador - Edición completa de publicaciones</p>
            </div>
            <div>
                <a href="publications.php" class="btn-secondary" style="text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Volver a Publicaciones
                </a>
            </div>
        </div>
        
        <!-- CONTENEDOR PRINCIPAL (como section-container de users.php) -->
        <div class="section-container">
            <!-- Información del propietario -->
            <div class="owner-badge" style="margin: 15px 20px 0 20px;">
                <i class="fas fa-user"></i> 
                <strong>Propietario:</strong> <?php echo htmlspecialchars($propietario['nombre_completo']); ?> 
                (<?php echo htmlspecialchars($propietario['email']); ?>)
                <span class="badge bg-info ms-2">Cuenta: <?php echo strtoupper($propietario['tipo_cuenta']); ?></span>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger m-3"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success m-3"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data" style="padding: 0 20px 20px 20px;">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <!-- Información básica -->
                <div class="form-card">
                    <h5><i class="fas fa-info-circle"></i> Información Básica</h5>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Título del Anuncio *</label>
                            <input type="text" class="form-control" name="titulo" 
                                   value="<?php echo htmlspecialchars($publicacion['titulo']); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Categoría *</label>
                            <select class="form-select select2" name="categoria_id" required>
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <optgroup label="<?php echo htmlspecialchars($cat['nombre']); ?>">
                                        <?php foreach ($subcategorias as $sub): ?>
                                            <?php if ($sub['padre_id'] == $cat['id']): ?>
                                                <option value="<?php echo $sub['id']; ?>" 
                                                    <?php echo $sub['id'] == $publicacion['categoria_id'] ? 'selected' : ''; ?>>
                                                    - <?php echo htmlspecialchars($sub['nombre']); ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Descripción *</label>
                        <textarea class="form-control" name="descripcion" rows="5" required><?php echo htmlspecialchars($publicacion['descripcion']); ?></textarea>
                    </div>
                </div>
                
                <!-- Detalles del vehículo -->
                <div class="form-card">
                    <h5><i class="fas fa-car"></i> Detalles del Vehículo</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Precio (COP) *</label>
                            <input type="number" class="form-control" name="precio" 
                                   value="<?php echo $publicacion['precio']; ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Negociable</label>
                            <select class="form-select" name="negociable">
                                <option value="1" <?php echo $publicacion['negociable'] ? 'selected' : ''; ?>>Sí</option>
                                <option value="0" <?php echo !$publicacion['negociable'] ? 'selected' : ''; ?>>No</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="estado_articulo">
                                <option value="nuevo" <?php echo $publicacion['estado_articulo'] == 'nuevo' ? 'selected' : ''; ?>>Nuevo</option>
                                <option value="usado" <?php echo $publicacion['estado_articulo'] == 'usado' ? 'selected' : ''; ?>>Usado</option>
                                <option value="reconstruido" <?php echo $publicacion['estado_articulo'] == 'reconstruido' ? 'selected' : ''; ?>>Reconstruido</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Año</label>
                            <input type="number" class="form-control" name="year_fabricacion" 
                                   value="<?php echo $publicacion['year_fabricacion']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Kilometraje</label>
                            <input type="number" class="form-control" name="kilometraje" 
                                   value="<?php echo $publicacion['kilometraje']; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Color</label>
                            <input type="text" class="form-control" name="color" 
                                   value="<?php echo htmlspecialchars($publicacion['color']); ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ubicación</label>
                        <input type="text" class="form-control" name="ubicacion" 
                               value="<?php echo htmlspecialchars($publicacion['ubicacion']); ?>">
                    </div>
                </div>
                
                <!-- Datos de Identificación del Vehículo -->
                <div class="form-card">
                    <h5><i class="fas fa-id-card"></i> Datos de Identificación</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Marca</label>
                            <input type="text" class="form-control" name="brand" value="<?php echo htmlspecialchars($publicacion['brand'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Línea/Modelo comercial</label>
                            <input type="text" class="form-control" name="linea_modelo_comercial" value="<?php echo htmlspecialchars($publicacion['linea_modelo_comercial'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Clase de vehículo</label>
                            <select class="form-select" name="clase_vehiculo">
                                <option value="">Seleccionar</option>
                                <option value="Automóvil" <?php echo (($publicacion['clase_vehiculo'] ?? '') == 'Automóvil') ? 'selected' : ''; ?>>Automóvil</option>
                                <option value="Campero" <?php echo (($publicacion['clase_vehiculo'] ?? '') == 'Campero') ? 'selected' : ''; ?>>Campero</option>
                                <option value="Camioneta" <?php echo (($publicacion['clase_vehiculo'] ?? '') == 'Camioneta') ? 'selected' : ''; ?>>Camioneta</option>
                                <option value="Bus" <?php echo (($publicacion['clase_vehiculo'] ?? '') == 'Bus') ? 'selected' : ''; ?>>Bus</option>
                                <option value="Motocicleta" <?php echo (($publicacion['clase_vehiculo'] ?? '') == 'Motocicleta') ? 'selected' : ''; ?>>Motocicleta</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tipo de carrocería</label>
                            <select class="form-select" name="tipo_carroceria">
                                <option value="">Seleccionar</option>
                                <option value="Sedán" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Sedán') ? 'selected' : ''; ?>>Sedán</option>
                                <option value="SUV" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'SUV') ? 'selected' : ''; ?>>SUV</option>
                                <option value="Pickup" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Pickup') ? 'selected' : ''; ?>>Pickup</option>
                                <option value="Coupé" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Coupé') ? 'selected' : ''; ?>>Coupé</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cilindrada (cc)</label>
                            <input type="number" class="form-control" name="cilindrada" value="<?php echo $publicacion['cilindrada'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Potencia (HP)</label>
                            <input type="number" class="form-control" name="potencia_hp" value="<?php echo $publicacion['potencia_hp'] ?? ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Combustible</label>
                            <select class="form-select" name="fuel_type">
                                <option value="">Seleccionar</option>
                                <option value="Gasolina" <?php echo (($publicacion['fuel_type'] ?? '') == 'Gasolina') ? 'selected' : ''; ?>>Gasolina</option>
                                <option value="Diésel" <?php echo (($publicacion['fuel_type'] ?? '') == 'Diésel') ? 'selected' : ''; ?>>Diésel</option>
                                <option value="Eléctrico" <?php echo (($publicacion['fuel_type'] ?? '') == 'Eléctrico') ? 'selected' : ''; ?>>Eléctrico</option>
                                <option value="Híbrido" <?php echo (($publicacion['fuel_type'] ?? '') == 'Híbrido') ? 'selected' : ''; ?>>Híbrido</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Capacidad</label>
                            <input type="text" class="form-control" name="capacidad" value="<?php echo htmlspecialchars($publicacion['capacidad'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Blindaje</label>
                            <select class="form-select" name="blindaje">
                                <option value="No" <?php echo (($publicacion['blindaje'] ?? 'No') == 'No') ? 'selected' : ''; ?>>No</option>
                                <option value="Nivel 1" <?php echo (($publicacion['blindaje'] ?? '') == 'Nivel 1') ? 'selected' : ''; ?>>Nivel 1</option>
                                <option value="Nivel 2" <?php echo (($publicacion['blindaje'] ?? '') == 'Nivel 2') ? 'selected' : ''; ?>>Nivel 2</option>
                                <option value="Nivel 3" <?php echo (($publicacion['blindaje'] ?? '') == 'Nivel 3') ? 'selected' : ''; ?>>Nivel 3</option>
                                <option value="Nivel 4" <?php echo (($publicacion['blindaje'] ?? '') == 'Nivel 4') ? 'selected' : ''; ?>>Nivel 4</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Números de Serie -->
                <div class="form-card">
                    <h5><i class="fas fa-barcode"></i> Números de Serie</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Número de Motor</label>
                            <input type="text" class="form-control" name="numero_motor" value="<?php echo htmlspecialchars($publicacion['numero_motor'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Número de Chasis</label>
                            <input type="text" class="form-control" name="numero_chasis" value="<?php echo htmlspecialchars($publicacion['numero_chasis'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Número VIN</label>
                            <input type="text" class="form-control" name="numero_vin" value="<?php echo htmlspecialchars($publicacion['numero_vin'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Datos Legales -->
                <div class="form-card">
                    <h5><i class="fas fa-gavel"></i> Datos Legales</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Servicio</label>
                            <select class="form-select" name="servicio">
                                <option value="">Seleccionar</option>
                                <option value="Particular" <?php echo (($publicacion['servicio'] ?? '') == 'Particular') ? 'selected' : ''; ?>>Particular</option>
                                <option value="Público" <?php echo (($publicacion['servicio'] ?? '') == 'Público') ? 'selected' : ''; ?>>Público</option>
                                <option value="Diplomático" <?php echo (($publicacion['servicio'] ?? '') == 'Diplomático') ? 'selected' : ''; ?>>Diplomático</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Origen</label>
                            <select class="form-select" name="origen">
                                <option value="">Seleccionar</option>
                                <option value="Nacional" <?php echo (($publicacion['origen'] ?? '') == 'Nacional') ? 'selected' : ''; ?>>Nacional</option>
                                <option value="Importado" <?php echo (($publicacion['origen'] ?? '') == 'Importado') ? 'selected' : ''; ?>>Importado</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Propietario (Nombre)</label>
                            <input type="text" class="form-control" name="propietario_nombre" value="<?php echo htmlspecialchars($publicacion['propietario_nombre'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Tipo Documento</label>
                            <select class="form-select" name="propietario_tipo_documento">
                                <option value="">Seleccionar</option>
                                <option value="CC" <?php echo (($publicacion['propietario_tipo_documento'] ?? '') == 'CC') ? 'selected' : ''; ?>>Cédula</option>
                                <option value="NIT" <?php echo (($publicacion['propietario_tipo_documento'] ?? '') == 'NIT') ? 'selected' : ''; ?>>NIT</option>
                                <option value="CE" <?php echo (($publicacion['propietario_tipo_documento'] ?? '') == 'CE') ? 'selected' : ''; ?>>Cédula Extranjería</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Número Documento</label>
                            <input type="text" class="form-control" name="propietario_numero_documento" value="<?php echo htmlspecialchars($publicacion['propietario_numero_documento'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Empresa Vinculadora</label>
                            <input type="text" class="form-control" name="empresa_vinculadora_nombre" value="<?php echo htmlspecialchars($publicacion['empresa_vinculadora_nombre'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NIT Vinculadora</label>
                            <input type="text" class="form-control" name="empresa_vinculadora_nit" value="<?php echo htmlspecialchars($publicacion['empresa_vinculadora_nit'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Visibilidad -->
                <div class="form-card">
                    <h5><i class="fas fa-eye"></i> Visibilidad</h5>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="solo_premium_elite" id="solo_premium" 
                               <?php echo $publicacion['solo_premium_elite'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="solo_premium">
                            <i class="fas fa-gem"></i> Solo visible para cuentas PREMIUM y ELITE
                        </label>
                    </div>
                </div>
                
                <!-- Imágenes existentes -->
                <?php if (!empty($imagenes)): ?>
                <div class="form-card">
                    <h5><i class="fas fa-images"></i> Imágenes Actuales</h5>
                    <div class="existing-images">
                        <?php foreach ($imagenes as $img): ?>
                            <div class="image-item">
                                <img src="<?php echo $img['image_path']; ?>" alt="Imagen">
                                <?php if ($img['is_primary']): ?>
                                    <div class="main-badge">Principal</div>
                                <?php endif; ?>
                                <a href="?id=<?php echo $publicacion_id; ?>&delete_image=<?php echo $img['id']; ?>" 
                                   class="delete-btn" onclick="return confirm('¿Eliminar esta imagen?')">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Nuevas imágenes -->
                <div class="form-card">
                    <h5><i class="fas fa-plus-circle"></i> Agregar Más Imágenes</h5>
                    <input type="file" class="form-control" name="nuevas_imagenes[]" accept="image/*" multiple>
                </div>
                
                <!-- Documentos existentes -->
                <?php if (!empty($documentos)): ?>
                <div class="form-card">
                    <h5><i class="fas fa-file-alt"></i> Documentos Actuales</h5>
                    <div class="list-group">
                        <?php foreach ($documentos as $doc): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?php echo strtoupper($doc['tipo_documento']); ?></strong>
                                    <a href="<?php echo $doc['url_documento']; ?>" target="_blank" class="ms-2">
                                        <i class="fas fa-download"></i> Ver
                                    </a>
                                </div>
                                <a href="?id=<?php echo $publicacion_id; ?>&delete_doc=<?php echo $doc['id']; ?>" 
                                   class="btn btn-danger btn-sm" onclick="return confirm('¿Eliminar este documento?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Nuevos documentos -->
                <div class="form-card">
                    <h5><i class="fas fa-plus-circle"></i> Agregar Documentos</h5>
                    <div id="nuevosDocumentos">
                        <div class="row mb-2">
                            <div class="col-md-4">
                                <select class="form-select" name="nuevo_documento_tipo[]">
                                    <option value="soat">SOAT</option>
                                    <option value="tecnicomecanica">Técnico-mecánica</option>
                                    <option value="tarjeta_propiedad">Tarjeta de Propiedad</option>
                                    <option value="factura_compra">Factura de Compra</option>
                                    <option value="otros">Otros</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <input type="file" class="form-control" name="nuevos_documentos[]" accept=".pdf,.jpg,.png">
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success btn-sm" onclick="addDocumentField()">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Botones -->
                <div class="form-card">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                    <a href="publications.php" class="btn-secondary" style="text-decoration: none; margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancelar
                    </a>
                </div>
            </form>
        </div>
    </main>
</div>

<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    // ============================================
    // FUNCIONES DE TEMA (COPIADAS DE users.php)
    // ============================================
    
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
    
    // ============================================
    // FUNCIONES EXISTENTES
    // ============================================
    
    $(document).ready(function() {
        $('.select2').select2({ 
            theme: 'bootstrap-5', 
            width: '100%',
            dropdownParent: $('body')
        });
    });
    
    function addDocumentField() {
        const container = document.getElementById('nuevosDocumentos');
        const newRow = document.createElement('div');
        newRow.className = 'row mb-2';
        newRow.innerHTML = `
            <div class="col-md-4">
                <select class="form-select" name="nuevo_documento_tipo[]">
                    <option value="soat">SOAT</option>
                    <option value="tecnicomecanica">Técnico-mecánica</option>
                    <option value="tarjeta_propiedad">Tarjeta de Propiedad</option>
                    <option value="factura_compra">Factura de Compra</option>
                    <option value="otros">Otros</option>
                </select>
            </div>
            <div class="col-md-6">
                <input type="file" class="form-control" name="nuevos_documentos[]" accept=".pdf,.jpg,.png">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.row').remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        container.appendChild(newRow);
    }
</script>
</body>
</html>