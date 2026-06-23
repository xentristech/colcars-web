<?php
/**
 * EASY CAR LUXURY - Editar Publicación
 * Ruta: /dashboard/user/edit-publication.php
 * MODIFICADO: Bloquea la edición si la cuenta está desactivada.
 * ADICIONADO: Nuevos campos para datos de identificación, números de serie y datos legales.
 * CORREGIDO: Rutas usando BASE_PATH (15/06/2025)
 * CORREGIDO: Sidebar usando user-sidebar.php y colores modo oscuro
 * CORREGIDO: Botones de carga de archivos con color #667EEA en modo oscuro
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// ============================================
// CORRECCIÓN: Usar BASE_PATH que ya está definida en config.php
// BASE_PATH = C:\wamp64\www\easycarluxury
// ============================================
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 3));
}
if (!defined('UPLOAD_ABSOLUTE_PATH')) {
    define('UPLOAD_ABSOLUTE_PATH', BASE_PATH . '/uploads/vehicles/');
}
// Crear directorio base si no existe
if (!is_dir(UPLOAD_ABSOLUTE_PATH)) {
    mkdir(UPLOAD_ABSOLUTE_PATH, 0777, true);
}
// ============================================

requireAuth();

$db = Database::getInstance();
$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /easycarluxury/public/login.php');
    exit;
}

$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

// ============================================
// VERIFICAR SI LA CUENTA ESTÁ ACTIVA
// ============================================
if (!$user || $user['activo'] != 1) {
    // Cuenta desactivada: redirigir con mensaje
    header('Location: index.php?error=account_inactive');
    exit;
}

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

$publicacion_id = intval($_GET['id'] ?? 0);

// Verificar propiedad
$publicacion = $db->getOne("SELECT * FROM publicaciones WHERE id = ? AND usuario_id = ?", [$publicacion_id, $user_id]);
if (!$publicacion) {
    header('Location: my-publications.php?error=not_found');
    exit;
}

$error = '';
$success = '';

// Obtener imágenes
$imagenes = $db->getAll("SELECT * FROM imagenes_publicaciones WHERE publicacion_id = ? ORDER BY sort_order", [$publicacion_id]);

// Obtener documentos
$documentos = $db->getAll("SELECT * FROM documentacion_articulos WHERE publicacion_id = ?", [$publicacion_id]);

// Obtener categorías
$categorias = $db->getAll("SELECT * FROM categorias WHERE padre_id IS NULL OR padre_id = 0 ORDER BY nombre");
$subcategorias = $db->getAll("SELECT * FROM categorias WHERE padre_id IS NOT NULL AND padre_id != 0 ORDER BY nombre");

// Procesar actualización (solo si cuenta activa)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        // Datos actualizados
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
            // Nuevos campos
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
            // Construir consulta UPDATE dinámica
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
            
            // ============================================
            // CORRECCIÓN: Agregar nuevas imágenes con ruta ABSOLUTA usando BASE_PATH
            // ============================================
            if (!empty($_FILES['nuevas_imagenes']['name'][0])) {
                $user_folder_name = 'user_' . $user_id;
                $sub_folder_name = 'publicaciones';
                $absolute_user_folder = BASE_PATH . '/uploads/vehicles/' . $user_folder_name . '/' . $sub_folder_name . '/';
                
                if (!is_dir($absolute_user_folder)) {
                    if (!mkdir($absolute_user_folder, 0777, true)) {
                        error_log("ERROR edit-publication: No se pudo crear la carpeta: " . $absolute_user_folder);
                        throw new Exception("No se pudo crear la carpeta para guardar las imágenes");
                    }
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
                        } else {
                            error_log("ERROR edit-publication: No se pudo mover la imagen a: " . $destination);
                        }
                    }
                }
            }
            // ============================================
            
            // ============================================
            // CORRECCIÓN: Agregar nuevos documentos con ruta ABSOLUTA usando BASE_PATH
            // ============================================
            if (!empty($_FILES['nuevos_documentos']['name'][0])) {
                $user_folder_name = 'user_' . $user_id;
                $sub_folder_name = 'documentos';
                $absolute_docs_folder = BASE_PATH . '/uploads/vehicles/' . $user_folder_name . '/' . $sub_folder_name . '/';
                
                if (!is_dir($absolute_docs_folder)) {
                    if (!mkdir($absolute_docs_folder, 0777, true)) {
                        error_log("ERROR edit-publication: No se pudo crear la carpeta de documentos: " . $absolute_docs_folder);
                        throw new Exception("No se pudo crear la carpeta para guardar los documentos");
                    }
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
                        } else {
                            error_log("ERROR edit-publication: No se pudo mover el documento a: " . $destination);
                        }
                    }
                }
            }
            // ============================================
            
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

// Eliminar imagen (CORREGIDO)
if (isset($_GET['delete_image'])) {
    $img_id = intval($_GET['delete_image']);
    $img = $db->getOne("SELECT * FROM imagenes_publicaciones WHERE id = ? AND publicacion_id = ?", [$img_id, $publicacion_id]);
    if ($img) {
        // ============================================
        // CORRECCIÓN: Usar BASE_PATH para eliminar archivo
        // ============================================
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 3));
        }
        $file_path = BASE_PATH . $img['image_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        $db->delete('imagenes_publicaciones', 'id = ?', [$img_id]);
        header("Location: edit-publication.php?id=$publicacion_id");
        exit;
    }
}

// Eliminar documento (CORREGIDO)
if (isset($_GET['delete_doc'])) {
    $doc_id = intval($_GET['delete_doc']);
    $doc = $db->getOne("SELECT * FROM documentacion_articulos WHERE id = ? AND publicacion_id = ?", [$doc_id, $publicacion_id]);
    if ($doc) {
        // ============================================
        // CORRECCIÓN: Usar BASE_PATH para eliminar archivo
        // ============================================
        if (!defined('BASE_PATH')) {
            define('BASE_PATH', dirname(__DIR__, 3));
        }
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
$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Publicación - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <style>
        /* ============================================
           ESTRUCTURA GLOBAL
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-primary);
            color: var(--text-primary);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* CONTENEDOR PRINCIPAL */
        .dashboard-wrapper {
            display: flex;
            flex: 1;
            width: 100%;
        }

        /* COLUMNA DEL SIDEBAR - ANCHO FIJO */
        .sidebar-column {
            flex-shrink: 0;
            width: 260px;
            min-height: 100%;
            display: flex;
            flex-direction: column;
        }

        /* COLUMNA DEL CONTENIDO */
        .content-column {
            flex: 1;
            background: var(--bg-primary);
            display: flex;
            flex-direction: column;
        }

        /* CONTENIDO PRINCIPAL */
        .main-content {
            padding: 20px;
            flex: 1;
            margin-top: 60px;
        }

        /* ============================================
           ESTILOS DEL CONTENIDO
           ============================================ */
        :root {
            --bg-primary: #f8f9fa;
            --bg-secondary: #ffffff;
            --text-primary: #212529;
            --text-secondary: #6c757d;
            --border-color: #dee2e6;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #ffffff;
            --text-secondary: #e0e0e0;
            --border-color: #2a2a3e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO OSCURO
           ============================================ */
        [data-theme="dark"] body,
        [data-theme="dark"] .main-content,
        [data-theme="dark"] .form-card,
        [data-theme="dark"] h1,
        [data-theme="dark"] h2,
        [data-theme="dark"] h3,
        [data-theme="dark"] h4,
        [data-theme="dark"] h5,
        [data-theme="dark"] h6,
        [data-theme="dark"] p,
        [data-theme="dark"] span:not(.badge):not(.alert),
        [data-theme="dark"] div:not(.alert):not(.badge):not(.modal-content),
        [data-theme="dark"] small,
        [data-theme="dark"] strong,
        [data-theme="dark"] label,
        [data-theme="dark"] .text-muted,
        [data-theme="dark"] .form-label,
        [data-theme="dark"] .form-check-label,
        [data-theme="dark"] .list-group-item {
            color: #ffffff !important;
        }

        [data-theme="dark"] i,
        [data-theme="dark"] .fas,
        [data-theme="dark"] .far,
        [data-theme="dark"] .fab {
            color: #ffffff !important;
        }

        [data-theme="dark"] a:not(.btn):not(.nav-link) {
            color: #a0c4ff !important;
        }

        [data-theme="dark"] .text-muted,
        [data-theme="dark"] small.text-muted {
            color: #c0c0c0 !important;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #5a1a1a;
            color: #ffcccc !important;
            border-color: #8b3a3a;
        }

        [data-theme="dark"] .alert-success {
            background-color: #1a4a2a;
            color: #ccffcc !important;
            border-color: #2a6a3a;
        }

        [data-theme="dark"] .alert-danger i,
        [data-theme="dark"] .alert-success i {
            color: inherit !important;
        }

        /* INPUTS, SELECTS, TEXTAREA EN MODO OSCURO - COLOR #222F58 */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select,
        [data-theme="dark"] textarea.form-control,
        [data-theme="dark"] input.form-control {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus,
        [data-theme="dark"] textarea.form-control:focus {
            background-color: #2a3a6a !important;
            color: #ffffff !important;
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        [data-theme="dark"] .form-control::placeholder,
        [data-theme="dark"] textarea.form-control::placeholder {
            color: #a0a0b0 !important;
        }

        /* BOTONES DE ARCHIVO (FILE INPUT) EN MODO OSCURO - COLOR #667EEA */
        [data-theme="dark"] input[type="file"]::file-selector-button,
        [data-theme="dark"] input[type="file"]::-webkit-file-upload-button {
            background-color: #667EEA !important;
            color: #ffffff !important;
            border: none !important;
            padding: 8px 16px !important;
            border-radius: 6px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
        }

        [data-theme="dark"] input[type="file"]::file-selector-button:hover,
        [data-theme="dark"] input[type="file"]::-webkit-file-upload-button:hover {
            background-color: #5a6fd6 !important;
            transform: scale(1.02) !important;
        }

        [data-theme="dark"] input[type="file"] {
            color: #ffffff !important;
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
        }

        /* BOTONES DE ARCHIVO EN MODO CLARO - MANTENER COLOR ORIGINAL */
        [data-theme="light"] input[type="file"]::file-selector-button,
        [data-theme="light"] input[type="file"]::-webkit-file-upload-button {
            background-color: #e9ecef !important;
            color: #212529 !important;
            border: 1px solid #ced4da !important;
            padding: 8px 16px !important;
            border-radius: 6px !important;
            cursor: pointer !important;
        }

        /* SELECT2 EN MODO OSCURO - COLOR #222F58 */
        [data-theme="dark"] .select2-container--bootstrap-5 .select2-selection {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .select2-container--bootstrap-5 .select2-selection__rendered {
            color: #ffffff !important;
        }

        [data-theme="dark"] .select2-container--bootstrap-5 .select2-selection__arrow {
            color: #ffffff !important;
        }

        [data-theme="dark"] .select2-container--bootstrap-5 .select2-dropdown {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .select2-container--bootstrap-5 .select2-results__option {
            color: #ffffff !important;
            background-color: #222F58 !important;
        }

        [data-theme="dark"] .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #667eea !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .select2-container--bootstrap-5 .select2-results__option[aria-selected="true"] {
            background-color: #2a3a6a !important;
            color: #ffffff !important;
        }

        /* BOTONES EN MODO OSCURO */
        [data-theme="dark"] .btn-primary {
            background-color: #667eea;
            border-color: #667eea;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-primary:hover {
            background-color: #5a6fd6;
            border-color: #5a6fd6;
        }

        [data-theme="dark"] .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-outline-primary {
            color: #ffffff !important;
            border-color: #667eea;
        }

        [data-theme="dark"] .btn-outline-primary:hover {
            background-color: #667eea;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-outline-danger {
            color: #ffffff !important;
            border-color: #dc3545;
        }

        [data-theme="dark"] .btn-outline-danger:hover {
            background-color: #dc3545;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-success {
            background-color: #28a745;
            border-color: #28a745;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-danger {
            background-color: #dc3545;
            border-color: #dc3545;
            color: #ffffff !important;
        }

        /* FORM CHECK EN MODO OSCURO */
        [data-theme="dark"] .form-check-input {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
        }

        [data-theme="dark"] .form-check-input:checked {
            background-color: #667eea !important;
            border-color: #667eea !important;
        }

        /* LIST GROUP EN MODO OSCURO */
        [data-theme="dark"] .list-group-item {
            background-color: #2a2a3e !important;
            border-color: #4a4a5e !important;
        }

        /* FOOTER EN MODO OSCURO */
        [data-theme="dark"] .dashboard-footer {
            background: #16213e;
            border-top-color: #2a2a3e;
        }

        [data-theme="dark"] .dashboard-footer a {
            color: #a0c4ff !important;
        }

        [data-theme="dark"] .dashboard-footer .footer-social a {
            background: rgba(102, 126, 234, 0.2);
            color: #ffffff !important;
        }

        /* MEMBERSHIP BADGE EN MODO OSCURO */
        [data-theme="dark"] .membership-badge {
            color: #ffffff !important;
        }

        [data-theme="dark"] .membership-badge i,
        [data-theme="dark"] .membership-badge small {
            color: #ffffff !important;
        }

        /* BOTÓN TEMA EN MODO OSCURO */
        [data-theme="dark"] .btn-theme {
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-theme i {
            color: #ffffff !important;
        }

        /* OFFCANVAS MÓVIL EN MODO OSCURO */
        [data-theme="dark"] .mobile-offcanvas {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%) !important;
        }

        [data-theme="dark"] .mobile-offcanvas .nav-link {
            color: rgba(255,255,255,0.8) !important;
        }

        [data-theme="dark"] .mobile-offcanvas .nav-link:hover,
        [data-theme="dark"] .mobile-offcanvas .nav-link.active {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }

        /* IMAGE ITEM EN MODO OSCURO */
        [data-theme="dark"] .image-item {
            border-color: #4a4a5e !important;
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .form-card,
        [data-theme="light"] h1,
        [data-theme="light"] h2,
        [data-theme="light"] h3,
        [data-theme="light"] h4,
        [data-theme="light"] h5,
        [data-theme="light"] h6,
        [data-theme="light"] p,
        [data-theme="light"] span,
        [data-theme="light"] small,
        [data-theme="light"] strong,
        [data-theme="light"] label,
        [data-theme="light"] .form-label,
        [data-theme="light"] .form-check-label,
        [data-theme="light"] .list-group-item {
            color: #212529 !important;
        }

        [data-theme="light"] i,
        [data-theme="light"] .fas,
        [data-theme="light"] .far,
        [data-theme="light"] .fab {
            color: #212529 !important;
        }

        [data-theme="light"] .text-muted {
            color: #6c757d !important;
        }

        [data-theme="light"] .btn-info i {
            color: #ffffff !important;
        }

        [data-theme="light"] .sidebar-column,
        [data-theme="light"] .sidebar-column *,
        [data-theme="light"] .sidebar-column .nav-link,
        [data-theme="light"] .sidebar-column .nav-link i,
        [data-theme="light"] .sidebar-column .nav-link span,
        [data-theme="light"] .sidebar-column h3,
        [data-theme="light"] .sidebar-column h4,
        [data-theme="light"] .sidebar-column p,
        [data-theme="light"] .sidebar-column div:not(.alert) {
            color: #ffffff !important;
        }

        [data-theme="light"] .membership-badge,
        [data-theme="light"] .membership-badge i,
        [data-theme="light"] .membership-badge small,
        [data-theme="light"] .membership-badge span {
            color: #ffffff !important;
        }

        /* ============================================
           ESTILOS ORIGINALES
           ============================================ */
        .form-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }

        .form-card:hover {
            transform: translateY(-3px);
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
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .main-badge {
            position: absolute;
            bottom: 5px;
            left: 5px;
            background: rgba(40, 167, 69, 0.9);
            color: white;
            font-size: 10px;
            padding: 2px 5px;
            border-radius: 5px;
        }

        .btn-theme {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            cursor: pointer;
            z-index: 1000;
        }

        .membership-badge {
            position: fixed;
            top: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            padding: 8px 15px;
            border-radius: 50px;
            color: white;
            font-weight: bold;
            z-index: 1000;
        }

        /* ESTILOS DEL NAVBAR MÓVIL */
        .mobile-navbar {
            display: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 12px 16px;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1002;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }
        
        .mobile-navbar .navbar-brand {
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .mobile-navbar .navbar-brand img {
            height: 28px;
            width: auto;
        }
        
        .mobile-navbar .btn-menu {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            font-size: 1.4rem;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .mobile-navbar .btn-menu:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .mobile-navbar .user-info {
            color: white;
            font-size: 0.75rem;
            background: rgba(255, 255, 255, 0.15);
            padding: 6px 12px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .mobile-offcanvas {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            width: 280px;
            z-index: 1050;
        }
        
        .mobile-offcanvas .offcanvas-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            padding: 15px;
        }
        
        .mobile-offcanvas .offcanvas-title {
            color: white;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.1rem;
        }
        
        .mobile-offcanvas .offcanvas-title img {
            height: 28px;
            width: auto;
        }
        
        .mobile-offcanvas .btn-close {
            filter: invert(1);
            opacity: 0.8;
        }
        
        .mobile-offcanvas .offcanvas-body {
            padding: 15px;
        }
        
        .mobile-offcanvas .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 15px;
            margin: 4px 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .mobile-offcanvas .nav-link:hover,
        .mobile-offcanvas .nav-link.active {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .mobile-offcanvas .nav-link i {
            width: 25px;
            font-size: 1.1rem;
        }
        
        .mobile-offcanvas hr {
            border-color: rgba(255, 255, 255, 0.2);
            margin: 10px 0;
        }

        .dashboard-footer {
            background: var(--bg-secondary);
            border-top: 1px solid var(--border-color);
            padding: 15px 0;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-secondary);
            width: 100%;
        }

        .dashboard-footer a {
            color: #667eea;
            text-decoration: none;
        }

        .dashboard-footer .footer-social {
            margin-top: 10px;
        }

        .dashboard-footer .footer-social a {
            display: inline-block;
            width: 30px;
            height: 30px;
            line-height: 30px;
            text-align: center;
            margin: 0 5px;
            border-radius: 50%;
            background: rgba(102, 126, 234, 0.1);
        }

        @media (max-width: 768px) {
            .mobile-navbar {
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            
            .sidebar-column {
                display: none;
            }
            
            body {
                padding-top: 60px;
            }
            
            .main-content {
                padding: 15px;
                margin-top: 0px;
            }
            
            .membership-badge {
                top: 70px;
                right: 10px;
                font-size: 0.7rem;
                padding: 5px 10px;
            }
            
            .btn-theme {
                bottom: 70px;
                right: 15px;
                width: 45px;
                height: 45px;
            }
            
            .dashboard-footer {
                padding: 12px 0;
                font-size: 0.7rem;
            }
            
            .dashboard-footer .footer-social a {
                width: 25px;
                height: 25px;
                line-height: 25px;
                font-size: 0.7rem;
            }
            
            .image-item {
                width: 70px;
                height: 70px;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR MÓVIL -->
    <div class="mobile-navbar">
        <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-brand">
            <img src="/easycarluxury/assets/imagenes/logos/colcars.png" alt="Colcars">
            <span>Colcars</span>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars(substr($user['nombre_completo'], 0, 12)); ?></span>
        </div>
    </div>

    <!-- OFFCANVAS MENÚ MÓVIL -->
    <div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">
                <img src="/easycarluxury/assets/imagenes/logos/colcars.png" alt="Colcars">
                Colcars
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <div class="text-center mb-3" style="color: white;">
                <i class="fas fa-user-circle fa-2x"></i>
                <p class="mt-2 mb-0"><?php echo htmlspecialchars($user['nombre_completo']); ?></p>
            </div>
            <hr>
            <nav class="nav flex-column">
                <a class="nav-link" href="index.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a class="nav-link active" href="my-publications.php"><i class="fas fa-list"></i> Mis Publicaciones</a>
                <a class="nav-link" href="new-publication.php"><i class="fas fa-plus-circle"></i> Nueva Publicación</a>
                <a class="nav-link" href="messages.php"><i class="fas fa-envelope"></i> Mensajes
                    <?php if (($unread_messages['total'] ?? 0) > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unread_messages['total']; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="my-offers.php"><i class="fas fa-gavel"></i> Mis Ofertas</a>
                <a class="nav-link" href="statistics.php"><i class="fas fa-chart-line"></i> Estadísticas</a>
                <a class="nav-link" href="membership.php"><i class="fas fa-gem"></i> Membresía</a>
                <a class="nav-link" href="payments.php"><i class="fas fa-credit-card"></i> Pagos</a>
                <a class="nav-link" href="invoices.php"><i class="fas fa-file-invoice"></i> Facturas</a>
                <a class="nav-link" href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Configuración</a>
                <hr class="my-2">
                <a class="nav-link" href="/easycarluxury/logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
        </div>
    </div>

    <div class="membership-badge">
        <i class="fas fa-crown"></i> Cuenta: <?php echo strtoupper($user['tipo_cuenta']); ?>
        <?php if ($user['tipo_cuenta'] != 'free'): ?>
            <small>(Expira: <?php echo date('d/m/Y', strtotime($user['fecha_expiracion'])); ?>)</small>
        <?php endif; ?>
    </div>

    <button class="btn-theme" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>

    <!-- ESTRUCTURA PRINCIPAL -->
    <div class="dashboard-wrapper">
        <!-- COLUMNA DEL SIDEBAR -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>
        </div>

        <!-- COLUMNA DEL CONTENIDO -->
        <div class="content-column">
            <div class="main-content">
                <h2><i class="fas fa-edit"></i> Editar Publicación</h2>
                <p class="text-muted">Modifica los datos de tu anuncio</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data">
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

                    <!-- Detalles -->
                    <div class="form-card">
                        <h5><i class="fas fa-car"></i> Detalles del Vehículo</h5>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Precio (COP) *</label>
                                <input type="number" class="form-control" name="precio" 
                                       value="<?php echo $publicacion['precio']; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">¿Negociable?</label>
                                <select class="form-select" name="negociable">
                                    <option value="1" <?php echo $publicacion['negociable'] ? 'selected' : ''; ?>>Sí, negociable</option>
                                    <option value="0" <?php echo !$publicacion['negociable'] ? 'selected' : ''; ?>>No, precio fijo</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Estado del Artículo</label>
                                <select class="form-select" name="estado_articulo">
                                    <option value="nuevo" <?php echo $publicacion['estado_articulo'] == 'nuevo' ? 'selected' : ''; ?>>Nuevo</option>
                                    <option value="usado" <?php echo $publicacion['estado_articulo'] == 'usado' ? 'selected' : ''; ?>>Usado</option>
                                    <option value="reconstruido" <?php echo $publicacion['estado_articulo'] == 'reconstruido' ? 'selected' : ''; ?>>Reconstruido</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Año de Fabricación</label>
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

                    <!-- NUEVA SECCIÓN: Datos de Identificación del Vehículo -->
                    <div class="form-card">
                        <h5><i class="fas fa-id-card"></i> 1. Datos de Identificación del Vehículo</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Marca</label>
                                <input type="text" class="form-control" name="brand" value="<?php echo htmlspecialchars($publicacion['brand'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Línea o modelo comercial</label>
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
                                    <option value="Otro" <?php echo (($publicacion['clase_vehiculo'] ?? '') == 'Otro') ? 'selected' : ''; ?>>Otro</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de carrocería</label>
                                <select class="form-select" name="tipo_carroceria">
                                    <option value="">Seleccionar</option>
                                    <option value="Sedán" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Sedán') ? 'selected' : ''; ?>>Sedán</option>
                                    <option value="Hatchback" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Hatchback') ? 'selected' : ''; ?>>Hatchback</option>
                                    <option value="Coupé" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Coupé') ? 'selected' : ''; ?>>Coupé</option>
                                    <option value="SUV" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'SUV') ? 'selected' : ''; ?>>SUV</option>
                                    <option value="Pickup" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Pickup') ? 'selected' : ''; ?>>Pickup</option>
                                    <option value="Furgón" <?php echo (($publicacion['tipo_carroceria'] ?? '') == 'Furgón') ? 'selected' : ''; ?>>Furgón</option>
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
                                    <option value="Gas" <?php echo (($publicacion['fuel_type'] ?? '') == 'Gas') ? 'selected' : ''; ?>>Gas</option>
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
                                    <option value="Nivel 3+" <?php echo (($publicacion['blindaje'] ?? '') == 'Nivel 3+') ? 'selected' : ''; ?>>Nivel 3+</option>
                                    <option value="Nivel 4" <?php echo (($publicacion['blindaje'] ?? '') == 'Nivel 4') ? 'selected' : ''; ?>>Nivel 4</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- NUEVA SECCIÓN: Números Únicos de Serie -->
                    <div class="form-card">
                        <h5><i class="fas fa-barcode"></i> 2. Números Únicos de Serie</h5>
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
                                <label class="form-label">Número de Serie o VIN</label>
                                <input type="text" class="form-control" name="numero_vin" value="<?php echo htmlspecialchars($publicacion['numero_vin'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- NUEVA SECCIÓN: Datos Legales, Administrativos y de Propiedad -->
                    <div class="form-card">
                        <h5><i class="fas fa-gavel"></i> 3. Datos Legales, Administrativos y de Propiedad</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Servicio</label>
                                <select class="form-select" name="servicio">
                                    <option value="">Seleccionar</option>
                                    <option value="Particular" <?php echo (($publicacion['servicio'] ?? '') == 'Particular') ? 'selected' : ''; ?>>Particular</option>
                                    <option value="Público" <?php echo (($publicacion['servicio'] ?? '') == 'Público') ? 'selected' : ''; ?>>Público</option>
                                    <option value="Diplomático" <?php echo (($publicacion['servicio'] ?? '') == 'Diplomático') ? 'selected' : ''; ?>>Diplomático</option>
                                    <option value="Oficial" <?php echo (($publicacion['servicio'] ?? '') == 'Oficial') ? 'selected' : ''; ?>>Oficial</option>
                                    <option value="Especial" <?php echo (($publicacion['servicio'] ?? '') == 'Especial') ? 'selected' : ''; ?>>Especial</option>
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
                                <label class="form-label">Propietario (Nombre completo)</label>
                                <input type="text" class="form-control" name="propietario_nombre" value="<?php echo htmlspecialchars($publicacion['propietario_nombre'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Propietario (Tipo documento)</label>
                                <select class="form-select" name="propietario_tipo_documento">
                                    <option value="">Seleccionar</option>
                                    <option value="CC" <?php echo (($publicacion['propietario_tipo_documento'] ?? '') == 'CC') ? 'selected' : ''; ?>>Cédula de Ciudadanía</option>
                                    <option value="NIT" <?php echo (($publicacion['propietario_tipo_documento'] ?? '') == 'NIT') ? 'selected' : ''; ?>>NIT</option>
                                    <option value="CE" <?php echo (($publicacion['propietario_tipo_documento'] ?? '') == 'CE') ? 'selected' : ''; ?>>Cédula de Extranjería</option>
                                    <option value="Pasaporte" <?php echo (($publicacion['propietario_tipo_documento'] ?? '') == 'Pasaporte') ? 'selected' : ''; ?>>Pasaporte</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Propietario (Número documento)</label>
                                <input type="text" class="form-control" name="propietario_numero_documento" value="<?php echo htmlspecialchars($publicacion['propietario_numero_documento'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Empresa Vinculadora (Nombre)</label>
                                <input type="text" class="form-control" name="empresa_vinculadora_nombre" value="<?php echo htmlspecialchars($publicacion['empresa_vinculadora_nombre'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Empresa Vinculadora (NIT)</label>
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
                                <i class="fas fa-gem"></i> Documentos visibles solo para cuentas PREMIUM y ELITE
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
                                       class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este documento?')">
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
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <a href="my-publications.php" class="btn btn-secondary btn-lg">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- FOOTER -->
    <footer class="dashboard-footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <p>&copy; <?php echo date('Y'); ?> Colcars - Todos los derechos reservados. By Software and Games Cel: 3151056434</p>
                    <p class="mb-0"><a href="/easycarluxury/terms">Términos y condiciones</a> | <a href="/easycarluxury/privacy">Política de privacidad</a> | <a href="/easycarluxury/contact">Contacto</a></p>
                    <div class="footer-social">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-whatsapp"></i></a>
                        <a href="#"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({ url: '/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
        }

        $(document).ready(function() {
            $('.select2').select2({
                theme: 'bootstrap-5',
                dropdownAutoWidth: true,
                width: '100%'
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