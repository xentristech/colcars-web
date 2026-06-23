<?php
/**
 * API para interacciones: ofertas, votos y comentarios
 * Ruta: /api/v1/interactions.php
 * MODIFICADO: Verificar que el vendedor de la publicación esté activo antes de permitir interacciones.
 * CORREGIDO: Eliminado 'updated_at' en replyComment() porque no existe en la tabla comentarios
 * CORREGIDO: session_start() movido al principio ANTES de cualquier salida para evitar error 500 en Apache
 * CORREGIDO: Rutas absolutas con __DIR__ para evitar errores de include
 */

// ==========================================
// CORREGIDO: session_start() DEBE IR ANTES DE CUALQUIER SALIDA
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// DEPURACIÓN ACTIVADA - ELIMINAR DESPUÉS DE SOLUCIONAR
// ==========================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Crear archivo de log personalizado
$logFile = __DIR__ . '/../../logs/api_debug.log';

// Función para escribir en el log (silenciosa, no produce output)
function writeLog($msg) {
    global $logFile;
    @file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

// Escribir en el log solo si estamos en entorno de servidor (no CLI)
if (isset($_SERVER['REQUEST_METHOD'])) {
    writeLog("=== NUEVA SOLICITUD ===");
    writeLog("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'desconocido'));
    writeLog("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'desconocido'));
    writeLog("GET params: " . json_encode($_GET));
    writeLog("POST input: " . file_get_contents('php://input'));
}

// ==========================================
// HEADERS - DEBEN IR ANTES DE CUALQUIER OUTPUT HTML
// ==========================================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ==========================================
// CORREGIDO: Rutas absolutas con __DIR__
// ==========================================
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$database = Database::getInstance();
$pdo = $database->getConnection();

if (!$pdo) {
    if (isset($_SERVER['REQUEST_METHOD'])) {
        writeLog("ERROR: No se pudo conectar a la base de datos");
    }
    echo json_encode(['success' => false, 'error' => 'Error de conexión a la base de datos']);
    exit;
}

if (isset($_SERVER['REQUEST_METHOD'])) {
    writeLog("Conexión a BD exitosa");
}

// Obtener input desde JSON, POST o GET
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) {
    $input = $_POST;
}

// También obtener parámetros GET
$action = $input['action'] ?? $_GET['action'] ?? '';

if (isset($_SERVER['REQUEST_METHOD'])) {
    writeLog("Action: " . $action);
}

switch ($action) {
    case 'make_offer':
        makeOffer($pdo, $input);
        break;
    case 'get_offers':
        getOffers($pdo, $input);
        break;
    case 'update_offer_status':
        updateOfferStatus($pdo, $input);
        break;
    case 'vote':
        handleVote($pdo, $input);
        break;
    case 'get_votes':
        getVotes($pdo, $input);
        break;
    case 'send_comment':
        sendComment($pdo, $input);
        break;
    case 'get_comments':
        getComments($pdo, $_GET);
        break;
    case 'reply_comment':
        replyComment($pdo, $input);
        break;
    default:
        if (isset($_SERVER['REQUEST_METHOD'])) {
            writeLog("Acción no válida: " . $action);
        }
        echo json_encode(['success' => false, 'error' => 'Acción no válida: ' . $action]);
        break;
}

function makeOffer($pdo, $data) {
    $publication_id = intval($data['publication_id'] ?? 0);
    $buyer_name = trim($data['buyer_name'] ?? '');
    $buyer_phone = trim($data['buyer_phone'] ?? '');
    $amount = floatval($data['amount'] ?? 0);
    $message = trim($data['message'] ?? '');
    $user_id = $_SESSION['usuario_id'] ?? null;

    // Verificar que la publicación existe y el vendedor está activo
    $checkPub = $pdo->prepare("SELECT p.id, u.activo FROM publicaciones p JOIN usuarios u ON p.usuario_id = u.id WHERE p.id = ? AND p.status = 'active'");
    $checkPub->execute([$publication_id]);
    $pub = $checkPub->fetch(PDO::FETCH_ASSOC);
    
    if (!$pub) {
        echo json_encode(['success' => false, 'error' => 'La publicación no existe o no está activa']);
        return;
    }
    
    if ($pub['activo'] != 1) {
        echo json_encode(['success' => false, 'error' => 'El vendedor tiene la cuenta desactivada. No se pueden enviar ofertas.']);
        return;
    }

    if (!$publication_id || !$buyer_phone || $amount <= 0) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos: publicación, teléfono y monto son requeridos']);
        return;
    }

    if ($user_id && empty($buyer_name)) {
        $userQuery = "SELECT nombre_completo FROM usuarios WHERE id = :user_id";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([':user_id' => $user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $buyer_name = $user['nombre_completo'];
        }
    }
    
    if (empty($buyer_name)) {
        $buyer_name = 'Visitante';
    }

    $query = "INSERT INTO offers (publication_id, user_id, buyer_name, buyer_phone, amount, message, status, created_at) 
              VALUES (:pub_id, :user_id, :name, :phone, :amount, :msg, 'pending', NOW())";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':pub_id' => $publication_id,
        ':user_id' => $user_id,
        ':name' => $buyer_name,
        ':phone' => $buyer_phone,
        ':amount' => $amount,
        ':msg' => $message
    ]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Oferta enviada correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar la oferta', 'debug' => $stmt->errorInfo()]);
    }
}

