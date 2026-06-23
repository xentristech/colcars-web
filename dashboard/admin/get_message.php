<?php
/**
 * C:\ServidorWeb\htdocs\easycarluxury\dashboard\admin\get_message.php
 * 
 * API para obtener detalles de un mensaje de contacto vía AJAX
 * Retorna JSON con los datos del mensaje
 * Usado por: contact_messages.php para los modales de ver, responder y editar
 */

// Iniciar sesión
session_start();

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// Verificar rol de administrador
$rolPermitido = in_array($_SESSION['rol_id'], [1, 2, 3, 4, 5]);
if (!$rolPermitido) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Permisos insuficientes']);
    exit;
}

require_once '../../config/database.php';

// Obtener ID del mensaje
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$simple = isset($_GET['simple']) ? intval($_GET['simple']) : 0;

if ($id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID inválido']);
    exit;
}

try {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    // Consultar el mensaje
    $sql = "SELECT * FROM contact_messages WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $mensaje = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$mensaje) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Mensaje no encontrado']);
        exit;
    }
    
    // Si es solicitud simple (para modales de responder/editar), retornar JSON
    if ($simple == 1) {
        header('Content-Type: application/json');
        echo json_encode([
            'id' => $mensaje['id'],
            'nombre_completo' => $mensaje['nombre_completo'],
            'email' => $mensaje['email'],
            'telefono' => $mensaje['telefono'],
            'whatsapp' => $mensaje['whatsapp'],
            'mensaje' => $mensaje['mensaje'],
            'respuesta' => $mensaje['respuesta'],
            'status' => $mensaje['status'],
            'created_at' => $mensaje['created_at'],
            'updated_at' => $mensaje['updated_at']
        ]);
        exit;
    }
    
    // Si es solicitud completa (para ver detalle), retornar HTML
    ?>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="mb-3">
                    <strong><i class="fas fa-user"></i> Nombre:</strong>
                    <p class="mt-1"><?php echo htmlspecialchars($mensaje['nombre_completo']); ?></p>
                </div>
                
                <div class="mb-3">
                    <strong><i class="fas fa-envelope"></i> Email:</strong>
                    <p class="mt-1"><?php echo htmlspecialchars($mensaje['email']); ?></p>
                </div>
                
                <div class="mb-3">
                    <strong><i class="fas fa-phone"></i> Teléfono:</strong>
                    <p class="mt-1"><?php echo htmlspecialchars($mensaje['telefono']); ?></p>
                </div>
                
                <?php if (!empty($mensaje['whatsapp'])): ?>
                <div class="mb-3">
                    <strong><i class="fab fa-whatsapp"></i> WhatsApp:</strong>
                    <p class="mt-1"><?php echo htmlspecialchars($mensaje['whatsapp']); ?></p>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <strong><i class="fas fa-calendar-alt"></i> Fecha:</strong>
                    <p class="mt-1"><?php echo date('d/m/Y H:i:s', strtotime($mensaje['created_at'])); ?></p>
                </div>
                
                <div class="mb-3">
                    <strong><i class="fas fa-tag"></i> Estado:</strong>
                    <p class="mt-1">
                        <span class="badge-status 
                            <?php echo $mensaje['status'] == 'pendiente' ? 'badge-pendiente' : ($mensaje['status'] == 'respondido' ? 'badge-respondido' : ($mensaje['status'] == 'archivado' ? 'badge-archivado' : 'badge-eliminado')); ?>">
                            <?php 
                                echo $mensaje['status'] == 'pendiente' ? 'Pendiente' : 
                                    ($mensaje['status'] == 'respondido' ? 'Respondido' : 
                                    ($mensaje['status'] == 'archivado' ? 'Archivado' : 'Eliminado'));
                            ?>
                        </span>
                    </p>
                </div>
                
                <div class="mb-3">
                    <strong><i class="fas fa-comment"></i> Mensaje:</strong>
                    <div class="p-3 mt-1" style="background: var(--bg-primary); border-radius: 8px;">
                        <?php echo nl2br(htmlspecialchars($mensaje['mensaje'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($mensaje['respuesta'])): ?>
                <div class="mb-3">
                    <strong><i class="fas fa-reply-all"></i> Respuesta:</strong>
                    <div class="p-3 mt-1" style="background: var(--bg-primary); border-radius: 8px; border-left: 4px solid #27ae60;">
                        <?php echo nl2br(htmlspecialchars($mensaje['respuesta'])); ?>
                    </div>
                    <?php if (!empty($mensaje['fecha_respuesta'])): ?>
                    <small class="text-muted">Respondido el: <?php echo date('d/m/Y H:i:s', strtotime($mensaje['fecha_respuesta'])); ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <style>
        .badge-status {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.65rem;
            font-weight: 600;
            display: inline-block;
        }
        .badge-pendiente { background: #f39c12; color: #fff; }
        .badge-respondido { background: #27ae60; color: #fff; }
        .badge-archivado { background: #3498db; color: #fff; }
        .badge-eliminado { background: #e74c3c; color: #fff; }
    </style>
    <?php
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error al obtener el mensaje: ' . $e->getMessage()]);
    exit;
}
?>