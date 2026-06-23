#!/usr/bin/env php
<?php
/**
 * CRON Job: Send pending invoices to DIAN
 * Execute every 15 minutes
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/dian.php';

$logFile = __DIR__ . '/../logs/dian_cron.log';

function writeLog($message) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND);
}

writeLog("Starting DIAN cron job");

try {
    $pdo = getDBConnection();
    $dian = new DianElectronicInvoicing($pdo, DIAN_ENVIRONMENT);
    
    // Get pending invoices (not sent to DIAN)
    $query = "SELECT i.*, p.amount, p.payment_date, u.id as user_id, u.full_name as user_name
              FROM invoices i
              JOIN payments p ON i.payment_id = p.id
              JOIN users u ON p.user_id = u.id
              LEFT JOIN dian_transactions dt ON i.id = dt.invoice_id
              WHERE dt.id IS NULL AND i.dian_status IS NULL
              AND p.status = 'completed'
              AND i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
              LIMIT 100";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $pendingInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    writeLog("Found " . count($pendingInvoices) . " pending invoices");
    
    foreach ($pendingInvoices as $invoice) {
        writeLog("Processing invoice #{$invoice['invoice_number']} for user {$invoice['user_id']}");
        
        // Prepare invoice data for DIAN
        $invoiceData = [
            'invoice_id' => $invoice['id'],
            'invoice_number' => $invoice['invoice_number'],
            'payment_terms' => 'CONTADO',
            'customer_nit' => $invoice['customer_nit'] ?? '900000001', // Default if not set
            'customer_name' => $invoice['customer_name'] ?? $invoice['user_name'],
            'customer_tax_scheme' => $invoice['customer_tax_scheme'] ?? '01',
            'items' => [
                [
                    'product_id' => 'MEMBERSHIP',
                    'description' => $invoice['concept'],
                    'quantity' => 1,
                    'unit_price' => $invoice['amount'] / 1.19, // Remove IVA
                    'unit_code' => 'ZZ'
                ]
            ]
        ];
        
        $result = $dian->sendInvoice($invoiceData, $invoice['user_id']);
        
        if ($result['success']) {
            writeLog("✓ Invoice {$invoice['invoice_number']} sent successfully. CUFE: {$result['cufe']}");
        } else {
            writeLog("✗ Failed to send invoice {$invoice['invoice_number']}: {$result['message']}");
        }
        
        // Avoid rate limiting
        sleep(2);
    }
    
    writeLog("DIAN cron job completed");
    
} catch (Exception $e) {
    writeLog("ERROR: " . $e->getMessage());
    exit(1);
}