#!/usr/bin/env php
<?php
/**
 * CRON Job: Clear cache every hour
 */

$cacheDir = __DIR__ . '/../cache/';
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    echo "Cache cleared at " . date('Y-m-d H:i:s') . "\n";
}