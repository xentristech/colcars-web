#!/usr/bin/env php
<?php
/**
 * CRON Job: Update statistics cache
 * Execute every hour
 */

require_once __DIR__ . '/../config/database.php';

$logFile = __DIR__ . '/../logs/cron_stats.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Updating statistics cache");

try {
    $pdo = getDBConnection();
    
    // Clear old cache
    $cacheDir = __DIR__ . '/../cache/';
    if (is_dir($cacheDir)) {
        array_map('unlink', glob($cacheDir . 'stats_*'));
    }
    
    // Calculate daily statistics
    $stats = [];
    
    // Users by day (last 30 days)
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $query = "SELECT COUNT(*) FROM users WHERE DATE(created_at) = '$date'";
        $stats['users_daily'][$date] = (int)$pdo->query($query)->fetchColumn();
    }
    
    // Revenue by day (last 30 days)
    for ($i = 29; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $query = "SELECT COALESCE(SUM(amount), 0) FROM payments WHERE DATE(payment_date) = '$date' AND status = 'completed'";
        $stats['revenue_daily'][$date] = (float)$pdo->query($query)->fetchColumn();
    }
    
    // Publications by category
    $query = "SELECT c.name, COUNT(p.id) as count 
              FROM categories c
              LEFT JOIN publications p ON p.category_id = c.id AND p.status = 'active'
              GROUP BY c.id";
    $stats['publications_by_category'] = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Popular searches
    $query = "SELECT search_term, COUNT(*) as count 
              FROM search_log 
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
              GROUP BY search_term 
              ORDER BY count DESC 
              LIMIT 10";
    $stats['popular_searches'] = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Save to cache file
    file_put_contents($cacheDir . 'stats_global.json', json_encode($stats));
    
    writeLog("Statistics cache updated");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    exit(1);
}