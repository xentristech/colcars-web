#!/usr/bin/env php
<?php
/**
 * EASY CAR LUXURY - CRON para generar reportes DIAN
 * Ejecutar diariamente
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();

// Reporte mensual de facturación
$mes_actual = date('Y-m');
$reporte = $db->getOne("
    SELECT 
        COUNT(*) as total_facturas,
        SUM(total) as total_ventas,
        SUM(iva) as total_iva,
        COUNT(CASE WHEN tipo_documento = 'nota_credito' THEN 1 END) as total_notas_credito,
        COUNT(CASE WHEN tipo_documento = 'nota_debito' THEN 1 END) as total_notas_debito
    FROM facturas
    WHERE DATE_FORMAT(fecha_emision, '%Y-%m') = ?
", [$mes_actual]);

// Guardar reporte
$reporte_path = BACKUP_PATH . 'reports/dian_report_' . $mes_actual . '.json';
if (!is_dir(dirname($reporte_path))) {
    mkdir(dirname($reporte_path), 0777, true);
}
file_put_contents($reporte_path, json_encode([
    'mes' => $mes_actual,
    'fecha_generacion' => date('c'),
    'reporte' => $reporte,
    'detalle' => $db->getAll("
        SELECT numero_factura, tipo_documento, total, estado_dian, fecha_emision
        FROM facturas
        WHERE DATE_FORMAT(fecha_emision, '%Y-%m') = ?
        ORDER BY fecha_emision DESC
    ", [$mes_actual])
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "[" . date('Y-m-d H:i:s') . "] Reporte DIAN generado: $reporte_path\n";