<?php
/**
 * EASY CAR LUXURY - API de Autenticación
 * Endpoints para login/registro vía AJAX
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$endpoint = str_replace('/api/v1/auth/', '', $path);

switch ($method) {
    case 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        
        if ($endpoint === 'login') {
            handleLogin($input);
        } elseif ($endpoint === 'register') {
            handleRegister($input);
        } elseif ($endpoint === 'refresh') {
            handleRefresh($input);
        } else {
            jsonError('Endpoint no encontrado', 404);
        }
        break;
    
    case 'GET':
        if ($endpoint === 'verify') {
            $token = $_GET['token'] ?? '';
            handleVerify($token);
        } else {
            jsonError('Endpoint no encontrado', 404);
        }
        break;
    
    default:
        jsonError('Método no permitido', 405);
        break;
}

function handleLogin($data) {
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        jsonError('Email y contraseña son requeridos', 400);
    }
    
    try {
        $db = Database::getInstance();
        
        $user = $db->getOne(
            "SELECT u.*, r.nombre as rol_nombre 
             FROM usuarios u 
             JOIN roles r ON u.rol_id = r.id 
             WHERE (u.email = ? OR u.username = ?) AND u.activo = 1",
            [$email, $email]
        );
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['email_verificado']) {
                jsonError('Por favor verifica tu email antes de iniciar sesión', 403);
            }
            
            // Actualizar último acceso
            $db->update('usuarios', 
                ['ultimo_acceso' => date('Y-m-d H:i:s')], 
                'id = ?', 
                [$user['id']]
            );
            
            logAudit($user['id'], 'LOGIN', 'usuarios', $user['id']);
            
            // Generar JWT
            $payload = [
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['rol_nombre'],
                'exp' => time() + JWT_EXPIRY
            ];
            
            $jwt = JWT::encode($payload, JWT_SECRET, 'HS256');
            
            jsonResponse([
                'token' => $jwt,
                'user' => [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['nombre_completo'],
                    'role' => $user['rol_nombre'],
                    'tipo_cuenta' => $user['tipo_cuenta']
                ]
            ], 'Login exitoso');
        } else {
            logAudit(null, 'LOGIN_FAILED', 'usuarios', 0, null, ['email' => $email]);
            jsonError('Credenciales incorrectas', 401);
        }
    } catch (Exception $e) {
        error_log("API Login error: " . $e->getMessage());
        jsonError('Error al iniciar sesión', 500);
    }
}

function handleRegister($data) {
    // Validaciones similares a register.php
    $errors = [];
    
    if (empty($data['nombre_completo'])) {
        $errors[] = 'Nombre completo requerido';
    }
    if (!validateEmail($data['email'] ?? '')) {
        $errors[] = 'Email inválido';
    }
    if (strlen($data['password'] ?? '') < 8) {
        $errors[] = 'Contraseña debe tener al menos 8 caracteres';
    }
    
    if (!empty($errors)) {
        jsonError('Errores de validación', 400, $errors);
    }
    
    try {
        $db = Database::getInstance();
        
        // Verificar existencia
        $existing = $db->getOne(
            "SELECT id FROM usuarios WHERE email = ? OR username = ?",
            [$data['email'], $data['username']]
        );
        
        if ($existing) {
            jsonError('El email o usuario ya está registrado', 409);
        }
        
        $verification_token = bin2hex(random_bytes(32));
        $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $user_id = $db->insert('usuarios', [
            'email' => $data['email'],
            'username' => $data['username'],
            'password_hash' => $password_hash,
            'nombre_completo' => $data['nombre_completo'],
            'tipo_documento' => $data['tipo_documento'] ?? 'CC',
            'numero_documento' => $data['numero_documento'],
            'telefono' => $data['telefono'],
            'rol_id' => 6,
            'tipo_cuenta' => 'free',
            'fecha_expiracion' => date('Y-m-d', strtotime('+30 days')),
            'limite_publicaciones_int' => 2,
            'email_verification_token' => $verification_token
        ]);
        
        if ($user_id) {
            logAudit($user_id, 'CREATE', 'usuarios', $user_id, null, $data);
            
            jsonResponse([
                'user_id' => $user_id,
                'verification_token' => $verification_token
            ], 'Registro exitoso. Verifica tu email.', 201);
        } else {
            jsonError('Error al crear usuario', 500);
        }
    } catch (Exception $e) {
        error_log("API Register error: " . $e->getMessage());
        jsonError('Error en el registro', 500);
    }
}

function handleVerify($token) {
    if (empty($token)) {
        jsonError('Token inválido', 400);
    }
    
    try {
        $db = Database::getInstance();
        
        $user = $db->getOne(
            "SELECT id FROM usuarios WHERE email_verification_token = ? AND email_verificado = 0",
            [$token]
        );
        
        if ($user) {
            $db->update('usuarios', 
                ['email_verificado' => 1, 'email_verification_token' => null], 
                'id = ?', 
                [$user['id']]
            );
            
            logAudit($user['id'], 'UPDATE', 'usuarios', $user['id'], null, ['email_verificado' => true]);
            
            jsonResponse(null, 'Email verificado exitosamente');
        } else {
            jsonError('Token inválido o ya verificado', 400);
        }
    } catch (Exception $e) {
        jsonError('Error al verificar email', 500);
    }
}