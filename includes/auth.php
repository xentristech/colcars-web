<?php
/**
 * EASY CAR LUXURY - Middleware de Autenticación
 * Verifica que el usuario esté logueado y tenga permisos
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Asegurar que la clase Database está disponible
if (!class_exists('Database')) {
    require_once __DIR__ . '/../config/database.php';
}

/**
 * CLASE AUTHENTICATION - Para manejo de autenticación con JWT y sesiones
 */
class Authentication {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Verificar credenciales de usuario
     */
    public function authenticate($email, $password) {
        $query = "SELECT u.*, r.nombre as role_name FROM usuarios u 
                  LEFT JOIN roles r ON u.rol_id = r.id 
                  WHERE u.email = :email AND u.activo = 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Actualizar último acceso
            $update = $this->pdo->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = :id");
            $update->execute([':id' => $user['id']]);
            
            return $user;
        }
        
        return false;
    }
    
    /**
     * Generar token JWT para el usuario
     */
    public function generateToken($user) {
        $payload = [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'username' => $user['username'],
            'role' => $user['role_name'] ?? $user['role'] ?? 'usuario',
            'exp' => time() + (86400 * 30) // 30 días de expiración
        ];
        
        // Crear token simple (base64 encode)
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload_encoded = json_encode($payload);
        
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload_encoded));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'easycarluxury_secret_key_2024', true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Validar token JWT
     */
    public function validateToken($token) {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, 'easycarluxury_secret_key_2024', true);
        $base64UrlSignatureCheck = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        if ($base64UrlSignature !== $base64UrlSignatureCheck) {
            return false;
        }
        
        $payload = json_decode(base64_decode($base64UrlPayload), true);
        
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Obtener usuario por ID
     */
    public function getUserById($userId) {
        $query = "SELECT u.*, r.nombre as role_name FROM usuarios u 
                  LEFT JOIN roles r ON u.rol_id = r.id 
                  WHERE u.id = :id";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obtener usuario por token
     */
    public function getUserByToken($token) {
        $payload = $this->validateToken($token);
        if (!$payload || !isset($payload['user_id'])) {
            return null;
        }
        return $this->getUserById($payload['user_id']);
    }
    
    /**
     * Verificar si el usuario está autenticado por token
     */
    public function checkAuth($token) {
        if (!$token) {
            return false;
        }
        $user = $this->getUserByToken($token);
        return $user !== null;
    }
}

/**
 * Verificar que el usuario está autenticado
 * CORREGIDO: Ahora busca 'usuario_id' que es la variable correcta del login
 */
function requireAuth() {
    // Buscar en ambas posibles variables de sesión para compatibilidad
    $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        header('Location: /easycarluxury/public/login.php');
        exit;
    }
    
    // Verificar membresía activa (si existe la función)
    if (function_exists('checkMembership')) {
        checkMembership($userId);
    }
}

/**
 * Verificar que el usuario tiene un rol específico
 */
function requireRole($allowed_roles) {
    requireAuth();
    
    $userRole = $_SESSION['user_role'] ?? null;
    
    if (!in_array($userRole, (array)$allowed_roles)) {
        http_response_code(403);
        die('Acceso denegado. No tienes permisos para acceder a esta página.');
    }
}

/**
 * Verificar que el usuario tiene un permiso específico
 */
function requirePermission($permiso_nombre) {
    requireAuth();
    
    try {
        // Asegurar que Database está disponible
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        $db = Database::getInstance();
        $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
        
        $has_permission = $db->getOne(
            "SELECT p.nombre 
             FROM usuarios u
             JOIN roles_permisos rp ON u.rol_id = rp.rol_id
             JOIN permisos p ON rp.permiso_id = p.id
             WHERE u.id = ? AND p.nombre = ?",
            [$userId, $permiso_nombre]
        );
        
        if (!$has_permission) {
            http_response_code(403);
            die('Acceso denegado. No tienes permiso para realizar esta acción.');
        }
    } catch (Exception $e) {
        error_log("Permission check error: " . $e->getMessage());
        http_response_code(500);
        die('Error al verificar permisos.');
    }
}

/**
 * Verificar que el usuario es el propietario del recurso o tiene rol admin
 */
function requireOwnership($resource_user_id) {
    requireAuth();
    
    $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
    $userRole = $_SESSION['user_role'] ?? null;
    
    $allowed_roles = ['superadmin', 'ingeniero', 'contador'];
    
    if ($userId != $resource_user_id && !in_array($userRole, $allowed_roles)) {
        http_response_code(403);
        die('Acceso denegado. No eres el propietario de este recurso.');
    }
}

/**
 * Obtener usuario actual
 */
function getCurrentUser() {
    $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
    
    if (!$userId) {
        return null;
    }
    
    try {
        // Asegurar que Database está disponible
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        $db = Database::getInstance();
        return $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$userId]);
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Obtener ID del usuario actual
 */
function getCurrentUserId() {
    return $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
}

/**
 * Obtener rol del usuario actual
 */
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? null;
}

// Función canUserPost ELIMINADA - ya existe en functions.php

/**
 * Obtener el tema actual del usuario (claro/oscuro)
 */
function getUserTheme($user_id) {
    if (isset($_COOKIE['user_theme'])) {
        $theme = $_COOKIE['user_theme'];
        if (in_array($theme, ['light', 'dark'])) {
            return $theme;
        }
    }
    
    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        $db = Database::getInstance();
        $user = $db->getOne("SELECT tema_oscuro FROM usuarios WHERE id = ?", [$user_id]);
        
        if ($user) {
            $theme = $user['tema_oscuro'] ? 'dark' : 'light';
            setcookie('user_theme', $theme, time() + 31536000, '/');
            return $theme;
        }
    } catch (Exception $e) {
        error_log("Error en getUserTheme: " . $e->getMessage());
    }
    
    return 'light';
}

/**
 * Actualizar el tema del usuario en base de datos
 */
function updateUserTheme($user_id, $theme) {
    if (!in_array($theme, ['light', 'dark'])) {
        return false;
    }
    
    try {
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        $db = Database::getInstance();
        $tema_oscuro = ($theme === 'dark') ? 1 : 0;
        
        $db->update('usuarios', ['tema_oscuro' => $tema_oscuro], 'id = :id', [':id' => $user_id]);
        
        setcookie('user_theme', $theme, time() + 31536000, '/');
        
        return true;
    } catch (Exception $e) {
        error_log("Error en updateUserTheme: " . $e->getMessage());
        return false;
    }
}

// Usar en cada página del dashboard:
// require_once __DIR__ . '/../includes/auth.php';
// requireAuth()