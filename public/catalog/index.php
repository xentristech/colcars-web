<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\public\catalog\index.php
 * Catálogo de vehículos - VERSIÓN CON FILTROS FUNCIONALES Y OFERTAS CORREGIDAS
 * CORRECCIÓN: Los campos de oferta ahora tienen z-index mayor que el overlay
 * Los filtros NO se modificaron - siguen funcionando perfectamente
 */

require_once '../../config/database.php';
require_once '../../includes/functions.php';

$database = Database::getInstance();
$pdo = $database->getConnection();

if (!$pdo) {
    die('Error de conexión a la base de datos');
}

// ==========================================
// PARÁMETROS DE FILTRO
// ==========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$min_price = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : '';
$max_price = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : '';
$year = trim($_GET['year'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$featured = isset($_GET['featured']);

// ==========================================
// FUNCIÓN PARA CONSTRUIR WHERE - SOLO POSICIONALES
// ==========================================
function buildWhereClause($search, $category, $min_price, $max_price, $year, $brand, $featured, &$values) {
    $conditions = ["p.status = 'active'", "u.activo = 1"];
    $values = [];
    
    if (!empty($search)) {
        $conditions[] = "(p.titulo LIKE ? OR p.descripcion LIKE ?)";
        $values[] = "%$search%";
        $values[] = "%$search%";
    }
    
    if (!empty($category) && is_numeric($category)) {
        $conditions[] = "p.categoria_id = ?";
        $values[] = (int)$category;
    }
    
    if ($min_price !== '' && is_numeric($min_price) && $min_price > 0) {
        $conditions[] = "p.precio >= ?";
        $values[] = (float)$min_price;
    }
    
    if ($max_price !== '' && is_numeric($max_price) && $max_price > 0) {
        $conditions[] = "p.precio <= ?";
        $values[] = (float)$max_price;
    }
    
    if (!empty($year) && is_numeric($year)) {
        $conditions[] = "p.year_fabricacion = ?";
        $values[] = (int)$year;
    }
    
    if (!empty($brand)) {
        $conditions[] = "p.brand = ?";
        $values[] = $brand;
    }
    
    if ($featured) {
        $conditions[] = "u.tipo_cuenta IN ('premium', 'elite')";
    }
    
    return implode(" AND ", $conditions);
}

// ==========================================
// CONSULTA PRINCIPAL
// ==========================================
$mainValues = [];
$whereClause = buildWhereClause($search, $category, $min_price, $max_price, $year, $brand, $featured, $mainValues);

// CONTAR TOTAL
$countSql = "SELECT COUNT(*) as total FROM publicaciones p JOIN usuarios u ON p.usuario_id = u.id WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
for ($i = 0; $i < count($mainValues); $i++) {
    $countStmt->bindValue($i + 1, $mainValues[$i]);
}
$countStmt->execute();
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

// OBTENER VEHÍCULOS
$sql = "SELECT 
            p.*, 
            u.nombre_completo as seller_name, 
            u.tipo_cuenta as membership_tier,
            COALESCE((SELECT COUNT(*) FROM favorites WHERE publication_id = p.id), 0) as likes_count,
            (SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
        FROM publicaciones p
        JOIN usuarios u ON p.usuario_id = u.id
        WHERE $whereClause
        ORDER BY p.destacado DESC, p.created_at DESC
        LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);
for ($i = 0; $i < count($mainValues); $i++) {
    $stmt->bindValue($i + 1, $mainValues[$i]);
}
$stmt->bindValue(count($mainValues) + 1, $limit, PDO::PARAM_INT);
$stmt->bindValue(count($mainValues) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// FILTRO DE MARCAS
// ==========================================
$brandsValues = [];
$brandsWhere = buildWhereClause($search, $category, $min_price, $max_price, $year, '', $featured, $brandsValues);

$brandsSql = "SELECT DISTINCT p.brand 
              FROM publicaciones p 
              JOIN usuarios u ON p.usuario_id = u.id 
              WHERE p.brand IS NOT NULL AND p.brand != '' AND $brandsWhere
              ORDER BY p.brand ASC";

$brandStmt = $pdo->prepare($brandsSql);
for ($i = 0; $i < count($brandsValues); $i++) {
    $brandStmt->bindValue($i + 1, $brandsValues[$i]);
}
$brandStmt->execute();
$brands = $brandStmt->fetchAll(PDO::FETCH_COLUMN);

// ==========================================
// FILTRO DE AÑOS
// ==========================================
$yearsValues = [];
$yearsWhere = buildWhereClause($search, $category, $min_price, $max_price, '', $brand, $featured, $yearsValues);

$yearsSql = "SELECT DISTINCT p.year_fabricacion 
             FROM publicaciones p 
             JOIN usuarios u ON p.usuario_id = u.id 
             WHERE p.year_fabricacion IS NOT NULL AND p.year_fabricacion > 1900 AND $yearsWhere
             ORDER BY p.year_fabricacion DESC";

$yearStmt = $pdo->prepare($yearsSql);
for ($i = 0; $i < count($yearsValues); $i++) {
    $yearStmt->bindValue($i + 1, $yearsValues[$i]);
}
$yearStmt->execute();
$years = $yearStmt->fetchAll(PDO::FETCH_COLUMN);

// ==========================================
// FILTRO DE CATEGORÍAS
// ==========================================
$catsValues = [];
$catsWhere = buildWhereClause($search, '', $min_price, $max_price, $year, $brand, $featured, $catsValues);

$categoriesSql = "SELECT DISTINCT c.id, c.nombre 
                  FROM categorias c
                  INNER JOIN publicaciones p ON c.id = p.categoria_id
                  INNER JOIN usuarios u ON p.usuario_id = u.id
                  WHERE c.activo = 1 AND $catsWhere
                  ORDER BY c.nombre ASC";

$catStmt = $pdo->prepare($categoriesSql);
for ($i = 0; $i < count($catsValues); $i++) {
    $catStmt->bindValue($i + 1, $catsValues[$i]);
}
$catStmt->execute();
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// ANUNCIOS Y TEMA
// ==========================================
$ads = $pdo->query("SELECT * FROM advertisements WHERE status = 'active' AND start_date <= NOW() AND end_date >= NOW() AND position = 'sidebar' LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);

$tema = isset($_COOKIE['theme']) ? $_COOKIE['theme'] : 'light';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Vehículos - Easy Car Luxury</title>
    <meta name="description" content="Explora nuestro catálogo de vehículos de lujo. Encuentra los mejores autos, motos, yates y más en Easy Car Luxury.">
    
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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
        
        .navbar > .container {
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
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.5));
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
            scrollbar-width: thin;
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
        
        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: #3498db !important;
        }
        
        .nav-link.active {
            color: #3498db !important;
        }
        
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
        
        .catalog-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 40px;
        }
        
        body.dark-theme .container-fluid,
        body.dark-theme .catalog-container,
        body.dark-theme .row,
        body.dark-theme .col-filters,
        body.dark-theme .col-results {
            background: #1a1a2e !important;
        }
        
        .col-filters {
            flex: 0 0 auto;
            width: 23%;
            padding-right: 20px;
        }
        
        .col-results {
            flex: 0 0 auto;
            width: 77%;
            padding-left: 10px;
            padding-right: 10px;
        }
        
        @media (max-width: 992px) {
            .col-filters {
                width: 100%;
                margin-bottom: 20px;
                padding-right: 0;
            }
            .col-results {
                width: 100%;
                padding-left: 0;
                padding-right: 0;
            }
            .catalog-container {
                padding: 0 20px;
            }
        }
        
        .filters-sidebar {
            background: #fff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        body.dark-theme .filters-sidebar {
            background: #2c2c3e;
        }
        
        .filters-sidebar h4 {
            font-size: 1.2rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        
        body.dark-theme .filters-sidebar h4 {
            color: #ffffff;
        }
        
        .filter-group {
            margin-bottom: 12px;
        }
        
        .filter-group label {
            font-weight: 600;
            margin-bottom: 4px;
            display: block;
            font-size: 0.8rem;
        }
        
        body.dark-theme .filter-group label {
            color: #ffffff;
        }
        
        .filter-group .form-control,
        .filter-group .form-select {
            height: 38px;
            font-size: 0.85rem;
        }
        
        body.dark-theme .form-control,
        body.dark-theme .form-select {
            background: #3a3a4e;
            border-color: #4a4a5e;
            color: #ffffff;
        }
        
        body.dark-theme .form-control::placeholder {
            color: #999;
        }
        
        body.dark-theme .form-check-label {
            color: #ffffff;
        }
        
        .vehicle-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .vehicle-card {
            background: #fff;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        body.dark-theme .vehicle-card {
            background: #2c2c3e;
        }
        
        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .vehicle-image {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .vehicle-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .vehicle-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 5;
        }
        
        .vehicle-card:hover .vehicle-overlay {
            opacity: 1;
        }
        
        .vehicle-overlay .btn {
            background: var(--azul-gradient);
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 30px;
            font-size: 0.85rem;
        }
        
        .vehicle-overlay .btn:hover {
            background: var(--azul-gradient-hover);
        }
        
        .vehicle-info {
            padding: 12px;
            position: relative;
            z-index: 10;
            background: inherit;
        }
        
        .vehicle-info h3 {
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        body.dark-theme .vehicle-info h3 {
            color: #fff;
        }
        
        .vehicle-price {
            font-size: 1.1rem;
            font-weight: 700;
            color: #3498db;
            margin: 8px 0;
        }
        
        body.dark-theme .vehicle-price {
            color: #5dade2;
        }
        
        .vehicle-meta {
            display: flex;
            gap: 10px;
            font-size: 0.7rem;
            margin: 8px 0;
            color: #666;
            flex-wrap: wrap;
        }
        
        body.dark-theme .vehicle-meta {
            color: #aaa;
        }
        
        .vehicle-meta i {
            margin-right: 3px;
            color: #3498db;
        }
        
        .vehicle-seller {
            font-size: 0.7rem;
            display: flex;
            justify-content: space-between;
            color: #666;
        }
        
        body.dark-theme .vehicle-seller {
            color: #aaa;
        }
        
        .vehicle-seller i {
            margin-right: 3px;
            color: #3498db;
        }
        
        .badge-elite {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #f9ca24;
            color: #1a1a2e;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            z-index: 15;
        }
        
        .badge-premium {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #2980b9;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            z-index: 15;
        }
        
        .no-image {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #2c3e50, #3498db);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .no-image i {
            font-size: 3rem;
            opacity: 0.7;
        }
        
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            background: #f8f9fa;
            padding: 8px 20px;
            border-radius: 16px;
        }
        
        body.dark-theme .results-header {
            background: #2c2c3e;
        }
        
        .results-header h3 {
            font-size: 1.1rem;
            margin: 0;
            font-weight: 600;
        }
        
        body.dark-theme .results-header h3 {
            color: #ffffff;
        }
        
        .results-header h3 span {
            font-size: 0.85rem;
            color: #666;
            font-weight: normal;
        }
        
        body.dark-theme .results-header h3 span {
            color: #aaa;
        }
        
        .results-options {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .results-options label {
            font-size: 0.8rem;
            margin: 0;
            font-weight: 500;
        }
        
        body.dark-theme .results-options label {
            color: #ffffff;
        }
        
        .results-options .form-select-sm {
            width: 70px;
            height: 32px;
            font-size: 0.8rem;
            border-radius: 20px;
        }
        
        body.dark-theme .results-options .form-select-sm {
            background: #3a3a4e;
            border-color: #4a4a5e;
            color: #ffffff;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            background: #fff;
            border-radius: 16px;
        }
        
        body.dark-theme .no-results {
            background: #2c2c3e;
            color: #ffffff;
        }
        
        .pagination-container {
            margin-top: 40px;
            display: flex;
            justify-content: center;
        }
        
        body.dark-theme .page-link {
            background: #2c2c3e;
            border-color: #4a4a5e;
            color: #ffffff;
        }
        
        body.dark-theme .page-item.active .page-link {
            background: #3498db;
            border-color: #3498db;
        }
        
        .btn-primary {
            background: var(--azul-gradient) !important;
            border: none !important;
        }
        
        .btn-primary:hover {
            background: var(--azul-gradient-hover) !important;
        }
        
        .btn-secondary {
            background: #6c757d;
            border: none;
        }
        
        body.dark-theme .btn-secondary {
            background: #4a4a5e;
            color: #ffffff;
        }
        
        body.dark-theme .btn-secondary:hover {
            background: #5a5a6e;
        }
        
        .sidebar-ad {
            border-radius: 12px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .footer {
            background-color: #1A1A2E !important;
            padding: 80px 0 60px;
            margin-top: 0;
            width: 100%;
        }
        
        .footer .container-fluid {
            max-width: 1400px;
            background-color: #1A1A2E !important;
            margin: 0 auto;
            padding: 0 40px;
        }
        
        .footer p {
            color: #b0b0b0;
            text-align: left;
            margin-bottom: 0;
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
            margin-bottom: 15px;
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .footer-brand {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            font-size: 1.3rem;
            font-weight: bold;
            color: #ffffff;
            margin-bottom: 15px;
        }
        
        .footer-brand img {
            height: 50px;
            width: auto;
        }
        
        .footer ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .footer ul li {
            margin-bottom: 8px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 25px;
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 30px;
            color: #b0b0b0;
        }
        
        /* CORRECCIÓN: Los campos de oferta ahora tienen z-index mayor que el overlay */
        .offer-form-card {
            margin-top: 10px;
            padding: 12px;
            background: rgba(52, 152, 219, 0.08);
            border-radius: 12px;
            border: 1px solid rgba(52, 152, 219, 0.2);
            position: relative;
            z-index: 20;
        }
        
        .offer-form-card .form-control-sm {
            font-size: 0.7rem;
            padding: 5px 8px;
            position: relative;
            z-index: 25;
        }
        
        .btn-offer {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            border: none;
            color: white;
            font-size: 0.7rem;
            padding: 5px 8px;
            border-radius: 20px;
            transition: all 0.3s ease;
            position: relative;
            z-index: 25;
        }
        
        .btn-offer:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(46, 204, 113, 0.3);
        }
        
        .offer-message {
            font-size: 0.6rem;
            margin-top: 5px;
        }
        
        /* CORRECCIÓN: Los botones de votos también deben estar por encima */
        .vote-buttons {
            display: flex;
            gap: 8px;
            align-items: center;
            margin: 10px 0;
            position: relative;
            z-index: 15;
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
        
        @media (max-width: 1200px) {
            .vehicle-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .col-filters, .col-results {
                width: 100%;
                padding: 0;
            }
            .catalog-container {
                padding: 0 20px;
            }
            .footer .container-fluid {
                padding: 0 20px;
                background-color: #1A1A2E !important;
            }
        }
        
        @media (max-width: 768px) {
            .vehicle-grid {
                grid-template-columns: repeat(2, 1fr);
            }
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
            .footer-brand img {
                height: 40px;
            }
            .vehicle-image {
                height: 180px;
            }
            .results-header {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
            }
            .results-options {
                justify-content: center;
            }
            .footer .container-fluid {
                padding: 0 15px;
            }
        }
        
        @media (max-width: 480px) {
            .vehicle-grid {
                grid-template-columns: 1fr;
            }
            .navbar-brand-text {
                margin-left: 55px;
                font-size: 0.8rem;
            }
            .navbar-logo-wrapper img {
                height: 45px;
            }
        }
    </style>
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
            <div class="navbar-right">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Menú">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item"><a class="nav-link" href="/">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link active" href="/catalog">Catálogo</a></li>
                        <li class="nav-item"><a class="nav-link" href="/contacto">Contacto</a></li>
                    </ul>
                    <div class="d-flex align-items-center ms-2">
                        <a class="nav-link" href="/login"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a>
                        <a class="btn-register ms-2" href="/register"><i class="fas fa-user-plus"></i> Registrarse</a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="catalog-container">
        <div class="container-fluid px-0">
            <div class="row">
                <div class="col-filters">
                    <div class="filters-sidebar">
                        <h4><i class="fas fa-filter"></i> Filtros</h4>
                        <form method="GET" action="" id="filterForm">
                            <div class="filter-group">
                                <label>Buscar</label>
                                <input type="text" name="search" class="form-control" placeholder="Marca, modelo..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Categoría</label>
                                <select name="category" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Marca</label>
                                <select name="brand" class="form-select">
                                    <option value="">Todas</option>
                                    <?php foreach ($brands as $b): ?>
                                    <?php if (!empty($b)): ?>
                                    <option value="<?php echo htmlspecialchars($b); ?>" <?php echo $brand == $b ? 'selected' : ''; ?>><?php echo htmlspecialchars($b); ?></option>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Año</label>
                                <select name="year" class="form-select">
                                    <option value="">Todos</option>
                                    <?php foreach ($years as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Precio mínimo (COP)</label>
                                <input type="number" name="min_price" class="form-control" placeholder="0" value="<?php echo htmlspecialchars($min_price); ?>">
                            </div>
                            <div class="filter-group">
                                <label>Precio máximo (COP)</label>
                                <input type="number" name="max_price" class="form-control" placeholder="1.000.000.000" value="<?php echo htmlspecialchars($max_price); ?>">
                            </div>
                            <div class="filter-group">
                                <div class="form-check">
                                    <input type="checkbox" name="featured" class="form-check-input" value="1" id="featured" <?php echo $featured ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="featured">Solo destacados (Premium/Elite)</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Aplicar filtros</button>
                            <a href="/catalog" class="btn btn-secondary w-100 mt-2">Limpiar filtros</a>
                        </form>
                    </div>
                    <?php foreach ($ads as $ad): ?>
                    <div class="sidebar-ad mt-3"><a href="<?php echo htmlspecialchars($ad['link_url']); ?>" target="_blank"><img src="<?php echo $ad['image_path']; ?>" class="img-fluid rounded"></a></div>
                    <?php endforeach; ?>
                </div>
                
                <div class="col-results">
                    <div class="results-header">
                        <h3>Resultados <span>(<?php echo number_format($total); ?> vehículos)</span></h3>
                        <div class="results-options">
                            <label>Mostrar:</label>
                            <select id="limitSelect" class="form-select form-select-sm">
                                <option value="12" <?php echo $limit == 12 ? 'selected' : ''; ?>>12</option>
                                <option value="24" <?php echo $limit == 24 ? 'selected' : ''; ?>>24</option>
                                <option value="48" <?php echo $limit == 48 ? 'selected' : ''; ?>>48</option>
                            </select>
                        </div>
                    </div>
                    
                    <?php if (count($vehicles) > 0): ?>
                    <div class="vehicle-grid">
                        <?php foreach ($vehicles as $vehicle): ?>
                        <div class="vehicle-card">
                            <?php if ($vehicle['membership_tier'] === 'elite'): ?>
                            <span class="badge-elite"><i class="fas fa-crown"></i> Elite</span>
                            <?php elseif ($vehicle['membership_tier'] === 'premium'): ?>
                            <span class="badge-premium"><i class="fas fa-gem"></i> Premium</span>
                            <?php endif; ?>
                            <div class="vehicle-image">
                                <?php if (!empty($vehicle['primary_image'])): ?>
                                    <img src="<?php echo $vehicle['primary_image']; ?>" alt="<?php echo htmlspecialchars($vehicle['titulo']); ?>" loading="lazy" onerror="this.style.display='none'; this.parentNode.querySelector('.no-image').style.display='flex';">
                                    <div class="no-image" style="display: none;"><i class="fas fa-car"></i></div>
                                <?php else: ?>
                                    <div class="no-image"><i class="fas fa-car"></i></div>
                                <?php endif; ?>
                                <div class="vehicle-overlay">
                                    <a href="/vehicle/<?php echo $vehicle['id']; ?>/<?php echo urlencode(str_replace(' ', '-', strtolower($vehicle['titulo']))); ?>" class="btn">Ver detalles</a>
                                </div>
                            </div>
                            <div class="vehicle-info">
                                <h3><?php echo htmlspecialchars($vehicle['titulo']); ?></h3>
                                <div class="vehicle-price">$ <?php echo number_format($vehicle['precio'], 0, ',', '.'); ?></div>
                                <div class="vehicle-meta">
                                    <span><i class="fas fa-calendar"></i> <?php echo $vehicle['year_fabricacion'] ?? 'N/A'; ?></span>
                                    <span><i class="fas fa-tachometer-alt"></i> <?php echo number_format($vehicle['kilometraje'] ?? 0); ?> km</span>
                                    <span><i class="fas fa-heart"></i> <?php echo $vehicle['likes_count']; ?></span>
                                </div>
                                <div class="vehicle-seller"><span><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($vehicle['seller_name']); ?></span></div>
                                <div class="vote-buttons" data-pub-id="<?php echo $vehicle['id']; ?>">
                                    <button class="vote-btn up-vote" data-type="up" title="Me gusta"><i class="fas fa-thumbs-up"></i> <span class="vote-count up-count">0</span></button>
                                    <button class="vote-btn down-vote" data-type="down" title="No me gusta"><i class="fas fa-thumbs-down"></i> <span class="vote-count down-count">0</span></button>
                                    <button class="vote-btn heart-vote" data-type="heart" title="Favorito"><i class="fas fa-heart"></i> <span class="vote-count heart-count"><?php echo $vehicle['likes_count'] ?? 0; ?></span></button>
                                </div>
                                <div class="offer-form-card">
                                    <div class="row g-2">
                                        <div class="col-6"><input type="text" class="form-control form-control-sm offer-phone" placeholder="Celular/WhatsApp"></div>
                                        <div class="col-4"><input type="number" class="form-control form-control-sm offer-amount" placeholder="Oferta $"></div>
                                        <div class="col-2"><button class="btn btn-offer btn-sm w-100 send-offer" data-pub-id="<?php echo $vehicle['id']; ?>"><i class="fas fa-gavel"></i></button></div>
                                    </div>
                                    <div class="offer-message small text-muted mt-1" style="display: none;"></div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($totalPages > 1): ?>
                    <nav class="pagination-container">
                        <ul class="pagination">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&year=<?php echo $year; ?>&brand=<?php echo urlencode($brand); ?>&featured=<?php echo $featured ? 1 : ''; ?>">Anterior</a></li>
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&year=<?php echo $year; ?>&brand=<?php echo urlencode($brand); ?>&featured=<?php echo $featured ? 1 : ''; ?>"><?php echo $i; ?></a></li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>"><a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category; ?>&min_price=<?php echo $min_price; ?>&max_price=<?php echo $max_price; ?>&year=<?php echo $year; ?>&brand=<?php echo urlencode($brand); ?>&featured=<?php echo $featured ? 1 : ''; ?>">Siguiente</a></li>
                        </ul>
                    </nav>
                    <?php endif; ?>
                    
                    <?php else: ?>
                    <div class="no-results">
                        <i class="fas fa-search fa-4x"></i>
                        <h3>No se encontraron vehículos</h3>
                        <p>Intenta con otros filtros o elimina algunos criterios de búsqueda</p>
                        <a href="/catalog" class="btn btn-primary">Ver todos los vehículos</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <div class="footer-brand">
                        <?php if ($tema === 'dark'): ?>
                            <img src="/assets/imagenes/logos/colcars_b.png" alt="Colcars Logo">
                        <?php else: ?>
                            <img src="/assets/imagenes/logos/logo_d.png" alt="Easy Car Luxury Logo">
                        <?php endif; ?>
                        <span>Colcars</span>
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
                        <li><a href="/terminos-condiciones">Términos y condiciones</a></li>
                        <li><a href="/politica-privacidad">Política de privacidad</a></li>
                        <li><a href="/politica-cookies">Política de cookies</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Horario de atención</h5>
                    <p>Lunes a Viernes: 8:00 - 20:00<br>Sábados: 9:00 - 14:00<br>Domingos: Cerrado</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Colcars. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <button class="theme-toggle-floating" id="themeToggleFloating" title="<?php echo $tema === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'; ?>">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
                    document.querySelectorAll('.navbar-logo-wrapper img, .footer-brand img').forEach(img => img.src = '/assets/imagenes/logos/colcars_b.png');
                } else {
                    document.body.classList.remove('dark-theme');
                    document.body.classList.add('light-theme');
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                    themeToggle.title = 'Cambiar a modo oscuro';
                    document.cookie = "theme=light; path=/; max-age=" + (365 * 24 * 60 * 60);
                    document.querySelectorAll('.navbar-logo-wrapper img, .footer-brand img').forEach(img => img.src = '/assets/imagenes/logos/logo_d.png');
                }
            }
            if (themeToggle) themeToggle.addEventListener('click', () => setTheme(document.body.classList.contains('dark-theme') ? 'light' : 'dark'));
        })();
        
        $('#limitSelect').change(function() {
            const url = new URL(window.location.href);
            url.searchParams.set('limit', $(this).val());
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        });
        
        const filterForm = document.getElementById('filterForm');
        if (filterForm) {
            filterForm.addEventListener('submit', function(e) {
                console.log('Formulario enviado correctamente');
                return true;
            });
        }
        
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
    </script>
    
    <script>
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
                        if (data.user_votes && data.user_votes.includes('up')) container.querySelector('.up-vote').classList.add('active-up');
                        if (data.user_votes && data.user_votes.includes('down')) container.querySelector('.down-vote').classList.add('active-down');
                        if (data.user_votes && data.user_votes.includes('heart')) container.querySelector('.heart-vote').classList.add('active-heart');
                    }
                });
        });
    }

    function handleVote(btn, pubId, type) {
        fetch('/api/v1/interactions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'vote', publication_id: pubId, vote_type: type })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = btn.closest('.vote-buttons');
                container.querySelector('.up-count').textContent = data.counts.up;
                container.querySelector('.down-count').textContent = data.counts.down;
                container.querySelector('.heart-count').textContent = data.counts.heart;
                container.querySelectorAll('.vote-btn').forEach(vb => vb.classList.remove('active-up', 'active-down', 'active-heart'));
                if (data.user_votes && data.user_votes.includes('up')) container.querySelector('.up-vote').classList.add('active-up');
                if (data.user_votes && data.user_votes.includes('down')) container.querySelector('.down-vote').classList.add('active-down');
                if (data.user_votes && data.user_votes.includes('heart')) container.querySelector('.heart-vote').classList.add('active-heart');
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
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'make_offer',
                publication_id: pubId,
                buyer_name: 'Visitante',
                buyer_phone: phone,
                amount: parseFloat(amount),
                message: 'Oferta desde catálogo'
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
    </script>
</body>
</html>