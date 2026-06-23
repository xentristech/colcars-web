<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\edit-user.php
 * EDITAR USUARIO - CON TEMA CLARO/OSCURO
 * MODIFICADO: Se agregó soporte para tema claro/oscuro desde audit.php
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';

// Obtener conexión PDO
$database = Database::getInstance();
$pdo = $database->getConnection();

$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

$userId = $_GET['id'] ?? null;
if (!$userId) {
    header('Location: users.php');
    exit;
}

// Get user details - usando tabla usuarios (español)
$query = "SELECT * FROM usuarios WHERE id = :id";
$stmt = $pdo->prepare($query);
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php?error=Usuario no encontrado');
    exit;
}

// Obtener el rol del usuario desde la tabla roles
$roleQuery = "SELECT r.nombre, r.id FROM roles r JOIN usuarios u ON u.rol_id = r.id WHERE u.id = :id";
$roleStmt = $pdo->prepare($roleQuery);
$roleStmt->execute([':id' => $userId]);
$roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
$user['role'] = $roleData['nombre'] ?? 'usuario';
$user['role_id'] = $roleData['id'] ?? 6;

// Superadmin cannot edit other superadmins unless they are superadmin themselves
if ($user['role'] === 'superadmin' && $admin['role'] !== 'superadmin') {
    header('Location: users.php?error=No tienes permiso para editar este usuario');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuario - Easy Car Luxury</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/easycarluxury/node_modules/sweetalert2/dist/sweetalert2.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ============================================
           VARIABLES DE TEMA CLARO (por defecto)
           ============================================ */
        :root {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #666666;
            --border-color: #e0e0e0;
            --card-bg: #ffffff;
            --input-bg: #ffffff;
            --input-border: #dddddd;
            --header-bg: #ffffff;
            --stat-row-color: #333;
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
            --input-bg: #222F58;
            --input-border: #4a4a5e;
            --header-bg: #16213e;
            --stat-row-color: #ffffff;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
        }

        /* CONTENEDOR PRINCIPAL CON FLEX */
        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
        }

        /* SIDEBAR - OCUPA SU ANCHO FIJO */
        .sidebar-column {
            flex-shrink: 0;
        }

        /* CONTENIDO PRINCIPAL */
        .admin-main {
            flex: 1;
            width: auto;
            padding: 20px 25px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding: 15px 20px;
            background: var(--header-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid var(--border-color);
        }

        .header-title h1 {
            font-size: 1.5rem;
            margin: 0 0 5px;
            color: var(--text-primary);
        }

        .header-title p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .card-header {
            padding: 15px 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .card-header h3 {
            font-size: 1.1rem;
            margin: 0;
            color: var(--text-primary);
        }

        .card-body {
            padding: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .form-control, .form-select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 0.9rem;
            transition: all 0.3s;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #c8a86b;
            box-shadow: 0 0 0 2px rgba(200,168,107,0.2);
        }

        .mb-3 {
            margin-bottom: 1rem;
        }

        .mt-3 {
            margin-top: 1rem;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .btn {
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-warning {
            background: #ffc107;
            color: #333;
        }

        .btn-warning:hover {
            background: #e0a800;
        }

        .w-100 {
            width: 100%;
        }

        .mb-2 {
            margin-bottom: 0.5rem;
        }

        .user-stats {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .stat-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
        }

        .stat-row span {
            color: var(--text-secondary);
        }

        .stat-row strong {
            color: var(--stat-row-color);
        }

        hr {
            margin: 10px 0;
            border-color: var(--border-color);
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

        @media (max-width: 992px) {
            .admin-main {
                padding: 70px 15px 15px;
            }
            .row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="admin-container">
    <!-- Sidebar Column -->
    <div class="sidebar-column">
        <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>
    </div>
    
    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-user-edit"></i> Editar Usuario</h1>
                <p>Modificando usuario: <?php echo htmlspecialchars($user['nombre_completo']); ?></p>
            </div>
        </div>
        
        <div class="row" style="display: flex; gap: 20px; flex-wrap: wrap;">
            <div style="flex: 2; min-width: 300px;">
                <div class="data-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Información del Usuario</h3>
                    </div>
                    <div class="card-body">
                        <form id="editUserForm">
                            <input type="hidden" id="userId" value="<?php echo $user['id']; ?>">
                            
                            <div class="mb-3">
                                <label class="form-label">Nombre completo *</label>
                                <input type="text" class="form-control" id="fullName" value="<?php echo htmlspecialchars($user['nombre_completo']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="phone" value="<?php echo htmlspecialchars($user['telefono'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Usuario</label>
                                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Rol *</label>
                                <select class="form-select" id="role" <?php echo $admin['role'] !== 'superadmin' ? 'disabled' : ''; ?>>
                                    <option value="6" <?php echo $user['role_id'] == 6 ? 'selected' : ''; ?>>Usuario</option>
                                    <option value="5" <?php echo $user['role_id'] == 5 ? 'selected' : ''; ?>>Asesor</option>
                                    <option value="4" <?php echo $user['role_id'] == 4 ? 'selected' : ''; ?>>Técnico</option>
                                    <option value="3" <?php echo $user['role_id'] == 3 ? 'selected' : ''; ?>>Contador</option>
                                    <option value="2" <?php echo $user['role_id'] == 2 ? 'selected' : ''; ?>>Ingeniero</option>
                                    <?php if ($admin['role'] === 'superadmin'): ?>
                                        <option value="1" <?php echo $user['role_id'] == 1 ? 'selected' : ''; ?>>Superadmin</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Membresía</label>
                                <select class="form-select" id="membershipTier">
                                    <option value="free" <?php echo ($user['tipo_cuenta'] ?? 'free') === 'free' ? 'selected' : ''; ?>>Free</option>
                                    <option value="pro" <?php echo ($user['tipo_cuenta'] ?? '') === 'pro' ? 'selected' : ''; ?>>Pro</option>
                                    <option value="premium" <?php echo ($user['tipo_cuenta'] ?? '') === 'premium' ? 'selected' : ''; ?>>Premium</option>
                                    <option value="elite" <?php echo ($user['tipo_cuenta'] ?? '') === 'elite' ? 'selected' : ''; ?>>Elite</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select" id="status">
                                    <option value="1" <?php echo $user['activo'] == 1 ? 'selected' : ''; ?>>Activo</option>
                                    <option value="0" <?php echo $user['activo'] == 0 ? 'selected' : ''; ?>>Inactivo</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Nueva Contraseña (dejar vacío para no cambiar)</label>
                                <input type="password" class="form-control" id="password">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirmar nueva contraseña</label>
                                <input type="password" class="form-control" id="confirmPassword">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                                <a href="users.php" class="btn btn-secondary">Cancelar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div style="flex: 1; min-width: 280px;">
                <div class="data-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-simple"></i> Estadísticas del Usuario</h3>
                    </div>
                    <div class="card-body">
                        <div class="user-stats">
                            <div class="stat-row">
                                <span>ID de usuario:</span>
                                <strong>#<?php echo $user['id']; ?></strong>
                            </div>
                            <div class="stat-row">
                                <span>Fecha de registro:</span>
                                <strong><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></strong>
                            </div>
                            <div class="stat-row">
                                <span>Último acceso:</span>
                                <strong><?php echo $user['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($user['ultimo_acceso'])) : 'Nunca'; ?></strong>
                            </div>
                            <hr>
                            <div class="stat-row" id="publicationsCount">
                                <span>Cargando publicaciones...</span>
                            </div>
                            <div class="stat-row" id="paymentsCount">
                                <span>Cargando pagos...</span>
                            </div>
                            <div class="stat-row" id="totalSpent">
                                <span>Cargando total gastado...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="data-card mt-3">
                    <div class="card-header">
                        <h3><i class="fas fa-exclamation-triangle"></i> Acciones Peligrosas</h3>
                    </div>
                    <div class="card-body">
                        <button class="btn btn-danger w-100 mb-2" onclick="resetUserPassword()">
                            <i class="fas fa-key"></i> Resetear Contraseña
                        </button>
                        <button class="btn btn-warning w-100 mb-2" onclick="sendTestEmail()">
                            <i class="fas fa-envelope"></i> Enviar Email de Prueba
                        </button>
                        <button class="btn btn-danger w-100" onclick="deleteUserAccount()">
                            <i class="fas fa-trash"></i> Eliminar Cuenta Permanentemente
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Botón para cambiar tema claro/oscuro -->
<button class="btn-theme" onclick="toggleTheme()">
    <i class="fas fa-moon"></i>
</button>

<!-- Footer -->
<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<script src="/easycarluxury/assets/libs/jquery/jquery.min.js"></script>
<script src="/easycarluxury/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="/easycarluxury/node_modules/sweetalert2/dist/sweetalert2.all.min.js"></script>
<script>
    // Función para cambiar tema claro/oscuro (desde audit.php)
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

    const token = localStorage.getItem('auth_token');
    const userId = <?php echo $user['id']; ?>;
    
    // Load user statistics
    function loadUserStats() {
        $.ajax({
            url: '/easycarluxury/api/v1/admin.php?action=get_user_stats&user_id=' + userId,
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function(response) {
                if (response.success) {
                    $('#publicationsCount').html(`<span>Publicaciones:</span> <strong>${response.data.publications_count}</strong>`);
                    $('#paymentsCount').html(`<span>Pagos realizados:</span> <strong>${response.data.payments_count}</strong>`);
                    $('#totalSpent').html(`<span>Total gastado:</span> <strong>$ ${response.data.total_spent.toLocaleString('es-CO')}</strong>`);
                }
            },
            error: function() {
                $('#publicationsCount').html(`<span>Error al cargar estadísticas</span>`);
            }
        });
    }
    
    loadUserStats();
    
    // Edit user form submission
    $('#editUserForm').submit(function(e) {
        e.preventDefault();
        
        const password = $('#password').val();
        const confirmPassword = $('#confirmPassword').val();
        
        if (password && password !== confirmPassword) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Las contraseñas no coinciden',
                confirmButtonColor: '#c8a86b'
            });
            return;
        }
        
        const userData = {
            action: 'update_user',
            user_id: userId,
            full_name: $('#fullName').val(),
            email: $('#email').val(),
            phone: $('#phone').val(),
            username: $('#username').val(),
            role_id: $('#role').val(),
            membership_tier: $('#membershipTier').val(),
            activo: $('#status').val()
        };
        
        if (password) {
            userData.password = password;
        }
        
        Swal.fire({
            title: 'Guardando...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        $.ajax({
            url: '/easycarluxury/api/v1/admin.php',
            method: 'PUT',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify(userData),
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Éxito!',
                        text: 'Usuario actualizado exitosamente',
                        confirmButtonColor: '#c8a86b'
                    }).then(() => {
                        window.location.href = 'users.php?success=updated';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'Error al actualizar usuario',
                        confirmButtonColor: '#c8a86b'
                    });
                }
            },
            error: function(xhr) {
                let errorMsg = 'Error al actualizar usuario';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: errorMsg,
                    confirmButtonColor: '#c8a86b'
                });
            }
        });
    });
    
    function resetUserPassword() {
        Swal.fire({
            title: 'Resetear Contraseña',
            input: 'password',
            inputLabel: 'Nueva contraseña',
            inputPlaceholder: 'Ingrese la nueva contraseña',
            inputAttributes: {
                minlength: 6,
                autocomplete: 'off'
            },
            showCancelButton: true,
            confirmButtonColor: '#c8a86b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Resetear',
            cancelButtonText: 'Cancelar',
            preConfirm: (newPassword) => {
                if (!newPassword || newPassword.length < 6) {
                    Swal.showValidationMessage('La contraseña debe tener al menos 6 caracteres');
                }
                return newPassword;
            }
        }).then((result) => {
            if (result.isConfirmed && result.value) {
                Swal.fire({
                    title: 'Guardando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '/easycarluxury/api/v1/admin.php',
                    method: 'PUT',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        action: 'reset_password',
                        user_id: userId,
                        password: result.value
                    }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Éxito!',
                                text: 'Contraseña reseteada exitosamente',
                                confirmButtonColor: '#c8a86b'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Error al resetear contraseña',
                                confirmButtonColor: '#c8a86b'
                            });
                        }
                    },
                    error: function(xhr) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al resetear contraseña',
                            confirmButtonColor: '#c8a86b'
                        });
                    }
                });
            }
        });
    }
    
    function sendTestEmail() {
        Swal.fire({
            title: 'Enviar Email',
            text: '¿Estás seguro de enviar un email de prueba a este usuario?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#c8a86b',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, enviar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Enviando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                $.ajax({
                    url: '/easycarluxury/api/v1/admin.php',
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({
                        action: 'send_test_email',
                        user_id: userId
                    }),
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Enviado!',
                                text: 'Email de prueba enviado exitosamente',
                                confirmButtonColor: '#c8a86b'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'Error al enviar email',
                                confirmButtonColor: '#c8a86b'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al enviar email',
                            confirmButtonColor: '#c8a86b'
                        });
                    }
                });
            }
        });
    }
    
    function deleteUserAccount() {
        Swal.fire({
            title: '⚠️ ADVERTENCIA EXTREMA',
            html: 'Esta acción eliminará permanentemente todos los datos del usuario, incluyendo publicaciones, pagos, mensajes, etc.<br><br><strong>Esta acción NO se puede deshacer.</strong>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, eliminar permanentemente',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Última confirmación',
                    text: '¿Estás ABSOLUTAMENTE seguro de eliminar esta cuenta?',
                    icon: 'error',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'No, cancelar'
                }).then((finalResult) => {
                    if (finalResult.isConfirmed) {
                        Swal.fire({
                            title: 'Eliminando...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: '/easycarluxury/api/v1/admin.php',
                            method: 'DELETE',
                            headers: {
                                'Authorization': 'Bearer ' + token,
                                'Content-Type': 'application/json'
                            },
                            data: JSON.stringify({
                                action: 'delete_user_permanently',
                                user_id: userId
                            }),
                            success: function(response) {
                                if (response.success) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Usuario Eliminado',
                                        text: 'El usuario ha sido eliminado permanentemente',
                                        confirmButtonColor: '#c8a86b'
                                    }).then(() => {
                                        window.location.href = 'users.php';
                                    });
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: response.message || 'Error al eliminar usuario',
                                        confirmButtonColor: '#c8a86b'
                                    });
                                }
                            },
                            error: function() {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Error al eliminar usuario',
                                    confirmButtonColor: '#c8a86b'
                                });
                            }
                        });
                    }
                });
            }
        });
    }
</script>
</body>
</html>