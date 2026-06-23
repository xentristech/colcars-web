#!/usr/bin/env php
<?php
/**
 * CRON Job: Expire memberships
 * Execute daily at 00:00
 */

require_once __DIR__ . '/../config/database.php';

$logFile = __DIR__ . '/../logs/cron_expire_memberships.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Starting membership expiration check");

try {
    $pdo = getDBConnection();
    
    // Get expired memberships
    $query = "SELECT um.*, u.email, u.full_name 
              FROM user_memberships um
              JOIN users u ON um.user_id = u.id
              WHERE um.end_date < NOW() 
              AND um.status = 'active'";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $expired = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("Found " . count($expired) . " expired memberships");
    
    foreach ($expired as $membership) {
        // Update membership status
        $update = "UPDATE user_memberships SET status = 'expired', updated_at = NOW() WHERE id = ?";
        $pdo->prepare($update)->execute([$membership['id']]);
        
        // Downgrade user to free tier
        $downgrade = "UPDATE users SET membership_tier = 'free' WHERE id = ?";
        $pdo->prepare($downgrade)->execute([$membership['user_id']]);
        
        // Deactivate publications that exceed free limit
        $maxFree = 1; // Default free limit
        $configQuery = "SELECT config_value FROM system_config WHERE config_key = 'max_publications_free'";
        $configStmt = $pdo->query($configQuery);
        if ($configStmt) {
            $maxFree = (int)$configStmt->fetchColumn();
        }
        
        // Get user's active publications
        $pubQuery = "SELECT id FROM publications WHERE user_id = ? AND status = 'active' ORDER BY created_at DESC";
        $pubStmt = $pdo->prepare($pubQuery);
        $pubStmt->execute([$membership['user_id']]);
        $publications = $pubStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Deactivate publications beyond limit
        if (count($publications) > $maxFree) {
            $toDeactivate = array_slice($publications, $maxFree);
            foreach ($toDeactivate as $pubId) {
                $deactivate = "UPDATE publications SET status = 'inactive' WHERE id = ?";
                $pdo->prepare($deactivate)->execute([$pubId]);
                writeLog("Deactivated publication #$pubId for user {$membership['user_id']}");
            }
        }
        
        // Send notification email
        $to = $membership['email'];
        $subject = "Tu membresía ha expirado - Easy Car Luxury";
        $message = "
            <html>
            <body>
                <h2>Hola {$membership['full_name']}</h2>
                <p>Tu membresía ha expirado. Tu cuenta ha sido revertida al plan Free.</p>
                <p>Los beneficios que tenías ya no están disponibles. Para recuperarlos, puedes renovar tu membresía desde tu panel de control.</p>
                <p><a href='https://easycarluxury.com/dashboard/user/membership.php'>Renovar membresía</a></p>
                <br>
                <p>¡Gracias por confiar en Easy Car Luxury!</p>
            </body>
            </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Easy Car Luxury <noreply@easycarluxury.com>\r\n";
        
        mail($to, $subject, $message, $headers);
        writeLog("Sent expiration email to {$membership['email']}");
    }
    
    writeLog("Membership expiration check completed");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    exit(1);
}