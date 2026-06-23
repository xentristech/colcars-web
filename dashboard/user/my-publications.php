<?php
/**
 * Colcars - Mis Publicaciones
 * Ruta: /dashboard/user/my-publications.php
 * MODIFICADO: Si la cuenta está desactivada, muestra mensaje y no permite acciones.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

$user_id = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: /easycarluxury/public/login.php');
    exit;
}

$db = Database::getInstance();
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

if (!$user || !is_array($user)) {
    session_destroy();
    header('Location: /easycarluxury/public/login.php');
    exit;
}

// ============================================
// VERIFICAR SI LA CUENTA ESTÁ ACTIVA
// ============================================
$cuenta_activa = ($user['activo'] == 1);

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

// Paginación
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Filtros
$statusFilter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

$where = "p.usuario_id = ?";
$params = [$user_id];

if ($statusFilter === 'activos') {
    $where .= " AND p.status = 'active'";
} elseif ($statusFilter === 'inactivos') {
    $where .= " AND p.status = 'inactive'";
}

if (!empty($search)) {
    $where .= " AND (p.titulo LIKE ? OR p.descripcion LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Total de publicaciones
$totalStmt = $db->query("SELECT COUNT(*) as total FROM publicaciones p WHERE $where", $params);
$totalRow = $totalStmt ? $totalStmt->fetch(PDO::FETCH_ASSOC) : ['total' => 0];
$total_count = $totalRow['total'];
$total_pages = ceil($total_count / $per_page);

// Obtener publicaciones (solo si cuenta activa, sino array vacío)
$publicaciones = [];
if ($cuenta_activa) {
    $paramsForQuery = $params;
    $paramsForQuery[] = $per_page;
    $paramsForQuery[] = $offset;

    $sql = "SELECT p.*, c.nombre as categoria_nombre
            FROM publicaciones p
            JOIN categorias c ON p.categoria_id = c.id
            WHERE $where
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";

    $stmt = $db->query($sql, $paramsForQuery);
    $publicaciones = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    if (!empty($publicaciones) && is_array($publicaciones)) {
        foreach ($publicaciones as $key => $pub) {
            $imagen = $db->getOne("SELECT image_path FROM imagenes_publicaciones WHERE publicacion_id = ? AND is_primary = 1 LIMIT 1", [$pub['id']]);
            $publicaciones[$key]['imagen_principal'] = $imagen ? $imagen['image_path'] : null;

            $comentarios = $db->getOne("SELECT COUNT(*) as total FROM comentarios WHERE publicacion_id = ? AND visible = 1", [$pub['id']]);
            $publicaciones[$key]['comentarios_count'] = $comentarios ? $comentarios['total'] : 0;
        }
    }
}

$theme = $_COOKIE['user_theme'] ?? ($user['tema_oscuro'] ? 'dark' : 'light');
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Publicaciones - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        /* ============================================
           ESTRUCTURA GLOBAL (SIN CAMBIOS)
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
        }

        /* ============================================
           ESTILOS DEL CONTENIDO (SIN CAMBIOS)
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
            --text-secondary: #a0a0a0;
            --border-color: #2a2a3e;
            --card-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.3);
        }

        .publication-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            transition: transform 0.3s;
        }

        .publication-card:hover {
            transform: translateY(-3px);
        }

        .publication-img {
            width: 120px;
            height: 120px;
            object-fit: cover;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: flex-end;
            margin-top: 10px;
        }

        .badge-container {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-bottom: 10px;
        }

        .filter-bar {
            background: var(--bg-secondary);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
        }

        .btn-outline-primary.active {
            background-color: #0d6efd;
            color: white;
        }

        .btn-outline-success.active {
            background-color: #198754;
            color: white;
        }

        .btn-outline-danger.active {
            background-color: #dc3545;
            color: white;
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

        /* ESTILOS DEL NAVBAR MÓVIL (SIN CAMBIOS) */
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
            transition: all 0.3s;
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

        /* ESTILOS DEL OFFCANVAS MÓVIL (SIN CAMBIOS) */
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
            transition: all 0.3s;
            font-size: 0.95rem;
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
            text-align: center;
        }
        
        .mobile-offcanvas hr {
            border-color: rgba(255, 255, 255, 0.2);
            margin: 10px 0;
        }

        /* FOOTER (SIN CAMBIOS) */
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
            transition: all 0.3s ease;
        }

        /* RESPONSIVE (SIN CAMBIOS) */
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
            }
            
            .publication-img {
                width: 100%;
                height: 150px;
            }
            
            .action-buttons {
                justify-content: flex-start;
            }
            
            .badge-container {
                justify-content: flex-start;
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
        }
    </style>
