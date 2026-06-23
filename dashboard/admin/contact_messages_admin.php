<?php
/**
 * Panel de Administración - Mensajes de Contacto
 * Ruta: C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\contact_messages_admin.php
 * Funcionalidades: Ver, responder, editar, eliminar, exportar (Excel/PDF), copias de seguridad
 * Modo claro/oscuro, responsivo
 * 
 * CORREGIDO:
 * - Función loadBackups() ahora usa POST en lugar de GET
 * - Mejor manejo de errores en loadStats()
 * - AGREGADO: Botón de descarga de copias de seguridad
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';

// Obtener la conexión PDO
$database = Database::getInstance();
$pdo = $database->getConnection();

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

$_SESSION['admin_name'] = $admin['full_name'];
$_SESSION['admin_role'] = $admin['role'];

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>Mensajes de Contacto - Colcars</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS - CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
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

        .admin-main {
            flex: 1;
            width: auto;
            padding: 15px 20px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
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

        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Tarjetas de estadísticas */
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .stat-card {
            flex: 1;
            min-width: 180px;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            border: 1px solid var(--border-color);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-info h3 {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0;
            color: var(--text-primary);
        }

        .stat-info p {
            margin: 0;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        /* Filtros */
        .filters-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .filters-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 140px;
        }

        .filter-group label {
            display: block;
            font-size: 0.7rem;
            margin-bottom: 4px;
            color: var(--text-secondary);
        }

        .form-select, .form-control {
            padding: 6px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.8rem;
            width: 100%;
            background: var(--input-bg);
            color: var(--text-primary);
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
            font-size: 0.8rem;
        }

        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
        }

        .btn-success {
            background: #28a745;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn-warning {
            background: #ffc107;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: #1a1a2e;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.7rem;
        }

        /* Tablas */
        .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
            margin-bottom: 30px;
        }

        .card-header {
            padding: 12px 20px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-header h3 {
            margin: 0;
            font-size: 1rem;
        }

        .table-responsive {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-primary);
            vertical-align: top;
        }

        .admin-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            font-size: 0.75rem;
            position: sticky;
            top: 0;
            white-space: nowrap;
        }

        .admin-table td {
            white-space: normal;
            word-wrap: break-word;
            word-break: break-word;
        }

        .admin-table tr:hover {
            background: var(--table-hover);
        }

        .message-message {
            max-width: 300px;
        }

        .message-actions {
            white-space: nowrap;
            min-width: 100px;
        }

        .message-actions .btn {
            margin: 0 2px;
        }

        /* Badges */
        .badge-unread {
            background: #dc3545;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            display: inline-block;
        }

        .badge-read {
            background: #28a745;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            display: inline-block;
        }

        .badge-responded {
            background: #17a2b8;
            color: white;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            display: inline-block;
        }

        .badge-pending {
            background: #ffc107;
            color: #1a1a2e;
            padding: 3px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            display: inline-block;
        }

        /* Botón de tema */
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
        }

        /* Modal en modo oscuro */
        [data-theme="dark"] .modal-content {
            background-color: #16213e;
            border-color: #2a2a3e;
        }

        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer {
            border-color: #2a2a3e;
        }

        [data-theme="dark"] .modal-title,
        [data-theme="dark"] .modal-body {
            color: #ffffff;
        }

        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }

        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background: #222F58;
            border-color: #4a4a5e;
            color: #ffffff;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px;
            }
            .stats-row {
                flex-direction: column;
            }
            .stat-card {
                min-width: auto;
            }
            .filters-form {
                flex-direction: column;
            }
            .filter-group {
                width: 100%;
            }
            .admin-table {
                min-width: 700px;
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
                justify-content: flex-start;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Loading overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #c8a86b;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .pagination {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .page-link {
            padding: 6px 12px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 0.75rem;
            cursor: pointer;
        }

        .page-link:hover, .page-link.active {
            background: #c8a86b;
            color: white;
            border-color: #c8a86b;
        }

        .swal2-container {
            z-index: 99999 !important;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="admin-main">
            <div class="admin-header">
                <div class="header-title">
                    <h1><i class="fas fa-envelope"></i> Mensajes de Contacto</h1>
                    <p>Gestión de mensajes enviados desde el formulario de contacto</p>
                </div>
                <div class="header-actions">
                    <button class="btn btn-success" id="refreshBtn">
                        <i class="fas fa-sync-alt"></i> Refrescar
                    </button>
                    <button class="btn btn-primary" id="exportExcelBtn">
                        <i class="fas fa-file-excel"></i> Exportar Excel
                    </button>
                    <button class="btn btn-primary" id="exportPdfBtn" style="background: #dc3545;">
                        <i class="fas fa-file-pdf"></i> Exportar PDF
                    </button>
                    <button class="btn btn-warning" id="backupCreateBtn">
                        <i class="fas fa-database"></i> Crear Backup
                    </button>
                </div>
            </div>

            <!-- Tarjetas de estadísticas -->
            <div class="stats-row" id="statsContainer">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-envelope"></i></div>
                    <div class="stat-info">
                        <h3 id="totalCount">0</h3>
                        <p>Total Mensajes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545, #b02a37);"><i class="fas fa-envelope-open"></i></div>
                    <div class="stat-info">
                        <h3 id="unreadCount">0</h3>
                        <p>No leídos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8, #138496);"><i class="fas fa-reply-all"></i></div>
                    <div class="stat-info">
                        <h3 id="respondedCount">0</h3>
                        <p>Respondidos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #28a745, #1e7e34);"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-info">
                        <h3 id="readCount">0</h3>
                        <p>Leídos</p>
                    </div>
                </div>
            </div>

            <!-- Filtros -->
            <div class="filters-card">
                <div class="filters-form">
                    <div class="filter-group">
                        <label><i class="fas fa-search"></i> Buscar</label>
                        <input type="text" id="searchInput" class="form-control" placeholder="Nombre, email, asunto...">
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-envelope-open"></i> Estado de lectura</label>
                        <select id="leidoFilter" class="form-select">
                            <option value="">Todos</option>
                            <option value="0">No leídos</option>
                            <option value="1">Leídos</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label><i class="fas fa-reply"></i> Estado de respuesta</label>
                        <select id="respondidoFilter" class="form-select">
                            <option value="">Todos</option>
                            <option value="0">No respondidos</option>
                            <option value="1">Respondidos</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button class="btn btn-primary" id="applyFiltersBtn">Aplicar Filtros</button>
                        <button class="btn btn-secondary" id="clearFiltersBtn">Limpiar</button>
                    </div>
                </div>
            </div>

            <!-- TABLA 1: Mensajes de Contacto -->
            <div class="data-card">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> Lista de Mensajes</h3>
                    <div>
                        <select id="perPageSelect" class="form-select" style="width: auto; display: inline-block;">
                            <option value="10">10 por página</option>
                            <option value="20" selected>20 por página</option>
                            <option value="50">50 por página</option>
                            <option value="100">100 por página</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="admin-table" id="messagesTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Asunto</th>
                                <th>Mensaje</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="messagesTableBody">
                            <tr>
                                <td colspan="9" class="text-center">Cargando mensajes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-header" id="paginationContainer" style="justify-content: center;">
                    <!-- Paginación dinámica -->
                </div>
            </div>

            <!-- TABLA 2: Copias de Seguridad -->
            <div class="data-card">
                <div class="card-header">
                    <h3><i class="fas fa-database"></i> Copias de Seguridad</h3>
                    <button class="btn btn-secondary btn-sm" id="refreshBackupsBtn">
                        <i class="fas fa-sync-alt"></i> Refrescar
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="admin-table backup-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nombre del archivo</th>
                                <th>Tamaño</th>
                                <th>Registros</th>
                                <th>Fecha de creación</th>
                                <th>Creado por</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="backupsTableBody">
                            <tr>
                                <td colspan="7" class="text-center">Cargando copias de seguridad...<\/td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Botón de tema -->
    <button class="btn-theme" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Modal para Ver/Responder/Editar Mensaje -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: #c8a86b; color: white;">
                    <h5 class="modal-title" id="messageModalTitle">Detalle del Mensaje</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="messageModalBody">
                    <form id="messageForm">
                        <input type="hidden" id="edit_id" name="id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre</label>
                                <input type="text" id="edit_nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" id="edit_email" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" id="edit_telefono" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Asunto</label>
                                <input type="text" id="edit_asunto" class="form-control" required>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Mensaje</label>
                                <textarea id="edit_mensaje" class="form-control" rows="4" required></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado de lectura</label>
                                <select id="edit_estado_leido" class="form-select">
                                    <option value="0">No leído</option>
                                    <option value="1">Leído</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Estado de respuesta</label>
                                <select id="edit_estado_respondido" class="form-select">
                                    <option value="0">No respondido</option>
                                    <option value="1">Respondido</option>
                                </select>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Respuesta del administrador</label>
                                <textarea id="edit_respuesta_admin" class="form-control" rows="3" placeholder="Escribe aquí tu respuesta al usuario..."></textarea>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="saveMessageBtn">Guardar cambios</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- SheetJS para exportar Excel -->
    <script src="https://cdn.sheetjs.com/xlsx-0.20.2/package/dist/xlsx.full.min.js"></script>
    <!-- jsPDF para exportar PDF -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>

    <script>
        // Variables globales
        let currentPage = 1;
        let currentLimit = 20;
        let currentFilters = {
            search: '',
            estado_leido: '',
            estado_respondido: ''
        };
        let totalMessages = 0;
        let totalPages = 0;
        
        const modal = new bootstrap.Modal(document.getElementById('messageModal'));
        
        // Función para obtener el tema actual
        function getCurrentTheme() {
            return document.documentElement.getAttribute('data-theme') || 'light';
        }
        
        // Función para mostrar SweetAlert con el tema adecuado
        function showSwalWithTheme(options) {
            const isDark = getCurrentTheme() === 'dark';
            const swalOptions = {
                ...options,
                background: isDark ? '#1a1a2e' : '#ffffff',
                color: isDark ? '#ffffff' : '#212529',
                confirmButtonColor: '#c8a86b',
                cancelButtonColor: isDark ? '#dc3545' : '#6c757d',
            };
            return Swal.fire(swalOptions);
        }
        
        // Mostrar/ocultar loading
        function showLoading(show) {
            document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
        }
        
        // Alternar tema
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
        
        // Inicializar icono del tema
        document.addEventListener('DOMContentLoaded', function() {
            const currentTheme = getCurrentTheme();
            const btnIcon = document.querySelector('.btn-theme i');
            if (btnIcon) {
                if (currentTheme === 'dark') {
                    btnIcon.classList.remove('fa-moon');
                    btnIcon.classList.add('fa-sun');
                }
            }
            loadMessages();
            loadBackups();
            loadStats();
        });
        
        // ==========================================
        // CORRECCIÓN: loadStats() - Mejor manejo de errores
        // ==========================================
        async function loadStats() {
            try {
                const response = await fetch('/api/v1/admin/contact_messages.php?action=list&limit=1');
                const result = await response.json();
                if (result.success) {
                    totalMessages = result.total;
                    document.getElementById('totalCount').innerText = totalMessages;
                    
                    const statsResponse = await fetch('/api/v1/admin/contact_messages.php?action=list&limit=1000');
                    const statsResult = await statsResponse.json();
                    if (statsResult.success && statsResult.messages) {
                        let unread = 0, read = 0, responded = 0;
                        statsResult.messages.forEach(msg => {
                            if (msg.estado_leido == 0) unread++;
                            else read++;
                            if (msg.estado_respondido == 1) responded++;
                        });
                        document.getElementById('unreadCount').innerText = unread;
                        document.getElementById('readCount').innerText = read;
                        document.getElementById('respondedCount').innerText = responded;
                    }
                } else {
                    console.warn('Stats: API returned success=false', result);
                }
            } catch (error) {
                console.error('Error loading stats:', error);
                // No mostrar alerta para no molestar al usuario
            }
        }
        
        // Cargar mensajes
        async function loadMessages() {
            showLoading(true);
            
            let url = `/api/v1/admin/contact_messages.php?action=list&page=${currentPage}&limit=${currentLimit}`;
            if (currentFilters.search) url += `&search=${encodeURIComponent(currentFilters.search)}`;
            if (currentFilters.estado_leido !== '') url += `&estado_leido=${currentFilters.estado_leido}`;
            if (currentFilters.estado_respondido !== '') url += `&estado_respondido=${currentFilters.estado_respondido}`;
            
            try {
                const response = await fetch(url);
                const result = await response.json();
                
                if (result.success) {
                    totalMessages = result.total;
                    totalPages = result.total_pages;
                    renderMessagesTable(result.messages);
                    renderPagination();
                } else {
                    showSwalWithTheme({
                        title: 'Error',
                        text: result.error || 'Error al cargar mensajes',
                        icon: 'error'
                    });
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showSwalWithTheme({
                    title: 'Error de conexión',
                    text: 'No se pudieron cargar los mensajes',
                    icon: 'error'
                });
            }
            
            showLoading(false);
        }
        
        // Renderizar tabla de mensajes
        function renderMessagesTable(messages) {
            const tbody = document.getElementById('messagesTableBody');
            
            if (!messages || messages.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" class="text-center">No hay mensajes para mostrar</td></tr>';
                return;
            }
            
            let html = '';
            for (const msg of messages) {
                const leidoBadge = msg.estado_leido == 1 ? '<span class="badge-read">Leído</span>' : '<span class="badge-unread">No leído</span>';
                const respondidoBadge = msg.estado_respondido == 1 ? '<span class="badge-responded">Respondido</span>' : '<span class="badge-pending">Pendiente</span>';
                const fecha = new Date(msg.fecha_creacion).toLocaleString('es-CO');
                
                html += `
                    <tr>
                        <td>${msg.id}</td>
                        <td><strong>${escapeHtml(msg.nombre)}</strong></td>
                        <td>${escapeHtml(msg.email)}</td>
                        <td>${escapeHtml(msg.telefono || '-')}</td>
                        <td><strong>${escapeHtml(msg.asunto)}</strong></td>
                        <td class="message-message">${escapeHtml(msg.mensaje.substring(0, 100))}${msg.mensaje.length > 100 ? '...' : ''}</td>
                        <td>${leidoBadge} ${respondidoBadge}</td>
                        <td>${fecha}</td>
                        <td class="message-actions">
                            <button class="btn btn-sm btn-primary" onclick="viewMessage(${msg.id})" title="Ver/Editar"><i class="fas fa-eye"></i></button>
                            <button class="btn btn-sm btn-success" onclick="replyMessage(${msg.id})" title="Responder"><i class="fas fa-reply"></i></button>
                            <button class="btn btn-sm btn-danger" onclick="deleteMessage(${msg.id})" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `;
            }
            tbody.innerHTML = html;
        }
        
        // Renderizar paginación
        function renderPagination() {
            const container = document.getElementById('paginationContainer');
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '<div class="pagination">';
            
            if (currentPage > 1) {
                html += `<button class="page-link" onclick="goToPage(${currentPage - 1})">« Anterior</button>`;
            }
            
            let startPage = Math.max(1, currentPage - 2);
            let endPage = Math.min(totalPages, currentPage + 2);
            
            if (startPage > 1) {
                html += `<button class="page-link" onclick="goToPage(1)">1</button>`;
                if (startPage > 2) html += `<span class="page-link disabled" style="cursor: default;">...</span>`;
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button class="page-link ${i === currentPage ? 'active' : ''}" onclick="goToPage(${i})">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) html += `<span class="page-link disabled" style="cursor: default;">...</span>`;
                html += `<button class="page-link" onclick="goToPage(${totalPages})">${totalPages}</button>`;
            }
            
            if (currentPage < totalPages) {
                html += `<button class="page-link" onclick="goToPage(${currentPage + 1})">Siguiente »</button>`;
            }
            
            html += '</div>';
            container.innerHTML = html;
        }
        
        function goToPage(page) {
            currentPage = page;
            loadMessages();
        }
        
        // Ver/Editar mensaje
        async function viewMessage(id) {
            showLoading(true);
            try {
                const response = await fetch(`/api/v1/admin/contact_messages.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const msg = result.message;
                    document.getElementById('edit_id').value = msg.id;
                    document.getElementById('edit_nombre').value = msg.nombre;
                    document.getElementById('edit_email').value = msg.email;
                    document.getElementById('edit_telefono').value = msg.telefono || '';
                    document.getElementById('edit_asunto').value = msg.asunto;
                    document.getElementById('edit_mensaje').value = msg.mensaje;
                    document.getElementById('edit_estado_leido').value = msg.estado_leido;
                    document.getElementById('edit_estado_respondido').value = msg.estado_respondido;
                    document.getElementById('edit_respuesta_admin').value = msg.respuesta_admin || '';
                    
                    document.getElementById('messageModalTitle').innerHTML = `<i class="fas fa-envelope"></i> Mensaje #${msg.id} - ${escapeHtml(msg.nombre)}`;
                    modal.show();
                } else {
                    showSwalWithTheme({ title: 'Error', text: result.error || 'No se pudo cargar el mensaje', icon: 'error' });
                }
            } catch (error) {
                showSwalWithTheme({ title: 'Error', text: 'Error de conexión', icon: 'error' });
            }
            showLoading(false);
        }
        
        // Responder mensaje
        async function replyMessage(id) {
            await viewMessage(id);
            document.getElementById('edit_respuesta_admin').focus();
        }
        
        // Guardar cambios del mensaje
        document.getElementById('saveMessageBtn').addEventListener('click', async function() {
            const formData = {
                action: 'update',
                id: parseInt(document.getElementById('edit_id').value),
                nombre: document.getElementById('edit_nombre').value,
                email: document.getElementById('edit_email').value,
                telefono: document.getElementById('edit_telefono').value,
                asunto: document.getElementById('edit_asunto').value,
                mensaje: document.getElementById('edit_mensaje').value,
                estado_leido: parseInt(document.getElementById('edit_estado_leido').value),
                estado_respondido: parseInt(document.getElementById('edit_estado_respondido').value),
                respuesta_admin: document.getElementById('edit_respuesta_admin').value
            };
            
            showLoading(true);
            try {
                const response = await fetch('/api/v1/admin/contact_messages.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const result = await response.json();
                
                if (result.success) {
                    showSwalWithTheme({ title: 'Éxito', text: result.message, icon: 'success' });
                    modal.hide();
                    loadMessages();
                    loadStats();
                } else {
                    showSwalWithTheme({ title: 'Error', text: result.error || 'Error al guardar', icon: 'error' });
                }
            } catch (error) {
                showSwalWithTheme({ title: 'Error', text: 'Error de conexión', icon: 'error' });
            }
            showLoading(false);
        });
        
        // Eliminar mensaje
        async function deleteMessage(id) {
            const result = await showSwalWithTheme({
                title: '¿Eliminar mensaje?',
                text: 'Esta acción no se puede deshacer.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            
            if (result.isConfirmed) {
                showLoading(true);
                try {
                    const response = await fetch(`/api/v1/admin/contact_messages.php?action=delete&id=${id}`, {
                        method: 'DELETE'
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        showSwalWithTheme({ title: 'Eliminado', text: data.message, icon: 'success' });
                        loadMessages();
                        loadStats();
                    } else {
                        showSwalWithTheme({ title: 'Error', text: data.error || 'Error al eliminar', icon: 'error' });
                    }
                } catch (error) {
                    showSwalWithTheme({ title: 'Error', text: 'Error de conexión', icon: 'error' });
                }
                showLoading(false);
            }
        }
        
        // ==================== COPIAS DE SEGURIDAD ====================
        
        // ==========================================
        // CORRECCIÓN: loadBackups() - Ahora usa POST con JSON
        // ==========================================
        async function loadBackups() {
            try {
                const response = await fetch('/api/v1/admin/contact_messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'backup_list' })
                });
                const result = await response.json();
                
                if (result.success) {
                    renderBackupsTable(result.backups);
                } else {
                    console.warn('Backups: API returned error:', result.error);
                }
            } catch (error) {
                console.error('Error loading backups:', error);
                // No mostrar alerta, solo consola
            }
        }
        
        // ==========================================
        // CORRECCIÓN: renderBackupsTable() - AGREGADO BOTÓN DE DESCARGA
        // ==========================================
        function renderBackupsTable(backups) {
            const tbody = document.getElementById('backupsTableBody');
            
            if (!backups || backups.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center">No hay copias de seguridad disponibles</td></tr>';
                return;
            }
            
            let html = '';
            let index = 1;
            for (const backup of backups) {
                const sizeKB = (backup.tamanio_bytes / 1024).toFixed(2);
                const fecha = new Date(backup.fecha_backup).toLocaleString('es-CO');
                
                html += `
                    <tr>
                        <td>${index++}<\/td>
                        <td><i class="fas fa-file-archive"></i> ${escapeHtml(backup.nombre_archivo)}<\/td>
                        <td>${sizeKB} KB<\/td>
                        <td>${backup.registros_incluidos}<\/td>
                        <td>${fecha}<\/td>
                        <td>${escapeHtml(backup.creado_por || '-')}<\/td>
                        <td>
                            <button class="btn btn-sm btn-success" onclick="downloadBackup('${escapeHtml(backup.nombre_archivo)}')" title="Descargar">
                                <i class="fas fa-download"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteBackup('${escapeHtml(backup.nombre_archivo)}')" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        <\/td>
                    </tr>
                `;
            }
            tbody.innerHTML = html;
        }
        
        // ==========================================
        // NUEVA FUNCIÓN: downloadBackup() - Descargar copia de seguridad
        // ==========================================
        async function downloadBackup(filename) {
            try {
                // Construir la URL de descarga directa al archivo
                const downloadUrl = `/dashboard/admin/uploads/mensajes-contacto/${encodeURIComponent(filename)}`;
                
                // Crear un enlace temporal y hacer clic para descargar
                const link = document.createElement('a');
                link.href = downloadUrl;
                link.download = filename;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                showSwalWithTheme({
                    title: 'Descargando',
                    text: `Iniciando descarga de "${filename}"`,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                });
            } catch (error) {
                console.error('Error downloading backup:', error);
                showSwalWithTheme({
                    title: 'Error',
                    text: 'No se pudo descargar el archivo',
                    icon: 'error'
                });
            }
        }
        
        // ==========================================
        // CORRECCIÓN: backupCreateBtn - Usa POST con JSON
        // ==========================================
        document.getElementById('backupCreateBtn').addEventListener('click', async function() {
            showLoading(true);
            try {
                const response = await fetch('/api/v1/admin/contact_messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'backup_create' })
                });
                const result = await response.json();
                
                if (result.success) {
                    showSwalWithTheme({
                        title: 'Backup creado',
                        text: result.message + ` (${result.total_records} registros)`,
                        icon: 'success'
                    });
                    loadBackups();
                } else {
                    showSwalWithTheme({ title: 'Error', text: result.error || 'Error al crear backup', icon: 'error' });
                }
            } catch (error) {
                console.error('Error creating backup:', error);
                showSwalWithTheme({ title: 'Error', text: 'Error de conexión', icon: 'error' });
            }
            showLoading(false);
        });
        
        // ==========================================
        // CORRECCIÓN: deleteBackup - Usa POST con JSON
        // ==========================================
        async function deleteBackup(filename) {
            const result = await showSwalWithTheme({
                title: '¿Eliminar backup?',
                text: `¿Estás seguro de eliminar "${filename}"?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            });
            
            if (result.isConfirmed) {
                showLoading(true);
                try {
                    const response = await fetch('/api/v1/admin/contact_messages.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'backup_delete', filename: filename })
                    });
                    const data = await response.json();
                    
                    if (data.success) {
                        showSwalWithTheme({ title: 'Eliminado', text: data.message, icon: 'success' });
                        loadBackups();
                    } else {
                        showSwalWithTheme({ title: 'Error', text: data.error || 'Error al eliminar', icon: 'error' });
                    }
                } catch (error) {
                    console.error('Error deleting backup:', error);
                    showSwalWithTheme({ title: 'Error', text: 'Error de conexión', icon: 'error' });
                }
                showLoading(false);
            }
        }
        
        document.getElementById('refreshBackupsBtn').addEventListener('click', function() {
            loadBackups();
        });
        
        // ==================== EXPORTACIONES ====================
        
        async function exportData(format) {
            showLoading(true);
            try {
                const response = await fetch('/api/v1/admin/contact_messages.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'export',
                        format: format,
                        filters: currentFilters
                    })
                });
                const result = await response.json();
                
                if (result.success && result.data && result.data.length > 0) {
                    if (format === 'excel') {
                        const wsData = [Object.keys(result.data[0])];
                        result.data.forEach(row => {
                            wsData.push(Object.values(row));
                        });
                        const wb = XLSX.utils.book_new();
                        const ws = XLSX.utils.aoa_to_sheet(wsData);
                        XLSX.utils.book_append_sheet(wb, ws, 'Mensajes Contacto');
                        XLSX.writeFile(wb, `mensajes_contacto_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.xlsx`);
                        showSwalWithTheme({ title: 'Exportado', text: 'Archivo Excel generado correctamente', icon: 'success' });
                    } else if (format === 'pdf') {
                        const { jsPDF } = window.jspdf;
                        const doc = new jsPDF({ orientation: 'landscape', unit: 'mm' });
                        const tableColumn = Object.keys(result.data[0]);
                        const tableRows = result.data.map(row => Object.values(row).map(v => String(v)));
                        doc.autoTable({
                            head: [tableColumn],
                            body: tableRows,
                            theme: 'striped',
                            headStyles: { fillColor: [200, 168, 107], textColor: [255, 255, 255] },
                            styles: { fontSize: 8, cellPadding: 2 },
                            margin: { top: 20 }
                        });
                        doc.setFontSize(16);
                        doc.text('Reporte de Mensajes de Contacto', 14, 15);
                        doc.setFontSize(10);
                        doc.text(`Generado: ${new Date().toLocaleString('es-CO')}`, 14, 22);
                        doc.save(`mensajes_contacto_${new Date().toISOString().slice(0,19).replace(/:/g, '-')}.pdf`);
                        showSwalWithTheme({ title: 'Exportado', text: 'Archivo PDF generado correctamente', icon: 'success' });
                    }
                } else {
                    showSwalWithTheme({ title: 'Error', text: result.error || 'No hay datos para exportar', icon: 'error' });
                }
            } catch (error) {
                console.error('Error exporting data:', error);
                showSwalWithTheme({ title: 'Error', text: 'Error de conexión', icon: 'error' });
            }
            showLoading(false);
        }
        
        document.getElementById('exportExcelBtn').addEventListener('click', function() {
            exportData('excel');
        });
        
        document.getElementById('exportPdfBtn').addEventListener('click', function() {
            exportData('pdf');
        });
        
        // ==================== FILTROS Y REFRESH ====================
        
        document.getElementById('applyFiltersBtn').addEventListener('click', function() {
            currentFilters = {
                search: document.getElementById('searchInput').value,
                estado_leido: document.getElementById('leidoFilter').value,
                estado_respondido: document.getElementById('respondidoFilter').value
            };
            currentPage = 1;
            loadMessages();
            loadStats();
        });
        
        document.getElementById('clearFiltersBtn').addEventListener('click', function() {
            document.getElementById('searchInput').value = '';
            document.getElementById('leidoFilter').value = '';
            document.getElementById('respondidoFilter').value = '';
            currentFilters = { search: '', estado_leido: '', estado_respondido: '' };
            currentPage = 1;
            loadMessages();
            loadStats();
        });
        
        document.getElementById('refreshBtn').addEventListener('click', function() {
            loadMessages();
            loadStats();
            loadBackups();
        });
        
        document.getElementById('perPageSelect').addEventListener('change', function() {
            currentLimit = parseInt(this.value);
            currentPage = 1;
            loadMessages();
        });
        
        function escapeHtml(str) {
            if (!str) return '';
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }
    </script>
</body>
</html>