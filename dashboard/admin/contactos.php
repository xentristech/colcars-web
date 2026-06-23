<?php
/**
 * Panel de administración para mensajes de contacto
 */

require_once '../../config/database.php';
require_once '../../includes/auth.php';

// Verificar que el usuario sea administrador (rol_id 1 es superadmin)
$user = requireAuth();
if (!in_array($user['rol_id'], [1, 2, 3, 4, 5])) {
    header('Location: /easycarluxury/login.php');
    exit;
}

$database = Database::getInstance();
$pdo = $database->getConnection();

// Procesar acciones
$action = $_GET['action'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($action === 'marcar_leido' && $id > 0) {
    $stmt = $pdo->prepare("UPDATE contactos SET leido = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: contactos.php");
    exit;
}

if ($action === 'responder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    $respuesta = trim($_POST['respuesta']);
    $respondido_por = $user['id'];
    
    if (!empty($respuesta)) {
        // Guardar respuesta en BD
        $stmt = $pdo->prepare("UPDATE contactos SET respuesta = ?, respondido = 1, respondido_por = ?, fecha_respuesta = NOW() WHERE id = ?");
        $stmt->execute([$respuesta, $respondido_por, $id]);
        
        // Obtener datos del contacto para enviar email
        $stmt = $pdo->prepare("SELECT nombre, email, mensaje FROM contactos WHERE id = ?");
        $stmt->execute([$id]);
        $contacto = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($contacto) {
            // Enviar email de respuesta
            $to = $contacto['email'];
            $subject = "Respuesta a tu consulta - Colcars";
            $email_message = "
            <html>
            <head><title>Respuesta a tu consulta - Colcars</title></head>
            <body>
                <h2>Hola {$contacto['nombre']},</h2>
                <p>Gracias por contactarnos. A continuación, nuestra respuesta a tu consulta:</p>
                <br>
                <div style='background: #f4f4f4; padding: 15px; border-radius: 10px;'>
                    " . nl2br(htmlspecialchars($respuesta)) . "
                </div>
                <br>
                <p>Si tienes más preguntas, no dudes en escribirnos nuevamente.</p>
                <br>
                <p>Saludos cordiales,<br><strong>Equipo de Colcars</strong></p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: Colcars <soporte@easycarluxury.com>" . "\r\n";
            
            @mail($to, $subject, $email_message, $headers);
        }
        
        header("Location: contactos.php");
        exit;
    }
}

// Paginación
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? 'todos';

// Construir query
$where = [];
if ($status === 'leidos') {
    $where[] = "leido = 1";
} elseif ($status === 'no_leidos') {
    $where[] = "leido = 0";
} elseif ($status === 'respondidos') {
    $where[] = "respondido = 1";
} elseif ($status === 'no_respondidos') {
    $where[] = "respondido = 0";
}

$whereClause = empty($where) ? '' : "WHERE " . implode(" AND ", $where);

// Total de registros
$countStmt = $pdo->query("SELECT COUNT(*) as total FROM contactos $whereClause");
$total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPages = ceil($total / $limit);

// Obtener mensajes
$query = "SELECT c.*, u.nombre_completo as respondedor_nombre 
          FROM contactos c
          LEFT JOIN usuarios u ON c.respondido_por = u.id
          $whereClause 
          ORDER BY c.leido ASC, c.created_at DESC 
          LIMIT $limit OFFSET $offset";
$stmt = $pdo->query($query);
$mensajes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mensajes de Contacto - Panel Admin</title>
    <link rel="stylesheet" href="/easycarluxury/assets/libs/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/easycarluxury/assets/libs/fontawesome/css/all.min.css">
    <style>
        body { background: #f4f6f9; }
        .message-card {
            border-left: 4px solid #3498db;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            background: #fff;
        }
        .message-card.unread {
            border-left-color: #e74c3c;
            background: #fff9f9;
        }
        .message-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .badge-no-leido { background: #e74c3c; }
        .badge-leido { background: #27ae60; }
        .badge-respondido { background: #3498db; }
        .modal-body {
            max-height: 500px;
            overflow-y: auto;
        }
        .btn-sm {
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center my-4">
                    <h2><i class="fas fa-envelope"></i> Mensajes de Contacto</h2>
                    <div>
                        <span class="badge bg-primary">Total: <?php echo $total; ?></span>
                    </div>
                </div>
                
                <!-- Filtros -->
                <div class="btn-group mb-4 flex-wrap">
                    <a href="?status=todos" class="btn btn-outline-primary <?php echo $status === 'todos' ? 'active' : ''; ?>">Todos</a>
                    <a href="?status=no_leidos" class="btn btn-outline-danger <?php echo $status === 'no_leidos' ? 'active' : ''; ?>">No leídos</a>
                    <a href="?status=leidos" class="btn btn-outline-success <?php echo $status === 'leidos' ? 'active' : ''; ?>">Leídos</a>
                    <a href="?status=no_respondidos" class="btn btn-outline-warning <?php echo $status === 'no_respondidos' ? 'active' : ''; ?>">No respondidos</a>
                    <a href="?status=respondidos" class="btn btn-outline-info <?php echo $status === 'respondidos' ? 'active' : ''; ?>">Respondidos</a>
                </div>
                
                <!-- Lista de mensajes -->
                <?php if (empty($mensajes)): ?>
                <div class="alert alert-info text-center">
                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                    No hay mensajes para mostrar.
                </div>
                <?php else: ?>
                
                <?php foreach ($mensajes as $msg): ?>
                <div class="card message-card <?php echo $msg['leido'] == 0 ? 'unread' : ''; ?>" id="msg-<?php echo $msg['id']; ?>">
                    <div class="card-body">
                        <div class="row align-items-start">
                            <div class="col-md-7">
                                <h5 class="card-title">
                                    <strong><?php echo htmlspecialchars($msg['nombre']); ?></strong>
                                    <small class="text-muted">(<?php echo htmlspecialchars($msg['email']); ?>)</small>
                                    <?php if ($msg['telefono']): ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($msg['telefono']); ?></span>
                                    <?php endif; ?>
                                </h5>
                                <h6 class="card-subtitle mb-2 text-muted">
                                    <i class="fas fa-tag"></i> <?php echo htmlspecialchars($msg['asunto']); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-globe"></i> IP: <?php echo htmlspecialchars($msg['ip_address'] ?: 'No registrada'); ?>
                                </h6>
                                <p class="card-text text-muted">
                                    <strong>Mensaje:</strong><br>
                                    <?php echo nl2br(htmlspecialchars(substr($msg['mensaje'], 0, 150))); ?>
                                    <?php if (strlen($msg['mensaje']) > 150): ?>...<?php endif; ?>
                                </p>
                                <?php if ($msg['respuesta']): ?>
                                <div class="alert alert-info mt-2 mb-0 p-2" style="font-size: 0.85rem;">
                                    <strong><i class="fas fa-reply"></i> Respuesta:</strong><br>
                                    <?php echo nl2br(htmlspecialchars(substr($msg['respuesta'], 0, 100))); ?>
                                    <?php if (strlen($msg['respuesta']) > 100): ?>...<?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-5 text-end">
                                <div class="mb-2">
                                    <?php if (!$msg['leido']): ?>
                                    <a href="?action=marcar_leido&id=<?php echo $msg['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Marcar como leído
                                    </a>
                                    <?php endif; ?>
                                    
                                    <button class="btn btn-sm btn-primary view-message" 
                                            data-id="<?php echo $msg['id']; ?>"
                                            data-nombre="<?php echo htmlspecialchars($msg['nombre']); ?>"
                                            data-email="<?php echo htmlspecialchars($msg['email']); ?>"
                                            data-telefono="<?php echo htmlspecialchars($msg['telefono']); ?>"
                                            data-asunto="<?php echo htmlspecialchars($msg['asunto']); ?>"
                                            data-mensaje="<?php echo htmlspecialchars($msg['mensaje']); ?>"
                                            data-fecha="<?php echo date('d/m/Y H:i', strtotime($msg['created_at'])); ?>"
                                            data-ip="<?php echo htmlspecialchars($msg['ip_address'] ?: 'No registrada'); ?>">
                                        <i class="fas fa-eye"></i> Ver
                                    </button>
                                    
                                    <?php if (!$msg['respondido']): ?>
                                    <button class="btn btn-sm btn-warning reply-message" 
                                            data-id="<?php echo $msg['id']; ?>"
                                            data-email="<?php echo htmlspecialchars($msg['email']); ?>"
                                            data-name="<?php echo htmlspecialchars($msg['nombre']); ?>">
                                        <i class="fas fa-reply"></i> Responder
                                    </button>
                                    <?php else: ?>
                                    <span class="badge bg-info d-block mt-2">
                                        <i class="fas fa-check-circle"></i> Respondido 
                                        <?php if ($msg['respondedor_nombre']): ?>
                                        por <?php echo htmlspecialchars($msg['respondedor_nombre']); ?>
                                        <?php endif; ?>
                                        <br><small><?php echo date('d/m/Y H:i', strtotime($msg['fecha_respuesta'])); ?></small>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <!-- Paginación -->
                <?php if ($totalPages > 1): ?>
                <nav class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Modal Ver Mensaje -->
    <div class="modal fade" id="viewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-envelope-open-text"></i> Detalle del mensaje</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><i class="fas fa-user"></i> Nombre:</strong>
                            <p id="view_nombre" class="mb-0"></p>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-envelope"></i> Email:</strong>
                            <p id="view_email" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><i class="fas fa-phone"></i> Teléfono:</strong>
                            <p id="view_telefono" class="mb-0"></p>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-tag"></i> Asunto:</strong>
                            <p id="view_asunto" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <strong><i class="fas fa-calendar"></i> Fecha:</strong>
                            <p id="view_fecha" class="mb-0"></p>
                        </div>
                        <div class="col-md-6">
                            <strong><i class="fas fa-globe"></i> IP:</strong>
                            <p id="view_ip" class="mb-0"></p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <strong><i class="fas fa-comment"></i> Mensaje:</strong>
                        <div id="view_mensaje" class="border rounded p-3 mt-1" style="background: #f8f9fa; max-height: 200px; overflow-y: auto;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Responder -->
    <div class="modal fade" id="replyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-reply"></i> Responder mensaje</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="reply_id">
                        <div class="alert alert-info">
                            <strong>Respondiendo a:</strong> <span id="reply_email"></span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label"><strong>Tu respuesta:</strong></label>
                            <textarea name="respuesta" id="reply_text" class="form-control" rows="6" required></textarea>
                            <small class="text-muted">Se enviará un email al cliente con tu respuesta.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="action" value="responder" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Enviar respuesta
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="/easycarluxury/assets/libs/jquery/jquery.min.js"></script>
    <script src="/easycarluxury/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Ver mensaje
        $('.view-message').click(function() {
            const data = $(this).data();
            $('#view_nombre').text(data.nombre);
            $('#view_email').text(data.email);
            $('#view_telefono').text(data.telefono || 'No especificado');
            $('#view_asunto').text(data.asunto);
            $('#view_fecha').text(data.fecha);
            $('#view_ip').text(data.ip);
            $('#view_mensaje').html(data.mensaje.replace(/\n/g, '<br>'));
            $('#viewModal').modal('show');
        });
        
        // Responder mensaje
        $('.reply-message').click(function() {
            const id = $(this).data('id');
            const email = $(this).data('email');
            const name = $(this).data('name');
            $('#reply_id').val(id);
            $('#reply_email').text(name + ' <' + email + '>');
            $('#replyModal').modal('show');
        });
    </script>
</body>
</html>