<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\edit_contact_message.php
 * 
 * Página independiente para editar mensajes de contacto
 * MODIFICADO: Misma estructura que audit.php (sidebar, footer, tema oscuro/claro, responsive)
 * Funcionalidades:
 * - Cargar mensaje por ID
 * - Editar todos los campos (nombre, email, teléfono, whatsapp, mensaje, estado)
 * - Validación de campos y email
 * - Redirección a contact_messages.php después de guardar
 * - Registro de auditoría
 */

// Iniciar sesión
session_start();

// Verificar si el usuario está logueado y tiene rol de administrador
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login.php');
    exit;
}

// Verificar rol de administrador (rol_id 1,2,3,4,5)
$rolPermitido = in_array($_SESSION['rol_id'], [1, 2, 3, 4, 5]);
if (!$rolPermitido) {
    header('Location: /dashboard/user/');
    exit;
}

require_once '../../config/database.php';
$database = Database::getInstance();
$pdo = $database->getConnection();

// Obtener el tema del administrador
$theme = $_COOKIE['admin_theme'] ?? 'light';

// Obtener ID del mensaje
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    header('Location: contact_messages.php?error=ID no válido');
    exit;
}

// Variables para el formulario
$mensaje = '';
$error = '';
$mensajeData = null;

// Función para registrar auditoría
function registrarAuditoria($pdo, $accion, $tabla, $registroId, $detalles) {
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $usuarioEmail = $_SESSION['email'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    $sql = "INSERT INTO auditoria (usuario_id, usuario_email, accion, tabla_afectada, registro_id, datos_nuevos, ip_address, user_agent, created_at) 
            VALUES (:usuario_id, :usuario_email, :accion, :tabla, :registro_id, :detalles, :ip, :user_agent, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':usuario_email' => $usuarioEmail,
        ':accion' => $accion,
        ':tabla' => $tabla,
        ':registro_id' => $registroId,
        ':detalles' => $detalles,
        ':ip' => $ip,
        ':user_agent' => $userAgent
    ]);
}

// Cargar datos del mensaje
$sql = "SELECT * FROM contact_messages WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $id]);
$mensajeData = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mensajeData) {
    header('Location: contact_messages.php?error=Mensaje no encontrado');
    exit;
}