function getOffers($pdo, $data) {
    $publication_id = intval($data['publication_id'] ?? 0);
    $user_id = $_SESSION['usuario_id'] ?? null;

    if (!$publication_id) {
        echo json_encode(['success' => false, 'error' => 'ID de publicación requerido']);
        return;
    }

    try {
        $query = "SELECT o.*, u.nombre_completo as seller_name 
                  FROM offers o
                  JOIN publicaciones p ON o.publication_id = p.id
                  JOIN usuarios u ON p.usuario_id = u.id
                  WHERE o.publication_id = :pub_id AND (p.usuario_id = :user_id OR :user_id IS NULL)
                  ORDER BY o.created_at DESC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':pub_id' => $publication_id, ':user_id' => $user_id]);
        $offers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success' => true, 'offers' => $offers]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al obtener ofertas: ' . $e->getMessage()]);
    }
}

function updateOfferStatus($pdo, $data) {
    $offer_id = intval($data['offer_id'] ?? 0);
    $status = $data['status'] ?? '';
    $user_id = $_SESSION['usuario_id'] ?? null;

    if (!$offer_id || !in_array($status, ['accepted', 'rejected', 'counter'])) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        return;
    }

    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        return;
    }

    $query = "UPDATE offers o
              JOIN publicaciones p ON o.publication_id = p.id
              SET o.status = :status
              WHERE o.id = :offer_id AND p.usuario_id = :user_id";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([':status' => $status, ':offer_id' => $offer_id, ':user_id' => $user_id]);

    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Estado actualizado correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al actualizar el estado']);
    }
}

function handleVote($pdo, $data) {
    $publication_id = intval($data['publication_id'] ?? 0);
    $vote_type = $data['vote_type'] ?? '';
    $user_id = $_SESSION['usuario_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

    if (!$publication_id || !in_array($vote_type, ['up', 'down', 'heart'])) {
        echo json_encode(['success' => false, 'error' => 'Datos inválidos']);
        return;
    }

    // Verificar que el vendedor está activo
    $checkSeller = $pdo->prepare("SELECT u.activo FROM publicaciones p JOIN usuarios u ON p.usuario_id = u.id WHERE p.id = ?");
    $checkSeller->execute([$publication_id]);
    $seller = $checkSeller->fetch(PDO::FETCH_ASSOC);
    if (!$seller || $seller['activo'] != 1) {
        echo json_encode(['success' => false, 'error' => 'No se puede votar porque el vendedor tiene la cuenta desactivada']);
        return;
    }

    if (!$user_id) {
        echo json_encode(['success' => false, 'redirect' => true, 'message' => 'Inicia sesión para votar']);
        return;
    }

    $checkQuery = "SELECT id FROM votes WHERE publication_id = :pub_id AND user_id = :user_id AND vote_type = :type";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([':pub_id' => $publication_id, ':user_id' => $user_id, ':type' => $vote_type]);
    
    if ($checkStmt->fetch()) {
        $deleteQuery = "DELETE FROM votes WHERE publication_id = :pub_id AND user_id = :user_id AND vote_type = :type";
        $deleteStmt = $pdo->prepare($deleteQuery);
        $deleteStmt->execute([':pub_id' => $publication_id, ':user_id' => $user_id, ':type' => $vote_type]);
        $voted = false;
        
        if ($vote_type === 'heart') {
            $updateLikeQuery = "UPDATE publicaciones SET likes = COALESCE(likes, 0) - 1 WHERE id = :pub_id AND likes > 0";
            $updateStmt = $pdo->prepare($updateLikeQuery);
            $updateStmt->execute([':pub_id' => $publication_id]);
        }
    } else {
        $insertQuery = "INSERT INTO votes (publication_id, user_id, ip_address, vote_type) VALUES (:pub_id, :user_id, :ip, :type)";
        $insertStmt = $pdo->prepare($insertQuery);
        $insertStmt->execute([':pub_id' => $publication_id, ':user_id' => $user_id, ':ip' => $ip_address, ':type' => $vote_type]);
        $voted = true;
        
        if ($vote_type === 'heart') {
            $updateLikeQuery = "UPDATE publicaciones SET likes = COALESCE(likes, 0) + 1 WHERE id = :pub_id";
            $updateStmt = $pdo->prepare($updateLikeQuery);
            $updateStmt->execute([':pub_id' => $publication_id]);
        }
    }

    $countsQuery = "SELECT vote_type, COUNT(*) as count FROM votes WHERE publication_id = :pub_id GROUP BY vote_type";
    $countsStmt = $pdo->prepare($countsQuery);
    $countsStmt->execute([':pub_id' => $publication_id]);
    $counts = [];
    while ($row = $countsStmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['vote_type']] = $row['count'];
    }

    $userQuery = "SELECT vote_type FROM votes WHERE publication_id = :pub_id AND user_id = :user_id";
    $userStmt = $pdo->prepare($userQuery);
    $userStmt->execute([':pub_id' => $publication_id, ':user_id' => $user_id]);
    $user_votes = $userStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'voted' => $voted,
        'counts' => [
            'up' => $counts['up'] ?? 0,
            'down' => $counts['down'] ?? 0,
            'heart' => $counts['heart'] ?? 0
        ],
        'user_votes' => $user_votes
    ]);
}

