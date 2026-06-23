<?php
/**
 * EASY CAR LUXURY - Cerrar Sesión para Usuarios del Dashboard
 * Ubicación: dashboard/user/logout.php
 */

session_start();

// Obtener información del usuario antes de destruir la sesión
$userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['email'] ?? null;

// Registrar el logout en auditoría
if ($userId) {
    try {
        $functionsPath = __DIR__ . '/../../includes/functions.php';
        if (file_exists($functionsPath)) {
            require_once $functionsPath;
            if (function_exists('logAudit')) {
                logAudit($userId, 'LOGOUT', 'usuarios', $userId);
            }
        }
        
        if (file_exists(__DIR__ . '/../../config/database.php')) {
            require_once __DIR__ . '/../../config/database.php';
            if (isset($pdo) && $pdo !== null) {
                $checkTable = $pdo->query("SHOW TABLES LIKE 'auditoria'");
                if ($checkTable && $checkTable->rowCount() > 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO auditoria (usuario_id, usuario_email, accion, tabla_afectada, ip_address, created_at) 
                        VALUES (:user_id, :email, 'LOGOUT', 'sesion', :ip, NOW())
                    ");
                    $stmt->execute([
                        ':user_id' => $userId,
                        ':email' => $userEmail,
                        ':ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
                    ]);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error al registrar logout en auditoría: " . $e->getMessage());
    }
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Limpiar cookies de autenticación
if (isset($_COOKIE['auth_token'])) {
    setcookie('auth_token', '', time() - 3600, '/');
}
if (isset($_COOKIE['refresh_token'])) {
    setcookie('refresh_token', '', time() - 3600, '/');
}

// Redirigir al login (ruta relativa: sube dos niveles desde dashboard/user/ hasta la raíz easycarluxury, luego entra a public/login.php)
header('Location: /easycarluxury/public/index.php');
exit;
?>