// Procesar formulario de edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_completo = trim($_POST['nombre_completo'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $mensaje_texto = trim($_POST['mensaje'] ?? '');
    $status = $_POST['status'] ?? 'pendiente';
    
    // Validaciones
    if (empty($nombre_completo) || empty($email) || empty($telefono) || empty($mensaje_texto)) {
        $error = 'Todos los campos obligatorios deben estar llenos.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo electrónico no es válido.';
    } else {
        // Guardar datos anteriores para auditoría
        $oldData = [
            'nombre_completo' => $mensajeData['nombre_completo'],
            'email' => $mensajeData['email'],
            'telefono' => $mensajeData['telefono'],
            'whatsapp' => $mensajeData['whatsapp'],
            'mensaje' => $mensajeData['mensaje'],
            'status' => $mensajeData['status']
        ];
        
        $sql = "UPDATE contact_messages SET 
                    nombre_completo = :nombre_completo, 
                    email = :email, 
                    telefono = :telefono, 
                    whatsapp = :whatsapp, 
                    mensaje = :mensaje, 
                    status = :status, 
                    updated_at = NOW() 
                WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        
        if ($stmt->execute([
            ':nombre_completo' => $nombre_completo,
            ':email' => $email,
            ':telefono' => $telefono,
            ':whatsapp' => $whatsapp ?: null,
            ':mensaje' => $mensaje_texto,
            ':status' => $status,
            ':id' => $id
        ])) {
            // Registrar auditoría
            $newData = [
                'nombre_completo' => $nombre_completo,
                'email' => $email,
                'telefono' => $telefono,
                'whatsapp' => $whatsapp,
                'mensaje' => $mensaje_texto,
                'status' => $status
            ];
            $detalles = json_encode([
                'accion' => 'editar_mensaje',
                'old_data' => $oldData,
                'new_data' => $newData
            ]);
            registrarAuditoria($pdo, 'editar', 'contact_messages', $id, $detalles);
            
            // Redirigir con mensaje de éxito
            header('Location: contact_messages.php?mensaje=Mensaje editado correctamente');
            exit;
        } else {
            $error = 'Error al guardar los cambios.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Mensaje de Contacto - Panel Admin</title>
    <!-- Ruta: /dashboard/admin/edit_contact_message.php -->
    
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

        .sidebar-column {
            flex-shrink: 0;
        }

        .admin-main {
            flex: 1;
            width: auto;
            padding: 15px 15px;
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

        .edit-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .form-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-label .required {
            color: #dc3545;
            margin-left: 3px;
        }

        .form-control, .form-select {
            padding: 8px 12px;
            border: 1px solid var(--input-border);
            border-radius: 8px;
            font-size: 0.9rem;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            outline: none;
            border-color: #c8a86b;
            box-shadow: 0 0 0 3px rgba(200,168,107,0.2);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            background: #5a6268;
        }

        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
        }

        [data-theme="dark"] .btn-secondary:hover {
            background: #3a3a4e;
        }

        .info-box {
            background: var(--bg-primary);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border-left: 4px solid #c8a86b;
        }

        .info-box i {
            color: #c8a86b;
            margin-right: 10px;
        }

        .info-box .info-label {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .info-box .info-value {
            color: var(--text-secondary);
            font-size: 0.85rem;
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
            border-radius: 10px;
            padding: 12px 20px;
            margin-bottom: 20px;
            border: none;
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        [data-theme="dark"] .alert-success {
            background-color: #0f3b2c;
            color: #a5d6a5;
            border-left-color: #27ae60;
        }

        [data-theme="dark"] .alert-danger {
            background-color: #3b1e1e;
            color: #f5a5a5;
            border-left-color: #e74c3c;
        }

        /* Badge de estado */
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-pendiente { background: #f39c12; color: #fff; }
        .badge-respondido { background: #27ae60; color: #fff; }
        .badge-archivado { background: #3498db; color: #fff; }
        .badge-eliminado { background: #e74c3c; color: #fff; }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px;
            }
        }

        @media (max-width: 768px) {
            .btn-theme {
                bottom: 70px;
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .edit-card {
                padding: 15px;
            }
            .form-label {
                font-size: 0.85rem;
            }
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
                    <h1><i class="fas fa-edit me-2"></i>Editar Mensaje de Contacto</h1>
                    <p>Modifica la información del mensaje recibido</p>
                </div>
                <div>
                    <a href="contact_messages.php" class="btn-secondary" style="padding: 8px 16px; text-decoration: none;">
                        <i class="fas fa-arrow-left"></i> Volver al listado
                    </a>
                </div>
            </div>
            
            <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <!-- Información del mensaje original -->
            <div class="info-box">
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i> Fecha de creación
                        </div>
                        <div class="info-value">
                            <?php echo date('d/m/Y H:i:s', strtotime($mensajeData['created_at'])); ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">
                            <i class="fas fa-tag"></i> Estado actual
                        </div>
                        <div class="info-value">
                            <span class="badge-status 
                                <?php echo $mensajeData['status'] == 'pendiente' ? 'badge-pendiente' : ($mensajeData['status'] == 'respondido' ? 'badge-respondido' : ($mensajeData['status'] == 'archivado' ? 'badge-archivado' : 'badge-eliminado')); ?>">
                                <?php 
                                    echo $mensajeData['status'] == 'pendiente' ? 'Pendiente' : 
                                        ($mensajeData['status'] == 'respondido' ? 'Respondido' : 
                                        ($mensajeData['status'] == 'archivado' ? 'Archivado' : 'Eliminado'));
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-label">
                            <i class="fas fa-clock"></i> Última actualización
                        </div>
                        <div class="info-value">
                            <?php echo !empty($mensajeData['updated_at']) ? date('d/m/Y H:i:s', strtotime($mensajeData['updated_at'])) : 'No actualizado'; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Formulario de edición -->
            <div class="edit-card">
                <form method="POST" id="editForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-user"></i> Nombre Completo 
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="nombre_completo" class="form-control" 
                                   value="<?php echo htmlspecialchars($mensajeData['nombre_completo']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i> Email 
                                <span class="required">*</span>
                            </label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($mensajeData['email']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-phone"></i> Teléfono 
                                <span class="required">*</span>
                            </label>
                            <input type="text" name="telefono" class="form-control" 
                                   value="<?php echo htmlspecialchars($mensajeData['telefono']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </label>
                            <input type="text" name="whatsapp" class="form-control" 
                                   value="<?php echo htmlspecialchars($mensajeData['whatsapp'] ?? ''); ?>">
                            <small class="text-muted">Formato: 3000000000 (10 dígitos)</small>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">
                                <i class="fas fa-comment"></i> Mensaje 
                                <span class="required">*</span>
                            </label>
                            <textarea name="mensaje" class="form-control" rows="6" required><?php 
                                echo htmlspecialchars($mensajeData['mensaje']); 
                            ?></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                <i class="fas fa-tag"></i> Estado
                            </label>
                            <select name="status" class="form-select">
                                <option value="pendiente" <?php echo $mensajeData['status'] == 'pendiente' ? 'selected' : ''; ?>>
                                    Pendiente
                                </option>
                                <option value="respondido" <?php echo $mensajeData['status'] == 'respondido' ? 'selected' : ''; ?>>
                                    Respondido
                                </option>
                                <option value="archivado" <?php echo $mensajeData['status'] == 'archivado' ? 'selected' : ''; ?>>
                                    Archivado
                                </option>
                                <option value="eliminado" <?php echo $mensajeData['status'] == 'eliminado' ? 'selected' : ''; ?>>
                                    Eliminado
                                </option>
                            </select>
                        </div>
                        
                        <?php if (!empty($mensajeData['respuesta'])): ?>
                        <div class="col-12 mb-3">
                            <label class="form-label">
                                <i class="fas fa-reply-all"></i> Respuesta anterior
                            </label>
                            <div class="form-control" style="background: var(--bg-primary); min-height: 80px;" readonly>
                                <?php echo nl2br(htmlspecialchars($mensajeData['respuesta'])); ?>
                            </div>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> La respuesta se mantiene al editar el mensaje
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
                        </button>
                        <a href="contact_messages.php" class="btn-secondary" style="text-decoration: none;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>
    
    <!-- Botón para cambiar tema claro/oscuro -->
    <button class="btn-theme" onclick="toggleTheme()">
        <i class="fas fa-moon"></i>
    </button>
    
    <!-- Footer -->
    <?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>
    
    <!-- SweetAlert2 JS - CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        
        // Validación del formulario antes de enviar
        $('#editForm').on('submit', function(e) {
            const nombre = $('input[name="nombre_completo"]').val().trim();
            const email = $('input[name="email"]').val().trim();
            const telefono = $('input[name="telefono"]').val().trim();
            const mensaje = $('textarea[name="mensaje"]').val().trim();
            
            if (!nombre || !email || !telefono || !mensaje) {
                e.preventDefault();
                showSwalWithTheme({
                    title: 'Campos incompletos',
                    text: 'Por favor completa todos los campos obligatorios (*)',
                    icon: 'warning',
                    confirmButtonText: 'Entendido'
                });
                return false;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                showSwalWithTheme({
                    title: 'Email inválido',
                    text: 'Por favor ingresa un correo electrónico válido',
                    icon: 'error',
                    confirmButtonText: 'Corregir'
                });
                return false;
            }
            
            const telefonoRegex = /^[0-9]{7,15}$/;
            if (!telefonoRegex.test(telefono.replace(/[\s\-\(\)\+]/g, ''))) {
                e.preventDefault();
                showSwalWithTheme({
                    title: 'Teléfono inválido',
                    text: 'Por favor ingresa un número de teléfono válido (solo números, 7-15 dígitos)',
                    icon: 'error',
                    confirmButtonText: 'Corregir'
                });
                return false;
            }
            
            // Confirmar antes de guardar
            e.preventDefault();
            showSwalWithTheme({
                title: '¿Guardar cambios?',
                text: '¿Estás seguro de que deseas guardar los cambios realizados?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, guardar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#editForm').off('submit').submit();
                }
            });
            
            return false;
        });
    </script>
</body>
</html>