function getVotes($pdo, $data) {
    $publication_id = intval($data['publication_id'] ?? 0);
    
    if (!$publication_id) {
        echo json_encode(['success' => false, 'error' => 'ID de publicación requerido']);
        return;
    }
    
    $countsQuery = "SELECT vote_type, COUNT(*) as count FROM votes WHERE publication_id = :pub_id GROUP BY vote_type";
    $countsStmt = $pdo->prepare($countsQuery);
    $countsStmt->execute([':pub_id' => $publication_id]);
    $counts = [];
    while ($row = $countsStmt->fetch(PDO::FETCH_ASSOC)) {
        $counts[$row['vote_type']] = $row['count'];
    }

    $user_votes = [];
    if (isset($_SESSION['usuario_id'])) {
        $userQuery = "SELECT vote_type FROM votes WHERE publication_id = :pub_id AND user_id = :user_id";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([':pub_id' => $publication_id, ':user_id' => $_SESSION['usuario_id']]);
        $user_votes = $userStmt->fetchAll(PDO::FETCH_COLUMN);
    }

    echo json_encode([
        'success' => true,
        'counts' => [
            'up' => $counts['up'] ?? 0,
            'down' => $counts['down'] ?? 0,
            'heart' => $counts['heart'] ?? 0
        ],
        'user_votes' => $user_votes
    ]);
}

function sendComment($pdo, $data) {
    $publication_id = intval($data['publication_id'] ?? 0);
    $comentario = trim($data['comentario'] ?? '');
    $nombre = trim($data['nombre'] ?? '');
    $email = trim($data['email'] ?? '');
    $user_id = $_SESSION['usuario_id'] ?? null;

    if (!$publication_id) {
        echo json_encode(['success' => false, 'error' => 'ID de publicación requerido']);
        return;
    }
    
    if (empty($comentario)) {
        echo json_encode(['success' => false, 'error' => 'El comentario no puede estar vacío']);
        return;
    }

    // Verificar que la publicación existe y el vendedor está activo
    $checkPubQuery = "SELECT p.id, p.usuario_id, u.activo FROM publicaciones p JOIN usuarios u ON p.usuario_id = u.id WHERE p.id = :pub_id AND p.status = 'active'";
    $checkPubStmt = $pdo->prepare($checkPubQuery);
    $checkPubStmt->execute([':pub_id' => $publication_id]);
    $publication = $checkPubStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$publication) {
        echo json_encode(['success' => false, 'error' => 'La publicación no existe o no está activa']);
        return;
    }
    
    if ($publication['activo'] != 1) {
        echo json_encode(['success' => false, 'error' => 'El vendedor tiene la cuenta desactivada. No se pueden enviar comentarios.']);
        return;
    }

    if ($user_id) {
        $userQuery = "SELECT nombre_completo, email FROM usuarios WHERE id = :user_id";
        $userStmt = $pdo->prepare($userQuery);
        $userStmt->execute([':user_id' => $user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $nombre = $user['nombre_completo'];
            $email = $user['email'];
        }
    }

    if (empty($nombre)) {
        $nombre = 'Visitante';
    }

    $query = "INSERT INTO comentarios (publicacion_id, usuario_id, nombre, email, comentario, visible, created_at) 
              VALUES (:pub_id, :user_id, :nombre, :email, :comentario, 1, NOW())";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([
        ':pub_id' => $publication_id,
        ':user_id' => $user_id,
        ':nombre' => $nombre,
        ':email' => $email,
        ':comentario' => $comentario
    ]);

    if ($result) {
        $commentId = $pdo->lastInsertId();
        $selectQuery = "SELECT c.*, u.nombre_completo as user_name 
                        FROM comentarios c
                        LEFT JOIN usuarios u ON c.usuario_id = u.id
                        WHERE c.id = :id";
        $selectStmt = $pdo->prepare($selectQuery);
        $selectStmt->execute([':id' => $commentId]);
        $newComment = $selectStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Comentario enviado correctamente',
            'comment' => $newComment
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al guardar el comentario', 'debug' => $stmt->errorInfo()]);
    }
}

