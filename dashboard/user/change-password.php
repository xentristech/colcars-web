<?php
/**
 * EASY CAR LUXURY - Cambio de Contraseña (Endpoint AJAX)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

header('Content-Type: application/json');

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];

$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son requeridos']);
    exit;
}

if (strlen($new_password) < 8) {
    echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 8 caracteres']);
    exit;
}

if ($new_password !== $confirm_password) {
    echo json_encode(['success' => false, 'message' => 'Las contraseñas no coinciden']);
    exit;
}

$user = $db->getOne("SELECT password_hash FROM usuarios WHERE id = ?", [$user_id]);

if (!password_verify($current_password, $user['password_hash'])) {
    echo json_encode(['success' => false, 'message' => 'Contraseña actual incorrecta']);
    exit;
}

$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
$db->update('usuarios', ['password_hash' => $new_hash], 'id = ?', [$user_id]);

logAudit($user_id, 'UPDATE', 'usuarios', $user_id, null, ['password_changed' => true]);

echo json_encode(['success' => true, 'message' => 'Contraseña cambiada exitosamente']);