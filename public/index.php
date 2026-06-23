<?php
    /**
 * C:\ServidorWeb\htdocs\easycarluxury\public\index.php
 * MODIFICADO: Formulario de contacto ahora guarda en base de datos
 * Se agregó endpoint API para recibir mensajes de contacto
 * MODIFICADO: Efectos hover en menú principal - aumento de tamaño y fondo según tema
 * MODIFICADO: Efectos hover en botones de usuario (Iniciar Sesión, Registrarse, Dropdown)
 */

    // Iniciar sesión si no está iniciada
    if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }

    require_once '../config/database.php';
    $database = Database::getInstance();
    $pdo      = $database->getConnection();

    require_once '../includes/functions.php';

    // ==========================================
    // ENDPOINT PARA GUARDAR MENSAJES DE CONTACTO
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');

    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'send_contact') {
        $nombre_completo = trim($_POST['nombre_completo'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $telefono        = trim($_POST['telefono'] ?? '');
        $whatsapp        = trim($_POST['whatsapp'] ?? '');
        $mensaje         = trim($_POST['mensaje'] ?? '');

        $errors = [];

        if (empty($nombre_completo)) {
            $errors[] = 'El nombre completo es obligatorio';
        }

        if (empty($email)) {
            $errors[] = 'El correo electrónico es obligatorio';
        } elseif (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico no es válido';
        }

        if (empty($telefono)) {
            $errors[] = 'El teléfono es obligatorio';
        }

        if (empty($mensaje)) {
            $errors[] = 'El mensaje es obligatorio';
        }

        if (empty($whatsapp)) {
            $whatsapp = null;
        }

        if (count($errors) > 0) {
            echo json_encode([
                'success' => false,
                'errors'  => $errors,
            ]);
            exit;
        }

        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $sql = "INSERT INTO contact_messages (nombre_completo, email, telefono, whatsapp, mensaje, ip_address, user_agent, status, created_at)
                VALUES (:nombre_completo, :email, :telefono, :whatsapp, :mensaje, :ip_address, :user_agent, 'pendiente', NOW())";

        $stmt = $pdo->prepare($sql);

        $result = $stmt->execute([
            ':nombre_completo' => $nombre_completo,
            ':email'           => $email,
            ':telefono'        => $telefono,
            ':whatsapp'        => $whatsapp,
            ':mensaje'         => $mensaje,
            ':ip_address'      => $ip_address,
            ':user_agent'      => $user_agent,
        ]);

        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Mensaje enviado correctamente. Te contactaremos pronto.',
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'errors'  => ['Error al guardar el mensaje. Por favor intenta de nuevo.'],
            ]);
        }
        exit;
    }
    }

    // Variables de sesión
    $isLoggedIn = isset($_SESSION['usuario_id']);
    $userName   = $_SESSION['nombre_completo'] ?? '';

    // ==========================================
    // DETECTAR SI HAY BÚSQUEDA ACTIVA
    // ==========================================
    $searchQ        = isset($_GET['q']) ? trim($_GET['q']) : '';
    $searchCategory = isset($_GET['category']) ? intval($_GET['category']) : 0;
    $searchMinPrice = isset($_GET['min_price']) ? floatval($_GET['min_price']) : 0;
    $searchMaxPrice = isset($_GET['max_price']) ? floatval($_GET['max_price']) : 0;

    $hasSearch = (! empty($searchQ) || $searchCategory > 0 || $searchMinPrice > 0 || $searchMaxPrice > 0);

    // Variables para resultados de búsqueda
    $searchResults   = [];
    $searchQueryText = '';

    // Si hay búsqueda, obtener resultados
    if ($hasSearch) {
    // Construir la consulta de búsqueda - SOLO POR MARCA (el category se ignora en la búsqueda pero el parámetro se mantiene por compatibilidad)
    $searchSql = "SELECT p.*, u.nombre_completo as seller_name, u.tipo_cuenta as membership_tier, u.activo,
                    (SELECT COUNT(*) FROM favorites WHERE publication_id = p.id) as likes_count,
                    (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                    FROM publicaciones p
                    JOIN usuarios u ON p.usuario_id = u.id
                    WHERE p.status = 'active' AND u.activo = 1";

    $searchParams = [];

    if (! empty($searchQ)) {
        // BÚSQUEDA SOLO POR MARCA (campo 'brand')
        $searchSql               .= " AND p.brand LIKE :search";
        $searchParams[':search']  = "%{$searchQ}%";
        $searchQueryText          = $searchQ;
    }

    // El filtro de categoría se ignora visualmente pero el código sigue aquí (no se elimina)
    if ($searchCategory > 0) {
        // Este filtro ya no se usa en la búsqueda pero el código se mantiene
        // $searchSql .= " AND p.categoria_id = :category";
        // $searchParams[':category'] = $searchCategory;
    }

    if ($searchMinPrice > 0) {
        $searchSql                  .= " AND p.precio >= :min_price";
        $searchParams[':min_price']  = $searchMinPrice;
        $searchQueryText            .= ($searchQueryText ? ', ' : '') . "Desde $" . number_format($searchMinPrice, 0);
    }

    if ($searchMaxPrice > 0) {
        $searchSql                  .= " AND p.precio <= :max_price";
        $searchParams[':max_price']  = $searchMaxPrice;
        $searchQueryText            .= ($searchQueryText ? ', ' : '') . "Hasta $" . number_format($searchMaxPrice, 0);
    }

    $searchSql .= " ORDER BY p.created_at DESC";

    $searchStmt  = $pdo->prepare($searchSql);
    $searchStmt->execute($searchParams);
    $searchResults  = $searchStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // MODIFICADO: añadir u.activo = 1
    $featuredQuery = "SELECT p.*, u.nombre_completo as seller_name, u.tipo_cuenta as membership_tier, u.activo,
                    (SELECT COUNT(*) FROM favorites WHERE publication_id = p.id) as likes_count,
                    (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                    FROM publicaciones p
                    JOIN usuarios u ON p.usuario_id = u.id
                    WHERE p.status = 'active'
                    AND u.tipo_cuenta IN ('premium', 'elite')
                    AND u.activo = 1
                    ORDER BY p.created_at DESC
                    LIMIT 12";
    $featured = $pdo->query($featuredQuery)->fetchAll(PDO::FETCH_ASSOC);

    // MODIFICADO: añadir u.activo = 1
    $recentQuery = "SELECT p.*, u.nombre_completo as seller_name, u.activo,
                (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
                FROM publicaciones p
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.status = 'active'
                AND u.activo = 1
                ORDER BY p.created_at DESC
                LIMIT 12";
    $recent = $pdo->query($recentQuery)->fetchAll(PDO::FETCH_ASSOC);

    $statsQuery = "SELECT
                (SELECT COUNT(*) FROM publicaciones WHERE status = 'active') as total_cars,
                (SELECT COUNT(*) FROM usuarios WHERE activo = 1 AND rol_id = 6) as total_sellers,
                (SELECT COUNT(*) FROM publicaciones WHERE status = 'active' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_this_week";
    $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

    $categories = $pdo->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);

    $adsQuery = "SELECT * FROM advertisements WHERE status = 'active'
                AND start_date <= CURDATE() AND end_date >= CURDATE()
                AND position IN ('home_top', 'home_bottom')
                ORDER BY created_at DESC";
    $ads = $pdo->query($adsQuery)->fetchAll(PDO::FETCH_ASSOC);

    // ============================================
    // CONSULTAR PLANES DE MEMBRESÍA DESDE LA BASE DE DATOS
    // ============================================
    $membershipsQuery = "SELECT * FROM memberships WHERE active = 1 AND name NOT IN ('sistema', 'administracion') ORDER BY sort_order ASC, price ASC";
    $memberships      = $pdo->query($membershipsQuery)->fetchAll(PDO::FETCH_ASSOC);

    // Función para parsear la descripción y convertirla en lista de características
    function parseFeatures($description)
    {
    if (empty($description)) {
        return [];
    }
    $lines    = explode("\n", trim($description));
    $features = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if (! empty($line)) {
            $features[] = $line;
        }
    }
    return $features;
    }

    $tema = 'light';
    if (isset($_COOKIE['theme'])) {
    $tema = $_COOKIE['theme'];
    }

    try {
    $updateRolls = "UPDATE imagenes_publicaciones SET image_path = 'https://images.unsplash.com/photo-1632245889029-40640de4ded3?w=800&h=500&fit=crop' WHERE publicacion_id = 6 AND is_primary = 1";
    $pdo->exec($updateRolls);
    $updateMcLaren = "UPDATE imagenes_publicaciones SET image_path = 'https://images.unsplash.com/photo-1580417658859-7b9e6f40ce1e?w=800&h=500&fit=crop' WHERE publicacion_id = 7 AND is_primary = 1";
    $pdo->exec($updateMcLaren);
    } catch (Exception $e) {
    }

    // Obtener todos los veículos para sugerencias (solo si hay búsqueda activa)
    $allVehicles = [];
    if ($hasSearch) {
    $allVehiclesQuery = "SELECT p.id, p.titulo, p.precio, c.nombre as categoria
                        FROM publicaciones p
                        LEFT JOIN categorias c ON p.categoria_id = c.id
                        JOIN usuarios u ON p.usuario_id = u.id
                        WHERE p.status = 'active' AND u.activo = 1
                        ORDER BY p.titulo ASC
                        LIMIT 50";
    $allVehicles = $pdo->query($allVehiclesQuery)->fetchAll(PDO::FETCH_ASSOC);
    }
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo $hasSearch ? 'Resultados de búsqueda | Colcars' : 'Colcars - Compra y Venta de Vehículos de Lujo'; ?>
    </title>
    <meta name="description"
        content="La plataforma líder en compra y venta de vehículos de lujo en Colombia. Encuentra los mejores autos de alta gama, con miembros verificados y garantía de calidad.">
    <meta name="keywords" content="autos de lujo, venta de carros, compra de vehiculos, carros premium, Colombia">

    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">

    <!-- CSS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/catalog.css">
    <link rel="stylesheet" href="/assets/css/dark-theme.css">
    <link rel="stylesheet" href="/assets/css/light-theme.css">







    <style>

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    :root {
        --azul-primary: #1a5276;
        --azul-primary-dark: #0e3a5c;
        --azul-gradient: linear-gradient(135deg, #1a5276, #2980b9, #3498db);
        --azul-gradient-hover: linear-gradient(135deg, #0e3a5c, #1a5276, #2471a3);
    }

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

    .navbar-nav {
        margin-right: 0;
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

    /* ============================================
           ESTILOS PARA EL BOTÓN DE TEMA FLOTANTE
           (NUEVO - POSICIÓN INFERIOR DERECHA)
           CORREGIDO: Color del icono en modo claro: negro (#1a1a2e)
           ============================================ */
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
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
    }

    body.dark-theme .theme-toggle-floating {
        background-color: #2c2c3e;
        color: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    body.dark-theme .theme-toggle-floating:hover {
        background: rgba(255, 255, 255, 0.2);
        transform: scale(1.1);
    }

    body.light-theme .theme-toggle-floating {
        background-color: #ffffff;
        color: #1a1a2e !important;
        border: 1px solid rgba(0, 0, 0, 0.1);
    }

    body.light-theme .theme-toggle-floating i {
        color: #1a1a2e !important;
    }

    body.light-theme .theme-toggle-floating:hover {
        background: rgba(0, 0, 0, 0.1);
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

    /* ============================================
           ESTILOS ÍCONO HAMBURGUESA
           Negro en modo claro (#000000) | Blanco en modo oscuro (#ffffff)
           ============================================ */
    .navbar-toggler {
        border: none;
        outline: none;
        transition: all 0.3s ease;
    }

    body.light-theme .navbar-toggler {
        color: #000000 !important;
        background: transparent;
    }

    body.light-theme .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(0, 0, 0, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    body.dark-theme .navbar-toggler {
        color: #ffffff !important;
        background: transparent;
    }

    body.dark-theme .navbar-toggler-icon {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
    }

    .navbar-toggler:focus {
        box-shadow: none;
        outline: none;
    }

    /* ============================================
           EFECTOS DE MENÚ PRINCIPAL - HOVER
           Aumento de tamaño y fondo según tema
           ============================================ */
    .navbar-nav .nav-item .nav-link {
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        border-radius: 8px;
        padding: 8px 16px;
        margin: 0 2px;
    }

    .navbar-nav .nav-item .nav-link:hover {
        transform: scale(1.08);
    }

    /* Tema claro - fondo azul claro */
    body.light-theme .navbar-nav .nav-item .nav-link:hover {
        background-color: #e3f2fd;
        color: #1a5276 !important;
    }

    /* Tema oscuro - fondo gris oscuro */
    body.dark-theme .navbar-nav .nav-item .nav-link:hover {
        background-color: #3a3a4e;
        color: #ffffff !important;
    }

    /* Asegurar que el texto del menú en modo claro tenga color adecuado */
    body.light-theme .navbar-nav .nav-link {
        color: #1a1a2e !important;
    }

    /* Asegurar que el texto del menú en modo oscuro tenga color adecuado */
    body.dark-theme .navbar-nav .nav-link {
        color: #ffffff !important;
    }

    /* ============================================
           EFECTOS PARA BOTONES DE USUARIO (login/register)
           Y PARA DROPDOWN DEL USUARIO LOGUEADO
           ============================================ */

    /* Efectos para el enlace "Iniciar Sesión" */
    .navbar .nav-link[href="/login"] {
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        border-radius: 8px;
        padding: 8px 16px;
    }

    .navbar .nav-link[href="/login"]:hover {
        transform: scale(1.08);
    }

    body.light-theme .navbar .nav-link[href="/login"]:hover {
        background-color: #e3f2fd;
        color: #1a5276 !important;
    }

    body.dark-theme .navbar .nav-link[href="/login"]:hover {
        background-color: #3a3a4e;
        color: #ffffff !important;
    }

    /* Efectos para el botón "Registrarse" */
    .navbar .btn-register {
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94) !important;
        border-radius: 30px !important;
    }

    .navbar .btn-register:hover {
        transform: scale(1.08) !important;
    }

    /* Efectos para el dropdown-toggle del usuario logueado */
    .user-dropdown .dropdown-toggle {
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        border-radius: 40px;
    }

    .user-dropdown .dropdown-toggle:hover {
        transform: scale(1.05);
    }

    /* Efectos para los items del dropdown (Mi panel, Mi perfil, Cerrar sesión) */
    .user-dropdown .dropdown-item {
        transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        border-radius: 8px;
    }

    .user-dropdown .dropdown-item:hover {
        transform: translateX(5px);
    }

    body.light-theme .user-dropdown .dropdown-item:hover {
        background-color: #e3f2fd;
        color: #1a5276 !important;
    }

    body.dark-theme .user-dropdown .dropdown-item:hover {
        background-color: #3a3a4e;
        color: #ffffff !important;
    }

    .hero-fullscreen {
        position: relative;
        height: 100vh;
        width: 100%;
        overflow: hidden;
    }

    .hero-carousel {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 0;
    }

    .hero-carousel .carousel,
    .hero-carousel .carousel-inner,
    .hero-carousel .carousel-item {
        height: 100%;
        width: 100%;
    }

    .hero-carousel .carousel-item img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .hero-content-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        background: rgba(0, 0, 0, 0.55);
        pointer-events: none;
    }

    .hero-content-overlay .container,
    .hero-content-overlay h1,
    .hero-content-overlay p,
    .hero-content-overlay .hero-stats,
    .hero-content-overlay .stat {
        pointer-events: none;
    }

    .hero-content-overlay .search-box,
    .hero-content-overlay .search-box input,
    .hero-content-overlay .search-box select,
    .hero-content-overlay .search-box button,
    .hero-content-overlay .search-box form,
    .hero-content-overlay .search-box .row {
        pointer-events: auto;
    }

    .hero-content-overlay h1 {
        font-size: 3.5rem;
        font-weight: 800;
        margin-bottom: 1rem;
        color: white;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
    }

    .hero-content-overlay p {
        font-size: 1.3rem;
        margin-bottom: 2rem;
        color: white;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
    }

    .hero-content-overlay .hero-stats {
        display: flex;
        justify-content: center;
        gap: 50px;
        margin: 40px 0;
    }

    .hero-content-overlay .hero-stats .stat {
        text-align: center;
        color: white;
    }

    .hero-content-overlay .hero-stats .stat i {
        font-size: 2rem;
        color: #3498db;
    }

    .hero-content-overlay .hero-stats .stat span {
        display: block;
        font-size: 1.8rem;
        font-weight: bold;
    }

    .hero-content-overlay .hero-stats .stat small {
        font-size: 0.9rem;
        opacity: 0.9;
    }

    .hero-content-overlay .search-box {
        background: rgba(0, 0, 0, 0.5);
        backdrop-filter: blur(10px);
        padding: 25px;
        border-radius: 60px;
        margin-top: 40px;
        max-width: 900px;
        margin-left: auto;
        margin-right: auto;
        position: relative;
    }

    .hero-content-overlay .search-box input,
    .hero-content-overlay .search-box select {
        background: white;
        border: none;
        border-radius: 30px;
        padding: 12px 20px;
        height: 50px;
    }

    .hero-content-overlay .search-box input:focus,
    .hero-content-overlay .search-box select:focus {
        outline: none;
        box-shadow: 0 0 0 2px #3498db;
    }

    .hero-content-overlay .btn-search {
        background: var(--azul-gradient);
        color: white;
        border: none;
        border-radius: 30px;
        padding: 12px;
        height: 50px;
        transition: all 0.3s ease;
        font-weight: 600;
    }

    .hero-content-overlay .btn-search:hover {
        background: var(--azul-gradient-hover);
        transform: scale(1.02);
    }

    .hero-carousel .carousel-control-prev,
    .hero-carousel .carousel-control-next {
        z-index: 15;
        width: 8%;
        opacity: 0.7;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .hero-carousel .carousel-control-prev:hover {
        opacity: 1;
        transform: translateX(-8px);
    }

    .hero-carousel .carousel-control-prev:active {
        transform: translateX(-4px) scale(0.95);
    }

    .hero-carousel .carousel-control-next:hover {
        opacity: 1;
        transform: translateX(8px);
    }

    .hero-carousel .carousel-control-next:active {
        transform: translateX(4px) scale(0.95);
    }

    .hero-carousel .carousel-control-prev-icon,
    .hero-carousel .carousel-control-next-icon {
        background-color: rgba(0, 0, 0, 0.5);
        border-radius: 50%;
        padding: 25px;
        background-size: 60%;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
    }

    .hero-carousel .carousel-control-prev:hover .carousel-control-prev-icon {
        background-color: rgba(52, 152, 219, 0.7);
        padding: 28px;
        background-size: 65%;
        box-shadow: 0 0 20px rgba(52, 152, 219, 0.5);
    }

    .hero-carousel .carousel-control-next:hover .carousel-control-next-icon {
        background-color: rgba(52, 152, 219, 0.7);
        padding: 28px;
        background-size: 65%;
        box-shadow: 0 0 20px rgba(52, 152, 219, 0.5);
    }

    .hero-carousel .carousel-indicators {
        z-index: 15;
        bottom: 20px;
    }

    .hero-carousel .carousel-indicators button {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        margin: 0 5px;
        background-color: white;
        opacity: 0.6;
        transition: all 0.3s ease;
    }

    .hero-carousel .carousel-indicators button:hover {
        opacity: 0.9;
        transform: scale(1.2);
    }

    .hero-carousel .carousel-indicators button.active {
        opacity: 1;
        background-color: #3498db;
        transform: scale(1.3);
    }

    .vehicle-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
    }

    .vehicle-card {
        transition: transform 0.3s ease, background 0.3s ease;
    }

    .vehicle-card:hover {
        transform: translateY(-5px);
    }

    .no-image {
        display: flex;
        align-items: center;
        justify-content: center;
        height: 100%;
        background: linear-gradient(135deg, #2c3e50, #3498db);
    }

    .no-image i {
        color: rgba(255, 255, 255, 0.5);
        font-size: 3rem;
    }

    .btn-view {
        background: var(--azul-gradient) !important;
        border: none !important;
        transition: all 0.3s ease;
    }

    .btn-view:hover {
        background: var(--azul-gradient-hover) !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
    }

    .btn-primary {
        background: var(--azul-gradient) !important;
        border: none !important;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: var(--azul-gradient-hover) !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
    }

    .btn-outline {
        border: 1px solid #3498db !important;
        color: #3498db !important;
        transition: all 0.3s ease;
    }

    .btn-outline:hover {
        background: #3498db !important;
        color: white !important;
        transform: translateY(-2px);
        box-shadow: 0 6px 15px rgba(52, 152, 219, 0.3);
    }

    .features-section .feature-icon {
        background: var(--azul-gradient);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .features-section .feature-icon:hover {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
    }

    .features-section .feature-icon i {
        color: #ffffff;
        font-size: 2rem;
    }

    body.light-theme .features-section .feature h3 {
        color: #1a1a2e;
    }

    body.light-theme .features-section .feature p {
        color: #555;
    }

    body.dark-theme .features-section .feature h3 {
        color: #ffffff;
    }

    body.dark-theme .features-section .feature p {
        color: #e0e0e0;
    }

    .plans-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        align-items: stretch;
    }

    .plan-card {
        border-radius: 16px;
        padding: 25px 20px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        position: relative;
        transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        cursor: pointer;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .plan-card:hover {
        transform: translateY(-12px) scale(1.03);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2), 0 0 0 2px rgba(52, 152, 219, 0.3);
    }

    .plan-card.featured {
        border: 2px solid #f9ca24;
        transform: scale(1.03);
    }

    .plan-card.featured:hover {
        transform: translateY(-12px) scale(1.06);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25), 0 0 0 3px rgba(249, 202, 36, 0.5);
    }

    .plan-card .plan-name {
        font-size: 1.2rem;
        font-weight: 700;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .plan-card .plan-price {
        font-size: 1.8rem;
        font-weight: 800;
        color: #3498db;
        margin-bottom: 12px;
    }

    .plan-card .plan-price span {
        font-size: 0.7rem;
        font-weight: 400;
        opacity: 0.7;
    }

    .plan-card .plan-features {
        list-style: none;
        padding: 0;
        margin: 10px 0 15px 0;
        text-align: left;
        flex-grow: 1;
    }

    .plan-card .plan-features li {
        padding: 6px 0;
        font-size: 0.85rem;
        border-bottom: 1px solid rgba(0, 0, 0, 0.06);
    }

    .plan-card .plan-btn {
        display: inline-block;
        padding: 10px 25px;
        border-radius: 30px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.3s ease;
        margin-top: auto;
    }

    .popular-badge {
        position: absolute;
        top: -14px;
        left: 50%;
        transform: translateX(-50%);
        background: #f9ca24;
        color: #1a1a2e;
        padding: 5px 16px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 1px;
        z-index: 2;
        box-shadow: 0 4px 10px rgba(249, 202, 36, 0.4);
    }

    .contact-info-wrapper,
    .contact-form-wrapper {
        background: #ffffff;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        transition: all 0.3s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .contact-info-wrapper:hover,
    .contact-form-wrapper:hover {
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.18);
    }

    body.dark-theme .contact-info-wrapper,
    body.dark-theme .contact-form-wrapper {
        background: #2c2c3e;
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
    }

    body.dark-theme .contact-info-wrapper:hover,
    body.dark-theme .contact-form-wrapper:hover {
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.4);
    }

    .contact-info-wrapper h3 {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 15px;
        border-bottom: 2px solid rgba(52, 152, 219, 0.2);
    }

    .contact-info-wrapper h3 i {
        color: #3498db;
        font-size: 1.5rem;
    }

    body.dark-theme .contact-info-wrapper h3 {
        color: #ffffff;
        border-bottom-color: rgba(52, 152, 219, 0.3);
    }

    body.dark-theme .contact-info-wrapper h3 i {
        color: #5dade2;
    }

    .contact-description {
        color: #666;
        font-size: 0.85rem;
        line-height: 1.5;
        margin-bottom: 15px;
        flex-shrink: 0;
    }

    body.dark-theme .contact-description {
        color: #b0b0b0;
    }

    .contact-info-list {
        list-style: none;
        padding: 0;
        margin: 0;
        flex-shrink: 0;
    }

    .contact-info-list li {
        padding: 8px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #333;
        font-size: 0.9rem;
    }

    .contact-info-list li i {
        color: #2980b9;
        width: 20px;
        text-align: center;
        font-size: 1rem;
    }

    body.dark-theme .contact-info-list li {
        color: #ffffff;
    }

    body.dark-theme .contact-info-list li i {
        color: #3498db;
    }

    .contact-social-links {
        margin-top: auto;
        padding-top: 20px;
        flex-shrink: 0;
    }

    .contact-social-links a {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background: rgba(41, 128, 185, 0.15);
        border-radius: 50%;
        margin-right: 10px;
        text-decoration: none;
        color: #2980b9;
        transition: all 0.3s ease;
    }

    .contact-social-links a:hover {
        background: #2980b9;
        color: #ffffff;
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(41, 128, 185, 0.3);
    }

    body.dark-theme .contact-social-links a {
        background: rgba(52, 152, 219, 0.2);
        color: #3498db;
    }

    body.dark-theme .contact-social-links a:hover {
        background: #3498db;
        color: #ffffff;
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(52, 152, 219, 0.3);
    }

    .contact-form-title {
        font-size: 1.4rem;
        font-weight: 700;
        margin-bottom: 20px;
        color: #1a5276;
        display: flex;
        align-items: center;
        gap: 10px;
        padding-bottom: 15px;
        border-bottom: 2px solid rgba(52, 152, 219, 0.2);
    }

    .contact-form-title i {
        color: #3498db;
        font-size: 1.5rem;
    }

    body.dark-theme .contact-form-title {
        color: #ffffff;
        border-bottom-color: rgba(52, 152, 219, 0.3);
    }

    body.dark-theme .contact-form-title i {
        color: #5dade2;
    }

    .input-group-text {
        background: #f8f9fa;
        border: 1px solid #ced4da;
        color: #3498db;
        min-width: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    body.dark-theme .input-group-text {
        background: #3a3a4e;
        border-color: #4a4a5e;
        color: #5dade2;
    }

    body.dark-theme .form-control {
        background: #3a3a4e;
        border-color: #4a4a5e;
        color: #ffffff;
    }

    body.dark-theme .form-control::placeholder {
        color: #999;
    }

    body.dark-theme .form-control:focus {
        background: #3a3a4e;
        border-color: #3498db;
        color: #ffffff;
    }

    body.light-theme .plan-card {
        background-color: #ffffff;
        color: #333;
    }

    body.light-theme .plan-card .plan-name {
        color: #1a5276;
    }

    body.light-theme .plan-card .plan-features li {
        color: #555;
    }

    body.light-theme .plan-card .plan-price span {
        color: #888;
    }

    body.dark-theme .plan-card {
        background-color: #2c2c3e;
        color: #ffffff;
    }

    body.dark-theme .plan-card .plan-name {
        color: #ffffff;
    }

    body.dark-theme .plan-card .plan-features li {
        color: #b0b0b0;
    }

    body.light-theme .section-header h2 i {
        color: #2980b9 !important;
    }

    body.light-theme .vehicle-meta span i,
    body.light-theme .vehicle-seller i {
        color: #2980b9;
    }

    body.dark-theme .section-header h2 i {
        color: #3498db !important;
    }

    body.dark-theme .vehicle-meta span i,
    body.dark-theme .vehicle-seller i {
        color: #3498db;
    }

    body.dark-theme .vehicle-card {
        background-color: #2c2c3e;
        color: #ffffff;
    }

    body.dark-theme .vehicle-card .vehicle-info h3 {
        color: #ffffff;
    }

    body.dark-theme .contact-section {
        background-color: #0f0f1a;
        color: #ffffff;
    }

    body.light-theme .contact-section {
        background-color: #f5f5f5;
        color: #333;
    }

    .badge-elite {
        background: #f9ca24 !important;
        color: #1a1a2e !important;
    }

    .badge-premium {
        background: #2980b9 !important;
        color: white !important;
    }

    .footer {
        background-color: #0a0a15;
        padding: 40px 0 20px;
    }

    .footer p {
        color: #b0b0b0;
        text-align: left;
    }

    .footer a {
        color: #3498db;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .footer a:hover {
        color: #5dade2;
        text-decoration: underline;
        padding-left: 5px;
    }

    .footer h5 {
        color: #ffffff;
    }

    .footer-brand {
        display: flex;
        align-items: center;
        justify-content: flex-start;
        gap: 12px;
        font-size: 1.5rem;
        font-weight: bold;
        color: #ffffff;
        margin-bottom: 10px;
        padding-left: 0;
    }

    .footer-brand img {
        height: 150px;
        width: auto;
    }

    .footer-bottom {
        text-align: center;
        padding-top: 20px;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        margin-top: 20px;
        color: #b0b0b0;
    }

    /* Estilos para mensajes de alerta del formulario */
    .contact-alert {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        padding: 15px 20px;
        border-radius: 8px;
        display: none;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        animation: slideInRight 0.3s ease;
    }

    .contact-alert.success {
        background-color: #27ae60;
        color: white;
        border-left: 4px solid #1e8449;
    }

    .contact-alert.error {
        background-color: #e74c3c;
        color: white;
        border-left: 4px solid #c0392b;
    }

    .contact-alert i {
        font-size: 1.2rem;
    }

    .contact-alert .close-alert {
        margin-left: auto;
        cursor: pointer;
        font-size: 1.2rem;
        opacity: 0.8;
    }

    .contact-alert .close-alert:hover {
        opacity: 1;
    }

    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }

        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @media (max-width: 1200px) {
        .vehicle-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .plans-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .hero-content-overlay h1 {
            font-size: 2rem;
        }

        .hero-content-overlay p {
            font-size: 1rem;
        }

        .hero-content-overlay .hero-stats {
            gap: 20px;
        }

        .hero-content-overlay .hero-stats .stat span {
            font-size: 1.2rem;
        }

        .hero-content-overlay .search-box {
            padding: 15px;
            margin: 0 20px;
            border-radius: 20px;
        }

        .hero-carousel .carousel-control-prev,
        .hero-carousel .carousel-control-next {
            width: 12%;
        }

        .hero-carousel .carousel-control-prev-icon,
        .hero-carousel .carousel-control-next-icon {
            padding: 15px;
            background-size: 50%;
        }

        .hero-carousel .carousel-control-prev:hover .carousel-control-prev-icon,
        .hero-carousel .carousel-control-next:hover .carousel-control-next-icon {
            padding: 18px;
            background-size: 55%;
        }

        .vehicle-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .plans-grid {
            grid-template-columns: 1fr;
            max-width: 400px;
            margin: 0 auto;
        }

        .navbar-left {
            margin-left: 10px;
        }

        .navbar-logo-wrapper img {
            height: 55px;
        }

        .navbar-brand-text {
            margin-left: 65px;
            font-size: 0.9rem;
        }

        .footer-brand img {
            height: 40px;
        }

        .contact-info-wrapper,
        .contact-form-wrapper {
            margin-bottom: 20px;
            height: auto;
        }

        .contact-alert {
            top: 10px;
            right: 10px;
            left: 10px;
            min-width: auto;
        }
    }

    @media (max-width: 480px) {
        .vehicle-grid {
            grid-template-columns: 1fr;
        }

        .navbar-logo-wrapper img {
            height: 45px;
        }

        .navbar-brand-text {
            margin-left: 55px;
            font-size: 0.8rem;
        }

        .contact-info-wrapper,
        .contact-form-wrapper {
            padding: 20px 15px;
        }
    }

    .vote-buttons {
        display: flex;
        gap: 8px;
        align-items: center;
        margin: 10px 0;
    }

    .vote-btn {
        background: transparent;
        border: none;
        cursor: pointer;
        padding: 5px 8px;
        border-radius: 25px;
        transition: all 0.3s ease;
        font-size: 0.9rem;
    }

    body.light-theme .vote-btn {
        color: #555;
    }

    body.dark-theme .vote-btn {
        color: #ccc;
    }

    .vote-btn i {
        font-size: 0.9rem;
    }

    .vote-btn.up-vote:hover {
        background: rgba(46, 204, 113, 0.2);
        color: #27ae60;
    }

    .vote-btn.down-vote:hover {
        background: rgba(231, 76, 60, 0.2);
        color: #e74c3c;
    }

    .vote-btn.heart-vote:hover {
        background: rgba(231, 76, 60, 0.2);
        color: #e74c3c;
    }

    .vote-btn.active-up {
        color: #27ae60;
    }

    .vote-btn.active-down {
        color: #e74c3c;
    }

    .vote-btn.active-heart {
        color: #e74c3c;
    }

    .vote-count {
        font-size: 0.7rem;
        margin-left: 3px;
        font-weight: 600;
    }

    .offer-form-card {
        margin-top: 10px;
        padding: 12px;
        background: rgba(52, 152, 219, 0.08);
        border-radius: 12px;
        border: 1px solid rgba(52, 152, 219, 0.2);
    }

    .offer-form-card .form-control-sm {
        font-size: 0.7rem;
        padding: 5px 8px;
    }

    .btn-offer {
        background: linear-gradient(135deg, #27ae60, #2ecc71);
        border: none;
        color: white;
        font-size: 0.7rem;
        padding: 5px 8px;
        border-radius: 20px;
        transition: all 0.3s ease;
    }

    .btn-offer:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
    }

    .offer-message {
        font-size: 0.6rem;
        margin-top: 5px;
    }

    .user-dropdown .dropdown-toggle::after {
        display: none;
    }

    .user-dropdown .dropdown-toggle {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 40px;
        padding: 6px 12px;
        text-decoration: none;
        color: inherit;
    }

    body.dark-theme .user-dropdown .dropdown-toggle {
        background: rgba(255, 255, 255, 0.15);
    }

    .user-dropdown .dropdown-toggle:hover {
        background: rgba(255, 255, 255, 0.25);
    }

    .user-dropdown .dropdown-menu {
        margin-top: 8px;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    body.dark-theme .user-dropdown .dropdown-menu {
        background-color: #2c2c3e;
        border-color: #3a3a4e;
    }

    body.dark-theme .user-dropdown .dropdown-item {
        color: #ffffff;
    }

    body.dark-theme .user-dropdown .dropdown-item:hover {
        background-color: #3a3a4e;
    }

    body.light-theme .user-dropdown .dropdown-toggle {
        background: rgba(0, 0, 0, 0.05);
    }

    body.light-theme .user-dropdown .dropdown-toggle:hover {
        background: rgba(0, 0, 0, 0.1);
    }

    .search-suggestions {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border-radius: 0 0 20px 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        max-height: 300px;
        overflow-y: auto;
        display: none;
    }

    body.dark-theme .search-suggestions {
        background: #2c2c3e;
        border: 1px solid #3a3a4e;
    }

    .search-suggestion-item {
        padding: 10px 15px;
        cursor: pointer;
        transition: background 0.2s ease;
        border-bottom: 1px solid #eee;
    }

    body.dark-theme .search-suggestion-item {
        border-bottom-color: #3a3a4e;
        color: #fff;
    }

    .search-suggestion-item:hover {
        background: #f0f0f0;
    }

    body.dark-theme .search-suggestion-item:hover {
        background: #3a3a4e;
    }

    .search-suggestion-item strong {
        color: #3498db;
    }

    .search-suggestion-item .suggestion-category {
        font-size: 0.7rem;
        color: #888;
        margin-left: 10px;
    }

    body.dark-theme .search-suggestion-item .suggestion-category {
        color: #aaa;
    }

    .search-suggestion-item .suggestion-price {
        font-size: 0.7rem;
        color: #27ae60;
        font-weight: 600;
    }

    body.dark-theme .search-suggestion-item .suggestion-price {
        color: #2ecc71;
    }

    .search-box {
        position: relative;
    }

    /* Estilos para resultados de búsqueda */
    .search-results-section {
        padding: 60px 0;
        background: var(--bg-color);
    }

    body.light-theme .search-results-section {
        background: #f8f9fa;
    }

    body.dark-theme .search-results-section {
        background: #1a1a2e;
    }

    .search-header {
        margin-bottom: 30px;
        padding-bottom: 15px;
        border-bottom: 2px solid #3498db;
    }

    .search-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .search-header h2 i {
        color: #3498db;
        margin-right: 10px;
    }

    .search-header p {
        color: #666;
        margin-bottom: 0;
    }

    body.dark-theme .search-header p {
        color: #aaa;
    }

    .no-results {
        text-align: center;
        padding: 60px 20px;
    }

    .no-results i {
        font-size: 4rem;
        color: #3498db;
        margin-bottom: 20px;
    }

    .no-results h3 {
        font-size: 1.5rem;
        margin-bottom: 10px;
    }

    .no-results p {
        color: #666;
    }

    body.dark-theme .no-results p {
        color: #aaa;
    }

    .clear-search-btn {
        display: inline-block;
        margin-top: 15px;
        padding: 8px 20px;
        background: #3498db;
        color: white;
        border-radius: 30px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .clear-search-btn:hover {
        background: #2980b9;
        transform: translateY(-2px);
    }

    .auto-reset-timer {
        font-size: 0.7rem;
        color: #888;
        margin-top: 10px;
    }

    body.dark-theme .auto-reset-timer {
        color: #aaa;
    }

    /* Estilos para el botón de enviar deshabilitado */
    .btn-send-disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    </style>






    <?php
        $configQuery = "SELECT config_value FROM system_config WHERE config_key = 'google_analytics_id'";
        $gaId        = $pdo->query($configQuery)->fetchColumn();
    if ($gaId): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo $gaId; ?>"></script>
    <script>
    window.dataLayer = window.dataLayer || [];

    function gtag() {
        dataLayer.push(arguments);
    }
    gtag('js', new Date());
    gtag('config', '<?php echo $gaId; ?>');
    </script>
    <?php endif; ?>
</head>

<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
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

            <!-- Botón hamburguesa - CSS define color según tema -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Contenido del navbar colapsable -->
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="/">Inicio</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/catalog">Catálogo</a>
                    </li>
                    <!-- Dropdown de categorías -->
                    <li class="nav-item dropdown">
                        <ul class="dropdown-menu dropdown-menu-scroll">
                            <?php foreach ($categories as $cat): ?>
                            <li><a class="dropdown-item"
                                    href="/catalog/category/<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></a>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/contacto">Contacto</a>
                    </li>
                </ul>

                <!-- Botones de usuario dentro del collapse -->
                <div class="d-flex align-items-center ms-2">
                    <?php if ($isLoggedIn): ?>
                    <div class="dropdown user-dropdown">
                        <a class="dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <i class="fas fa-user-circle"></i>
                            <span><?php echo htmlspecialchars($userName); ?></span>
                            <i class="fas fa-chevron-down ms-1 small"></i>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/dashboard/user/"><i
                                        class="fas fa-tachometer-alt me-2"></i> Mi panel</a></li>
                            <li><a class="dropdown-item" href="/dashboard/user/profile.php"><i
                                        class="fas fa-id-card me-2"></i> Mi perfil</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="/logout.php"><i class="fas fa-sign-out-alt me-2"></i>
                                    Cerrar sesión</a></li>
                        </ul>
                    </div>
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
    </nav>

    <div class="hero-fullscreen">
        <div class="hero-carousel">
            <div id="mainCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel" data-bs-interval="2000">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="0" class="active"></button>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="2"></button>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="3"></button>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="4"></button>
                    <button type="button" data-bs-target="#mainCarousel" data-bs-slide-to="5"></button>
                </div>
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <img src="https://images.unsplash.com/photo-1533473359331-0135ef1b58bf?w=1920&h=1080&fit=crop"
                            alt="Toyota Sequoia">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1625047509168-a7026f36de04?w=1920&h=1080&fit=crop"
                            alt="Toyota Fortuner">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1555215695-3004980ad54e?w=1920&h=1080&fit=crop"
                            alt="BMW Serie 7">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1606664515524-ed2f786a0bd6?w=1920&h=1080&fit=crop"
                            alt="Audi Q8">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1621135802920-133df287f89c?w=1920&h=1080&fit=crop"
                            alt="Mercedes-Benz Clase S">
                    </div>
                    <div class="carousel-item">
                        <img src="https://images.unsplash.com/photo-1580273916550-e323be2ae537?w=1920&h=1080&fit=crop"
                            alt="Porsche Cayenne">
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#mainCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Anterior</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#mainCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Siguiente</span>
                </button>
            </div>
        </div>

        <div class="hero-content-overlay">
            <div class="container">
                <h1>Encuentra el Vehículo de tus Sueños</h1>
                <p>La plataforma líder en compra y venta de autos de lujo en Colombia</p>
                <div class="hero-stats">
                    <div class="stat">
                        <i class="fas fa-car"></i>
                        <span><?php echo number_format($stats['total_cars']); ?>+</span>
                        <small>Vehículos disponibles</small>
                    </div>
                    <div class="stat">
                        <i class="fas fa-users"></i>
                        <span><?php echo number_format($stats['total_sellers']); ?>+</span>
                        <small>Vendedores verificados</small>
                    </div>
                    <div class="stat">
                        <i class="fas fa-chart-line"></i>
                        <span><?php echo $stats['new_this_week']; ?></span>
                        <small>Nuevos esta semana</small>
                    </div>
                </div>

                <!-- FORMULARIO DE BÚSQUEDA -->
                <div class="search-box">
                    <form id="searchForm" action="" method="GET">
                        <div class="row g-2">
                            <div class="col-md-5" style="position: relative;">
                                <input type="text" name="q" id="searchQ" class="form-control"
                                    placeholder="Buscar por MARCA (ej: BMW, Audi, Mercedes)..." autocomplete="off"
                                    value="<?php echo htmlspecialchars($searchQ); ?>">
                                <div id="searchSuggestions" class="search-suggestions"></div>
                            </div>
                            <!-- Selector de categorías - OCULTO pero NO ELIMINADO -->
                            <div class="col-md-3" style="display: none;">
                                <select name="category" id="searchCategory" class="form-select">
                                    <option value="">Todas las categorías</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>"
                                        <?php echo($searchCategory == $cat['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="min_price" id="searchMinPrice" class="form-control"
                                    placeholder="Precio mínimo"
                                    value="<?php echo $searchMinPrice > 0 ? number_format($searchMinPrice, 0, '', '') : ''; ?>"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="max_price" id="searchMaxPrice" class="form-control"
                                    placeholder="Precio máximo"
                                    value="<?php echo $searchMaxPrice > 0 ? number_format($searchMaxPrice, 0, '', '') : ''; ?>"
                                    oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                            </div>
                            <div class="col-md-1">
                                <button type="submit" id="searchSubmitBtn" class="btn btn-search w-100">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($hasSearch): ?>
    <!-- SECCIÓN DE RESULTADOS DE BÚSQUEDA -->
    <section class="search-results-section">
        <div class="container">
            <div class="search-header">
                <h2><i class="fas fa-search"></i> Resultados de búsqueda</h2>
                <?php if (! empty($searchQueryText)): ?>
                <p>Mostrando resultados para: <strong><?php echo htmlspecialchars($searchQueryText); ?></strong></p>
                <?php endif; ?>
                <a href="/" class="clear-search-btn"><i class="fas fa-times"></i> Limpiar búsqueda</a>
                <div class="auto-reset-timer" id="autoResetTimer">
                    <i class="fas fa-clock"></i> La página se restablecerá automáticamente en <span
                        id="timerCountdown">30</span> segundos
                </div>
            </div>

            <?php if (count($searchResults) > 0): ?>
            <div class="vehicle-grid">
                <?php foreach ($searchResults as $vehicle): ?>
                <div class="vehicle-card"
                    style="border-radius: 12px; overflow: hidden; transition: transform 0.3s ease; position: relative; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                    <?php if ($vehicle['membership_tier'] === 'elite'): ?>
                    <span class="badge-elite"
                        style="position: absolute; top: 10px; left: 10px; background: #f9ca24; color: #1a1a2e; padding: 5px 10px; border-radius: 20px; font-size: 11px; z-index: 1;">
                        <i class="fas fa-crown"></i> Elite
                    </span>
                    <?php elseif ($vehicle['membership_tier'] === 'premium'): ?>
                    <span class="badge-premium"
                        style="position: absolute; top: 10px; left: 10px; background: #2980b9; color: white; padding: 5px 10px; border-radius: 20px; font-size: 11px; z-index: 1;">
                        <i class="fas fa-gem"></i> Premium
                    </span>
                    <?php endif; ?>
                    <div class="vehicle-image" style="height: 200px; overflow: hidden;">
                        <?php if (! empty($vehicle['primary_image'])): ?>
                        <img src="<?php echo $vehicle['primary_image']; ?>"
                            alt="<?php echo htmlspecialchars($vehicle['titulo']); ?>"
                            style="width: 100%; height: 100%; object-fit: cover;"
                            onerror="this.style.display='none'; this.parentNode.querySelector('.no-image').style.display='flex';">
                        <div class="no-image" style="display: none;">
                            <i class="fas fa-car"></i>
                        </div>
                        <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-car"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="vehicle-info" style="padding: 15px;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($vehicle['titulo']); ?></h3>
                        <div class="vehicle-price"
                            style="font-size: 1.3rem; font-weight: bold; color: #3498db; margin: 8px 0;">$
                            <?php echo number_format($vehicle['precio'], 0, ',', '.'); ?></div>
                        <div class="vehicle-meta" style="display: flex; gap: 15px; font-size: 0.8rem; margin: 10px 0;">
                            <span><i class="fas fa-calendar"></i> <?php echo $vehicle['year_fabricacion']; ?></span>
                            <span><i class="fas fa-tachometer-alt"></i>
                                <?php echo number_format($vehicle['kilometraje'] ?? 0); ?> km</span>
                            <span><i class="fas fa-heart"></i> <?php echo $vehicle['likes_count'] ?? 0; ?></span>
                        </div>
                        <div class="vehicle-seller" style="font-size: 0.8rem; margin-bottom: 10px;">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($vehicle['seller_name']); ?>
                        </div>
                        <div class="vote-buttons" data-pub-id="<?php echo $vehicle['id']; ?>">
                            <button class="vote-btn up-vote" data-type="up" title="Me gusta">
                                <i class="fas fa-thumbs-up"></i> <span class="vote-count up-count">0</span>
                            </button>
                            <button class="vote-btn down-vote" data-type="down" title="No me gusta">
                                <i class="fas fa-thumbs-down"></i> <span class="vote-count down-count">0</span>
                            </button>
                            <button class="vote-btn heart-vote" data-type="heart" title="Favorito">
                                <i class="fas fa-heart"></i> <span
                                    class="vote-count heart-count"><?php echo $vehicle['likes_count'] ?? 0; ?></span>
                            </button>
                        </div>
                        <div class="offer-form-card">
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm offer-phone"
                                        placeholder="Celular/WhatsApp">
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control form-control-sm offer-amount"
                                        placeholder="Oferta $">
                                </div>
                                <div class="col-2">
                                    <button class="btn btn-offer btn-sm w-100 send-offer"
                                        data-pub-id="<?php echo $vehicle['id']; ?>">
                                        <i class="fas fa-gavel"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="offer-message small text-muted mt-1" style="display: none;"></div>
                        </div>
                        <a href="/vehicle/<?php echo $vehicle['id']; ?>/<?php echo urlencode(str_replace(' ', '-', strtolower($vehicle['titulo']))); ?>"
                            class="btn btn-view"
                            style="display: block; width: 100%; padding: 8px; border-radius: 8px; color: white; text-align: center; text-decoration: none;">Ver
                            detalles</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No se encontraron resultados</h3>
                <p>No encontramos vehículos que coincidan con tu búsqueda.</p>
                <p>Sugerencias:</p>
                <ul style="list-style: none; padding: 0;">
                    <li>• Revisa la ortografía de la marca</li>
                    <li>• Prueba con otra marca</li>
                    <li>• Amplía el rango de precios</li>
                </ul>
                <a href="/" class="clear-search-btn"><i class="fas fa-home"></i> Volver al inicio</a>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php else: ?>
    <!-- SECCIONES NORMALES -->

    <?php foreach ($ads as $ad): if ($ad['position'] === 'home_top'): ?>
    <div class="container mt-4">
        <a href="<?php echo htmlspecialchars($ad['link_url']); ?>" target="_blank" class="ad-banner">
            <img src="<?php echo $ad['image_path']; ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>"
                class="img-fluid rounded">
        </a>
    </div>
    <?php break;endif;endforeach; ?>

    <section class="featured-section" style="padding: 60px 0;">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 40px;">
                <h2><i class="fas fa-star"></i> Vehículos Destacados</h2>
                <p>Los mejores vehículos seleccionados para ti</p>
            </div>
            <div class="vehicle-grid">
                <?php foreach ($featured as $vehicle): ?>
                <div class="vehicle-card"
                    style="border-radius: 12px; overflow: hidden; transition: transform 0.3s ease; position: relative; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                    <?php if ($vehicle['membership_tier'] === 'elite'): ?>
                    <span class="badge-elite"
                        style="position: absolute; top: 10px; left: 10px; background: #f9ca24; color: #1a1a2e; padding: 5px 10px; border-radius: 20px; font-size: 11px; z-index: 1;">
                        <i class="fas fa-crown"></i> Elite
                    </span>
                    <?php elseif ($vehicle['membership_tier'] === 'premium'): ?>
                    <span class="badge-premium"
                        style="position: absolute; top: 10px; left: 10px; background: #2980b9; color: white; padding: 5px 10px; border-radius: 20px; font-size: 11px; z-index: 1;">
                        <i class="fas fa-gem"></i> Premium
                    </span>
                    <?php endif; ?>
                    <div class="vehicle-image" style="height: 200px; overflow: hidden;">
                        <?php if (! empty($vehicle['primary_image'])): ?>
                        <img src="<?php echo $vehicle['primary_image']; ?>"
                            alt="<?php echo htmlspecialchars($vehicle['titulo']); ?>"
                            style="width: 100%; height: 100%; object-fit: cover;"
                            onerror="this.style.display='none'; this.parentNode.querySelector('.no-image').style.display='flex';">
                        <div class="no-image" style="display: none;">
                            <i class="fas fa-car"></i>
                        </div>
                        <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-car"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="vehicle-info" style="padding: 15px;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($vehicle['titulo']); ?></h3>
                        <div class="vehicle-price"
                            style="font-size: 1.3rem; font-weight: bold; color: #3498db; margin: 8px 0;">$
                            <?php echo number_format($vehicle['precio'], 0, ',', '.'); ?></div>
                        <div class="vehicle-meta" style="display: flex; gap: 15px; font-size: 0.8rem; margin: 10px 0;">
                            <span><i class="fas fa-calendar"></i> <?php echo $vehicle['year_fabricacion']; ?></span>
                            <span><i class="fas fa-tachometer-alt"></i>
                                <?php echo number_format($vehicle['kilometraje'] ?? 0); ?> km</span>
                            <span><i class="fas fa-heart"></i> <?php echo $vehicle['likes_count'] ?? 0; ?></span>
                        </div>
                        <div class="vehicle-seller" style="font-size: 0.8rem; margin-bottom: 10px;">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($vehicle['seller_name']); ?>
                        </div>
                        <div class="vote-buttons" data-pub-id="<?php echo $vehicle['id']; ?>">
                            <button class="vote-btn up-vote" data-type="up" title="Me gusta">
                                <i class="fas fa-thumbs-up"></i> <span class="vote-count up-count">0</span>
                            </button>
                            <button class="vote-btn down-vote" data-type="down" title="No me gusta">
                                <i class="fas fa-thumbs-down"></i> <span class="vote-count down-count">0</span>
                            </button>
                            <button class="vote-btn heart-vote" data-type="heart" title="Favorito">
                                <i class="fas fa-heart"></i> <span
                                    class="vote-count heart-count"><?php echo $vehicle['likes_count'] ?? 0; ?></span>
                            </button>
                        </div>
                        <div class="offer-form-card">
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm offer-phone"
                                        placeholder="Celular/WhatsApp">
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control form-control-sm offer-amount"
                                        placeholder="Oferta $">
                                </div>
                                <div class="col-2">
                                    <button class="btn btn-offer btn-sm w-100 send-offer"
                                        data-pub-id="<?php echo $vehicle['id']; ?>">
                                        <i class="fas fa-gavel"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="offer-message small text-muted mt-1" style="display: none;"></div>
                        </div>
                        <a href="/vehicle/<?php echo $vehicle['id']; ?>/<?php echo urlencode(str_replace(' ', '-', strtolower($vehicle['titulo']))); ?>"
                            class="btn btn-view"
                            style="display: block; width: 100%; padding: 8px; border-radius: 8px; color: white; text-align: center; text-decoration: none;">Ver
                            detalles</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="recent-section" style="padding: 60px 0;">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 40px;">
                <h2><i class="fas fa-clock"></i> Recién Llegados</h2>
                <p>Los vehículos más recientes en la plataforma</p>
            </div>
            <div class="vehicle-grid">
                <?php foreach ($recent as $vehicle): ?>
                <div class="vehicle-card"
                    style="border-radius: 12px; overflow: hidden; box-shadow: 0 3px 10px rgba(0,0,0,0.1);">
                    <div class="vehicle-image" style="height: 200px; overflow: hidden;">
                        <?php if (! empty($vehicle['primary_image'])): ?>
                        <img src="<?php echo $vehicle['primary_image']; ?>"
                            alt="<?php echo htmlspecialchars($vehicle['titulo']); ?>"
                            style="width: 100%; height: 100%; object-fit: cover;"
                            onerror="this.style.display='none'; this.parentNode.querySelector('.no-image').style.display='flex';">
                        <div class="no-image" style="display: none;">
                            <i class="fas fa-car"></i>
                        </div>
                        <?php else: ?>
                        <div class="no-image">
                            <i class="fas fa-car"></i>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="vehicle-info" style="padding: 15px;">
                        <h3 style="font-size: 1.1rem; margin-bottom: 5px;">
                            <?php echo htmlspecialchars($vehicle['titulo']); ?></h3>
                        <div class="vehicle-price"
                            style="font-size: 1.3rem; font-weight: bold; color: #3498db; margin: 8px 0;">$
                            <?php echo number_format($vehicle['precio'], 0, ',', '.'); ?></div>
                        <div class="vehicle-meta" style="display: flex; gap: 15px; font-size: 0.8rem; margin: 10px 0;">
                            <span><i class="fas fa-calendar"></i> <?php echo $vehicle['year_fabricacion']; ?></span>
                            <span><i class="fas fa-tachometer-alt"></i>
                                <?php echo number_format($vehicle['kilometraje'] ?? 0); ?> km</span>
                            <span><i class="fas fa-heart"></i> <?php echo $vehicle['likes_count'] ?? 0; ?></span>
                        </div>
                        <div class="vehicle-seller" style="font-size: 0.8rem; margin-bottom: 10px;">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($vehicle['seller_name']); ?>
                        </div>
                        <div class="vote-buttons" data-pub-id="<?php echo $vehicle['id']; ?>">
                            <button class="vote-btn up-vote" data-type="up" title="Me gusta">
                                <i class="fas fa-thumbs-up"></i> <span class="vote-count up-count">0</span>
                            </button>
                            <button class="vote-btn down-vote" data-type="down" title="No me gusta">
                                <i class="fas fa-thumbs-down"></i> <span class="vote-count down-count">0</span>
                            </button>
                            <button class="vote-btn heart-vote" data-type="heart" title="Favorito">
                                <i class="fas fa-heart"></i> <span
                                    class="vote-count heart-count"><?php echo $vehicle['likes_count'] ?? 0; ?></span>
                            </button>
                        </div>
                        <div class="offer-form-card">
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="text" class="form-control form-control-sm offer-phone"
                                        placeholder="Celular/WhatsApp">
                                </div>
                                <div class="col-4">
                                    <input type="number" class="form-control form-control-sm offer-amount"
                                        placeholder="Oferta $">
                                </div>
                                <div class="col-2">
                                    <button class="btn btn-offer btn-sm w-100 send-offer"
                                        data-pub-id="<?php echo $vehicle['id']; ?>">
                                        <i class="fas fa-gavel"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="offer-message small text-muted mt-1" style="display: none;"></div>
                        </div>
                        <a href="/vehicle/<?php echo $vehicle['id']; ?>/<?php echo urlencode(str_replace(' ', '-', strtolower($vehicle['titulo']))); ?>"
                            class="btn btn-view"
                            style="display: block; width: 100%; padding: 8px; border-radius: 8px; color: white; text-align: center; text-decoration: none;">Ver
                            detalles</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="/catalog" class="btn btn-primary" style="padding: 10px 30px; border-radius: 30px;">Ver todo el
                    catálogo</a>
            </div>
        </div>
    </section>

    <section class="features-section" style="padding: 60px 0;">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 40px;">
                <h2><i class="fas fa-check-circle"></i> ¿Por qué elegirnos?</h2>
                <p>La mejor experiencia en compra y venta de vehículos de lujo</p>
            </div>
            <div class="features-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
                <div class="feature" style="text-align: center; padding: 20px;">
                    <div class="feature-icon"
                        style="width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-shield-alt" style="font-size: 2rem;"></i>
                    </div>
                    <h3>Vendedores Verificados</h3>
                    <p>Todos nuestros vendedores pasan por un riguroso proceso de verificación</p>
                </div>
                <div class="feature" style="text-align: center; padding: 20px;">
                    <div class="feature-icon"
                        style="width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-file-invoice" style="font-size: 2rem;"></i>
                    </div>
                    <h3>Facturación Electrónica</h3>
                    <p>Documentos con validez DIAN y trazabilidad completa</p>
                </div>
                <div class="feature" style="text-align: center; padding: 20px;">
                    <div class="feature-icon"
                        style="width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-headset" style="font-size: 2rem;"></i>
                    </div>
                    <h3>Soporte 24/7</h3>
                    <p>Atención personalizada todos los días del año</p>
                </div>
                <div class="feature" style="text-align: center; padding: 20px;">
                    <div class="feature-icon"
                        style="width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-lock" style="font-size: 2rem;"></i>
                    </div>
                    <h3>Transacciones Seguras</h3>
                    <p>Procesamiento de pagos con wompi y protección al comprador</p>
                </div>
            </div>
        </div>
    </section>

    <section class="plans-section" style="padding: 60px 0;">
        <div class="container">
            <div class="section-header" style="text-align: center; margin-bottom: 40px;">
                <h2><i class="fas fa-crown"></i> Planes de Membresía</h2>
                <p>Elige el plan que mejor se adapte a tus necesidades</p>
            </div>
            <div class="plans-grid">
                <?php foreach ($memberships as $plan):
                        $features       = parseFeatures($plan['description']);
                        $formattedPrice = $plan['price'] > 0 ? '$ ' . number_format($plan['price'], 0, ',', '.') : 'Gratis';
                        $priceSuffix    = $plan['price'] > 0 ? '<span>/mes</span>' : '';
                        $buttonText     = ($plan['price'] > 0) ? 'Comenzar' : 'Registrarse';
                        $buttonClass    = ($plan['price'] > 0) ? 'plan-btn' : 'plan-btn btn-outline';
                        $buttonStyle    = ($plan['is_featured'] == 1 && $plan['price'] > 0) ? 'style="background: var(--azul-gradient); color: white;"' : '';
                ?>
                <div class="plan-card <?php echo $plan['is_featured'] == 1 ? 'featured' : ''; ?>">
                    <?php if ($plan['is_featured'] == 1): ?>
                    <div class="popular-badge">Popular</div>
                    <?php endif; ?>
                    <div class="plan-name"><?php echo htmlspecialchars(ucfirst($plan['name'])); ?></div>
                    <div class="plan-price"><?php echo $formattedPrice; ?> <?php echo $priceSuffix; ?></div>
                    <ul class="plan-features">
                        <?php foreach ($features as $feature): ?>
                        <li><i class="fas fa-check" style="color: #00b894; margin-right: 8px;"></i>
                            <?php echo htmlspecialchars($feature); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <a href="/register" class="<?php echo $buttonClass; ?>"
                        <?php echo $buttonStyle; ?>><?php echo $buttonText; ?></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php foreach ($ads as $ad): if ($ad['position'] === 'home_bottom'): ?>
    <div class="container mt-4 mb-4">
        <a href="<?php echo htmlspecialchars($ad['link_url']); ?>" target="_blank" class="ad-banner">
            <img src="<?php echo $ad['image_path']; ?>" alt="<?php echo htmlspecialchars($ad['title']); ?>"
                class="img-fluid rounded">
        </a>
    </div>
    <?php break;endif;endforeach; ?>

    <section id="contact" class="contact-section" style="padding: 60px 0;">
        <div class="container">
            <div class="row align-items-stretch">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="contact-info-wrapper">
                        <h3><i class="fas fa-envelope"></i> Contáctanos</h3>
                        <p class="contact-description">
                            En Easy Car estamos comprometidos con tu éxito. Nuestro servicio está diseñado para que
                            puedas impulsar tu negocio con tu artículo de venta y alcanzar las metas que te propongas.
                            Esperamos que los beneficios obtenidos superen tus expectativas.<br><br>
                            Estamos aquí para apoyarte. Si tienes alguna inquietud, no dudes en contactar a nuestro
                            soporte técnico; te responderemos en el menor tiempo posible para brindarte una solución
                            pronta y efectiva.<br><br>
                            Tu satisfacción es nuestra prioridad.<br>
                            <strong>El equipo de Easy Car</strong>
                        </p>
                        <ul class="contact-info-list">
                            <li><i class="fas fa-phone"></i> +57 300 000 0000</li>
                            <li><i class="fas fa-mobile-alt"></i> +57 315 000 0000</li>
                            <li><i class="fab fa-whatsapp"></i> +57 300 000 0000</li>
                            <li><i class="fas fa-envelope"></i> contacto@easycarluxury.com</li>
                            <li><i class="fas fa-map-marker-alt"></i> Bogotá, Colombia</li>
                        </ul>
                        <div class="contact-social-links">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-tiktok"></i></a>
                            <a href="#"><i class="fab fa-youtube"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="contact-form-wrapper">
                        <div class="contact-form-title">
                            <i class="fas fa-paper-plane"></i>
                            Formulario de Contacto
                        </div>
                        <form id="contactForm">
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="contactNombre" name="nombre_completo"
                                        placeholder="Nombre completo" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" id="contactEmail" name="email"
                                        placeholder="Correo electrónico" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="contactTelefono" name="telefono"
                                            placeholder="Teléfono" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fab fa-whatsapp"></i></span>
                                        <input type="tel" class="form-control" id="contactWhatsapp" name="whatsapp"
                                            placeholder="WhatsApp (opcional)">
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-comment-dots"></i></span>
                                    <textarea class="form-control" id="contactMensaje" name="mensaje" rows="4"
                                        placeholder="Tu mensaje" required></textarea>
                                </div>
                            </div>
                            <button type="submit" id="contactSubmitBtn" class="btn btn-primary w-100"
                                style="border-radius: 30px; padding: 12px 30px;">
                                <i class="fas fa-paper-plane me-2"></i>Enviar mensaje
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <div class="footer-brand">
                        <img src="/assets/imagenes/logos/logo_s.png" alt="Easy Car Luxury Logo">
                        <span>Colcars</span>
                    </div>
                    <p>La plataforma líder en compra y venta de vehículos de lujo en Colombia.</p>
                </div>
                <div class="col-md-2">
                    <h5>Enlaces</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li><a href="/">Inicio</a></li>
                        <li><a href="/catalog">Catálogo</a></li>
                        <li><a href="/contacto">Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Legal</h5>
                    <ul style="list-style: none; padding: 0;">
                        <li><a href="#">Términos y condiciones</a></li>
                        <li><a href="#">Política de privacidad</a></li>
                        <li><a href="#">Política de cookies</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Horario de atención</h5>
                    <p>Lunes a Viernes: 8:00 - 20:00<br>
                        Sábados: 9:00 - 14:00<br>
                        Domingos: Cerrado</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Colcars. Todos los derechos reservados. &nbsp; &nbsp; &nbsp; &nbsp;by
                    Software and Games</p>
            </div>
        </div>
    </footer>

    <!-- JavaScript CDN -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/catalog.js"></script>
    <script src="/assets/js/theme-switcher.js"></script>

    <!-- Botón de tema flotante en esquina inferior derecha -->
    <button class="theme-toggle-floating" id="themeToggleFloating"
        title="<?php echo $tema === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'; ?>">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <!-- Div para alertas dinámicas -->
    <div id="contactAlert" class="contact-alert">
        <i class="fas"></i>
        <span class="alert-message"></span>
        <span class="close-alert">&times;</span>
    </div>

    <script>
    // ==========================================
    // FORMULARIO DE CONTACTO - ENVÍO CON AJAX
    // ==========================================
    (function() {
        const contactForm = document.getElementById('contactForm');
        const submitBtn = document.getElementById('contactSubmitBtn');
        const alertDiv = document.getElementById('contactAlert');
        const alertIcon = alertDiv.querySelector('i');
        const alertMessage = alertDiv.querySelector('.alert-message');
        const closeAlertBtn = alertDiv.querySelector('.close-alert');

        function showAlert(message, type) {
            alertDiv.classList.remove('success', 'error');
            if (type === 'success') {
                alertDiv.classList.add('success');
                alertIcon.className = 'fas fa-check-circle';
            } else {
                alertDiv.classList.add('error');
                alertIcon.className = 'fas fa-exclamation-circle';
            }
            alertMessage.textContent = message;
            alertDiv.style.display = 'flex';

            setTimeout(() => {
                alertDiv.style.display = 'none';
            }, 5000);
        }

        closeAlertBtn.addEventListener('click', function() {
            alertDiv.style.display = 'none';
        });

        async function sendContactMessage(formData) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Enviando...';
            submitBtn.classList.add('btn-send-disabled');

            const urlParams = new URLSearchParams(formData);

            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: urlParams.toString()
                });

                const data = await response.json();

                if (data.success) {
                    showAlert(data.message, 'success');
                    contactForm.reset();
                } else {
                    const errorMessage = data.errors ? data.errors.join(', ') : 'Error al enviar el mensaje';
                    showAlert(errorMessage, 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showAlert('Error de conexión. Por favor intenta de nuevo.', 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i>Enviar mensaje';
                submitBtn.classList.remove('btn-send-disabled');
            }
        }

        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();

                const nombre = document.getElementById('contactNombre').value.trim();
                const email = document.getElementById('contactEmail').value.trim();
                const telefono = document.getElementById('contactTelefono').value.trim();
                const mensaje = document.getElementById('contactMensaje').value.trim();

                if (!nombre) {
                    showAlert('Por favor ingresa tu nombre completo', 'error');
                    return;
                }
                if (!email) {
                    showAlert('Por favor ingresa tu correo electrónico', 'error');
                    return;
                }
                if (!email.includes('@') || !email.includes('.')) {
                    showAlert('Por favor ingresa un correo electrónico válido', 'error');
                    return;
                }
                if (!telefono) {
                    showAlert('Por favor ingresa tu número de teléfono', 'error');
                    return;
                }
                if (!mensaje) {
                    showAlert('Por favor escribe tu mensaje', 'error');
                    return;
                }

                const formData = new FormData(contactForm);
                formData.append('action', 'send_contact');

                sendContactMessage(formData);
            });
        }
    })();
    </script>

    <script>
    // ==========================================
    // AUTO-RESET: 30 SEGUNDOS SIN ACTIVIDAD, RECARGAR PARA QUITAR BÚSQUEDA
    // ==========================================
    <?php if ($hasSearch): ?>
    let resetTimer;
    let timeLeft = 30;
    const timerElement = document.getElementById('timerCountdown');

    function startResetTimer() {
        if (resetTimer) clearInterval(resetTimer);
        timeLeft = 30;
        if (timerElement) timerElement.textContent = timeLeft;

        resetTimer = setInterval(function() {
            timeLeft--;
            if (timerElement) timerElement.textContent = timeLeft;

            if (timeLeft <= 0) {
                clearInterval(resetTimer);
                window.location.href = '/';
            }
        }, 1000);
    }

    function resetTimerOnActivity() {
        if (resetTimer) {
            clearInterval(resetTimer);
            startResetTimer();
        }
    }

    startResetTimer();

    // Eventos que reinician el timer
    document.addEventListener('click', resetTimerOnActivity);
    document.addEventListener('keypress', resetTimerOnActivity);
    document.addEventListener('scroll', resetTimerOnActivity);
    document.addEventListener('mousemove', resetTimerOnActivity);
    <?php endif; ?>

        // ==========================================
        // SUGERENCIAS DE BÚSQUEDA
        // ==========================================
        (function() {
            const searchForm = document.getElementById('searchForm');
            const searchQ = document.getElementById('searchQ');
            const searchCategory = document.getElementById('searchCategory');
            const searchMinPrice = document.getElementById('searchMinPrice');
            const searchMaxPrice = document.getElementById('searchMaxPrice');
            const suggestionsBox = document.getElementById('searchSuggestions');

            let debounceTimer;
            let allVehicles = <?php echo json_encode($allVehicles); ?>;

            function fetchSuggestions(query) {
                if (query.length < 2 || allVehicles.length === 0) {
                    suggestionsBox.style.display = 'none';
                    return;
                }

                const filtered = allVehicles.filter(vehicle =>
                    vehicle.titulo.toLowerCase().includes(query.toLowerCase())
                ).slice(0, 8);

                if (filtered.length > 0) {
                    displaySuggestions(filtered, query);
                } else {
                    suggestionsBox.style.display = 'none';
                }
            }

            function displaySuggestions(suggestions, query) {
                let html = '';
                suggestions.forEach(item => {
                    let highlightedTitle = item.titulo.replace(new RegExp(`(${query})`, 'gi'),
                        '<strong>$1</strong>');
                    html += `
                    <div class="search-suggestion-item" data-value="${item.titulo.replace(/"/g, '&quot;')}">
                        <i class="fas fa-car me-2" style="color: #3498db;"></i>
                        ${highlightedTitle}
                        <span class="suggestion-category">${item.categoria || 'Vehículo'}</span>
                        <span class="suggestion-price float-end">$${formatNumber(item.precio)}</span>
                    </div>
                `;
                });

                suggestionsBox.innerHTML = html;
                suggestionsBox.style.display = 'block';

                document.querySelectorAll('.search-suggestion-item').forEach(item => {
                    item.addEventListener('click', function() {
                        searchQ.value = this.dataset.value;
                        suggestionsBox.style.display = 'none';
                        searchForm.submit();
                    });
                });
            }

            function formatNumber(num) {
                return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
            }

            if (searchQ) {
                searchQ.addEventListener('input', function(e) {
                    clearTimeout(debounceTimer);
                    const query = this.value.trim();

                    debounceTimer = setTimeout(() => {
                        if (query.length >= 2) {
                            fetchSuggestions(query);
                        } else {
                            suggestionsBox.style.display = 'none';
                        }
                    }, 300);
                });

                searchQ.addEventListener('blur', function() {
                    setTimeout(() => {
                        suggestionsBox.style.display = 'none';
                    }, 200);
                });

                searchQ.addEventListener('focus', function() {
                    if (this.value.trim().length >= 2) {
                        fetchSuggestions(this.value.trim());
                    }
                });
            }

            document.addEventListener('click', function(e) {
                if (searchQ && !searchQ.contains(e.target) && suggestionsBox && !suggestionsBox.contains(e
                        .target)) {
                    suggestionsBox.style.display = 'none';
                }
            });
        })();

    // ==========================================
    // VOTOS Y OFERTAS
    // ==========================================
    function loadVotes() {
        document.querySelectorAll('.vote-buttons').forEach(container => {
            const pubId = container.dataset.pubId;
            fetch(`/api/v1/interactions.php?action=get_votes&publication_id=${pubId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        container.querySelector('.up-count').textContent = data.counts.up;
                        container.querySelector('.down-count').textContent = data.counts.down;
                        container.querySelector('.heart-count').textContent = data.counts.heart;

                        if (data.user_votes && data.user_votes.includes('up')) {
                            container.querySelector('.up-vote').classList.add('active-up');
                        }
                        if (data.user_votes && data.user_votes.includes('down')) {
                            container.querySelector('.down-vote').classList.add('active-down');
                        }
                        if (data.user_votes && data.user_votes.includes('heart')) {
                            container.querySelector('.heart-vote').classList.add('active-heart');
                        }
                    }
                });
        });
    }

    function handleVote(btn, pubId, type) {
        fetch('/api/v1/interactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'vote',
                    publication_id: pubId,
                    vote_type: type
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    const container = btn.closest('.vote-buttons');
                    container.querySelector('.up-count').textContent = data.counts.up;
                    container.querySelector('.down-count').textContent = data.counts.down;
                    container.querySelector('.heart-count').textContent = data.counts.heart;

                    container.querySelectorAll('.vote-btn').forEach(vb => {
                        vb.classList.remove('active-up', 'active-down', 'active-heart');
                    });
                    if (data.user_votes && data.user_votes.includes('up')) {
                        container.querySelector('.up-vote').classList.add('active-up');
                    }
                    if (data.user_votes && data.user_votes.includes('down')) {
                        container.querySelector('.down-vote').classList.add('active-down');
                    }
                    if (data.user_votes && data.user_votes.includes('heart')) {
                        container.querySelector('.heart-vote').classList.add('active-heart');
                    }
                } else if (data.redirect) {
                    window.location.href = '/login';
                } else {
                    alert(data.error || 'Error al registrar voto');
                }
            });
    }

    function sendOffer(btn, pubId) {
        const card = btn.closest('.vehicle-card');
        const phoneInput = card.querySelector('.offer-phone');
        const amountInput = card.querySelector('.offer-amount');
        const messageDiv = card.querySelector('.offer-message');

        const phone = phoneInput.value.trim();
        const amount = amountInput.value.trim();

        if (!phone) {
            messageDiv.textContent = 'Ingresa tu número de teléfono';
            messageDiv.style.display = 'block';
            messageDiv.style.color = '#e74c3c';
            setTimeout(() => messageDiv.style.display = 'none', 3000);
            return;
        }

        if (!amount || parseFloat(amount) <= 0) {
            messageDiv.textContent = 'Ingresa un monto válido';
            messageDiv.style.display = 'block';
            messageDiv.style.color = '#e74c3c';
            setTimeout(() => messageDiv.style.display = 'none', 3000);
            return;
        }

        fetch('/api/v1/interactions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'make_offer',
                    publication_id: pubId,
                    buyer_name: 'Visitante',
                    buyer_phone: phone,
                    amount: parseFloat(amount),
                    message: 'Oferta desde página principal'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    messageDiv.textContent = '✅ Oferta enviada con éxito';
                    messageDiv.style.color = '#27ae60';
                    messageDiv.style.display = 'block';
                    phoneInput.value = '';
                    amountInput.value = '';
                    setTimeout(() => messageDiv.style.display = 'none', 3000);
                } else if (data.redirect) {
                    window.location.href = '/login';
                } else {
                    messageDiv.textContent = data.error || 'Error al enviar oferta';
                    messageDiv.style.color = '#e74c3c';
                    messageDiv.style.display = 'block';
                    setTimeout(() => messageDiv.style.display = 'none', 3000);
                }
            });
    }

    document.addEventListener('DOMContentLoaded', function() {
        loadVotes();

        document.querySelectorAll('.up-vote, .down-vote, .heart-vote').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const container = this.closest('.vote-buttons');
                const pubId = container.dataset.pubId;
                const type = this.dataset.type;
                handleVote(this, pubId, type);
            });
        });

        document.querySelectorAll('.send-offer').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const pubId = this.dataset.pubId;
                sendOffer(this, pubId);
            });
        });
    });

    // ==========================================
    // MODIFICADO: Controlador del botón de tema flotante
    // ==========================================
    (function() {
        const themeToggle = document.getElementById('themeToggleFloating');
        const currentTheme = document.body.classList.contains('dark-theme') ? 'dark' : 'light';
        const icon = themeToggle.querySelector('i');

        function setTheme(theme) {
            if (theme === 'dark') {
                document.body.classList.remove('light-theme');
                document.body.classList.add('dark-theme');
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
                themeToggle.title = 'Cambiar a modo claro';
                document.cookie = "theme=dark; path=/; max-age=" + (365 * 24 * 60 * 60);
                // Cambiar logo a colcars_b.png
                const logoImg = document.querySelector('.navbar-logo-wrapper img');
                if (logoImg) logoImg.src = '/assets/imagenes/logos/colcars_b.png';
            } else {
                document.body.classList.remove('dark-theme');
                document.body.classList.add('light-theme');
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
                themeToggle.title = 'Cambiar a modo oscuro';
                document.cookie = "theme=light; path=/; max-age=" + (365 * 24 * 60 * 60);
                // Cambiar logo a logo_d.png
                const logoImg = document.querySelector('.navbar-logo-wrapper img');
                if (logoImg) logoImg.src = '/assets/imagenes/logos/logo_d.png';
            }
        }

        if (themeToggle) {
            themeToggle.addEventListener('click', function() {
                const isDark = document.body.classList.contains('dark-theme');
                if (isDark) {
                    setTheme('light');
                } else {
                    setTheme('dark');
                }
            });
        }
    })();

    // ==========================================
    // CORRECCIÓN MENÚ HAMBURGUESA - FORZAR INICIALIZACIÓN
    // ==========================================
    (function() {
        // Inicializar el menú hamburguesa de Bootstrap si es necesario
        if (typeof bootstrap !== 'undefined') {
            // Forzar la inicialización del collapse en móviles
            const navbarToggler = document.querySelector('.navbar-toggler');
            const navbarCollapse = document.getElementById('navbarNav');

            if (navbarToggler && navbarCollapse) {
                // Asegurar que el menú se pueda abrir/cerrar
                navbarToggler.addEventListener('click', function() {
                    // Bootstrap maneja esto automáticamente, pero forzamos reflow
                    setTimeout(function() {
                        if (navbarCollapse.classList.contains('show')) {
                            navbarCollapse.style.display = 'block';
                        } else {
                            navbarCollapse.style.display = '';
                        }
                    }, 10);
                });

                // Corregir visualización en móviles cuando está abierto
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
                observer.observe(navbarCollapse, {
                    attributes: true
                });
            }
        }

        // Asegurar que los dropdowns funcionen dentro del menú colapsado
        const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
        dropdownToggles.forEach(function(toggle) {
            if (toggle.getAttribute('data-bs-toggle') === 'dropdown') {
                // Ya tienen el atributo correcto, Bootstrap lo maneja
            }
        });
    })();
    </script>
</body>

</html>