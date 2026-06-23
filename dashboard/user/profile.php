<?php
/**
 * Colcars - Perfil del Usuario
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

$db = Database::getInstance();
$pdo = $db->getConnection();
$user_id = $_SESSION['user_id'];
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

$unread_messages = $db->getOne("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND status = 'unread'", [$user_id]);

$error = '';
$success = '';
$warning = '';

// Separar teléfono en código país y número
$phone_parts = explode(' ', $user['telefono'] ?? '', 2);
$phone_code = $phone_parts[0] ?? '+57';
$phone_number = $phone_parts[1] ?? '';

// Lista de códigos de país
$country_codes = [
    '+57' => 'Colombia (+57)',
    '+58' => 'Venezuela (+58)',
    '+51' => 'Perú (+51)',
    '+56' => 'Chile (+56)',
    '+54' => 'Argentina (+54)',
    '+52' => 'México (+52)',
    '+1' => 'EE.UU/Canadá (+1)',
    '+34' => 'España (+34)',
    '+33' => 'Francia (+33)',
    '+49' => 'Alemania (+49)',
    '+44' => 'Reino Unido (+44)',
    '+55' => 'Brasil (+55)',
    '+598' => 'Uruguay (+598)',
    '+595' => 'Paraguay (+595)',
    '+591' => 'Bolivia (+591)',
    '+593' => 'Ecuador (+593)',
    '+507' => 'Panamá (+507)',
    '+506' => 'Costa Rica (+506)',
    '+503' => 'El Salvador (+503)',
    '+502' => 'Guatemala (+502)',
    '+504' => 'Honduras (+504)',
    '+505' => 'Nicaragua (+505)',
    '+86' => 'China (+86)',
    '+81' => 'Japón (+81)',
    '+82' => 'Corea del Sur (+82)',
    '+91' => 'India (+91)',
];

// Obtener documentos del usuario
$user_documents = [];
$stmt = $pdo->prepare("SELECT id, document_type, file_path, file_name, file_size, mime_type, verified, created_at FROM user_documents WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($user_docs as $doc) {
    $user_documents[$doc['document_type']] = $doc;
}

// Procesar formulario de actualización de perfil
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Token de seguridad inválido';
    } else {
        if ($_POST['action'] === 'update_profile') {
            // Construir teléfono completo con código país
            $phone_code_selected = $_POST['phone_code'] ?? '+57';
            $phone_number_raw = sanitize($_POST['phone_number'] ?? '');
            $full_phone = trim($phone_code_selected . ' ' . $phone_number_raw);
            
            $email = sanitize($_POST['email'] ?? '');
            $direccion = sanitize($_POST['direccion'] ?? '');
            $ciudad = sanitize($_POST['ciudad'] ?? '');
            $departamento = sanitize($_POST['departamento'] ?? '');

            // Validaciones
            if (empty($email)) {
                $error = 'El email es requerido';
            } elseif (!validateEmail($email)) {
                $error = 'Email inválido';
            } elseif (empty($phone_number_raw)) {
                $error = 'El teléfono es requerido';
            } elseif (!validatePhone($phone_number_raw)) {
                $error = 'Teléfono inválido (formato: 3001234567)';
            } else {
                try {
                    // Verificar si el email ya existe en otro usuario
                    if ($email !== $user['email']) {
                        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                        $stmt->execute([$email, $user_id]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($existing) {
                            $error = 'El email ya está registrado por otro usuario';
                        }
                    }

                    if (empty($error)) {
                        // Actualizar usuario usando PDO directamente
                        $sql = "UPDATE usuarios SET 
                                    email = :email, 
                                    telefono = :telefono, 
                                    direccion = :direccion, 
                                    ciudad = :ciudad, 
                                    departamento = :departamento 
                                WHERE id = :id";
                        
                        $stmt = $pdo->prepare($sql);
                        $result = $stmt->execute([
                            ':email' => $email,
                            ':telefono' => $full_phone,
                            ':direccion' => $direccion,
                            ':ciudad' => $ciudad,
                            ':departamento' => $departamento,
                            ':id' => $user_id
                        ]);
                        
                        if ($result) {
                            // Registrar en auditoría
                            logAudit($user_id, 'UPDATE', 'usuarios', $user_id, $user, [
                                'email' => $email,
                                'telefono' => $full_phone,
                                'direccion' => $direccion,
                                'ciudad' => $ciudad,
                                'departamento' => $departamento
                            ]);
                            
                            // Recargar datos del usuario después de actualizar
                            $user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);
                            
                            // Actualizar variables de teléfono
                            $phone_parts = explode(' ', $user['telefono'] ?? '', 2);
                            $phone_code = $phone_parts[0] ?? '+57';
                            $phone_number = $phone_parts[1] ?? '';
                            
                            $success = 'Perfil actualizado correctamente';
                            
                            // Actualizar sesión
                            $_SESSION['user_email'] = $user['email'];
                        } else {
                            $error = 'No se pudo actualizar el perfil: ' . implode(' ', $stmt->errorInfo());
                        }
                    }
                } catch (Exception $e) {
                    $error = 'Error al actualizar el perfil: ' . $e->getMessage();
                }
            }
        } elseif ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (strlen($new_password) < 8) {
                $error = 'La nueva contraseña debe tener al menos 8 caracteres';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Las contraseñas no coinciden';
            } elseif (!password_verify($current_password, $user['password_hash'])) {
                $error = 'Contraseña actual incorrecta';
            } else {
                try {
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET password_hash = :password_hash WHERE id = :id");
                    $result = $stmt->execute([
                        ':password_hash' => $new_hash,
                        ':id' => $user_id
                    ]);
                    
                    if ($result) {
                        logAudit($user_id, 'UPDATE', 'usuarios', $user_id, null, ['password_changed' => true]);
                        $success = 'Contraseña cambiada correctamente. Por favor inicia sesión nuevamente.';
                    } else {
                        $error = 'Error al cambiar la contraseña';
                    }
                } catch (Exception $e) {
                    $error = 'Error al cambiar la contraseña: ' . $e->getMessage();
                }
            }
        }
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
    <title>Mi Perfil - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/easycarluxury/assets/imagenes/favicon/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

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
        [data-theme="dark"] .profile-card,
        [data-theme="dark"] .document-card,
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
        [data-theme="dark"] .info-label,
        [data-theme="dark"] .form-label,
        [data-theme="dark"] .document-info,
        [data-theme="dark"] .document-info a {
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

        /* INPUTS EN MODO OSCURO - COLOR #222F58 */
        [data-theme="dark"] .form-control,
        [data-theme="dark"] .form-select {
            background-color: #222F58 !important;
            border-color: #4a4a5e !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .form-control:focus,
        [data-theme="dark"] .form-select:focus {
            background-color: #2a3a6a !important;
            color: #ffffff !important;
            border-color: #667eea !important;
            box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
        }

        [data-theme="dark"] .form-control::placeholder {
            color: #a0a0b0 !important;
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

        [data-theme="dark"] .btn-warning {
            background-color: #ffc107;
            border-color: #ffc107;
            color: #1a1a2e !important;
        }

        [data-theme="dark"] .btn-warning i {
            color: #1a1a2e !important;
        }

        [data-theme="dark"] .btn-sm.btn-outline-primary {
            color: #ffffff !important;
            border-color: #667eea;
        }

        [data-theme="dark"] .btn-sm.btn-outline-primary:hover {
            background-color: #667eea;
            color: #ffffff !important;
        }

        /* BADGES EN MODO OSCURO */
        [data-theme="dark"] .badge.bg-primary {
            background-color: #667eea !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .badge.bg-secondary {
            background-color: #6c757d !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .badge.bg-info {
            background-color: #0dcaf0 !important;
            color: #1a1a2e !important;
        }

        [data-theme="dark"] .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #1a1a2e !important;
        }

        [data-theme="dark"] .badge.bg-success {
            background-color: #28a745 !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .badge.bg-danger {
            background-color: #dc3545 !important;
            color: #ffffff !important;
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

        [data-theme="dark"] .dashboard-footer .footer-social a i {
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

        /* DOCUMENT CARD EN MODO OSCURO */
        [data-theme="dark"] .document-card {
            background: #16213e !important;
        }

        [data-theme="dark"] .document-info {
            background: rgba(102, 126, 234, 0.15) !important;
        }

        [data-theme="dark"] .document-info a {
            color: #a0c4ff !important;
        }

        /* INFO ROW EN MODO OSCURO */
        [data-theme="dark"] .info-row {
            border-bottom-color: #4a4a5e !important;
        }

        /* ============================================
           CORRECCIONES DE COLORES MODO CLARO
           ============================================ */
        [data-theme="light"] body,
        [data-theme="light"] .main-content,
        [data-theme="light"] .profile-card,
        [data-theme="light"] .document-card,
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
        [data-theme="light"] .form-label {
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

        [data-theme="light"] .btn-warning i {
            color: #212529 !important;
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
        .profile-card {
            background: var(--bg-secondary);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            border: 1px solid var(--border-color);
            transition: transform 0.3s;
        }

        .profile-card:hover {
            transform: translateY(-5px);
        }

        .info-row {
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-label {
            font-weight: 600;
            color: var(--text-secondary);
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

        /* ESTILOS DEL OFFCANVAS MÓVIL */
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

        /* FOOTER */
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

        .document-card {
            background: var(--bg-secondary);
            border-radius: 12px;
            padding: 15px;
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all 0.3s;
        }

        .document-card:hover {
            transform: translateY(-3px);
            border-color: #667eea;
        }

        .document-info {
            margin-top: 10px;
            padding: 8px;
            background: rgba(102, 126, 234, 0.1);
            border-radius: 8px;
            font-size: 0.7rem;
        }

        .document-info a {
            color: #667eea;
            text-decoration: none;
            word-break: break-all;
        }

        .document-info a:hover {
            text-decoration: underline;
        }

        /* Estilos para Select2 */
        .select2-container--bootstrap-5 .select2-selection {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
            color: var(--text-primary);
            min-height: 38px;
        }
        
        .select2-container--bootstrap-5 .select2-dropdown {
            background-color: var(--bg-secondary);
            border-color: var(--border-color);
        }
        
        .select2-container--bootstrap-5 .select2-results__option {
            color: var(--text-primary);
        }
        
        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #667eea;
            color: white;
        }

        /* Grupo de teléfono */
        .phone-group {
            display: flex;
            gap: 10px;
        }
        
        .phone-group .phone-code {
            width: 220px;
            flex-shrink: 0;
        }
        
        .phone-group .phone-number {
            flex: 1;
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
            
            .phone-group {
                flex-direction: column;
                gap: 8px;
            }
            
            .phone-group .phone-code {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- NAVBAR MÓVIL (FUERA DEL DASHBOARD-WRAPPER) -->
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
                <a class="nav-link" href="my-publications.php"><i class="fas fa-list"></i> Mis Publicaciones</a>
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
                <a class="nav-link active" href="profile.php"><i class="fas fa-user"></i> Mi Perfil</a>
                <a class="nav-link" href="settings.php"><i class="fas fa-cog"></i> Configuración</a>
                <hr class="my-2">
                <a class="nav-link" href="/easycarluxury/logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a>
            </nav>
        </div>
    </div>

    <!-- MEMBERSHIP BADGE -->
    <div class="membership-badge">
        <i class="fas fa-crown"></i> Cuenta: <?php echo strtoupper($user['tipo_cuenta']); ?>
        <?php if ($user['tipo_cuenta'] != 'free'): ?>
            <small>(Expira: <?php echo date('d/m/Y', strtotime($user['fecha_expiracion'])); ?>)</small>
        <?php endif; ?>
    </div>

    <!-- BOTÓN TEMA -->
    <button class="btn-theme" onclick="toggleTheme()"><i class="fas fa-moon"></i></button>

    <!-- ESTRUCTURA PRINCIPAL -->
    <div class="dashboard-wrapper">
        <!-- COLUMNA DEL SIDEBAR (incluye el sidebar desktop) -->
        <div class="sidebar-column">
            <?php include __DIR__ . '/../includes/user-sidebar.php'; ?>
        </div>

        <!-- COLUMNA DEL CONTENIDO -->
        <div class="content-column">
            <div class="main-content">
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <h2><i class="fas fa-user"></i> Mi Perfil</h2>
                </div>
                <p class="text-muted">Gestiona tu información personal</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php if (strpos($success, 'contraseña') !== false): ?>
                        <script>
                            setTimeout(function() {
                                window.location.href = '/easycarluxury/logout';
                            }, 3000);
                        </script>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="row">
                    <!-- Datos de Identificación -->
                    <div class="col-md-12">
                        <div class="profile-card">
                            <h5><i class="fas fa-id-card"></i> Datos de Identificación</h5>
                            <p class="text-muted small">Estos datos no se pueden modificar por seguridad</p>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-4 col-md-3 info-label">Tipo Documento:</div>
                                    <div class="col-8 col-md-9"><?php echo htmlspecialchars($user['tipo_documento'] ?? 'CC'); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-4 col-md-3 info-label">Número Documento:</div>
                                    <div class="col-8 col-md-9"><?php echo htmlspecialchars($user['numero_documento'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-4 col-md-3 info-label">Nombre Completo:</div>
                                    <div class="col-8 col-md-9"><?php echo htmlspecialchars($user['nombre_completo'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-4 col-md-3 info-label">Usuario:</div>
                                    <div class="col-8 col-md-9"><?php echo htmlspecialchars($user['username'] ?? ''); ?></div>
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-4 col-md-3 info-label">Tipo Cuenta:</div>
                                    <div class="col-8 col-md-9">
                                        <span class="badge bg-<?php echo $user['tipo_cuenta'] == 'elite' ? 'warning' : ($user['tipo_cuenta'] == 'premium' ? 'info' : ($user['tipo_cuenta'] == 'pro' ? 'primary' : 'secondary')); ?>">
                                            <?php echo strtoupper($user['tipo_cuenta']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php if ($user['tipo_cuenta'] != 'free'): ?>
                                <div class="info-row">
                                    <div class="row">
                                        <div class="col-4 col-md-3 info-label">Expiración:</div>
                                        <div class="col-8 col-md-9"><?php echo date('d/m/Y', strtotime($user['fecha_expiracion'])); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="info-row">
                                <div class="row">
                                    <div class="col-4 col-md-3 info-label">Verificado DIAN:</div>
                                    <div class="col-8 col-md-9">
                                        <?php if ($user['verificado_dian']): ?>
                                            <i class="fas fa-check-circle text-success"></i> Verificado
                                        <?php else: ?>
                                            <i class="fas fa-times-circle text-danger"></i> No verificado 
                                            <a href="#" onclick="verifyDIAN()" class="ms-2">Verificar ahora</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de Contacto -->
                    <div class="col-md-6">
                        <form method="POST" action="" id="profileForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="profile-card">
                                <h5><i class="fas fa-address-card"></i> Información de Contacto</h5>
                                <div class="mb-3">
                                    <label class="form-label">Email *</label>
                                    <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Teléfono *</label>
                                    <div class="phone-group">
                                        <div class="phone-code">
                                            <select name="phone_code" class="form-select phone-code-select">
                                                <?php foreach ($country_codes as $code => $name): ?>
                                                    <option value="<?php echo $code; ?>" <?php echo $phone_code == $code ? 'selected' : ''; ?>>
                                                        <?php echo $name; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="phone-number">
                                            <input type="tel" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" placeholder="3001234567" required>
                                        </div>
                                    </div>
                                    <small class="text-muted">Ejemplo: 3001234567 (solo números)</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Dirección</label>
                                    <input type="text" class="form-control" name="direccion" value="<?php echo htmlspecialchars($user['direccion'] ?? ''); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Ciudad</label>
                                        <input type="text" class="form-control" name="ciudad" value="<?php echo htmlspecialchars($user['ciudad'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Departamento</label>
                                        <input type="text" class="form-control" name="departamento" value="<?php echo htmlspecialchars($user['departamento'] ?? ''); ?>">
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Guardar Cambios</button>
                            </div>
                        </form>
                    </div>

                    <!-- Cambiar Contraseña -->
                    <div class="col-md-6">
                        <div class="profile-card">
                            <h5><i class="fas fa-key"></i> Cambiar Contraseña</h5>
                            <form id="passwordForm" method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <div class="mb-3">
                                    <label class="form-label">Contraseña Actual</label>
                                    <input type="password" class="form-control" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nueva Contraseña</label>
                                    <input type="password" class="form-control" name="new_password" id="new_password" required>
                                    <small class="text-muted">Mínimo 8 caracteres</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirmar Nueva Contraseña</label>
                                    <input type="password" class="form-control" name="confirm_password" required>
                                </div>
                                <button type="submit" class="btn btn-warning w-100"><i class="fas fa-exchange-alt"></i> Cambiar Contraseña</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Documentos de Identidad -->
                <div class="profile-card">
                    <h5><i class="fas fa-file-upload"></i> Documentos de Identidad</h5>
                    <p class="text-muted">Sube tus documentos para verificar tu identidad (Requerido para facturación DIAN)</p>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="document-card">
                                <i class="fas fa-id-card fa-3x mb-2" style="color: #667eea;"></i>
                                <h6>Cédula</h6>
                                <?php if (isset($user_documents['cedula'])): ?>
                                    <div class="document-info">
                                        <i class="fas fa-file-<?php echo $user_documents['cedula']['mime_type'] == 'pdf' ? 'pdf' : 'image'; ?>"></i>
                                        <a href="<?php echo $user_documents['cedula']['file_path']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($user_documents['cedula']['file_name']); ?>
                                        </a>
                                        <small class="d-block text-muted">
                                            Subido: <?php echo date('d/m/Y', strtotime($user_documents['cedula']['created_at'])); ?>
                                            <?php if ($user_documents['cedula']['verified']): ?>
                                                <span class="badge bg-success ms-1">Verificado</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-primary mt-2" onclick="uploadDocument('cedula')">
                                    <i class="fas fa-upload"></i> <?php echo isset($user_documents['cedula']) ? 'Reemplazar' : 'Subir'; ?>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="document-card">
                                <i class="fas fa-building fa-3x mb-2" style="color: #667eea;"></i>
                                <h6>RUT</h6>
                                <?php if (isset($user_documents['rut'])): ?>
                                    <div class="document-info">
                                        <i class="fas fa-file-<?php echo $user_documents['rut']['mime_type'] == 'pdf' ? 'pdf' : 'image'; ?>"></i>
                                        <a href="<?php echo $user_documents['rut']['file_path']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($user_documents['rut']['file_name']); ?>
                                        </a>
                                        <small class="d-block text-muted">
                                            Subido: <?php echo date('d/m/Y', strtotime($user_documents['rut']['created_at'])); ?>
                                            <?php if ($user_documents['rut']['verified']): ?>
                                                <span class="badge bg-success ms-1">Verificado</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-primary mt-2" onclick="uploadDocument('rut')">
                                    <i class="fas fa-upload"></i> <?php echo isset($user_documents['rut']) ? 'Reemplazar' : 'Subir'; ?>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="document-card">
                                <i class="fas fa-file-invoice fa-3x mb-2" style="color: #667eea;"></i>
                                <h6>Resolución DIAN</h6>
                                <?php if (isset($user_documents['resolucion_dian'])): ?>
                                    <div class="document-info">
                                        <i class="fas fa-file-<?php echo $user_documents['resolucion_dian']['mime_type'] == 'pdf' ? 'pdf' : 'image'; ?>"></i>
                                        <a href="<?php echo $user_documents['resolucion_dian']['file_path']; ?>" target="_blank">
                                            <?php echo htmlspecialchars($user_documents['resolucion_dian']['file_name']); ?>
                                        </a>
                                        <small class="d-block text-muted">
                                            Subido: <?php echo date('d/m/Y', strtotime($user_documents['resolucion_dian']['created_at'])); ?>
                                            <?php if ($user_documents['resolucion_dian']['verified']): ?>
                                                <span class="badge bg-success ms-1">Verificado</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php endif; ?>
                                <button class="btn btn-sm btn-primary mt-2" onclick="uploadDocument('resolucion_dian')">
                                    <i class="fas fa-upload"></i> <?php echo isset($user_documents['resolucion_dian']) ? 'Reemplazar' : 'Subir'; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
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
        // Función para obtener el tema actual
        function getCurrentTheme() {
            return document.documentElement.getAttribute('data-theme') || 'light';
        }

        // Función para mostrar SweetAlert2 con el tema adecuado
        function showSwalWithTheme(options) {
            const theme = getCurrentTheme();
            const isDark = theme === 'dark';
            
            const swalOptions = {
                ...options,
                background: isDark ? '#1a1a2e' : '#ffffff',
                color: isDark ? '#ffffff' : '#212529',
                confirmButtonColor: '#667eea',
                cancelButtonColor: isDark ? '#dc3545' : '#6c757d',
                backdrop: isDark ? 'rgba(0, 0, 0, 0.8)' : 'rgba(0, 0, 0, 0.4)',
            };
            
            return Swal.fire(swalOptions);
        }

        // Inicializar Select2 para mejor experiencia
        $(document).ready(function() {
            $('.phone-code-select').select2({
                theme: 'bootstrap-5',
                width: '100%',
                dropdownAutoWidth: true
            });
        });

        function toggleTheme() {
            const html = document.documentElement;
            const newTheme = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', newTheme);
            document.cookie = `user_theme=${newTheme}; path=/; max-age=31536000`;
            $.ajax({ url: '/easycarluxury/api/v1/users/settings.php', method: 'POST', data: { theme: newTheme } });
        }

        // Cambiar contraseña
        $('#passwordForm').on('submit', function(e) {
            e.preventDefault();
            const newPass = $('#new_password').val();
            
            if (newPass.length < 8) {
                showSwalWithTheme({
                    title: 'Error',
                    text: 'La contraseña debe tener al menos 8 caracteres',
                    icon: 'error',
                    confirmButtonText: 'Aceptar'
                });
                return;
            }
            
            showSwalWithTheme({
                title: '¿Cambiar contraseña?',
                text: 'Serás desconectado después del cambio',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, cambiar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: window.location.href,
                        method: 'POST',
                        data: $(this).serialize(),
                        success: function(response) {
                            showSwalWithTheme({
                                title: 'Éxito',
                                text: 'Contraseña cambiada correctamente. Por favor inicia sesión nuevamente.',
                                icon: 'success',
                                confirmButtonText: 'Aceptar'
                            }).then(() => {
                                window.location.href = '/easycarluxury/logout';
                            });
                        },
                        error: function() {
                            showSwalWithTheme({
                                title: 'Error',
                                text: 'Error al cambiar contraseña',
                                icon: 'error',
                                confirmButtonText: 'Cerrar'
                            });
                        }
                    });
                }
            });
        });

        // Verificar DIAN
        function verifyDIAN() {
            showSwalWithTheme({
                title: 'Verificar con DIAN',
                text: '¿Deseas verificar tus datos con la DIAN?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Verificar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    showSwalWithTheme({
                        title: 'Procesando',
                        text: 'Verificando con la DIAN...',
                        icon: 'info',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    setTimeout(() => {
                        showSwalWithTheme({
                            title: 'Verificado',
                            text: 'Tus datos han sido verificados correctamente',
                            icon: 'success',
                            confirmButtonText: 'Aceptar'
                        }).then(() => {
                            location.reload();
                        });
                    }, 1500);
                }
            });
        }

        // Subir documento
        function uploadDocument(type) {
            let title = '';
            let docTypeName = '';
            if (type === 'cedula') {
                title = 'Subir Cédula';
                docTypeName = 'Cédula';
            } else if (type === 'rut') {
                title = 'Subir RUT';
                docTypeName = 'RUT';
            } else {
                title = 'Subir Resolución DIAN';
                docTypeName = 'Resolución DIAN';
            }
            
            showSwalWithTheme({
                title: title,
                html: '<input type="file" id="docFile" accept=".pdf,.jpg,.jpeg,.png" class="form-control">',
                showCancelButton: true,
                confirmButtonText: 'Subir',
                cancelButtonText: 'Cancelar',
                preConfirm: () => {
                    const file = document.getElementById('docFile').files[0];
                    if (!file) {
                        Swal.showValidationMessage('Selecciona un archivo');
                        return false;
                    }
                    if (file.size > 5 * 1024 * 1024) {
                        Swal.showValidationMessage('El archivo no debe superar los 5MB');
                        return false;
                    }
                    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                    if (!allowedTypes.includes(file.type)) {
                        Swal.showValidationMessage('Formato no permitido. Use PDF, JPG o PNG');
                        return false;
                    }
                    return file;
                }
            }).then((result) => {
                if (result.value) {
                    const formData = new FormData();
                    formData.append('document', result.value);
                    formData.append('type', type);
                    
                    showSwalWithTheme({
                        title: 'Subiendo...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    $.ajax({
                        url: '/easycarluxury/api/v1/upload-document.php',
                        method: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            let data;
                            try {
                                data = typeof response === 'string' ? JSON.parse(response) : response;
                            } catch(e) {
                                data = { success: false, error: 'Respuesta inválida del servidor' };
                            }
                            if (data.success) {
                                showSwalWithTheme({
                                    title: 'Éxito',
                                    text: docTypeName + ' subido correctamente',
                                    icon: 'success',
                                    confirmButtonText: 'Aceptar'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                showSwalWithTheme({
                                    title: 'Error',
                                    text: data.error || 'No se pudo subir el documento',
                                    icon: 'error',
                                    confirmButtonText: 'Cerrar'
                                });
                            }
                        },
                        error: function(xhr) {
                            let errorMsg = 'Error al subir el documento';
                            try {
                                const response = JSON.parse(xhr.responseText);
                                if (response.error) errorMsg = response.error;
                            } catch(e) {}
                            showSwalWithTheme({
                                title: 'Error',
                                text: errorMsg,
                                icon: 'error',
                                confirmButtonText: 'Cerrar'
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>