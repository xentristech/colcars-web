<?php
/**
 * EASY CAR LUXURY - Redirección al formulario de recuperación
 * Este archivo recibe el token vía GET y redirige a forgot-password.php con el token
 */

$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: forgot-password.php');
    exit;
}

// Redirigir a forgot-password.php con el token
header('Location: forgot-password.php?token=' . urlencode($token));
exit;
?>