<?php
/**
 * C:\wamp64\www\easycarluxury\public\catalog\detail.php
 * CORREGIDO: Tablas horizontales con panel blanco, sombra, hover.
 * Publicidad en segunda tabla ocupando toda la altura disponible.
 * CORREGIDO: Visor de imágenes (botones siguiente/anterior funcionando correctamente)
 * CORREGIDO: Comentarios y paginación
 * CORREGIDO: Rutas de API para evitar Error 500
 * CORREGIDO: Rutas de botones "Ver detalles" en vehículos relacionados
 * MODIFICADO: Agregada publicidad desde tabla publicidad
 * MODIFICADO: Navbar corregido - logo izquierda, hamburguesa derecha
 * MODIFICADO: Botón de tema flotante en esquina inferior derecha
 * MODIFICADO: Logo dinámico - modo claro: logo_d.png, modo oscuro: colcars_b.png
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = Database::getInstance();
$pdo = $database->getConnection();

if (!$pdo) {
    die('Error de conexión');
}

// Obtener ID
$id = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
} else {
    $request_uri = $_SERVER['REQUEST_URI'];
    if (preg_match('/\/vehicle\/(\d+)/', $request_uri, $matches)) {
        $id = intval($matches[1]);
    }
}

if (!$id || $id <= 0) {
    header('Location: index.php');
    exit;
}

// Obtener vehículo - incluye u.activo y filtrar por activo
$query = "SELECT p.*, 
            u.id as seller_id, 
            u.nombre_completo as seller_name, 
            u.email as seller_email,
            u.telefono as seller_phone,
            u.tipo_cuenta as membership_tier,
            u.activo as seller_active,
            c.nombre as category_name
            FROM publicaciones p
            JOIN usuarios u ON p.usuario_id = u.id
            LEFT JOIN categorias c ON p.categoria_id = c.id
            WHERE p.id = :id AND p.status = 'active' AND u.activo = 1";

$stmt = $pdo->prepare($query);
$stmt->execute([':id' => $id]);
$vehicle = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vehicle) {
    header('Location: index.php?error=user_inactive');
    exit;
}

// Registrar visita
$visitQuery = "INSERT INTO publication_views (publication_id, ip_address, viewed_at) 
                VALUES (:publication_id, :ip, NOW())";
$visitStmt = $pdo->prepare($visitQuery);
$visitStmt->execute([
    ':publication_id' => $id,
    ':ip' => $_SERVER['REMOTE_ADDR'] ?? ''
]);

$updateVisitsQuery = "UPDATE publicaciones SET visitas = visitas + 1 WHERE id = :id";
$updateVisitsStmt = $pdo->prepare($updateVisitsQuery);
$updateVisitsStmt->execute([':id' => $id]);

// Obtener imágenes
$imagesQuery = "SELECT * FROM imagenes_publicaciones WHERE publicacion_id = :id ORDER BY is_primary DESC, sort_order ASC";
$imagesStmt = $pdo->prepare($imagesQuery);
$imagesStmt->execute([':id' => $id]);
$images = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);

// Contar likes
$likesCount = 0;
$likesQuery = "SELECT COUNT(*) as count FROM favorites WHERE publication_id = :id";
$likesStmt = $pdo->prepare($likesQuery);
$likesStmt->execute([':id' => $id]);
$likesResult = $likesStmt->fetch(PDO::FETCH_ASSOC);
$likesCount = $likesResult['count'] ?? 0;

// Vehículos relacionados - filtrar por u.activo = 1
$relatedQuery = "SELECT p.*, 
                        u.tipo_cuenta as seller_tier,
                        (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                        FROM publicaciones p
                        JOIN usuarios u ON p.usuario_id = u.id
                        WHERE p.categoria_id = :category_id 
                        AND p.id != :id 
                        AND p.status = 'active'
                        AND u.activo = 1
                        ORDER BY p.destacado DESC, p.created_at DESC
                        LIMIT 8";
$relatedStmt = $pdo->prepare($relatedQuery);
$relatedStmt->execute([':category_id' => $vehicle['categoria_id'], ':id' => $id]);
$related = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar like
$liked = false;
if (isset($_SESSION['usuario_id'])) {
    $checkLike = "SELECT * FROM favorites WHERE user_id = :user_id AND publication_id = :pub_id";
    $likeStmt = $pdo->prepare($checkLike);
    $likeStmt->execute([':user_id' => $_SESSION['usuario_id'], ':pub_id' => $id]);
    $liked = $likeStmt->fetch() ? true : false;
}

// TEMA
$tema = 'light';
if (isset($_COOKIE['theme'])) {
    $tema = $_COOKIE['theme'];
}

$vehicleImage = !empty($images) ? $images[0]['image_path'] : '';

// Obtener categorías para el dropdown
$categoriesQuery = "SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre";
$categoriesStmt = $pdo->query($categoriesQuery);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener usuario actual para el nombre
$current_user_name = '';
$current_user_email = '';
if (isset($_SESSION['usuario_id'])) {
    $userQuery = "SELECT nombre_completo, email FROM usuarios WHERE id = :id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([':id' => $_SESSION['usuario_id']]);
    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);
    if ($userData) {
        $current_user_name = $userData['nombre_completo'];
        $current_user_email = $userData['email'];
    }
}

// Contar comentarios para la paginación
$countCommentsQuery = "SELECT COUNT(*) as total FROM comentarios WHERE publicacion_id = :pub_id AND visible = 1";
$countStmt = $pdo->prepare($countCommentsQuery);
$countStmt->execute([':pub_id' => $id]);
$totalComments = $countStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// ==========================================
// OBTENER PUBLICIDAD (BANNER Y VIDEO) PARA MOSTRAR EN detail.php
// ==========================================

// Obtener banner activo (posición banner_principal)
$banner_activo = null;
$bannerQuery = "SELECT * FROM publicidad 
                WHERE tipo = 'banner' 
                AND posicion = 'banner_principal' 
                AND activo = 1
                AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
                AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
                ORDER BY orden ASC, created_at DESC
                LIMIT 1";
$bannerStmt = $pdo->prepare($bannerQuery);
$bannerStmt->execute();
$banner_activo = $bannerStmt->fetch(PDO::FETCH_ASSOC);

// Obtener video activo (posición video_espacio)
$video_activo = null;
$videoQuery = "SELECT * FROM publicidad 
               WHERE tipo = 'video' 
               AND posicion = 'video_espacio' 
               AND activo = 1
               AND (fecha_inicio IS NULL OR fecha_inicio <= CURDATE())
               AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())
               ORDER BY orden ASC, created_at DESC
               LIMIT 1";
$videoStmt = $pdo->prepare($videoQuery);
$videoStmt->execute();
$video_activo = $videoStmt->fetch(PDO::FETCH_ASSOC);

// Determinar base URL para rutas (sin /easycarluxury porque DocumentRoot ya apunta allí)
$baseUrl = ''; // Vacío porque DocumentRoot ya está en easycarluxury
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($vehicle['titulo']); ?> | Easy Car Luxury</title>

    <!-- Bootstrap CSS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome CSS CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <link rel="stylesheet" href="/assets/css/catalog.css">
    <link rel="stylesheet" href="/assets/css/dark-theme.css">
    <link rel="stylesheet" href="/assets/css/light-theme.css">

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body.light-theme {
        background: #f0f2f5;
        color: #333;
    }

    body.dark-theme {
        background: #1a1a2e;
        color: #ffffff;
    }

    body.dark-theme .info-card,
    body.dark-theme .gallery-card {
        background: #2c2c3e;
        color: #ffffff;
    }

    body.dark-theme .description-text {
        background: #1e2a3a;
        border-color: #444;
        color: #ddd;
    }

    body.dark-theme .seller-panel-white {
        background: #2c2c3e;
    }

    body.dark-theme .seller-card-blue {
        background: #1a3a4a;
        color: #ffffff;
    }

    body.dark-theme .stats-row {
        border-color: #444;
    }

    body.dark-theme .text-muted {
        color: #aaa !important;
    }

    body.dark-theme .vehicle-price {
        color: #5dade2;
    }

    body.dark-theme .vehicle-title {
        color: #ffffff;
    }

    body.dark-theme .related-card {
        background: #2c2c3e;
    }

    body.dark-theme .thumbnail-panel {
        background: #1a3a4a;
    }

    body.dark-theme .detail-container {
        background: transparent;
    }

    body.dark-theme .navbar {
        background: #0a0a15 !important;
        border-bottom: 1px solid #1a1a2e;
    }

    body.dark-theme .vehicle-title {
        color: #ffffff !important;
    }

    body.dark-theme .breadcrumb-item.active {
        color: #ffffff !important;
    }

    /* ========== NAVBAR CORREGIDO ========== */
    .navbar {
        overflow: visible !important;
        z-index: 1030;
    }

    .navbar>.container {
        overflow: visible !important;
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .navbar-left {
        display: flex;
        align-items: center;
        margin-left: 40px;
        padding: 8px 0;
        position: relative;
    }

    .navbar-brand {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        font-weight: 700;
        font-size: 1.3rem;
        text-decoration: none;
        padding: 0;
        margin: 0;
        height: auto;
    }

    .navbar-logo-wrapper {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        z-index: 1040;
        pointer-events: auto;
        padding: 6px;
        margin: 2px 0;
    }

    .navbar-logo-wrapper img {
        height: 50px;
        width: auto;
        margin-top: -5px;
        display: block;
        transition: transform 0.3s ease, filter 0.3s ease;
    }

    .navbar-logo-wrapper img:hover {
        transform: scale(1.08);
        filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.5));
    }

    .navbar-brand-text {
        margin-left: 100px;
        white-space: nowrap;
        color: #ffffff;
        position: relative;
        z-index: 1040;
    }

    body.light-theme .navbar-brand-text {
        color: #1a1a2e;
    }

    body.dark-theme .navbar-brand-text {
        color: #ffffff;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .navbar-collapse {
        flex-grow: 0;
    }

    /* ========== ÍCONO HAMBURGUESA (Negro en claro, Blanco en oscuro) ========== */
    .navbar-toggler {
        border: none;
        outline: none;
        transition: all 0.3s ease;
        background: transparent;
    }

    body.light-theme .navbar-toggler {
        color: #000000 !important;
    }

    body.light-theme .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(0, 0, 0, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    body.dark-theme .navbar-toggler {
        color: #ffffff !important;
    }

    body.dark-theme .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    .navbar-toggler:focus {
        box-shadow: none;
        outline: none;
    }

    .dropdown-menu-scroll {
        max-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
        scrollbar-width: thin;
        scrollbar-color: #3498db #f0f0f0;
    }

    .dropdown-menu-scroll::-webkit-scrollbar {
        width: 6px;
    }

    .dropdown-menu-scroll::-webkit-scrollbar-track {
        background: #f0f0f0;
        border-radius: 3px;
    }

    .dropdown-menu-scroll::-webkit-scrollbar-thumb {
        background: #3498db;
        border-radius: 3px;
    }

    body.dark-theme .dropdown-menu-scroll::-webkit-scrollbar-track {
        background: #2c2c3e;
    }

    body.dark-theme .dropdown-menu-scroll::-webkit-scrollbar-thumb {
        background: #5dade2;
    }

    .btn-register {
        padding: 8px 18px;
        border-radius: 30px;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 2px solid;
    }

    body.light-theme .btn-register {
        border-color: #1a5276;
        color: #1a5276;
        background: transparent;
    }

    body.light-theme .btn-register:hover {
        background: #1a5276;
        color: #ffffff;
    }

    body.dark-theme .btn-register {
        border-color: #ffffff;
        color: #ffffff;
        background: transparent;
    }

    body.dark-theme .btn-register:hover {
        background: #ffffff;
        color: #1a1a2e;
    }

    /* ========== BOTÓN DE TEMA FLOTANTE (igual que index.php) ========== */
    .theme-toggle-floating {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 9999;
        background: none;
        border: none;
        font-size: 1.3rem;
        cursor: pointer;
        padding: 12px;
        border-radius: 50%;
        transition: all 0.3s ease;
        width: 48px;
        height: 48px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
    }
    
    body.dark-theme .theme-toggle-floating {
        background-color: #2c2c3e;
        color: #ffffff;
        border: 1px solid rgba(255,255,255,0.2);
    }
    
    body.dark-theme .theme-toggle-floating:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.1);
    }
    
    body.light-theme .theme-toggle-floating {
        background-color: #ffffff;
        color: #1a1a2e !important;
        border: 1px solid rgba(0,0,0,0.1);
    }
    
    body.light-theme .theme-toggle-floating i {
        color: #1a1a2e !important;
    }
    
    body.light-theme .theme-toggle-floating:hover {
        background: rgba(0,0,0,0.1);
        transform: scale(1.1);
    }
    
    .theme-toggle-floating::before {
        content: attr(title);
        position: absolute;
        bottom: -30px;
        left: 50%;
        transform: translateX(-50%);
        background: #333;
        color: white;
        font-size: 0.7rem;
        padding: 4px 8px;
        border-radius: 4px;
        white-space: nowrap;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        pointer-events: none;
        z-index: 100;
    }
    
    .theme-toggle-floating:hover::before {
        opacity: 1;
        visibility: visible;
    }
    
    body.light-theme .theme-toggle-floating::before {
        background: #666;
    }

    .nav-link {
        color: rgba(255, 255, 255, 0.9) !important;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .nav-link:hover {
        color: #3498db !important;
    }

    .detail-container {
        max-width: 1400px;
        margin: 20px auto;
        padding: 0 20px;
    }

    .breadcrumb {
        background: transparent;
        padding: 10px 0;
        margin-bottom: 15px;
        font-size: 0.85rem;
    }

    .breadcrumb-item a {
        color: #3498db;
        text-decoration: none;
    }

    .row-custom {
        display: flex;
        gap: 25px;
        flex-wrap: wrap;
        align-items: stretch;
    }

    .col-gallery {
        flex: 1.2;
        min-width: 300px;
        display: flex;
        flex-direction: column;
    }

    .col-info {
        flex: 0.8;
        min-width: 320px;
        display: flex;
        flex-direction: column;
    }

    .gallery-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 15px;
        height: 100%;
        display: flex;
        flex-direction: column;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    }

    body.dark-theme .gallery-card {
        background: #2c2c3e;
    }

    .main-image-container {
        position: relative;
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        background: #f8f9fa;
    }

    .main-image {
        width: 100%;
        height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .main-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .gallery-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 0 10px;
        pointer-events: none;
        z-index: 10;
    }

    .gallery-nav button {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        pointer-events: auto;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    body.light-theme .gallery-nav button {
        background: rgba(0, 0, 0, 0.6);
        color: white;
    }

    body.light-theme .gallery-nav button:hover {
        background: black;
        transform: scale(1.1);
    }

    body.dark-theme .gallery-nav button {
        background: rgba(255, 255, 255, 0.8);
        color: #1a1a2e;
    }

    body.dark-theme .gallery-nav button:hover {
        background: white;
        transform: scale(1.1);
    }

    .thumbnail-panel {
        background: #e0f0f8;
        border-radius: 12px;
        padding: 12px;
        margin-top: 15px;
    }

    body.dark-theme .thumbnail-panel {
        background: #1a3a4a;
    }

    .thumbnail-container {
        display: flex;
        gap: 10px;
        overflow-x: auto;
        padding-bottom: 5px;
    }

    .thumbnail {
        width: 80px;
        height: 80px;
        border-radius: 10px;
        overflow: hidden;
        cursor: pointer;
        border: 3px solid transparent;
        opacity: 0.7;
        flex-shrink: 0;
        transition: all 0.3s ease;
        background: #ffffff;
    }

    .thumbnail.active {
        border-color: #3498db;
        opacity: 1;
    }

    .thumbnail img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .info-card {
        background: #ffffff;
        border-radius: 16px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    body.dark-theme .info-card {
        background: #2c2c3e;
    }

    .vehicle-title {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 10px 0 5px;
    }

    .vehicle-price {
        font-size: 1.6rem;
        font-weight: 800;
        color: #3498db;
        margin: 5px 0;
    }

    .vehicle-price small {
        font-size: 0.7rem;
        color: #666;
    }

    .stats-row {
        display: flex;
        gap: 20px;
        padding: 8px 0;
        border-top: 1px solid #dee2e6;
        border-bottom: 1px solid #dee2e6;
        margin: 8px 0;
        font-size: 0.8rem;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .stat-item i {
        font-size: 0.85rem;
        color: #3498db;
        cursor: pointer;
    }

    .section-title {
        font-size: 0.95rem;
        font-weight: 700;
        margin: 15px 0 10px;
        padding-bottom: 5px;
        border-bottom: 2px solid #3498db;
        display: inline-block;
    }

    /* ESTILOS PARA PANELES BLANCOS CON SOMBRA Y HOVER - CORREGIDO */
    .tables-horizontal-container {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 25px;
        margin: 30px 0;
        align-items: stretch;
    }

    .white-panel {
        background: #ffffff;
        border-radius: 16px;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        display: flex;
        flex-direction: column;
        height: 100%;
    }

    body.dark-theme .white-panel {
        background: #2c2c3e;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .white-panel:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
    }

    .panel-title {
        padding: 12px 16px 8px 16px;
        font-weight: 700;
        font-size: 0.9rem;
        color: #2c3e50;
        border-bottom: 1px solid #e9ecef;
        flex-shrink: 0;
    }

    body.dark-theme .panel-title {
        color: #ecf0f1;
        border-bottom-color: #3a3a4e;
    }

    .panel-title i {
        margin-right: 8px;
        color: #3498db;
    }

    /* Bloque azul de la tabla (contenido) - no crece */
    .blue-table-wrapper {
        background: #e0f0f8;
        padding: 10px 12px;
        margin: 12px;
        border-radius: 12px;
        flex-shrink: 0;
    }

    body.dark-theme .blue-table-wrapper {
        background: #1a3a4a;
    }

    .compact-table {
        width: 100%;
        border-collapse: collapse;
    }

    .compact-table tr {
        border-bottom: 1px solid #c8dde8;
    }

    .compact-table tr:last-child {
        border-bottom: none;
    }

    .compact-table td {
        padding: 8px 6px;
        font-size: 0.8rem;
        vertical-align: top;
    }

    .compact-table td:first-child {
        font-weight: 600;
        width: 45%;
        color: #2c3e50;
    }

    .compact-table td:last-child {
        color: #1a1a2e;
    }

    body.dark-theme .compact-table tr {
        border-bottom-color: #2c5a6e;
    }

    body.dark-theme .compact-table td:first-child {
        color: #b0d4f0;
    }

    body.dark-theme .compact-table td:last-child {
        color: #f0f0f0;
    }

    /* Publicidad debajo de la tabla: ocupa toda la altura restante */
    .ad-placeholder-below {
        background: linear-gradient(135deg, #f9f9fc, #eef2f7);
        border-radius: 12px;
        margin: 0 12px 12px 12px;
        text-align: center;
        font-size: 0.75rem;
        color: #7f8c8d;
        border: 1px dashed #bdc3c7;
        transition: all 0.2s ease;
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        min-height: 80px;
    }

    body.dark-theme .ad-placeholder-below {
        background: #1e2a3a;
        border-color: #5dade2;
        color: #a0c4e0;
    }

    .ad-placeholder-below i {
        font-size: 1.5rem;
        display: block;
        margin-bottom: 8px;
        color: #3498db;
    }

    @media (max-width: 992px) {
        .tables-horizontal-container {
            grid-template-columns: 1fr;
            gap: 20px;
        }
    }

    .banner-placeholder {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 16px;
        padding: 30px 20px;
        text-align: center;
        margin: 20px 0;
        color: white;
        font-weight: bold;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .banner-placeholder p {
        margin: 0;
        font-size: 1.1rem;
    }
    .banner-placeholder i {
        font-size: 2rem;
        margin-bottom: 10px;
        display: block;
    }

    .description-text {
        font-size: 0.85rem;
        line-height: 1.5;
        text-align: justify;
        background: #ffffff;
        padding: 12px;
        border-radius: 12px;
        max-height: 180px;
        overflow-y: auto;
        border: 1px solid #e0e0e0;
    }

    body.dark-theme .description-text {
        background: #1e2a3a;
        border-color: #444;
    }

    .seller-section {
        margin-top: 20px;
    }

    .seller-panel-white {
        background: #ffffff;
        border-radius: 12px;
        padding: 15px;
    }

    body.dark-theme .seller-panel-white {
        background: #2c2c3e;
    }

    .seller-card-blue {
        background: #e0f0f8;
        border-radius: 12px;
        padding: 15px;
        display: flex;
        align-items: center;
        gap: 15px;
        flex-wrap: wrap;
    }

    body.dark-theme .seller-card-blue {
        background: #1a3a4a;
    }

    .seller-avatar-large {
        width: 70px;
        height: 70px;
        border-radius: 12px;
        overflow: hidden;
        background: linear-gradient(135deg, #1a5276, #3498db);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .seller-avatar-large img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .seller-avatar-large i {
        font-size: 2.2rem;
        color: white;
    }

    .seller-info-large {
        flex: 1;
    }

    .seller-info-large h4 {
        font-size: 0.95rem;
        margin-bottom: 5px;
    }

    .seller-info-large p {
        margin-bottom: 3px;
        font-size: 0.75rem;
    }

    .seller-info-large i {
        width: 20px;
        color: #3498db;
    }

    .seller-buttons-large {
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }

    .btn-contact {
        padding: 8px 16px;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.75rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-width: 100px;
        cursor: pointer;
        border: none;
    }

    .btn-whatsapp {
        background: #25D366;
        color: white;
    }

    .btn-whatsapp:hover {
        background: #128C7E;
        transform: translateY(-2px);
    }

    .btn-email {
        background: #2980b9;
        color: white;
    }

    .btn-email:hover {
        background: #1a5276;
        transform: translateY(-2px);
    }

    .badge-elite,
    .badge-premium {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 0.65rem;
        font-weight: 600;
    }

    .badge-elite {
        background: #f9ca24;
        color: #1a1a2e;
    }

    .badge-premium {
        background: #2980b9;
        color: white;
    }

    .no-image-fallback {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #2c3e50, #3498db);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-direction: column;
        color: white;
    }

    .related-section {
        margin-top: 40px;
    }

    .related-scroll {
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        scrollbar-width: thin;
        padding-bottom: 10px;
    }

    .related-grid {
        display: inline-flex;
        gap: 20px;
        white-space: normal;
    }

    .related-card {
        width: 260px;
        background: #fff;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        display: inline-block;
        white-space: normal;
    }

    body.dark-theme .related-card {
        background: #2c2c3e;
    }

    .related-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    }

    .related-image {
        height: 160px;
        position: relative;
        overflow: hidden;
        background: #f0f0f0;
    }

    .related-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .no-image-fallback-small {
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #2c3e50, #3498db);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
    }

    .related-badge {
        position: absolute;
        top: 8px;
        right: 8px;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.6rem;
        font-weight: 600;
    }

    .related-info {
        padding: 12px;
    }

    .related-title {
        font-size: 0.85rem;
        font-weight: 700;
        margin-bottom: 5px;
        white-space: normal;
    }

    .related-price {
        font-size: 0.9rem;
        font-weight: 700;
        color: #3498db;
    }

    .btn-outline-azul {
        background: transparent;
        border: 1px solid #3498db;
        color: #3498db;
        padding: 5px 12px;
        border-radius: 20px;
        text-decoration: none;
        display: inline-block;
        text-align: center;
        font-size: 0.7rem;
        transition: all 0.3s ease;
    }

    .btn-outline-azul:hover {
        background: #3498db;
        color: white;
    }

    .image-modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.95);
        z-index: 9999;
    }

    .modal-content-custom {
        position: relative;
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-image {
        max-width: 90%;
        max-height: 90%;
        object-fit: contain;
    }

    .close-modal {
        position: absolute;
        top: 20px;
        right: 40px;
        color: white;
        font-size: 3rem;
        cursor: pointer;
    }

    .modal-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding: 0 30px;
        pointer-events: none;
        z-index: 10000;
    }

    .modal-nav button {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: rgba(0, 0, 0, 0.6);
        border: none;
        color: white;
        cursor: pointer;
        pointer-events: auto;
        font-size: 1.5rem;
        transition: all 0.3s ease;
    }

    .modal-nav button:hover {
        background: #3498db;
        transform: scale(1.1);
    }

    .footer {
        background-color: #0a0a15;
        padding: 40px 0 20px;
        margin-top: 60px;
    }

    .footer p,
    .footer li,
    .footer a,
    .footer h5 {
        color: white;
    }

    .footer a {
        text-decoration: none;
        opacity: 0.8;
    }

    .footer a:hover {
        opacity: 1;
        color: #3498db;
    }

    .footer-brand {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
    }

    .footer-brand img {
        height: 50px;
    }

    .footer ul {
        list-style: none;
        padding: 0;
    }

    .footer-bottom {
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    @media (max-width: 992px) {
        .row-custom {
            flex-direction: column;
        }

        .main-image {
            height: 350px;
        }

        .related-card {
            width: 240px;
        }
    }

    @media (max-width: 768px) {
        .navbar-brand-text {
            margin-left: 65px;
            font-size: 0.9rem;
        }

        .navbar-logo-wrapper img {
            height: 55px;
        }

        .navbar-left {
            margin-left: 10px;
        }

        .seller-card-blue {
            flex-direction: column;
            text-align: center;
        }

        .seller-buttons-large {
            justify-content: center;
        }

        .related-card {
            width: 220px;
        }

        .thumbnail {
            width: 60px;
            height: 60px;
        }
    }

    .votes-detalle {
        display: flex;
        gap: 20px;
        margin: 15px 0;
        padding: 10px 0;
        border-top: 1px solid #dee2e6;
        border-bottom: 1px solid #dee2e6;
    }

    .vote-detalle-btn {
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 8px 15px;
        border-radius: 30px;
        transition: all 0.3s ease;
        font-size: 1rem;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .vote-detalle-btn i {
        font-size: 1.2rem;
    }

    .vote-detalle-btn.up-vote:hover {
        background: rgba(46, 204, 113, 0.2);
        color: #27ae60;
    }

    .vote-detalle-btn.down-vote:hover {
        background: rgba(231, 76, 60, 0.2);
        color: #e74c3c;
    }

    .vote-detalle-btn.heart-vote:hover {
        background: rgba(231, 76, 60, 0.2);
        color: #e74c3c;
    }

    .vote-detalle-btn.active-up {
        color: #27ae60;
    }

    .vote-detalle-btn.active-down {
        color: #e74c3c;
    }

    .vote-detalle-btn.active-heart {
        color: #e74c3c;
    }

    .vote-detalle-count {
        font-weight: 600;
        margin-left: 5px;
    }

    .offer-section {
        margin-top: 20px;
        padding: 15px;
        background: rgba(52, 152, 219, 0.05);
        border-radius: 12px;
    }

    .offer-input-group {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .offer-input-group .form-control {
        flex: 1;
        min-width: 150px;
    }

    .btn-send-offer {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 30px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-send-offer:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(46, 204, 113, 0.3);
    }

    .comments-section {
        margin-top: 40px;
    }

    /* Estilos compactos para comentarios */
    .comment-item {
        background: var(--bg-secondary);
        border-radius: 8px;
        padding: 10px 12px;
        margin-bottom: 8px;
        border: 1px solid var(--border-color);
        transition: all 0.2s ease;
    }

    .comment-item:hover {
        background: rgba(52, 152, 219, 0.05);
    }

    .comment-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 6px;
        font-size: 0.85rem;
    }

    .comment-author {
        font-weight: 600;
        font-size: 0.85rem;
    }

    .comment-author i {
        font-size: 0.75rem;
        margin-right: 4px;
        color: #3498db;
    }

    .comment-date {
        font-size: 0.7rem;
        color: #999;
    }

    .comment-content {
        font-size: 0.8rem;
        line-height: 1.4;
        margin-bottom: 6px;
        color: #555;
    }

    body.dark-theme .comment-content {
        color: #ccc;
    }

    .comment-response {
        background: rgba(40, 167, 69, 0.08);
        border-radius: 6px;
        padding: 8px 10px;
        margin-top: 6px;
        border-left: 3px solid #28a745;
        font-size: 0.75rem;
    }

    .comment-response strong {
        font-size: 0.7rem;
    }

    .comment-form {
        margin-top: 20px;
        background: rgba(52, 152, 219, 0.03);
        padding: 15px;
        border-radius: 12px;
    }

    body.light-theme .comment-item {
        background: #f8f9fa;
        border-color: #e9ecef;
    }

    body.dark-theme .comment-item {
        background: #2c2c3e;
        border-color: #3a3a4e;
    }

    /* Estilos de paginación */
    .comments-pagination {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
        padding-top: 10px;
        border-top: 1px solid #dee2e6;
        flex-wrap: wrap;
        gap: 10px;
    }

    .pagination-controls {
        display: flex;
        gap: 5px;
        align-items: center;
        flex-wrap: wrap;
    }

    .pagination-btn {
        padding: 5px 10px;
        border: 1px solid #dee2e6;
        background: transparent;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 0.75rem;
    }

    .pagination-btn:hover {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    .pagination-btn.active {
        background: #3498db;
        color: white;
        border-color: #3498db;
    }

    .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .per-page-selector {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 0.75rem;
    }

    .per-page-selector select {
        padding: 4px 8px;
        border-radius: 5px;
        border: 1px solid #dee2e6;
        background: transparent;
        font-size: 0.75rem;
    }

    .comments-info {
        font-size: 0.7rem;
        color: #999;
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 2px solid #f3f3f3;
        border-top: 2px solid #3498db;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }
        100% {
            transform: rotate(360deg);
        }
    }
    </style>
</head>

<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">

    <!-- NAVBAR CORREGIDO - Logo izquierda, hamburguesa derecha -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <div class="navbar-left">
                <a class="navbar-brand" href="/">
                    <span class="navbar-logo-wrapper">
                        <?php if ($tema === 'dark'): ?>
                            <img src="/assets/imagenes/logos/colcars_b.png" alt="Colcars Logo">
                        <?php else: ?>
                            <img src="/assets/imagenes/logos/logo_d.png" alt="Easy Car Luxury Logo">
                        <?php endif; ?>
                    </span>
                    <span class="navbar-brand-text"></span>
                </a>
            </div>

            <div class="navbar-right">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Inicio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/catalog">Catálogo</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/contacto">Contacto</a>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center ms-2">
                        <?php if (isset($_SESSION['usuario_id'])): ?>
                        <a class="nav-link" href="../../dashboard/admin/index.php">
                            <i class="fas fa-user-circle"></i> Mi Cuenta
                        </a>
                        <a class="nav-link" href="/logout">
                            <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                        </a>
                        <?php else: ?>
                        <a class="nav-link" href="/login">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                        <a class="btn-register ms-2" href="/register">
                            <i class="fas fa-user-plus"></i> Registrarse
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="detail-container">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/">Inicio</a></li>
                    <li class="breadcrumb-item"><a href="/catalog">Catálogo</a></li>
                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($vehicle['titulo']); ?></li>
                </ol>
            </nav>

            <div class="row-custom">
                <div class="col-gallery">
                    <div class="gallery-card">
                        <div class="main-image-container" id="mainImageContainer">
                            <div class="main-image">
                                <?php if (!empty($images[0]['image_path'])): ?>
                                <img id="mainImage" src="<?php echo $images[0]['image_path']; ?>"
                                    alt="<?php echo htmlspecialchars($vehicle['titulo']); ?>"
                                    onerror="this.style.display='none'; this.parentNode.querySelector('.no-image-fallback').style.display='flex';">
                                <div class="no-image-fallback" style="display: none;">
                                    <i class="fas fa-car fa-4x"></i>
                                    <p><?php echo htmlspecialchars($vehicle['titulo']); ?></p>
                                </div>
                                <?php else: ?>
                                <div class="no-image-fallback">
                                    <i class="fas fa-car fa-4x"></i>
                                    <p><?php echo htmlspecialchars($vehicle['titulo']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if (count($images) > 1): ?>
                            <div class="gallery-nav">
                                <button id="prevBtn"><i class="fas fa-chevron-left"></i></button>
                                <button id="nextBtn"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php if (count($images) > 1): ?>
                        <div class="thumbnail-panel">
                            <div class="thumbnail-container" id="thumbnailContainer">
                                <?php foreach ($images as $index => $img): ?>
                                <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>"
                                    data-index="<?php echo $index; ?>">
                                    <img src="<?php echo $img['image_path']; ?>" alt="Miniatura"
                                        onerror="this.src='https://placehold.co/80x80/3498db/white?text=No'">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="seller-section">
                        <div class="seller-panel-white">
                            <div class="seller-card-blue">
                                <div class="seller-avatar-large">
                                    <?php if ($vehicleImage): ?>
                                    <img src="<?php echo $vehicleImage; ?>" alt="Vehículo"
                                        onerror="this.style.display='none'; this.parentNode.querySelector('i').style.display='flex';">
                                    <i style="display: none;" class="fas fa-car"></i>
                                    <?php else: ?>
                                    <i class="fas fa-car"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="seller-info-large">
                                    <h4><i class="fas fa-user-circle"></i>
                                        <?php echo htmlspecialchars($vehicle['seller_name']); ?></h4>
                                    <p><i class="fas fa-envelope"></i>
                                        <?php echo htmlspecialchars($vehicle['seller_email']); ?></p>
                                    <?php if ($vehicle['seller_phone']): ?>
                                    <p><i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($vehicle['seller_phone']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($vehicle['membership_tier'] === 'elite'): ?>
                                    <span class="badge-elite"><i class="fas fa-crown"></i> Vendedor Elite</span>
                                    <?php elseif ($vehicle['membership_tier'] === 'premium'): ?>
                                    <span class="badge-premium"><i class="fas fa-gem"></i> Vendedor Premium</span>
                                    <?php endif; ?>
                                </div>
                                <div class="seller-buttons-large">
                                    <a href="mailto:<?php echo $vehicle['seller_email']; ?>"
                                        class="btn-contact btn-email">
                                        <i class="fas fa-envelope"></i> Email
                                    </a>
                                    <?php if ($vehicle['seller_phone']): ?>
                                    <a href="https://wa.me/57<?php echo preg_replace('/[^0-9]/', '', $vehicle['seller_phone']); ?>?text=Hola%2C%20estoy%20interesado%20en%20<?php echo urlencode($vehicle['titulo']); ?>"
                                        class="btn-contact btn-whatsapp" target="_blank">
                                        <i class="fab fa-whatsapp"></i> WhatsApp
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-info">
                    <div class="info-card">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted"><i class="fas fa-calendar-alt"></i>
                                <?php echo date('d/m/Y', strtotime($vehicle['created_at'])); ?></small>
                        </div>

                        <h1 class="vehicle-title"><?php echo htmlspecialchars($vehicle['titulo']); ?></h1>

                        <div class="vehicle-price">
                            $ <?php echo number_format($vehicle['precio'], 0, ',', '.'); ?> <small>COP</small>
                        </div>

                        <div class="stats-row">
                            <div class="stat-item"><i class="fas fa-eye"></i>
                                <?php echo number_format($vehicle['visitas'] ?? 0); ?> visitas</div>
                            <div class="stat-item">
                                <i class="fas fa-heart like-icon" id="likeIcon"
                                    style="<?php echo $liked ? 'color: #e74c3c;' : ''; ?>"></i>
                                <span id="likeCount"><?php echo $likesCount; ?></span>
                            </div>
                            <div class="stat-item">
                                <i class="fas fa-share-alt share-icon" id="shareBtn"></i> Compartir
                            </div>
                        </div>

                        <div class="votes-detalle">
                            <button class="vote-detalle-btn up-vote" data-type="up" id="voteUpBtn">
                                <i class="fas fa-thumbs-up"></i> <span class="vote-detalle-count" id="upCount">0</span>
                            </button>
                            <button class="vote-detalle-btn down-vote" data-type="down" id="voteDownBtn">
                                <i class="fas fa-thumbs-down"></i> <span class="vote-detalle-count"
                                    id="downCount">0</span>
                            </button>
                            <button class="vote-detalle-btn heart-vote" data-type="heart" id="voteHeartBtn">
                                <i class="fas fa-heart"></i> <span class="vote-detalle-count"
                                    id="heartCount"><?php echo $likesCount; ?></span>
                            </button>
                        </div>

                        <div class="section-title">Descripción</div>
                        <p class="description-text">
                            <?php echo nl2br(htmlspecialchars($vehicle['descripcion'] ?? 'Sin descripción')); ?></p>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- TABLAS HORIZONTALES CON PANEL BLANCO, SOMBRA, HOVER Y PUBLICIDAD QUE OCUPA TODO EL ALTO -->
            <!-- ============================================ -->
            <div class="tables-horizontal-container">
                <!-- 1. Datos de Identificación del Vehículo -->
                <div class="white-panel">
                    <div class="panel-title">
                        <i class="fas fa-id-card"></i> 1. Datos de Identificación del Vehículo
                    </div>
                    <div class="blue-table-wrapper">
                        <table class="compact-table">
                            <tr><td>Marca</td>
                            <td><?php echo htmlspecialchars($vehicle['brand'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Línea o modelo comercial</td>
                            <td><?php echo htmlspecialchars($vehicle['linea_modelo_comercial'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Clase de vehículo</td>
                            <td><?php echo htmlspecialchars($vehicle['clase_vehiculo'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Tipo de carrocería</td>
                            <td><?php echo htmlspecialchars($vehicle['tipo_carroceria'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Modelo (Año de fabricación)</td>
                            <td><?php echo htmlspecialchars($vehicle['year_fabricacion'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Color</td>
                            <td><?php echo htmlspecialchars($vehicle['color'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Cilindrada (cc)</td>
                            <td><?php echo htmlspecialchars($vehicle['cilindrada'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Potencia (HP)</td>
                            <td><?php echo htmlspecialchars($vehicle['potencia_hp'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Combustible</td>
                            <td><?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Capacidad</td>
                            <td><?php echo htmlspecialchars($vehicle['capacidad'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Blindaje</td>
                            <td><?php echo htmlspecialchars($vehicle['blindaje'] ?? 'No especificado'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- 2. Números Únicos de Serie (con espacio publicitario que ocupa toda la altura restante) -->
                <div class="white-panel">
                    <div class="panel-title">
                        <i class="fas fa-barcode"></i> 2. Números Únicos de Serie
                    </div>
                    <div class="blue-table-wrapper">
                        <table class="compact-table">
                            <tr><td>Número de Motor</td>
                            <td><?php echo htmlspecialchars($vehicle['numero_motor'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Número de Chasis</td>
                            <td><?php echo htmlspecialchars($vehicle['numero_chasis'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Número de Serie o VIN</td>
                            <td><?php echo htmlspecialchars($vehicle['numero_vin'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                    <!-- Publicidad que se expande verticalmente -->
                    <?php if ($video_activo): ?>
                        <div class="video-publicidad-container" style="margin: 0 12px 12px 12px;">
                            <?php if (!empty($video_activo['archivo_url'])): ?>
                                <video id="publicidadVideo" controls style="width: 100%; border-radius: 12px; max-height: 400px;">
                                    <source src="<?php echo htmlspecialchars($video_activo['archivo_url']); ?>" type="video/mp4">
                                    Tu navegador no soporta videos.
                                </video>
                                <div class="text-center mt-2">
                                    <button onclick="togglePublicidadFullscreen()" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-expand"></i> Pantalla completa
                                    </button>
                                </div>
                            <?php elseif (!empty($video_activo['video_embed'])): ?>
                                <div class="video-embed-container">
                                    <iframe src="<?php echo htmlspecialchars($video_activo['video_embed']); ?>" 
                                            style="width: 100%; height: 280px; border-radius: 12px;" 
                                            frameborder="0" 
                                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                            allowfullscreen>
                                    </iframe>
                                </div>
                            <?php else: ?>
                                <div class="ad-placeholder-below">
                                    <i class="fas fa-ad"></i>
                                    <span>Espacio publicitario disponible</span>
                                    <small>Contáctanos para anunciarte aquí</small>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($video_activo['link_url'])): ?>
                                <div class="text-center mt-2">
                                    <a href="<?php echo htmlspecialchars($video_activo['link_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt"></i> Más información
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($video_activo['descripcion'])): ?>
                                <div class="text-center mt-1">
                                    <small class="text-muted"><?php echo htmlspecialchars($video_activo['descripcion']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="ad-placeholder-below">
                            <i class="fas fa-ad"></i>
                            <span>Espacio publicitario disponible</span>
                            <small>Contáctanos para anunciarte aquí</small>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 3. Datos Legales, Administrativos y de Propiedad -->
                <div class="white-panel">
                    <div class="panel-title">
                        <i class="fas fa-gavel"></i> 3. Datos Legales, Administrativos y de Propiedad
                    </div>
                    <div class="blue-table-wrapper">
                        <table class="compact-table">
                            <tr><td>Servicio</td>
                            <td><?php echo htmlspecialchars($vehicle['servicio'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Origen</td>
                            <td><?php echo htmlspecialchars($vehicle['origen'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Propietario (Nombre completo)</td>
                            <td><?php echo htmlspecialchars($vehicle['propietario_nombre'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Propietario (Tipo documento)</td>
                            <td><?php echo htmlspecialchars($vehicle['propietario_tipo_documento'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Propietario (Número documento)</td>
                            <td><?php echo htmlspecialchars($vehicle['propietario_numero_documento'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Empresa Vinculadora (Nombre)</td>
                            <td><?php echo htmlspecialchars($vehicle['empresa_vinculadora_nombre'] ?? 'N/A'); ?></td>
                            </tr>
                            <tr><td>Empresa Vinculadora (NIT)</td>
                            <td><?php echo htmlspecialchars($vehicle['empresa_vinculadora_nit'] ?? 'N/A'); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- BANNER PUBLICITARIO (placeholder principal) -->
            <?php if ($banner_activo && !empty($banner_activo['archivo_url'])): ?>
                <?php if (!empty($banner_activo['link_url'])): ?>
                <a href="<?php echo htmlspecialchars($banner_activo['link_url']); ?>" target="_blank" class="banner-link">
                <?php endif; ?>
                    <div class="banner-placeholder" style="background: none; padding: 0; overflow: hidden;">
                        <img src="<?php echo htmlspecialchars($banner_activo['archivo_url']); ?>" 
                             alt="<?php echo htmlspecialchars($banner_activo['titulo']); ?>"
                             style="width: 100%; border-radius: 16px; display: block;">
                    </div>
                <?php if (!empty($banner_activo['link_url'])): ?>
                </a>
                <?php endif; ?>
            <?php else: ?>
                <div class="banner-placeholder">
                    <i class="fas fa-ad"></i>
                    <p>Espacio para publicidad</p>
                    <small>Contenido patrocinado</small>
                </div>
            <?php endif; ?>

            <div class="offer-section">
                <h5 class="section-title" style="font-size: 0.9rem;"><i class="fas fa-gavel"></i> Haz una oferta</h5>
                <div class="offer-input-group">
                    <input type="text" id="offerPhone" class="form-control" placeholder="Celular / WhatsApp *" required>
                    <input type="number" id="offerAmount" class="form-control" placeholder="Monto de la oferta *" required>
                    <textarea id="offerMessage" class="form-control" rows="2" placeholder="Mensaje (opcional)"></textarea>
                    <button id="sendOfferBtn" class="btn-send-offer"><i class="fas fa-paper-plane"></i> Enviar oferta</button>
                </div>
                <div id="offerResultMsg" class="mt-2 small" style="display: none;"></div>
            </div>

            <?php if (!empty($related)): ?>
            <div class="related-section">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="section-title" style="font-size: 1rem;"><i class="fas fa-car"></i> Vehículos Relacionados</h3>
                    <a href="/catalog" class="btn-outline-azul">Ver todos <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="related-scroll">
                    <div class="related-grid">
                        <?php foreach ($related as $rel): ?>
                        <div class="related-card">
                            <div class="related-image">
                                <?php if (!empty($rel['primary_image'])): ?>
                                <img src="<?php echo $rel['primary_image']; ?>" alt="<?php echo htmlspecialchars($rel['titulo']); ?>"
                                    onerror="this.style.display='none'; this.parentNode.querySelector('.no-image-fallback-small').style.display='flex';">
                                <div class="no-image-fallback-small" style="display: none;"><i class="fas fa-car fa-2x"></i></div>
                                <?php else: ?>
                                <div class="no-image-fallback-small"><i class="fas fa-car fa-2x"></i></div>
                                <?php endif; ?>
                                <?php if ($rel['seller_tier'] === 'elite'): ?>
                                <div class="related-badge" style="background: #f9ca24; color: #1a1a2e;"><i class="fas fa-crown"></i> Elite</div>
                                <?php elseif ($rel['seller_tier'] === 'premium'): ?>
                                <div class="related-badge" style="background: #2980b9; color: white;"><i class="fas fa-gem"></i> Premium</div>
                                <?php endif; ?>
                            </div>
                            <div class="related-info">
                                <h4 class="related-title"><?php echo htmlspecialchars($rel['titulo']); ?></h4>
                                <div class="related-price">$ <?php echo number_format($rel['precio'], 0, ',', '.'); ?></div>
                                <div class="mt-2">
                                    <a href="/vehicle/<?php echo $rel['id']; ?>/<?php echo urlencode(str_replace(' ', '-', strtolower($rel['titulo']))); ?>"
                                        class="btn-outline-azul w-100 d-block">Ver detalles</a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- SECCIÓN DE COMENTARIOS CON PAGINACIÓN -->
            <div class="comments-section">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="section-title" style="font-size: 0.9rem; margin: 0;"><i class="fas fa-comments"></i> Comentarios y Preguntas</h5>
                    <span class="comments-info" id="commentsInfo"><?php echo $totalComments; ?> comentario(s)</span>
                </div>

                <div id="commentsList" class="mb-3">
                    <div class="text-center py-3" id="commentsLoading">
                        <div class="loading-spinner"></div> Cargando comentarios...
                    </div>
                </div>

                <div class="comments-pagination">
                    <div class="per-page-selector">
                        <span>Mostrar:</span>
                        <select id="perPageSelect">
                            <option value="10">10</option>
                            <option value="25" selected>25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                        <span>comentarios</span>
                    </div>
                    <div class="pagination-controls" id="paginationControls"></div>
                </div>

                <div class="comment-form">
                    <h6><i class="fas fa-pen-alt"></i> Deja tu comentario o pregunta</h6>
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-md-6 mb-2">
                                <input type="text" id="commentName" class="form-control" placeholder="Tu nombre"
                                    value="<?php echo htmlspecialchars($current_user_name ?: 'Visitante'); ?>">
                            </div>
                            <div class="col-md-6 mb-2">
                                <input type="email" id="commentEmail" class="form-control" placeholder="Tu email (opcional)"
                                    value="<?php echo htmlspecialchars($current_user_email); ?>">
                            </div>
                        </div>
                        <textarea id="commentText" class="form-control" rows="3" placeholder="Escribe tu comentario o pregunta..."></textarea>
                    </div>
                    <button id="submitCommentBtn" class="btn btn-primary"
                        style="background: linear-gradient(135deg, #3498db, #2980b9); border: none; padding: 10px 25px; border-radius: 30px;">
                        <i class="fas fa-paper-plane"></i> Enviar comentario
                    </button>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="footer-brand">
                        <?php if ($tema === 'dark'): ?>
                            <img src="/assets/imagenes/logos/colcars_b.png" alt="Colcars Logo">
                        <?php else: ?>
                            <img src="/assets/imagenes/logos/logo_d.png" alt="Easy Car Luxury">
                        <?php endif; ?>
                        <span>Easy Car Luxury</span>
                    </div>
                    <p>La plataforma líder en compra y venta de vehículos de lujo en Colombia.</p>
                </div>
                <div class="col-md-2">
                    <h5>Enlaces</h5>
                    <ul>
                        <li><a href="/">Inicio</a></li>
                        <li><a href="/catalog">Catálogo</a></li>
                        <li><a href="/contacto">Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Legal</h5>
                    <ul>
                        <li><a href="#">Términos y condiciones</a></li>
                        <li><a href="#">Política de privacidad</a></li>
                        <li><a href="#">Política de cookies</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Horario</h5>
                    <p>Lunes a Viernes: 8:00 - 20:00<br>Sábados: 9:00 - 14:00</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Easy Car Luxury. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- BOTÓN DE TEMA FLOTANTE (esquina inferior derecha) -->
    <button class="theme-toggle-floating" id="themeToggleFloating" title="<?php echo $tema === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'; ?>">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <div id="imageModal" class="image-modal">
        <div class="modal-content-custom">
            <span class="close-modal">&times;</span>
            <div class="modal-nav">
                <button id="modalPrevBtn"><i class="fas fa-chevron-left"></i></button>
                <button id="modalNextBtn"><i class="fas fa-chevron-right"></i></button>
            </div>
            <img id="modalImage" class="modal-image" src="" alt="Imagen ampliada">
        </div>
    </div>

    <!-- jQuery CDN -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // ==========================================
    // VARIABLES GLOBALES
    // ==========================================
    const pubId = <?php echo $id; ?>;
    const isLoggedIn = <?php echo isset($_SESSION['usuario_id']) ? 'true' : 'false'; ?>;
    const baseUrl = ''; // Vacío porque DocumentRoot ya está en la raíz del proyecto
    let allComments = [];
    let currentPage = 1;
    let perPage = 25;

    // ==========================================
    // CONTROLADOR DEL BOTÓN DE TEMA FLOTANTE
    // ==========================================
    (function() {
        const themeToggle = document.getElementById('themeToggleFloating');
        const icon = themeToggle.querySelector('i');
        
        function setTheme(theme) {
            if (theme === 'dark') {
                document.body.classList.remove('light-theme');
                document.body.classList.add('dark-theme');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                themeToggle.title = 'Cambiar a modo claro';
                document.cookie = "theme=dark; path=/; max-age=" + (365 * 24 * 60 * 60);
                // Cambiar logos a colcars_b.png
                const navbarLogo = document.querySelector('.navbar-logo-wrapper img');
                const footerLogo = document.querySelector('.footer-brand img');
                if (navbarLogo) navbarLogo.src = '/assets/imagenes/logos/colcars_b.png';
                if (footerLogo) footerLogo.src = '/assets/imagenes/logos/colcars_b.png';
            } else {
                document.body.classList.remove('dark-theme');
                document.body.classList.add('light-theme');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                themeToggle.title = 'Cambiar a modo oscuro';
                document.cookie = "theme=light; path=/; max-age=" + (365 * 24 * 60 * 60);
                // Cambiar logos a logo_d.png
                const navbarLogo = document.querySelector('.navbar-logo-wrapper img');
                const footerLogo = document.querySelector('.footer-brand img');
                if (navbarLogo) navbarLogo.src = '/assets/imagenes/logos/logo_d.png';
                if (footerLogo) footerLogo.src = '/assets/imagenes/logos/logo_d.png';
            }
        }
        
        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const isDark = document.body.classList.contains('dark-theme');
                setTheme(isDark ? 'light' : 'dark');
            });
        }
    })();

    // ==========================================
    // VISOR DE IMÁGENES CORREGIDO
    // ==========================================
    const imagesRaw = <?php echo json_encode(array_column($images, 'image_path')); ?>;
    const images = imagesRaw.filter(function(img) {
        return img !== null && img !== '';
    });
    let currentIndex = 0;

    function setImage(index) {
        if (images.length === 0) {
            return;
        }
        currentIndex = (index + images.length) % images.length;
        const mainImg = document.getElementById('mainImage');
        if (mainImg) {
            mainImg.src = images[currentIndex];
            mainImg.style.display = 'block';
            const fallback = mainImg.parentNode.querySelector('.no-image-fallback');
            if (fallback) {
                fallback.style.display = 'none';
            }
        }
        const thumbs = document.querySelectorAll('.thumbnail');
        for (var i = 0; i < thumbs.length; i++) {
            if (i === currentIndex) {
                thumbs[i].classList.add('active');
            } else {
                thumbs[i].classList.remove('active');
            }
        }
    }

    if (images.length > 0) {
        setImage(0);
    }

    const thumbs = document.querySelectorAll('.thumbnail');
    for (var i = 0; i < thumbs.length; i++) {
        thumbs[i].addEventListener('click', function() {
            var index = parseInt(this.getAttribute('data-index'));
            setImage(index);
        });
    }

    const prevBtn = document.getElementById('prevBtn');
    if (prevBtn) {
        prevBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (images.length > 0) {
                setImage(currentIndex - 1);
            }
        });
    }

    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (images.length > 0) {
                setImage(currentIndex + 1);
            }
        });
    }

    // ==========================================
    // MODAL DE IMÁGENES CORREGIDO
    // ==========================================
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    let modalIndex = 0;

    const mainImageContainer = document.getElementById('mainImageContainer');
    if (mainImageContainer) {
        mainImageContainer.addEventListener('click', function() {
            if (images.length > 0) {
                modalIndex = currentIndex;
                modalImg.src = images[modalIndex];
                modal.style.display = 'block';
            }
        });
    }

    const closeModal = document.querySelector('.close-modal');
    if (closeModal) {
        closeModal.addEventListener('click', function() {
            modal.style.display = 'none';
        });
    }

    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
            }
        });
    }

    const modalPrevBtn = document.getElementById('modalPrevBtn');
    if (modalPrevBtn) {
        modalPrevBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (images.length > 0) {
                modalIndex = (modalIndex - 1 + images.length) % images.length;
                modalImg.src = images[modalIndex];
            }
        });
    }

    const modalNextBtn = document.getElementById('modalNextBtn');
    if (modalNextBtn) {
        modalNextBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (images.length > 0) {
                modalIndex = (modalIndex + 1) % images.length;
                modalImg.src = images[modalIndex];
            }
        });
    }

    // ==========================================
    // LIKE
    // ==========================================
    const likeIcon = document.getElementById('likeIcon');
    if (likeIcon) {
        likeIcon.addEventListener('click', function() {
            if (!isLoggedIn) {
                Swal.fire({
                    icon: 'info',
                    title: 'Inicia sesión',
                    text: 'Debes iniciar sesión para dar like',
                    confirmButtonText: 'Iniciar sesión'
                }).then(function() {
                    window.location.href = '/login';
                });
                return;
            }
            fetch('/api/v1/public.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle_like', publication_id: pubId })
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.liked) {
                        likeIcon.style.color = '#e74c3c';
                    } else {
                        likeIcon.style.color = '';
                    }
                    document.getElementById('likeCount').textContent = data.count;
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
            });
        });
    }

    // ==========================================
    // COMPARTIR
    // ==========================================
    const shareBtn = document.getElementById('shareBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            navigator.clipboard.writeText(window.location.href);
            Swal.fire({
                icon: 'success',
                title: 'Enlace copiado',
                text: 'El enlace ha sido copiado al portapapeles',
                timer: 2000,
                showConfirmButton: false
            });
        });
    }

    // ==========================================
    // VOTOS (UP, DOWN, HEART)
    // ==========================================
    function loadVotesDetalle(pubId) {
        fetch('/api/v1/interactions.php?action=get_votes&publication_id=' + pubId)
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('upCount').textContent = data.counts.up;
                    document.getElementById('downCount').textContent = data.counts.down;
                    document.getElementById('heartCount').textContent = data.counts.heart;
                    if (data.user_votes && data.user_votes.indexOf('up') !== -1) {
                        document.getElementById('voteUpBtn').classList.add('active-up');
                    }
                    if (data.user_votes && data.user_votes.indexOf('down') !== -1) {
                        document.getElementById('voteDownBtn').classList.add('active-down');
                    }
                    if (data.user_votes && data.user_votes.indexOf('heart') !== -1) {
                        document.getElementById('voteHeartBtn').classList.add('active-heart');
                    }
                }
            })
            .catch(function(error) {
                console.error('Error loading votes:', error);
            });
    }

    function handleVoteDetalle(pubId, type) {
        if (!isLoggedIn) {
            Swal.fire({
                icon: 'info',
                title: 'Inicia sesión',
                text: 'Debes iniciar sesión para votar',
                confirmButtonText: 'Iniciar sesión'
            }).then(function() {
                window.location.href = '/login';
            });
            return;
        }
        fetch('/api/v1/interactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'vote', publication_id: pubId, vote_type: type })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                document.getElementById('upCount').textContent = data.counts.up;
                document.getElementById('downCount').textContent = data.counts.down;
                document.getElementById('heartCount').textContent = data.counts.heart;
                var btns = document.querySelectorAll('.vote-detalle-btn');
                for (var i = 0; i < btns.length; i++) {
                    btns[i].classList.remove('active-up', 'active-down', 'active-heart');
                }
                if (data.user_votes && data.user_votes.indexOf('up') !== -1) {
                    document.getElementById('voteUpBtn').classList.add('active-up');
                }
                if (data.user_votes && data.user_votes.indexOf('down') !== -1) {
                    document.getElementById('voteDownBtn').classList.add('active-down');
                }
                if (data.user_votes && data.user_votes.indexOf('heart') !== -1) {
                    document.getElementById('voteHeartBtn').classList.add('active-heart');
                }
            } else if (data.redirect) {
                window.location.href = '/login';
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.error || 'Error al registrar voto'
                });
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
        });
    }

    // ==========================================
    // ENVIAR OFERTA
    // ==========================================
    function sendOfferDetalle() {
        var phone = document.getElementById('offerPhone').value.trim();
        var amount = document.getElementById('offerAmount').value.trim();
        var message = document.getElementById('offerMessage').value.trim();
        if (!phone) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Ingresa tu número de teléfono' });
            return;
        }
        if (!amount || parseFloat(amount) <= 0) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Ingresa un monto válido' });
            return;
        }
        var btn = document.getElementById('sendOfferBtn');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Enviando...';
        btn.disabled = true;
        var commentName = document.getElementById('commentName');
        var buyerName = (commentName ? commentName.value : 'Visitante');
        fetch('/api/v1/interactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'make_offer',
                publication_id: pubId,
                buyer_name: buyerName,
                buyer_phone: phone,
                amount: parseFloat(amount),
                message: message || 'Sin mensaje adicional'
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Oferta enviada!',
                    text: 'El vendedor se pondrá en contacto contigo',
                    timer: 3000,
                    showConfirmButton: false
                });
                document.getElementById('offerPhone').value = '';
                document.getElementById('offerAmount').value = '';
                document.getElementById('offerMessage').value = '';
            } else if (data.redirect) {
                window.location.href = '/login';
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al enviar la oferta' });
            }
        })
        .catch(function(error) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión al enviar la oferta' });
        });
    }

    // ==========================================
    // COMENTARIOS - VERSIÓN CORREGIDA
    // ==========================================

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function displayCommentsPaginated() {
        var commentsContainer = document.getElementById('commentsList');
        if (!commentsContainer) return;
        
        var start = (currentPage - 1) * perPage;
        var end = start + perPage;
        var paginatedComments = allComments.slice(start, end);
        var totalPages = Math.ceil(allComments.length / perPage);
        
        if (paginatedComments.length > 0) {
            var html = '';
            for (var i = 0; i < paginatedComments.length; i++) {
                var comment = paginatedComments[i];
                var displayName = comment.user_name || comment.nombre || 'Anónimo';
                var commentText = comment.comentario || '';
                var fecha = new Date(comment.created_at);
                var fechaFormateada = fecha.toLocaleDateString('es-CO', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric', 
                    hour: '2-digit', 
                    minute: '2-digit' 
                });
                
                html += '<div class="comment-item">';
                html += '<div class="comment-header">';
                html += '<span class="comment-author"><i class="fas fa-user-circle"></i> ' + escapeHtml(displayName) + '</span>';
                html += '<span class="comment-date">' + fechaFormateada + '</span>';
                html += '</div>';
                html += '<div class="comment-content">' + escapeHtml(commentText).replace(/\n/g, '<br>') + '</div>';
                if (comment.respuesta) {
                    html += '<div class="comment-response"><strong><i class="fas fa-reply"></i> Respuesta:</strong><br>' + escapeHtml(comment.respuesta).replace(/\n/g, '<br>') + '</div>';
                }
                html += '</div>';
            }
            commentsContainer.innerHTML = html;
        } else {
            commentsContainer.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-comments fa-2x mb-2"></i><p>No hay comentarios aún. ¡Sé el primero en comentar!</p></div>';
        }
        
        updatePaginationControls(totalPages);
        var commentsInfo = document.getElementById('commentsInfo');
        if (commentsInfo) {
            commentsInfo.textContent = allComments.length + ' comentario(s)';
        }
    }

    function updatePaginationControls(totalPages) {
        var controlsContainer = document.getElementById('paginationControls');
        if (!controlsContainer) return;
        if (totalPages <= 1) {
            controlsContainer.innerHTML = '';
            return;
        }
        var html = '';
        html += '<button class="pagination-btn" onclick="goToPage(' + (currentPage - 1) + ')"' + (currentPage === 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';
        var startPage = Math.max(1, currentPage - 2);
        var endPage = Math.min(totalPages, currentPage + 2);
        if (startPage > 1) {
            html += '<button class="pagination-btn" onclick="goToPage(1)">1</button>';
            if (startPage > 2) html += '<span class="px-1">...</span>';
        }
        for (var i = startPage; i <= endPage; i++) {
            html += '<button class="pagination-btn ' + (i === currentPage ? 'active' : '') + '" onclick="goToPage(' + i + ')">' + i + '</button>';
        }
        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += '<span class="px-1">...</span>';
            html += '<button class="pagination-btn" onclick="goToPage(' + totalPages + ')">' + totalPages + '</button>';
        }
        html += '<button class="pagination-btn" onclick="goToPage(' + (currentPage + 1) + ')"' + (currentPage === totalPages ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
        controlsContainer.innerHTML = html;
    }

    function goToPage(page) {
        var totalPages = Math.ceil(allComments.length / perPage);
        if (page < 1 || page > totalPages) return;
        currentPage = page;
        displayCommentsPaginated();
        var commentsSection = document.querySelector('.comments-section');
        if (commentsSection) {
            commentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function loadComments(pubId) {
        var commentsContainer = document.getElementById('commentsList');
        if (!commentsContainer) return;
        
        commentsContainer.innerHTML = '<div class="text-center py-3"><div class="loading-spinner"></div> Cargando comentarios...</div>';
        var url = '/api/v1/interactions.php?action=get_comments&publication_id=' + pubId;
        
        fetch(url)
            .then(function(res) {
                if (!res.ok) {
                    throw new Error('HTTP error: ' + res.status);
                }
                return res.json();
            })
            .then(function(data) {
                if (data.success && data.comments) {
                    allComments = data.comments;
                    currentPage = 1;
                    displayCommentsPaginated();
                } else {
                    allComments = [];
                    displayCommentsPaginated();
                    if (data.error) {
                        console.error('Error de API:', data.error);
                    }
                }
            })
            .catch(function(error) {
                console.error('Error loading comments:', error);
                commentsContainer.innerHTML = '<div class="text-center py-3 text-danger"><i class="fas fa-exclamation-triangle"></i> Error al cargar comentarios: ' + error.message + '</div>';
            });
    }

    function sendComment(pubId) {
        var name = document.getElementById('commentName').value.trim();
        var email = document.getElementById('commentEmail').value.trim();
        var comment = document.getElementById('commentText').value.trim();
        
        if (!comment) {
            Swal.fire({ icon: 'warning', title: 'Campo vacío', text: 'Por favor escribe tu comentario' });
            return;
        }
        if (!name) {
            Swal.fire({ icon: 'warning', title: 'Campo requerido', text: 'Por favor ingresa tu nombre' });
            return;
        }
        
        var btn = document.getElementById('submitCommentBtn');
        var originalText = btn.innerHTML;
        btn.innerHTML = '<span class="loading-spinner"></span> Enviando...';
        btn.disabled = true;
        
        fetch('/api/v1/interactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'send_comment',
                publication_id: pubId,
                nombre: name,
                email: email,
                comentario: comment
            })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            if (data.success) {
                document.getElementById('commentText').value = '';
                loadComments(pubId);
                Swal.fire({
                    icon: 'success',
                    title: '¡Comentario enviado!',
                    text: 'Tu comentario ha sido publicado correctamente',
                    timer: 2000,
                    showConfirmButton: false
                });
            } else if (data.redirect) {
                window.location.href = '/login';
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: data.error || 'Error al enviar comentario' });
            }
        })
        .catch(function(error) {
            btn.innerHTML = originalText;
            btn.disabled = false;
            console.error('Error sending comment:', error);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error de conexión al enviar comentario' });
        });
    }

    // Función para pantalla completa del video publicitario
    function togglePublicidadFullscreen() {
        const video = document.getElementById('publicidadVideo');
        if (video) {
            if (video.requestFullscreen) {
                video.requestFullscreen();
            } else if (video.webkitRequestFullscreen) {
                video.webkitRequestFullscreen();
            } else if (video.msRequestFullscreen) {
                video.msRequestFullscreen();
            }
        }
    }

    // ==========================================
    // CORRECCIÓN MENÚ HAMBURGUESA
    // ==========================================
    (function() {
        if (typeof bootstrap !== 'undefined') {
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.getElementById('navbarNav');
            
            if (navbarToggler && navbarCollapse) {
                navbarToggler.addEventListener('click', function() {
                    setTimeout(function() {
                        if (navbarCollapse.classList.contains('show')) {
                            navbarCollapse.style.display = 'block';
                        } else {
                            navbarCollapse.style.display = '';
                        }
                    }, 10);
                });
                
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.attributeName === 'class') {
                            if (navbarCollapse.classList.contains('show')) {
                                navbarCollapse.style.display = 'block';
                            } else {
                                navbarCollapse.style.display = '';
                            }
                        }
                    });
                });
                observer.observe(navbarCollapse, { attributes: true });
            }
        }
    })();

    // ==========================================
    // EVENT LISTENERS
    // ==========================================
    document.addEventListener('DOMContentLoaded', function() {
        loadVotesDetalle(pubId);
        loadComments(pubId);
        
        var voteUpBtn = document.getElementById('voteUpBtn');
        if (voteUpBtn) {
            voteUpBtn.addEventListener('click', function() {
                handleVoteDetalle(pubId, 'up');
            });
        }
        
        var voteDownBtn = document.getElementById('voteDownBtn');
        if (voteDownBtn) {
            voteDownBtn.addEventListener('click', function() {
                handleVoteDetalle(pubId, 'down');
            });
        }
        
        var voteHeartBtn = document.getElementById('voteHeartBtn');
        if (voteHeartBtn) {
            voteHeartBtn.addEventListener('click', function() {
                handleVoteDetalle(pubId, 'heart');
            });
        }
        
        var sendOfferBtn = document.getElementById('sendOfferBtn');
        if (sendOfferBtn) {
            sendOfferBtn.addEventListener('click', sendOfferDetalle);
        }
        
        var submitCommentBtn = document.getElementById('submitCommentBtn');
        if (submitCommentBtn) {
            submitCommentBtn.addEventListener('click', function() {
                sendComment(pubId);
            });
        }
        
        var commentText = document.getElementById('commentText');
        if (commentText) {
            commentText.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    e.preventDefault();
                    sendComment(pubId);
                }
            });
        }
        
        var perPageSelect = document.getElementById('perPageSelect');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function(e) {
                perPage = parseInt(e.target.value);
                currentPage = 1;
                displayCommentsPaginated();
            });
        }
    });

    window.goToPage = goToPage;
    window.togglePublicidadFullscreen = togglePublicidadFullscreen;
    </script>
</body>
</html>