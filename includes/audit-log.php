<?php
/**
 * audit-log.php - Sistema Centralizado de Auditoría
 * Registra TODAS las acciones de TODOS los usuarios del sistema
 */

class AuditLog {
    private $pdo;
    private $userId;
    private $userEmail;
    private $userRole;
    private $ipAddress;
    private $userAgent;
    
    /**
     * Constructor - Inicializa la auditoría
     */
    public function __construct($pdo, $userId = null, $userEmail = null, $userRole = null) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->userEmail = $userEmail;
        $this->userRole = $userRole;
        $this->ipAddress = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
    
    /**
     * Obtener la IP real del cliente
     */
    private function getClientIP() {
        $ip = null;
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['HTTP_FORWARDED'])) {
            $ip = $_SERVER['HTTP_FORWARDED'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
        return $ip;
    }
    
    /**
     * Registrar una acción en la tabla de auditoría
     * 
     * @param string $action Acción realizada (CREATE, READ, UPDATE, DELETE, LOGIN, LOGOUT, VIEW, SUSPEND, RESTORE, UPGRADE, DOWNGRADE)
     * @param string $targetType Tipo de elemento afectado (usuario, publicacion, categoria, membresia, etc.)
     * @param int|null $targetId ID del elemento afectado
     * @param array|string|null $oldData Datos anteriores (antes del cambio)
     * @param array|string|null $newData Datos nuevos (después del cambio)
     * @param string|null $page La página donde se realizó la acción
     * @param string|null $additionalInfo Información adicional
     */
    public function register($action, $targetType = null, $targetId = null, $oldData = null, $newData = null, $page = null, $additionalInfo = null) {
        try {
            // Si no se proporcionó página, detectar automáticamente
            if ($page === null) {
                $page = $_SERVER['REQUEST_URI'] ?? 'unknown';
            }
            
            // Preparar datos en JSON
            $oldDataJson = null;
            $newDataJson = null;
            
            if ($oldData !== null) {
                $oldDataJson = is_array($oldData) ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : $oldData;
            }
            
            if ($newData !== null) {
                $newDataJson = is_array($newData) ? json_encode($newData, JSON_UNESCAPED_UNICODE) : $newData;
            }
            
            // Construir detalles completos
            $details = [
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'page' => $page,
                'additional_info' => $additionalInfo,
                'timestamp' => date('Y-m-d H:i:s')
            ];
            
            // Solo incluir datos si existen
            if ($oldDataJson) {
                $details['old_data'] = $oldDataJson;
            }
            if ($newDataJson) {
                $details['new_data'] = $newDataJson;
            }
            
            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
            
            // Insertar en la tabla principal de auditoría (compatible con tu estructura existente)
            $query = "INSERT INTO auditoria (usuario_id, usuario_email, rol_usuario, accion, tabla_afectada, registro_id, datos_anteriores, datos_nuevos, ip_address, user_agent, created_at) 
                      VALUES (:usuario_id, :usuario_email, :rol_usuario, :accion, :tabla_afectada, :registro_id, :datos_anteriores, :datos_nuevos, :ip_address, :user_agent, NOW())";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute([
                ':usuario_id' => $this->userId,
                ':usuario_email' => $this->userEmail,
                ':rol_usuario' => $this->userRole,
                ':accion' => $action,
                ':tabla_afectada' => $targetType,
                ':registro_id' => $targetId,
                ':datos_anteriores' => $oldDataJson,
                ':datos_nuevos' => $newDataJson,
                ':ip_address' => $this->ipAddress,
                ':user_agent' => $this->userAgent
            ]);
            
            // También insertar en admin_audit_log para administradores (si es una acción administrativa)
            if (in_array($this->userRole, ['superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor'])) {
                $queryAdmin = "INSERT INTO admin_audit_log (admin_id, action, target_type, target_id, details, ip_address, user_agent, created_at) 
                               VALUES (:admin_id, :action, :target_type, :target_id, :details, :ip_address, :user_agent, NOW())";
                $stmtAdmin = $this->pdo->prepare($queryAdmin);
                $stmtAdmin->execute([
                    ':admin_id' => $this->userId,
                    ':action' => $action,
                    ':target_type' => $targetType,
                    ':target_id' => $targetId,
                    ':details' => $detailsJson,
                    ':ip_address' => $this->ipAddress,
                    ':user_agent' => $this->userAgent
                ]);
            }
            
            return true;
        } catch (Exception $e) {
            // No detener la ejecución si falla la auditoría, solo registrar error
            error_log("Error al registrar auditoría: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Registrar visita a una página
     */
    public function registerPageView($page, $additionalInfo = null) {
        return $this->register('VIEW', 'page', null, null, null, $page, $additionalInfo);
    }
    
    /**
     * Registrar creación de un elemento
     */
    public function registerCreate($targetType, $targetId, $newData, $additionalInfo = null) {
        return $this->register('CREATE', $targetType, $targetId, null, $newData, null, $additionalInfo);
    }
    
    /**
     * Registrar lectura/visualización de un elemento
     */
    public function registerRead($targetType, $targetId, $additionalInfo = null) {
        return $this->register('READ', $targetType, $targetId, null, null, null, $additionalInfo);
    }
    
    /**
     * Registrar actualización de un elemento
     */
    public function registerUpdate($targetType, $targetId, $oldData, $newData, $additionalInfo = null) {
        return $this->register('UPDATE', $targetType, $targetId, $oldData, $newData, null, $additionalInfo);
    }
    
    /**
     * Registrar eliminación de un elemento
     */
    public function registerDelete($targetType, $targetId, $oldData, $additionalInfo = null) {
        return $this->register('DELETE', $targetType, $targetId, $oldData, null, null, $additionalInfo);
    }
    
    /**
     * Registrar inicio de sesión
     */
    public function registerLogin($additionalInfo = null) {
        return $this->register('LOGIN', 'session', $this->userId, null, null, null, $additionalInfo);
    }
    
    /**
     * Registrar cierre de sesión
     */
    public function registerLogout($additionalInfo = null) {
        return $this->register('LOGOUT', 'session', $this->userId, null, null, null, $additionalInfo);
    }
    
    /**
     * Registrar suspensión de usuario
     */
    public function registerSuspend($targetUserId, $additionalInfo = null) {
        return $this->register('SUSPEND', 'usuario', $targetUserId, null, null, null, $additionalInfo);
    }
    
    /**
     * Registrar restauración de usuario
     */
    public function registerRestore($targetUserId, $additionalInfo = null) {
        return $this->register('RESTORE', 'usuario', $targetUserId, null, null, null, $additionalInfo);
    }
    
    /**
     * Registrar upgrade de membresía
     */
    public function registerUpgrade($targetUserId, $oldTier, $newTier, $additionalInfo = null) {
        return $this->register('UPGRADE', 'membresia', $targetUserId, ['tier' => $oldTier], ['tier' => $newTier], null, $additionalInfo);
    }
    
    /**
     * Registrar downgrade de membresía
     */
    public function registerDowngrade($targetUserId, $oldTier, $newTier, $additionalInfo = null) {
        return $this->register('DOWNGRADE', 'membresia', $targetUserId, ['tier' => $oldTier], ['tier' => $newTier], null, $additionalInfo);
    }
}

/**
 * Función helper para inicializar auditoría desde cualquier archivo
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param array|null $user Datos del usuario (si no se proporciona, se toma de sesión)
 * @return AuditLog
 */
function initAudit($pdo, $user = null) {
    $userId = null;
    $userEmail = null;
    $userRole = null;
    
    if ($user !== null) {
        $userId = $user['id'] ?? ($user['user_id'] ?? null);
        $userEmail = $user['email'] ?? ($user['usuario_email'] ?? null);
        $userRole = $user['role'] ?? ($user['rol'] ?? null);
    } elseif (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        $userEmail = $_SESSION['user_email'] ?? null;
        $userRole = $_SESSION['user_role'] ?? 'usuario';
    } elseif (isset($_SESSION['admin_id'])) {
        $userId = $_SESSION['admin_id'];
        $userEmail = $_SESSION['admin_email'] ?? null;
        $userRole = $_SESSION['admin_role'] ?? 'admin';
    }
    
    return new AuditLog($pdo, $userId, $userEmail, $userRole);
}
?>