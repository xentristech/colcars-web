#!/usr/bin/env php
<?php
/**
 * CRON Job: Send weekly email digest
 * Execute every Monday at 09:00
 */

require_once __DIR__ . '/../config/database.php';

$logFile = __DIR__ . '/../logs/cron_digest.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Starting weekly digest");

try {
    $pdo = getDBConnection();
    
    // Get users who want digest (opt-in)
    $query = "SELECT id, email, full_name FROM users WHERE status = 'active' AND receive_digest = 1";
    $users = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Get weekly statistics
    $statsQuery = "SELECT 
                    (SELECT COUNT(*) FROM publications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'active') as new_publications,
                    (SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as new_users,
                    (SELECT COUNT(*) FROM publications WHERE status = 'active') as total_cars";
    $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
    
    // Get top publications from last week
    $topQuery = "SELECT p.id, p.title, p.price, 
                 (SELECT image_path FROM publication_images WHERE publication_id = p.id AND is_primary = 1 LIMIT 1) as image
                 FROM publications p
                 WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                 ORDER BY p.views_count DESC
                 LIMIT 5";
    $topPublications = $pdo->query($topQuery)->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($users as $user) {
        $subject = "Resumen semanal - Easy Car Luxury";
        
        $html = "
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; }
                    .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
                    .stats { display: flex; justify-content: space-around; margin: 20px 0; }
                    .stat { text-align: center; }
                    .stat-number { font-size: 24px; font-weight: bold; color: #e74c3c; }
                    .publication-item { margin: 15px 0; padding: 10px; border: 1px solid #eee; }
                    .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #7f8c8d; }
                </style>
            </head>
            <body>
                <div class='header'>
                    <h1>Easy Car Luxury</h1>
                    <p>Resumen semanal</p>
                </div>
                
                <div class='stats'>
                    <div class='stat'>
                        <div class='stat-number'>{$stats['new_publications']}</div>
                        <div>Nuevos vehículos</div>
                    </div>
                    <div class='stat'>
                        <div class='stat-number'>{$stats['new_users']}</div>
                        <div>Nuevos usuarios</div>
                    </div>
                    <div class='stat'>
                        <div class='stat-number'>{$stats['total_cars']}</div>
                        <div>Vehículos totales</div>
                    </div>
                </div>
                
                <div style='padding: 20px;'>
                    <h2>Más vistos de la semana</h2>";
        
        foreach ($topPublications as $pub) {
            $html .= "
                    <div class='publication-item'>
                        <h3>{$pub['title']}</h3>
                        <p>Precio: $" . number_format($pub['price'], 0, ',', '.') . "</p>
                        <a href='https://easycarluxury.com/catalog/vehicle/{$pub['id']}'>Ver detalles</a>
                    </div>";
        }
        
        $html .= "
                </div>
                
                <div class='footer'>
                    <p>¿Ya no quieres recibir este correo? Cambia tus preferencias en tu panel de usuario.</p>
                    <p>&copy; " . date('Y') . " Easy Car Luxury. Todos los derechos reservados.</p>
                </div>
            </body>
            </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Easy Car Luxury <newsletter@easycarluxury.com>\r\n";
        
        mail($user['email'], $subject, $html, $headers);
        writeLog("Sent digest to {$user['email']}");
    }
    
    writeLog("Weekly digest completed. Sent to " . count($users) . " users");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    exit(1);
}