function getComments($pdo, $data) {
    $publication_id = intval($data['publication_id'] ?? 0);

    if (!$publication_id) {
        echo json_encode(['success' => false, 'error' => 'ID de publicación requerido']);
        return;
    }

    try {
        $query = "SELECT 
                    c.*,
                    u.nombre_completo as user_name,
                    u.email as user_email,
                    CASE 
                        WHEN c.usuario_id IS NOT NULL THEN u.nombre_completo
                        ELSE c.nombre
                    END as display_name
                  FROM comentarios c
                  LEFT JOIN usuarios u ON c.usuario_id = u.id
                  WHERE c.publicacion_id = :pub_id AND c.visible = 1
                  ORDER BY c.created_at ASC";
        
        $stmt = $pdo->prepare($query);
        
        if (!$stmt) {
            echo json_encode(['success' => false, 'error' => 'Error al preparar la consulta']);
            return;
        }
        
        if (!$stmt->execute([':pub_id' => $publication_id])) {
            echo json_encode(['success' => false, 'error' => 'Error al ejecutar la consulta: ' . implode(' ', $stmt->errorInfo())]);
            return;
        }
        
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($comments as &$comment) {
            $comment['fecha_formateada'] = date('d/m/Y H:i', strtotime($comment['created_at']));
            $comment['es_propietario'] = (isset($_SESSION['usuario_id']) && 
                                         isset($comment['usuario_id']) && 
                                         $comment['usuario_id'] == $_SESSION['usuario_id']);
        }
        
        echo json_encode([
            'success' => true, 
            'comments' => $comments,
            'total' => count($comments)
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => 'Error al obtener comentarios: ' . $e->getMessage()]);
    }
}

function replyComment($pdo, $data) {
    $comment_id = intval($data['comment_id'] ?? 0);
    $respuesta = trim($data['respuesta'] ?? '');
    $user_id = $_SESSION['usuario_id'] ?? null;

    if (!$comment_id || empty($respuesta)) {
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        return;
    }
    
    if (!$user_id) {
        echo json_encode(['success' => false, 'error' => 'Debes iniciar sesión para responder', 'redirect' => true]);
        return;
    }

    // Verificar que el comentario pertenece a una publicación del usuario logueado y que el vendedor está activo
    $checkQuery = "SELECT c.id, c.comentario, c.nombre, p.titulo, u.activo 
                   FROM comentarios c
                   JOIN publicaciones p ON c.publicacion_id = p.id
                   JOIN usuarios u ON p.usuario_id = u.id
                   WHERE c.id = :comment_id AND p.usuario_id = :user_id AND u.activo = 1";
    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute([':comment_id' => $comment_id, ':user_id' => $user_id]);
    $comment = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$comment) {
        echo json_encode(['success' => false, 'error' => 'No autorizado, el comentario no existe o el vendedor está desactivado']);
        return;
    }

    // ==========================================
    // CORREGIDO: Eliminado 'updated_at' porque NO existe en la tabla comentarios
    // ==========================================
    $query = "UPDATE comentarios SET respuesta = :respuesta WHERE id = :id";
    $stmt = $pdo->prepare($query);
    $result = $stmt->execute([':respuesta' => $respuesta, ':id' => $comment_id]);

    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Respuesta enviada correctamente',
            'comment_id' => $comment_id,
            'respuesta' => $respuesta
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Error al enviar la respuesta']);
    }
}
?>