<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';
require_once '../../includes/functions.php';

session_start();

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

if ($method === 'POST') {
    $action = $input['action'] ?? '';
    
    if ($action === 'toggle_like') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['redirect' => true]);
            exit;
        }
        
        $userId = $_SESSION['user_id'];
        $pubId = $input['publication_id'];
        
        // Check if already liked
        $check = $pdo->prepare("SELECT * FROM favorites WHERE user_id = ? AND publication_id = ?");
        $check->execute([$userId, $pubId]);
        
        if ($check->fetch()) {
            // Unlike
            $delete = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND publication_id = ?");
            $delete->execute([$userId, $pubId]);
            $liked = false;
        } else {
            // Like
            $insert = $pdo->prepare("INSERT INTO favorites (user_id, publication_id, created_at) VALUES (?, ?, NOW())");
            $insert->execute([$userId, $pubId]);
            $liked = true;
        }
        
        // Get updated count
        $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM favorites WHERE publication_id = ?");
        $countStmt->execute([$pubId]);
        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        echo json_encode(['success' => true, 'liked' => $liked, 'count' => $count]);
        exit;
    }
    
    if ($action === 'add_comment') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Login required']);
            exit;
        }
        
        $userId = $_SESSION['user_id'];
        $pubId = $input['publication_id'];
        $comment = trim($input['comment']);
        
        if (empty($comment)) {
            echo json_encode(['error' => 'Comment cannot be empty']);
            exit;
        }
        
        $insert = $pdo->prepare("INSERT INTO publication_comments (publication_id, user_id, comment, status, created_at) VALUES (?, ?, ?, 'pending', NOW())");
        $insert->execute([$pubId, $userId, $comment]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'contact_seller') {
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['error' => 'Login required']);
            exit;
        }
        
        $senderId = $_SESSION['user_id'];
        $receiverId = $input['receiver_id'];
        $subject = $input['subject'];
        $message = $input['message'];
        $publicationId = $input['publication_id'] ?? null;
        
        $insert = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, subject, message, publication_id, status, created_at) VALUES (?, ?, ?, ?, ?, 'unread', NOW())");
        $insert->execute([$senderId, $receiverId, $subject, $message, $publicationId]);
        
        echo json_encode(['success' => true]);
        exit;
    }
}

if ($method === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'search_suggestions') {
        $q = $_GET['q'] ?? '';
        
        if (strlen($q) < 2) {
            echo json_encode([]);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT id, title, price, 
                                (SELECT image_path FROM publication_images WHERE publication_id = p.id AND is_primary = 1 LIMIT 1) as image
                                FROM publications p
                                WHERE title LIKE ? AND status = 'active'
                                LIMIT 5");
        $stmt->execute(["%$q%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode($results);
        exit;
    }
}
?>