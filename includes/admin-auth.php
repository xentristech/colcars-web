<?php
/**
 * EASY CAR LUXURY - Admin Authentication Class
 */

class AdminAuth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Detectar si la petición es AJAX/API
     */
    private function isAjaxRequest() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') !== false);
    }
    
    /**
     * Detectar si se ejecuta desde línea de comandos
     */
    private function isCli() {
        return php_sapi_name() === 'cli';
    }
    
    /**
     * Verificar si el usuario es administrador
     * Compatible con ambas estructuras de tabla (usuarios + roles O users)
     */
    public function verifyAdmin() {
        // ========== DEPURACIÓN INICIAL ==========
        error_log("=== ADMIN AUTH: verifyAdmin() INICIADO ===");
        error_log("SESSION completa: " . print_r($_SESSION, true));
        
        // ========== NUEVO: USAR SESIÓN DIRECTAMENTE (AGREGADO - NO QUITAR) ==========
        $userRole = $_SESSION['user_role'] ?? $_SESSION['rol_nombre'] ?? null;
        $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? null;
        
        if ($userId && in_array($userRole, ['superadmin', 'admin', 'ingeniero', 'contador', 'tecnico', 'asesor'])) {
            error_log("ADMIN AUTH: Autenticación exitosa VÍA SESIÓN. Role: " . $userRole);
            return [
                'id' => $userId,
                'full_name' => $_SESSION['full_name'] ?? $_SESSION['nombre_completo'] ?? $_SESSION['user_name'] ?? 'Administrador',
                'email' => $_SESSION['email'] ?? $_SESSION['user_email'] ?? '',
                'role' => $userRole,
                'rol_id' => $_SESSION['rol_id'] ?? null,
                'tipo_cuenta' => $_SESSION['tipo_cuenta'] ?? $_SESSION['user_tipo_cuenta'] ?? 'free'
            ];
        }
        // ========== FIN NUEVO ==========
        
        // Verificar si hay sesión de usuario
        $hasUsuarioId = isset($_SESSION['usuario_id']);
        $hasUserId = isset($_SESSION['user_id']);
        $hasAdminId = isset($_SESSION['admin_id']);
        
        error_log("SESSION CHECKS - usuario_id: " . ($hasUsuarioId ? $_SESSION['usuario_id'] : 'NO') . ", user_id: " . ($hasUserId ? $_SESSION['user_id'] : 'NO') . ", admin_id: " . ($hasAdminId ? $_SESSION['admin_id'] : 'NO'));
        
        if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
            error_log("ADMIN AUTH: No hay sesión de usuario");
            return $this->handleUnauthorized();
        }
        
        $userId = $_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
        error_log("ADMIN AUTH: User ID seleccionado: " . ($userId ?? 'NINGUNO'));
        
        if (!$userId) {
            error_log("ADMIN AUTH: User ID es nulo");
            return $this->handleUnauthorized();
        }
        
        // ========== INTENTAR CON ESTRUCTURA NUEVA (users) ==========
        error_log("ADMIN AUTH: Intentando autenticar con estructura 'users' para ID: " . $userId);
        
        // Intentar con estructura nueva (users)
        try {
            // Verificar si existe la tabla 'users'
            $checkTable = $this->pdo->query("SHOW TABLES LIKE 'users'");
            $hasUsersTable = ($checkTable && $checkTable->rowCount() > 0);
            error_log("ADMIN AUTH: Tabla 'users' existe: " . ($hasUsersTable ? 'SI' : 'NO'));
            
            if ($hasUsersTable) {
                // Estructura nueva: tabla 'users'
                $stmt = $this->pdo->prepare("
                    SELECT u.*, u.role as role 
                    FROM users u 
                    WHERE u.id = :id AND u.deleted_at IS NULL
                ");
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("ADMIN AUTH: Resultado users query: " . print_r($user, true));
                
                if ($user && in_array($user['role'], ['admin', 'superadmin'])) {
                    error_log("ADMIN AUTH: Autenticación exitosa con tabla 'users'. Role: " . $user['role']);
                    return [
                        'id' => $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'rol_id' => null,
                        'status' => $user['status'] ?? 'active'
                    ];
                } else {
                    error_log("ADMIN AUTH: Usuario no es admin o no existe en 'users'");
                }
            }
        } catch (PDOException $e) {
            // Tabla no existe o error, continuar con estructura antigua
            error_log("ADMIN AUTH: Error en estructura 'users': " . $e->getMessage());
        }
        
        // ========== INTENTAR CON ESTRUCTURA ANTIGUA (usuarios + roles) ==========
        error_log("ADMIN AUTH: Intentando autenticar con estructura 'usuarios' para ID: " . $userId);
        
        // Intentar con estructura antigua (usuarios + roles)
        try {
            // Verificar si existe la tabla 'usuarios'
            $checkTable = $this->pdo->query("SHOW TABLES LIKE 'usuarios'");
            $hasUsuariosTable = ($checkTable && $checkTable->rowCount() > 0);
            error_log("ADMIN AUTH: Tabla 'usuarios' existe: " . ($hasUsuariosTable ? 'SI' : 'NO'));
            
            if ($hasUsuariosTable) {
                $stmt = $this->pdo->prepare("
                    SELECT u.*, r.nombre as role, r.id as rol_id
                    FROM usuarios u 
                    JOIN roles r ON u.rol_id = r.id 
                    WHERE u.id = :id AND u.activo = 1
                ");
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                error_log("ADMIN AUTH: Resultado usuarios query: " . print_r($user, true));
                
                $validRoles = ['superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor'];
                if ($user && in_array($user['role'], $validRoles)) {
                    error_log("ADMIN AUTH: Autenticación exitosa con tabla 'usuarios'. Role: " . $user['role'] . ", rol_id: " . $user['rol_id']);
                    return [
                        'id' => $user['id'],
                        'full_name' => $user['nombre_completo'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'rol_id' => $user['rol_id'],
                        'tipo_cuenta' => $user['tipo_cuenta'] ?? 'free'
                    ];
                } else {
                    error_log("ADMIN AUTH: Usuario no es admin o no existe en 'usuarios'");
                }
            }
        } catch (PDOException $e) {
            // Error en la consulta
            error_log("ADMIN AUTH: Error en estructura 'usuarios': " . $e->getMessage());
        }
        
        // ========== NO ES ADMINISTRADOR ==========
        error_log("ADMIN AUTH: ACCESO DENEGADO - El usuario ID " . $userId . " no es administrador.");
        return $this->handleUnauthorized();
    }
    
    /**
     * Manejar respuesta para peticiones no autorizadas
     * Diferencia entre peticiones AJAX/API y peticiones normales de navegador
     */
    private function handleUnauthorized() {
        // Si es línea de comandos (pruebas), retornar false sin redirigir
        if ($this->isCli()) {
            return false;
        }
        
        // Si es petición AJAX o API
        if ($this->isAjaxRequest()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unauthorized', 'message' => 'No active session or insufficient permissions']);
            exit;
        }
        
        // Petición normal de navegador - redirigir al login
        header('Location: /easycarluxury/login');
        exit;
    }
    
    /**
     * Obtener estadísticas del dashboard
     */
    public function getDashboardStats() {
        $stats = [];
        
        try {
            // Verificar qué estructura de tablas existe
            $checkUsers = $this->pdo->query("SHOW TABLES LIKE 'users'");
            $hasNewStructure = ($checkUsers && $checkUsers->rowCount() > 0);
            
            if ($hasNewStructure) {
                // Estructura nueva (tabla users)
                // Total users
                $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL");
                $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // New users this month
                $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users WHERE deleted_at IS NULL AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
                $stats['new_users_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Total publications active
                $checkPublications = $this->pdo->query("SHOW TABLES LIKE 'publications'");
                if ($checkPublications && $checkPublications->rowCount() > 0) {
                    $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM publications WHERE status = 'active' AND deleted_at IS NULL");
                    $stats['total_publications'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    // Pending reviews
                    $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM publications WHERE status = 'pending' AND deleted_at IS NULL");
                    $stats['pending_reviews'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                } else {
                    $stats['total_publications'] = 0;
                    $stats['pending_reviews'] = 0;
                }
                
                // Active subscriptions (users with paid membership)
                $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM users WHERE membership_tier IN ('pro', 'premium', 'elite') AND status = 'active'");
                $stats['active_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Revenue this month
                $checkPayments = $this->pdo->query("SHOW TABLES LIKE 'payments'");
                if ($checkPayments && $checkPayments->rowCount() > 0) {
                    $stmt = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
                    $stats['revenue_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                } else {
                    $stats['revenue_this_month'] = 0;
                }
                
                // Users by role
                $stmt = $this->pdo->query("SELECT role, COUNT(*) as count FROM users WHERE deleted_at IS NULL GROUP BY role");
                $stats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
            } else {
                // Estructura antigua (tablas usuarios + roles)
                // Total users
                $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1");
                $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // New users this month
                $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE activo = 1 AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())");
                $stats['new_users_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Total publications active
                $checkPublications = $this->pdo->query("SHOW TABLES LIKE 'publicaciones'");
                if ($checkPublications && $checkPublications->rowCount() > 0) {
                    $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM publicaciones WHERE status = 'active'");
                    $stats['total_publications'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                    
                    // Pending reviews
                    $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM publicaciones WHERE status = 'pending_review'");
                    $stats['pending_reviews'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                } else {
                    $stats['total_publications'] = 0;
                    $stats['pending_reviews'] = 0;
                }
                
                // Active subscriptions
                $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM usuarios WHERE tipo_cuenta IN ('pro', 'premium', 'elite') AND (fecha_expiracion IS NULL OR fecha_expiracion >= CURDATE())");
                $stats['active_subscriptions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                
                // Revenue this month
                $checkPayments = $this->pdo->query("SHOW TABLES LIKE 'payments'");
                if ($checkPayments && $checkPayments->rowCount() > 0) {
                    $stmt = $this->pdo->query("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE status = 'completed' AND MONTH(payment_date) = MONTH(NOW()) AND YEAR(payment_date) = YEAR(NOW())");
                    $stats['revenue_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
                } else {
                    $stats['revenue_this_month'] = 0;
                }
                
                // Users by role
                $stmt = $this->pdo->query("SELECT r.nombre as role, COUNT(u.id) as count FROM usuarios u JOIN roles r ON u.rol_id = r.id GROUP BY r.id");
                $stats['users_by_role'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Error en getDashboardStats: " . $e->getMessage());
            $stats = [
                'total_users' => 0,
                'new_users_this_month' => 0,
                'total_publications' => 0,
                'pending_reviews' => 0,
                'active_subscriptions' => 0,
                'revenue_this_month' => 0,
                'users_by_role' => []
            ];
        }
        
        return $stats;
    }
    
    /**
     * Registrar acción en el log de auditoría de administradores
     */
    public function logAction($adminId, $action, $targetType, $targetId = null, $details = null) {
        try {
            // Obtener IP del usuario
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
            // Obtener User Agent
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            
            // Verificar qué estructura de tabla existe para auditoría
            // Primero intentar con tabla admin_audit_log
            $checkAdminLog = $this->pdo->query("SHOW TABLES LIKE 'admin_audit_log'");
            $hasAdminLogTable = ($checkAdminLog && $checkAdminLog->rowCount() > 0);
            
            if ($hasAdminLogTable) {
                // Usar tabla admin_audit_log
                $query = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address, user_agent, created_at) 
                          VALUES (:admin_id, :action, :target_type, :target_id, :details, :ip_address, :user_agent, NOW())";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([
                    ':admin_id' => $adminId,
                    ':action' => $action,
                    ':target_type' => $targetType,
                    ':target_id' => $targetId,
                    ':details' => $details,
                    ':ip_address' => $ipAddress,
                    ':user_agent' => $userAgent
                ]);
                return true;
            }
            
            // Si no existe admin_audit_log, intentar con tabla auditoria genérica
            $checkAuditoria = $this->pdo->query("SHOW TABLES LIKE 'auditoria'");
            $hasAuditoriaTable = ($checkAuditoria && $checkAuditoria->rowCount() > 0);
            
            if ($hasAuditoriaTable) {
                $query = "INSERT INTO auditoria (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos, ip_address, user_agent, created_at) 
                          VALUES (:usuario_id, :accion, :tabla_afectada, :registro_id, :datos_nuevos, :ip_address, :user_agent, NOW())";
                $stmt = $this->pdo->prepare($query);
                $stmt->execute([
                    ':usuario_id' => $adminId,
                    ':accion' => $action,
                    ':tabla_afectada' => $targetType,
                    ':registro_id' => $targetId,
                    ':datos_nuevos' => $details,
                    ':ip_address' => $ipAddress,
                    ':user_agent' => $userAgent
                ]);
                return true;
            }
            
            // No hay tabla de auditoría disponible
            error_log("ADMIN AUTH: No se encontró tabla de auditoría (admin_audit_log o auditoria)");
            return false;
            
        } catch (PDOException $e) {
            error_log("Error en logAction: " . $e->getMessage());
            return false;
        }
    }
}