<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// CORREGIDO: ruta correcta a database.php desde api/v1/
// La estructura es: api/v1/search_suggestions.php
// Database está en: ../../config/database.php

require_once __DIR__ . '/../../config/database.php';

// Verificar que la clase Database existe
if (!class_exists('Database')) {
    echo json_encode(['success' => false, 'error' => 'Database class not found']);
    exit;
}

$database = Database::getInstance();
$pdo = $database->getConnection();

if (!$pdo) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

$response = ['success' => false, 'suggestions' => []];

if (strlen($query) >= 2) {
    try {
        $sql = "SELECT p.id, p.titulo, p.precio, c.nombre as categoria 
                FROM publicaciones p
                LEFT JOIN categorias c ON p.categoria_id = c.id
                JOIN usuarios u ON p.usuario_id = u.id
                WHERE p.status = 'active' 
                AND u.activo = 1
                AND (p.titulo LIKE :query 
                    OR p.brand LIKE :query 
                    OR p.linea_modelo_comercial LIKE :query)
                ORDER BY p.destacado DESC, p.created_at DESC
                LIMIT 8";
        
        $stmt = $pdo->prepare($sql);
        $searchTerm = "%{$query}%";
        $stmt->bindParam(':query', $searchTerm, PDO::PARAM_STR);
        $stmt->execute();
        
        $suggestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $response['success'] = true;
        $response['suggestions'] = $suggestions;
    } catch (PDOException $e) {
        $response['error'] = $e->getMessage();
    }
}

echo json_encode($response);
?>