<?php
// test_db.php - Colocar en la raíz del proyecto
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Diagnóstico Rápido - Colcars</h1>";

// 1. Verificar ruta del proyecto
echo "<h2>1. Información del Servidor</h2>";
echo "<pre>";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Path: " . __FILE__ . "\n";
echo "Script Directory: " . __DIR__ . "\n";
echo "</pre>";

// 2. Intentar cargar la configuración
echo "<h2>2. Cargando Configuración</h2>";
$configPath = __DIR__ . '/config/database.php';
if (file_exists($configPath)) {
    echo "<span style='color:green'>✓ config/database.php encontrado</span><br>";
    require_once $configPath;
    
    if (class_exists('Database')) {
        echo "<span style='color:green'>✓ Clase Database existe</span><br>";
        
        try {
            $db = Database::getInstance();
            $pdo = $db->getConnection();
            echo "<span style='color:green'>✓ Conexión a BD exitosa</span><br>";
            
            // Verificar tablas
            echo "<h2>3. Verificando Tablas</h2>";
            $tables = ['usuarios', 'users', 'roles', 'publicaciones'];
            echo "<ul>";
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->rowCount() > 0;
                $color = $exists ? 'green' : 'red';
                $status = $exists ? 'EXISTE' : 'NO EXISTE';
                echo "<li style='color:$color'>$table: $status</li>";
            }
            echo "</ul>";
            
            // Mostrar usuarios
            echo "<h2>4. Usuarios en tabla 'usuarios'</h2>";
            $stmt = $pdo->query("SELECT id, nombre_completo, email, activo, tipo_cuenta, rol_id FROM usuarios LIMIT 10");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($users) > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Nombre</th><th>Email</th><th>Activo</th><th>Tipo</th><th>rol_id</th></tr>";
                foreach ($users as $user) {
                    $activoColor = $user['activo'] == 1 ? 'green' : 'red';
                    echo "<tr>";
                    echo "<td>{$user['id']}</td>";
                    echo "<td>{$user['nombre_completo']}</td>";
                    echo "<td>{$user['email']}</td>";
                    echo "<td style='color:$activoColor'>{$user['activo']}</td>";
                    echo "<td>{$user['tipo_cuenta']}</td>";
                    echo "<td>{$user['rol_id']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<span style='color:red'>No hay usuarios en la tabla 'usuarios'</span>";
            }
            
            // Mostrar roles
            echo "<h2>5. Roles disponibles</h2>";
            $stmt = $pdo->query("SELECT * FROM roles");
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($roles) > 0) {
                echo "<table border='1' cellpadding='5'>";
                echo "<tr><th>ID</th><th>Nombre</th><th>Descripción</th></tr>";
                foreach ($roles as $role) {
                    echo "<tr>";
                    echo "<td>{$role['id']}</td>";
                    echo "<td>{$role['nombre']}</td>";
                    echo "<td>" . ($role['descripcion'] ?? '-') . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<span style='color:red'>No hay roles definidos</span>";
            }
            
        } catch (Exception $e) {
            echo "<span style='color:red'>Error de BD: " . $e->getMessage() . "</span>";
        }
    } else {
        echo "<span style='color:red'>✗ Clase Database no encontrada</span>";
    }
} else {
    echo "<span style='color:red'>✗ No se encontró config/database.php en: " . $configPath . "</span>";
}

echo "<h2>6. Variables de Sesión (si existen)</h2>";
session_start();
echo "<pre>";
print_r($_SESSION);
echo "</pre>";
?>