</head>
<body>

    <!-- NAVBAR MÓVIL (SIN CAMBIOS) -->
    <div class="mobile-navbar">
        <button class="btn-menu" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileOffcanvas">
            <i class="fas fa-bars"></i>
        </button>
        <div class="navbar-brand">
            <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">
            <span>Colcars</span>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars(substr($user['nombre_completo'], 0, 12)); ?></span>
        </div>
    </div>

    <!-- OFFCANVAS MENÚ MÓVIL (SIN CAMBIOS) -->
    <div class="offcanvas offcanvas-start mobile-offcanvas" tabindex="-1" id="mobileOffcanvas">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title">
                <img src="/assets/imagenes/logos/colcars.png" alt="Colcars">
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
                <a class="nav-link" href="index.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link active" href="my-publications.php">
                    <i class="fas fa-list"></i> Mis Publicaciones
                </a>
                <a class="nav-link" href="new-publication.php">
                    <i class="fas fa-plus-circle"></i> Nueva Publicación
                </a>
                <a class="nav-link" href="messages.php">
                    <i class="fas fa-envelope"></i> Mensajes
                    <?php if (($unread_messages['total'] ?? 0) > 0): ?>
                        <span class="badge bg-danger ms-1"><?php echo $unread_messages['total']; ?></span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="my-offers.php">
                    <i class="fas fa-gavel"></i> Mis Ofertas
                </a>
                <a class="nav-link" href="statistics.php">
                    <i class="fas fa-chart-line"></i> Estadísticas
                </a>
                <a class="nav-link" href="membership.php">
                    <i class="fas fa-gem"></i> Membresía
                </a>
                <a class="nav-link" href="payments.php">
                    <i class="fas fa-credit-card"></i> Pagos
                </a>
                <a class="nav-link" href="invoices.php">
                    <i class="fas fa-file-invoice"></i> Facturas
                </a>
                <a class="nav-link" href="profile.php">
                    <i class="fas fa-user"></i> Mi Perfil
                </a>
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog"></i> Configuración
                </a>
                <hr class="my-2">
                <a class="nav-link" href="/easycarluxury/logout">
                    <i class="fas fa-sign-out-alt"></i> Cerrar Sesión
                </a>
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
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-list"></i> Mis Publicaciones</h2>
                    <?php if ($cuenta_activa): ?>
                        <a href="new-publication.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva Publicación</a>
                    <?php endif; ?>
                </div>

                <?php if (!$cuenta_activa): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-ban"></i> <strong>Cuenta desactivada!</strong> No puedes administrar tus publicaciones porque tu cuenta está desactivada. Contacta al soporte para más información.
                    </div>
                <?php else: ?>

                <div class="filter-bar">
                    <div class="row align-items-center">
                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="btn-group">
                                <a href="?status=all" class="btn btn-outline-primary <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">Todas</a>
                                <a href="?status=activos" class="btn btn-outline-success <?php echo $statusFilter === 'activos' ? 'active' : ''; ?>">Activas</a>
                                <a href="?status=inactivos" class="btn btn-outline-danger <?php echo $statusFilter === 'inactivos' ? 'active' : ''; ?>">Inactivas</a>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <form method="GET" class="d-flex">
                                <input type="hidden" name="status" value="<?php echo $statusFilter; ?>">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" placeholder="Buscar..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <small class="text-muted">Total: <?php echo $total_count; ?> publicaciones</small>
                        </div>
                    </div>
                </div>

                <?php if (empty($publicaciones) || !is_array($publicaciones) || count($publicaciones) === 0): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-car fa-4x text-muted mb-3"></i>
                        <h5>No tienes publicaciones</h5>
                        <p class="text-muted">Comienza a vender creando tu primer anuncio</p>
                        <a href="new-publication.php" class="btn btn-primary"><i class="fas fa-plus"></i> Crear Publicación</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($publicaciones as $pub): ?>
                        <div class="publication-card">
                            <div class="row g-0">
                                <div class="col-md-2">
                                    <?php if (!empty($pub['imagen_principal'])): ?>
                                        <img src="<?php echo htmlspecialchars($pub['imagen_principal']); ?>" class="publication-img w-100">
                                    <?php else: ?>
                                        <div class="publication-img w-100 bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="fas fa-image fa-3x text-white"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-10">
                                    <div class="p-3">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h5><?php echo htmlspecialchars($pub['titulo']); ?></h5>
                                                <div class="mb-2">
                                                    <span class="badge bg-info"><?php echo htmlspecialchars($pub['categoria_nombre']); ?></span>
                                                    <span class="badge bg-primary"><?php echo ucfirst($pub['estado_articulo'] ?? 'usado'); ?></span>
                                                </div>
                                                <div class="row text-muted small">
                                                    <div class="col-md-3"><i class="fas fa-eye"></i> <?php echo number_format($pub['visitas'] ?? 0); ?> visitas</div>
                                                    <div class="col-md-3"><i class="fas fa-heart"></i> <?php echo number_format($pub['likes'] ?? 0); ?> likes</div>
                                                    <div class="col-md-3"><i class="fas fa-comment"></i> <?php echo $pub['comentarios_count'] ?? 0; ?> comentarios</div>
                                                    <div class="col-md-3"><i class="fas fa-calendar"></i> <?php echo date('d/m/Y', strtotime($pub['created_at'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                                <div class="badge-container">
                                                    <?php if ($pub['destacado']): ?>
                                                        <span class="badge bg-warning"><i class="fas fa-star"></i> Destacado</span>
                                                    <?php endif; ?>
                                                    <span class="badge bg-<?php echo $pub['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $pub['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                                    </span>
                                                </div>
                                                <div class="action-buttons">
                                                    <a href="/public/catalog/detail.php?id=<?php echo $pub['id']; ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="fas fa-eye"></i> Ver</a>
                                                    <a href="edit-publication.php?id=<?php echo $pub['id']; ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-edit"></i> Editar</a>
                                                    <button onclick="toggleStatus(<?php echo $pub['id']; ?>, '<?php echo $pub['status']; ?>')" class="btn btn-sm btn-outline-warning"><i class="fas fa-<?php echo $pub['status'] === 'active' ? 'pause' : 'play'; ?>"></i></button>
                                                    <button onclick="deletePublication(<?php echo $pub['id']; ?>)" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                                </div>
                                                <div class="mt-2"><strong class="text-primary"><?php echo formatMoney($pub['precio']); ?></strong></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($total_pages > 1): ?>
                        <nav>
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-left"></i></a></li>
                                <?php endif; ?>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>"><a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a></li>
                                <?php endfor; ?>
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $statusFilter; ?>&search=<?php echo urlencode($search); ?>"><i class="fas fa-chevron-right"></i></a></li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php endif; // fin if cuenta_activa ?>
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
    <script>
        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({ url: '/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
        }

        function toggleStatus(id, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'activar' : 'desactivar';
            Swal.fire({
                title: `¿${action === 'activar' ? 'Activar' : 'Desactivar'} publicación?`,
                text: `La publicación quedará ${action === 'activar' ? 'visible' : 'oculta'}`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: `Sí, ${action}`
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/easycarluxury/dashboard/user/update-status.php',
                        method: 'POST',
                        data: { id: id, status: newStatus },
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                Swal.fire('Éxito', `Publicación ${action}da correctamente`, 'success');
                                location.reload();
                            } else {
                                Swal.fire('Error', r.message || 'Error', 'error');
                            }
                        },
                        error: function() { Swal.fire('Error', 'No se pudo cambiar el estado', 'error'); }
                    });
                }
            });
        }

        function deletePublication(id) {
            Swal.fire({
                title: '¿Eliminar publicación?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: '/easycarluxury/dashboard/user/delete-publication.php',
                        method: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(r) {
                            if (r.success) {
                                Swal.fire('Eliminado', 'Publicación eliminada correctamente', 'success');
                                location.reload();
                            } else {
                                Swal.fire('Error', r.message || 'Error', 'error');
                            }
                        },
                        error: function() { Swal.fire('Error', 'No se pudo eliminar', 'error'); }
                    });
                }
            });
        }
    </script>
</body>
</html>