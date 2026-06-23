#!/usr/bin/env php
<?php
/**
 * CRON Job: Clear temporary files
 * Execute hourly
 */

$logFile = __DIR__ . '/../logs/cron_cleanup.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Starting temporary files cleanup");

// Clear cache directory
$cacheDir = __DIR__ . '/../cache/';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file) > 3600)) {
            unlink($file);
            $deleted++;
        }
    }
    writeLog("Cleared $deleted files from cache");
}

// Clear temp uploads older than 24 hours
$tempDir = __DIR__ . '/../uploads/temp/';
if (is_dir($tempDir)) {
    $files = glob($tempDir . '*');
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file) && (time() - filemtime($file) > 86400)) {
            unlink($file);
            $deleted++;
        }
    }
    writeLog("Cleared $deleted temporary uploads");
}

// Clear logs older than 30 days
$logsDir = __DIR__ . '/../logs/';
if (is_dir($logsDir)) {
    $files = glob($logsDir . '*.log');
    $deleted = 0;
    foreach ($files as $file) {
        if (time() - filemtime($file) > 30 * 24 * 60 * 60) {
            unlink($file);
            $deleted++;
        }
    }
    writeLog("Cleared $deleted old log files");
}

writeLog("Cleanup completed");