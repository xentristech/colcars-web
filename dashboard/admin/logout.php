<?php
/**
 * EASY CAR LUXURY - Cerrar Sesión para Administradores (Dashboard Admin)
 * Ubicación: dashboard/admin/logout.php
 * MODIFICADO: Redirige al login con mensaje de sesión cerrada
 */

session_start();

// Obtener información del usuario antes de destruir la sesión
$userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
$userEmail = $_SESSION['email'] ?? null;
$userName = $_SESSION['nombre_completo'] ?? $_SESSION['full_name'] ?? null;

// Registrar el logout en auditoría si es posible
if ($userId) {
    try {
        // Intentar incluir funciones necesarias
        $functionsPath = __DIR__ . '/../../includes/functions.php';
        if (file_exists($functionsPath)) {
            require_once $functionsPath;
            if (function_exists('logAudit')) {
                logAudit($userId, 'LOGOUT', 'usuarios', $userId);
            }
        }
        
        // También intentar registrar en tabla de auditoría directamente
        if (file_exists(__DIR__ . '/../../config/database.php')) {
            require_once __DIR__ . '/../../config/database.php';
            if (isset($pdo) && $pdo !== null) {
                // Verificar si la tabla auditoria existe
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
        // Ignorar errores de auditoría
        error_log("Error al registrar logout en auditoría: " . $e->getMessage());
    }
}

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la cookie de sesión si existe
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

// Limpiar cualquier dato de sesión restante
if (session_status() === PHP_SESSION_ACTIVE) {
    session_unset();
    session_destroy();
}

// ==========================================
// MODIFICADO: Redirigir al login con mensaje de sesión cerrada
// ==========================================
header('Location: /login?error=sesion_cerrada');
exit;
?>