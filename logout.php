<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\user\logout.php
 * EASY CAR LUXURY - Cierre de Sesión (Dashboard User)
 * MODIFICADO: Redirige al login con mensaje de sesión cerrada
 */

// Iniciar sesión para poder destruirla
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// DESTRUIR LA SESIÓN COMPLETAMENTE
// ==========================================

// 1. Limpiar todas las variables de sesión
$_SESSION = array();

// 2. Destruir la sesión
session_destroy();

// 3. Limpiar cookies de sesión si existen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Eliminar cualquier otra cookie de sesión que pueda existir
if (isset($_COOKIE['PHPSESSID'])) {
    setcookie('PHPSESSID', '', time() - 3600, '/');
}

// 5. Redirigir al login con mensaje de sesión cerrada
header('Location: /login?error=sesion_cerrada');
exit;
?>