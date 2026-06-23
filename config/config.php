<?php
/**
 * EASY CAR LUXURY - Configuración Global
 * Carga de variables de entorno y configuraciones del sistema
 */

// Configurar zona horaria
date_default_timezone_set('America/Bogota');

// Cargar variables de entorno desde .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Saltar comentarios
        if (strpos($line, '#') === 0) continue;
        // Saltar líneas vacías
        if (empty($line)) continue;
        
        // Buscar el signo =
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Eliminar comillas si las tiene
            if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            if (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                $value = substr($value, 1, -1);
            }
            
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

// Limpiar y asegurar variables obligatorias
if (!isset($_ENV['APP_ENV']) || empty($_ENV['APP_ENV'])) $_ENV['APP_ENV'] = 'development';
if (!isset($_ENV['APP_URL']) || empty($_ENV['APP_URL'])) $_ENV['APP_URL'] = 'http://localhost/easycarluxury';
if (!isset($_ENV['DB_HOST'])) $_ENV['DB_HOST'] = 'localhost';
if (!isset($_ENV['DB_NAME'])) $_ENV['DB_NAME'] = 'easycarluxury';
if (!isset($_ENV['DB_USER'])) $_ENV['DB_USER'] = 'root';
if (!isset($_ENV['DB_PASS'])) $_ENV['DB_PASS'] = '';

// Configurar manejo de errores
if ($_ENV['APP_ENV'] === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
}

// Definir constantes globales
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', $_ENV['APP_URL']);
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('TEMP_PATH', BASE_PATH . '/uploads/temp/');
define('LOG_PATH', BASE_PATH . '/logs/');
define('BACKUP_PATH', BASE_PATH . '/backups/');

// Configurar sesión (no iniciar aquí, se iniciará cuando sea necesario)
if (!defined('SESSION_STARTED')) {
    define('SESSION_STARTED', false);
}

// Configurar límites de subida
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_file_uploads', '20');
ini_set('memory_limit', '256M');

// Configurar JWT
define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'default-secret-key-change-me');
define('JWT_EXPIRY', 3600 * 24 * 7); // 7 días

// Configurar membresías
define('MEMBERSHIP_PRICES', [
    'free' => 0,
    'pro' => 49900,
    'premium' => 89900,
    'elite' => 168000
]);

define('MEMBERSHIP_LIMITS', [
    'free' => 2,
    'pro' => 999999,
    'premium' => 999999,
    'elite' => 999999
]);

define('MEMBERSHIP_DAYS', [
    'free' => 30,
    'pro' => 30,
    'premium' => 30,
    'elite' => 30
]);

// Configurar DIAN
define('DIAN_ENVIRONMENT', $_ENV['DIAN_ENVIRONMENT'] ?? 'test');
define('DIAN_NIT', $_ENV['DIAN_NIT'] ?? '900123456');
define('DIAN_RESOLUTION_NUMBER', $_ENV['DIAN_RESOLUTION_NUMBER'] ?? '18760000001');

// Configurar IVA
define('IVA_PERCENTAGE', $_ENV['IVA_PERCENTAGE'] ?? 19);

// Configurar pagos
define('WOMPI_PUBLIC_KEY', $_ENV['WOMPI_PUBLIC_KEY'] ?? '');
define('WOMPI_PRIVATE_KEY', $_ENV['WOMPI_PRIVATE_KEY'] ?? '');

// Configurar email
define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com');
define('SMTP_PORT', $_ENV['SMTP_PORT'] ?? 587);
define('SMTP_USER', $_ENV['SMTP_USER'] ?? '');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM_EMAIL', $_ENV['SMTP_FROM_EMAIL'] ?? 'noreply@easycarluxury.com');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Easy Car Luxury');

// Función para obtener configuración de la BD (se cargará después de Database)
function getConfig($key, $default = null) {
    static $configs = null;
    
    if ($configs === null) {
        try {
            if (class_exists('Database')) {
                $db = Database::getInstance();
                $pdo = $db->getConnection();
                if ($pdo) {
                    $stmt = $pdo->query("SELECT config_key, config_value FROM system_config WHERE config_key IS NOT NULL");
                    if ($stmt) {
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $configs = [];
                        foreach ($result as $row) {
                            $configs[$row['config_key']] = $row['config_value'];
                        }
                    } else {
                        $configs = [];
                    }
                } else {
                    $configs = [];
                }
            } else {
                $configs = [];
            }
        } catch (Exception $e) {
            $configs = [];
        }
    }
    
    return $configs[$key] ?? $default;
}