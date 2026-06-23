<?php
/**
 * API - Exportar Estadísticas
 * Genera reportes en Excel (XLSX), CSV y PDF
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Cargar autoload de Composer para PHPSpreadsheet y mPDF
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Mpdf\Mpdf;

// Verificar autenticación
requireAuth();

$user_id = $_SESSION['user_id'];
$user = Database::getInstance()->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Token de seguridad inválido']);
    exit;
}

$formato = $_POST['formato'] ?? 'excel';
$tipo_reporte = $_POST['tipo_reporte'] ?? 'completo';
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d', strtotime('-30 days'));
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');

$db = Database::getInstance();

// ============================================
// RECOLECTAR DATOS
// ============================================

// 1. Resumen general
$resumen = $db->getOne("
    SELECT 
        COUNT(p.id) as total_publicaciones,
        SUM(p.visitas) as total_visitas,
        SUM(p.likes) as total_likes,
        (SELECT COUNT(*) FROM comentarios c JOIN publicaciones p2 ON c.publicacion_id = p2.id WHERE p2.usuario_id = ? AND c.visible = 1) as total_comentarios,
        (SELECT COUNT(*) FROM offers o JOIN publicaciones p2 ON o.publication_id = p2.id WHERE p2.usuario_id = ?) as total_ofertas
    FROM publicaciones p
    WHERE p.usuario_id = ? AND p.status = 'active'
", [$user_id, $user_id, $user_id]);

// 2. Visitas por día
$visitas = $db->getAll("
    SELECT DATE(pv.viewed_at) as fecha, COUNT(*) as visitas
    FROM publication_views pv
    JOIN publicaciones p ON pv.publication_id = p.id
    WHERE p.usuario_id = ? AND DATE(pv.viewed_at) BETWEEN ? AND ?
    GROUP BY DATE(pv.viewed_at)
    ORDER BY fecha DESC
", [$user_id, $fecha_inicio, $fecha_fin]);

// 3. Top publicaciones
$top_publicaciones = $db->getAll("
    SELECT p.titulo, p.visitas, p.likes,
           (SELECT COUNT(*) FROM comentarios WHERE publicacion_id = p.id AND visible = 1) as comentarios
    FROM publicaciones p
    WHERE p.usuario_id = ? AND p.status = 'active'
    ORDER BY p.visitas DESC
    LIMIT 20
", [$user_id]);

// 4. Rendimiento por categoría
$categorias = $db->getAll("
    SELECT c.nombre as categoria, COUNT(p.id) as publicaciones, SUM(p.visitas) as visitas
    FROM publicaciones p
    JOIN categorias c ON p.categoria_id = c.id
    WHERE p.usuario_id = ? AND p.status = 'active'
    GROUP BY c.id
    ORDER BY visitas DESC
", [$user_id]);

// 5. Horario de visitas
$horario = $db->getAll("
    SELECT HOUR(pv.viewed_at) as hora, COUNT(*) as visitas
    FROM publication_views pv
    JOIN publicaciones p ON pv.publication_id = p.id
    WHERE p.usuario_id = ?
    GROUP BY HOUR(pv.viewed_at)
    ORDER BY hora ASC
", [$user_id]);

// ============================================
// FUNCIÓN: EXPORTAR A PDF (usando mPDF)
// ============================================
function exportPDF($resumen, $visitas, $top_publicaciones, $categorias, $horario, $user, $fecha_inicio, $fecha_fin) {
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L', // Landscape
        'margin_left' => 10,
        'margin_right' => 10,
        'margin_top' => 10,
        'margin_bottom' => 10
    ]);
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Reporte de Estadísticas</title>
        <style>
            body { font-family: "DejaVu Sans", sans-serif; font-size: 10pt; }
            h1 { color: #667eea; font-size: 18pt; text-align: center; margin-bottom: 5px; }
            h2 { color: #333; font-size: 14pt; margin-top: 20px; margin-bottom: 10px; background: #f0f0f0; padding: 5px; }
            .header-info { text-align: center; margin-bottom: 20px; font-size: 9pt; color: #666; }
            table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
            th { background: #667eea; color: white; padding: 8px; text-align: left; }
            td { padding: 6px; border-bottom: 1px solid #ddd; }
            .total-row { font-weight: bold; background: #f5f5f5; }
            .footer { margin-top: 30px; text-align: center; font-size: 8pt; color: #999; border-top: 1px solid #ddd; padding-top: 10px; }
        </style>
    </head>
    <body>
        <h1>📊 Reporte de Estadísticas</h1>
        <div class="header-info">
            <strong>Usuario:</strong> ' . htmlspecialchars($user['nombre_completo']) . '<br>
            <strong>Email:</strong> ' . htmlspecialchars($user['email']) . '<br>
            <strong>Tipo de cuenta:</strong> ' . strtoupper($user['tipo_cuenta']) . '<br>
            <strong>Período:</strong> ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)) . '<br>
            <strong>Fecha generación:</strong> ' . date('d/m/Y H:i:s') . '
        </div>
        
        <h2>📈 Resumen General</h2>
        <table>
            <tr><th style="width:50%">Métrica</th><th>Valor</th></tr>
            <tr><td>Total Publicaciones</td><td>' . number_format($resumen['total_publicaciones'] ?? 0) . '</td></tr>
            <tr><td>Total Visitas</td><td>' . number_format($resumen['total_visitas'] ?? 0) . '</td></tr>
            <tr><td>Total Likes</td><td>' . number_format($resumen['total_likes'] ?? 0) . '</td></tr>
            <tr><td>Total Comentarios</td><td>' . number_format($resumen['total_comentarios'] ?? 0) . '</td></tr>
            <tr><td>Total Ofertas</td><td>' . number_format($resumen['total_ofertas'] ?? 0) . '</td></tr>
        </table>
        
        <h2>📅 Visitas por Día</h2>
        <table>
            <tr><th>Fecha</th><th>Visitas</th></tr>';
    
    foreach ($visitas as $v) {
        $html .= '<tr><td>' . date('d/m/Y', strtotime($v['fecha'])) . '</td><td>' . number_format($v['visitas']) . '</td></tr>';
    }
    
    $html .= '</table>
        
        <h2>🏆 Top Publicaciones</h2>
        <table>
            <tr><th>Título</th><th>Visitas</th><th>Likes</th><th>Comentarios</th></tr>';
    
    foreach ($top_publicaciones as $pub) {
        $html .= '<tr>
            <td>' . htmlspecialchars(substr($pub['titulo'], 0, 50)) . '</td>
            <td>' . number_format($pub['visitas']) . '</td>
            <td>' . number_format($pub['likes']) . '</td>
            <td>' . number_format($pub['comentarios']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
        
        <h2>📂 Rendimiento por Categoría</h2>
        <table>
            <tr><th>Categoría</th><th>Publicaciones</th><th>Visitas</th></tr>';
    
    foreach ($categorias as $cat) {
        $html .= '<tr>
            <td>' . htmlspecialchars($cat['categoria']) . '</td>
            <td>' . number_format($cat['publicaciones']) . '</td>
            <td>' . number_format($cat['visitas']) . '</td>
        </tr>';
    }
    
    $html .= '</table>
        
        <h2>⏰ Horario con Más Visitas</h2>
        <table>
            <tr><th>Hora</th><th>Visitas</th></tr>';
    
    for ($i = 0; $i < 24; $i++) {
        $visitas_hora = 0;
        foreach ($horario as $h) {
            if ($h['hora'] == $i) {
                $visitas_hora = $h['visitas'];
                break;
            }
        }
        $html .= '<tr><td>' . str_pad($i, 2, '0', STR_PAD_LEFT) . ':00</td><td>' . number_format($visitas_hora) . '</td></tr>';
    }
    
    $html .= '</table>
        
        <div class="footer">
            Reporte generado por Colcars - Sistema de Estadísticas<br>
            &copy; ' . date('Y') . ' Colcars - Todos los derechos reservados
        </div>
    </body>
    </html>';
    
    $mpdf->WriteHTML($html);
    $mpdf->Output('reporte_estadisticas_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// ============================================
// FUNCIÓN: EXPORTAR A CSV
// ============================================
function exportCSV($data, $filename) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM para UTF-8
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// ============================================
// FUNCIÓN: EXPORTAR A EXCEL (XLSX)
// ============================================
function exportExcel($resumen, $visitas, $top_publicaciones, $categorias, $horario, $user, $fecha_inicio, $fecha_fin) {
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getProperties()
        ->setCreator('Colcars')
        ->setLastModifiedBy('Colcars')
        ->setTitle('Reporte de Estadísticas')
        ->setSubject('Estadísticas de usuario');
    
    // Eliminar hoja por defecto
    $spreadsheet->removeSheetByIndex(0);
    
    // ============================================
    // HOJA 1: RESUMEN GENERAL
    // ============================================
    $sheet1 = $spreadsheet->createSheet();
    $sheet1->setTitle('Resumen General');
    
    $sheet1->setCellValue('A1', 'REPORTE DE ESTADÍSTICAS');
    $sheet1->setCellValue('A2', 'Usuario: ' . $user['nombre_completo']);
    $sheet1->setCellValue('A3', 'Email: ' . $user['email']);
    $sheet1->setCellValue('A4', 'Tipo cuenta: ' . strtoupper($user['tipo_cuenta']));
    $sheet1->setCellValue('A5', 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)));
    $sheet1->setCellValue('A6', 'Fecha generación: ' . date('d/m/Y H:i:s'));
    $sheet1->setCellValue('A8', 'Métrica');
    $sheet1->setCellValue('B8', 'Valor');
    
    $sheet1->setCellValue('A9', 'Total Publicaciones');
    $sheet1->setCellValue('B9', $resumen['total_publicaciones'] ?? 0);
    $sheet1->setCellValue('A10', 'Total Visitas');
    $sheet1->setCellValue('B10', $resumen['total_visitas'] ?? 0);
    $sheet1->setCellValue('A11', 'Total Likes');
    $sheet1->setCellValue('B11', $resumen['total_likes'] ?? 0);
    $sheet1->setCellValue('A12', 'Total Comentarios');
    $sheet1->setCellValue('B12', $resumen['total_comentarios'] ?? 0);
    $sheet1->setCellValue('A13', 'Total Ofertas');
    $sheet1->setCellValue('B13', $resumen['total_ofertas'] ?? 0);
    
    $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    $sheet1->getStyle('A8:B8')->getFont()->setBold(true);
    $sheet1->getStyle('A8:B8')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF667eea');
    $sheet1->getStyle('A8:B8')->getFont()->getColor()->setARGB('FFFFFFFF');
    foreach (range('A', 'B') as $col) {
        $sheet1->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ============================================
    // HOJA 2: VISITAS POR DÍA
    // ============================================
    $sheet2 = $spreadsheet->createSheet();
    $sheet2->setTitle('Visitas por Día');
    
    $sheet2->setCellValue('A1', 'VISITAS POR DÍA');
    $sheet2->setCellValue('A3', 'Fecha');
    $sheet2->setCellValue('B3', 'Visitas');
    
    $row = 4;
    foreach ($visitas as $v) {
        $sheet2->setCellValue('A' . $row, date('d/m/Y', strtotime($v['fecha'])));
        $sheet2->setCellValue('B' . $row, $v['visitas']);
        $row++;
    }
    
    $sheet2->getStyle('A3:B3')->getFont()->setBold(true);
    $sheet2->getStyle('A3:B3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF667eea');
    $sheet2->getColumnDimension('A')->setAutoSize(true);
    $sheet2->getColumnDimension('B')->setAutoSize(true);
    
    // ============================================
    // HOJA 3: TOP PUBLICACIONES
    // ============================================
    $sheet3 = $spreadsheet->createSheet();
    $sheet3->setTitle('Top Publicaciones');
    
    $sheet3->setCellValue('A1', 'TOP PUBLICACIONES');
    $sheet3->setCellValue('A3', 'Título');
    $sheet3->setCellValue('B3', 'Visitas');
    $sheet3->setCellValue('C3', 'Likes');
    $sheet3->setCellValue('D3', 'Comentarios');
    
    $row = 4;
    foreach ($top_publicaciones as $pub) {
        $sheet3->setCellValue('A' . $row, $pub['titulo']);
        $sheet3->setCellValue('B' . $row, $pub['visitas']);
        $sheet3->setCellValue('C' . $row, $pub['likes']);
        $sheet3->setCellValue('D' . $row, $pub['comentarios']);
        $row++;
    }
    
    $sheet3->getStyle('A3:D3')->getFont()->setBold(true);
    $sheet3->getStyle('A3:D3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF667eea');
    foreach (range('A', 'D') as $col) {
        $sheet3->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ============================================
    // HOJA 4: RENDIMIENTO POR CATEGORÍA
    // ============================================
    $sheet4 = $spreadsheet->createSheet();
    $sheet4->setTitle('Categorías');
    
    $sheet4->setCellValue('A1', 'RENDIMIENTO POR CATEGORÍA');
    $sheet4->setCellValue('A3', 'Categoría');
    $sheet4->setCellValue('B3', 'Publicaciones');
    $sheet4->setCellValue('C3', 'Visitas');
    
    $row = 4;
    foreach ($categorias as $cat) {
        $sheet4->setCellValue('A' . $row, $cat['categoria']);
        $sheet4->setCellValue('B' . $row, $cat['publicaciones']);
        $sheet4->setCellValue('C' . $row, $cat['visitas']);
        $row++;
    }
    
    $sheet4->getStyle('A3:C3')->getFont()->setBold(true);
    $sheet4->getStyle('A3:C3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF667eea');
    foreach (range('A', 'C') as $col) {
        $sheet4->getColumnDimension($col)->setAutoSize(true);
    }
    
    // ============================================
    // HOJA 5: HORARIO DE VISITAS
    // ============================================
    $sheet5 = $spreadsheet->createSheet();
    $sheet5->setTitle('Horario');
    
    $sheet5->setCellValue('A1', 'HORARIO CON MÁS VISITAS');
    $sheet5->setCellValue('A3', 'Hora');
    $sheet5->setCellValue('B3', 'Visitas');
    
    $row = 4;
    for ($i = 0; $i < 24; $i++) {
        $visitas_hora = 0;
        foreach ($horario as $h) {
            if ($h['hora'] == $i) {
                $visitas_hora = $h['visitas'];
                break;
            }
        }
        $sheet5->setCellValue('A' . $row, str_pad($i, 2, '0', STR_PAD_LEFT) . ':00');
        $sheet5->setCellValue('B' . $row, $visitas_hora);
        $row++;
    }
    
    $sheet5->getStyle('A3:B3')->getFont()->setBold(true);
    $sheet5->getStyle('A3:B3')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF667eea');
    $sheet5->getColumnDimension('A')->setAutoSize(true);
    $sheet5->getColumnDimension('B')->setAutoSize(true);
    
    $spreadsheet->setActiveSheetIndex(0);
    
    // Exportar
    $filename = 'reporte_estadisticas_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

// ============================================
// EXPORTAR SEGÚN FORMATO
// ============================================

// Preparar datos para CSV (cuando se solicita CSV)
if ($formato === 'csv') {
    $csv_data = [];
    
    if ($tipo_reporte === 'visitas') {
        $csv_data[] = ['FECHA', 'VISITAS'];
        foreach ($visitas as $v) {
            $csv_data[] = [date('d/m/Y', strtotime($v['fecha'])), $v['visitas']];
        }
        exportCSV($csv_data, 'reporte_visitas');
    } elseif ($tipo_reporte === 'publicaciones') {
        $csv_data[] = ['TITULO', 'VISITAS', 'LIKES', 'COMENTARIOS'];
        foreach ($top_publicaciones as $pub) {
            $csv_data[] = [$pub['titulo'], $pub['visitas'], $pub['likes'], $pub['comentarios']];
        }
        exportCSV($csv_data, 'reporte_publicaciones');
    } else {
        // Reporte completo en CSV (múltiples secciones)
        $csv_data[] = ['=== RESUMEN GENERAL ==='];
        $csv_data[] = ['Métrica', 'Valor'];
        $csv_data[] = ['Total Publicaciones', $resumen['total_publicaciones'] ?? 0];
        $csv_data[] = ['Total Visitas', $resumen['total_visitas'] ?? 0];
        $csv_data[] = ['Total Likes', $resumen['total_likes'] ?? 0];
        $csv_data[] = ['Total Comentarios', $resumen['total_comentarios'] ?? 0];
        $csv_data[] = ['Total Ofertas', $resumen['total_ofertas'] ?? 0];
        $csv_data[] = [];
        $csv_data[] = ['=== VISITAS POR DÍA ==='];
        $csv_data[] = ['Fecha', 'Visitas'];
        foreach ($visitas as $v) {
            $csv_data[] = [date('d/m/Y', strtotime($v['fecha'])), $v['visitas']];
        }
        $csv_data[] = [];
        $csv_data[] = ['=== TOP PUBLICACIONES ==='];
        $csv_data[] = ['Título', 'Visitas', 'Likes', 'Comentarios'];
        foreach ($top_publicaciones as $pub) {
            $csv_data[] = [$pub['titulo'], $pub['visitas'], $pub['likes'], $pub['comentarios']];
        }
        exportCSV($csv_data, 'reporte_completo');
    }
} elseif ($formato === 'pdf') {
    // Exportar a PDF
    exportPDF($resumen, $visitas, $top_publicaciones, $categorias, $horario, $user, $fecha_inicio, $fecha_fin);
} else {
    // Exportar a Excel
    exportExcel($resumen, $visitas, $top_publicaciones, $categorias, $horario, $user, $fecha_inicio, $fecha_fin);
}
?>