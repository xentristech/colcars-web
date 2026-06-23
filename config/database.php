<?php
/**
 * EASY CAR LUXURY - Configuración de Base de Datos
 * Clase PDO para conexión segura a MySQL
 */

class Database {
    private static $instance = null;
    private $connection;
    
    // Configuración de la base de datos
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    
    /**
     * Constructor privado (Singleton)
     */
    private function __construct() {
        // Cargar variables de entorno si no están cargadas
        if (empty($_ENV['DB_HOST'])) {
            $this->loadEnv();
        }
        
        // Configuración por defecto si no hay .env
        $this->host = $_ENV['DB_HOST'] ?? 'localhost';
        $this->db_name = $_ENV['DB_NAME'] ?? 'easycarluxury';
        $this->username = $_ENV['DB_USER'] ?? 'root';
        $this->password = $_ENV['DB_PASS'] ?? 'Colcars2026#$%&';
        $this->charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';
        
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset} COLLATE {$this->charset}_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Registrar error en log
            error_log("Error de conexión a BD: " . $e->getMessage());
            
            // En desarrollo mostrar error, en producción solo mensaje genérico
            if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
                die("Error de conexión a la base de datos: " . $e->getMessage());
            } else {
                die("Error de conexión a la base de datos. Por favor contacte al administrador.");
            }
        }
    }
    
    /**
     * Cargar variables de entorno desde .env
     */
    private function loadEnv() {
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (strpos($line, '#') === 0 || empty($line)) continue;
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
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
    }
    
    /**
     * Obtener instancia única de la base de datos (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtener la conexión PDO
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Ejecutar consulta preparada
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError($e, $sql, $params);
            return false;
        }
    }
    
    /**
     * Obtener un solo registro
     */
    public function getOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetch();
        }
        return false;
    }
    
    /**
     * Obtener múltiples registros
     */
    public function getAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        if ($stmt) {
            return $stmt->fetchAll();
        }
        return [];
    }
    
    /**
     * Insertar registro y retornar ID insertado
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = ':' . implode(', :', $fields);
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $fields) . ") VALUES ({$placeholders})";
        
        $stmt = $this->query($sql, $data);
        if ($stmt) {
            return $this->connection->lastInsertId();
        }
        return false;
    }
    
    /**
     * Actualizar registro
     */
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach ($data as $key => $value) {
            $set[] = "{$key} = :{$key}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
    
    /**
     * Eliminar registro
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
    
    /**
     * Iniciar transacción
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transacción
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transacción
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Escapar string para LIKE
     */
    public function escapeLike($string) {
        return addcslashes($string, '%_');
    }
    
    /**
     * Registrar error en logs_sistema
     */
    private function logError($exception, $sql = '', $params = []) {
        try {
            $logSql = "INSERT INTO logs_sistema (tipo, mensaje, archivo, linea) VALUES (?, ?, ?, ?)";
            $mensaje = $exception->getMessage() . " | SQL: " . $sql . " | PARAMS: " . json_encode($params);
            $this->query($logSql, ['error', $mensaje, $exception->getFile(), $exception->getLine()]);
        } catch (Exception $e) {
            error_log("Error crítico en logError: " . $e->getMessage());
        }
    }
    
    /**
     * Prevenir clonación (Singleton)
     */
    private function __clone() {}
    
    /**
     * Prevenir deserialización (Singleton)
     */
    public function __wakeup() {}
}

// Función helper global para acceso rápido a la BD
function db() {
    return Database::getInstance()->getConnection();
}

// Variable global $pdo para compatibilidad con código existente
if (!isset($pdo)) {
    $pdo = Database::getInstance()->getConnection();
}