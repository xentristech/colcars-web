#!/usr/bin/env php
<?php
/**
 * CRON Job: Database backup
 * Execute daily at 02:00
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/backup.php';

$logFile = __DIR__ . '/../logs/cron_backup.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Starting database backup");

try {
    $pdo = getDBConnection();
    $backup = new DatabaseBackup($pdo);
    $result = $backup->createBackup();
    
    if ($result['success']) {
        writeLog("Backup created successfully: " . $result['filename']);
        
        // Upload to remote storage (optional)
        // uploadToS3($result['filename']);
        
        // Clean old backups (keep last 30 days)
        $backupDir = __DIR__ . '/../backups/';
        $files = glob($backupDir . 'backup_*.sql.gz');
        $now = time();
        foreach ($files as $file) {
            if ($now - filemtime($file) > 30 * 24 * 60 * 60) {
                unlink($file);
                writeLog("Deleted old backup: " . basename($file));
            }
        }
    } else {
        writeLog("Backup failed: " . $result['error']);
    }
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    exit(1);
}