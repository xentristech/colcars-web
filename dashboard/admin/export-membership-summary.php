<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    die("Error: No has iniciado sesión.");
}

require_once '../../config/database.php';
require_once '../../includes/admin-auth.php';

if (!isset($pdo) || $pdo === null) {
    die("Error: No se pudo establecer conexión con la base de datos");
}

$adminAuth = new AdminAuth($pdo);

try {
    $admin = $adminAuth->verifyAdmin();
    if (!$admin || empty($admin)) {
        die("Error: No tienes permisos de administrador.");
    }
} catch (Exception $e) {
    die("Error de autenticación: " . $e->getMessage());
}

// Obtener formato de exportación
$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Costos por membresía
$membershipCosts = [
    'free' => 20000,
    'pro' => 75000,
    'premium' => 150000,
    'elite' => 300000
];

// Obtener datos
$summaryData = [];
$totalUsers = 0;
$totalRevenue = 0;

foreach ($membershipCosts as $type => $cost) {
    $query = "SELECT COUNT(*) as count FROM usuarios WHERE tipo_cuenta = :type AND activo = 1";
    $stmt = $pdo->prepare($query);
    $stmt->execute([':type' => $type]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $total = $count * $cost;
    
    $summaryData[] = [
        'membership' => ucfirst($type),
        'users' => $count,
        'cost' => $cost,
        'total' => $total
    ];
    
    $totalUsers += $count;
    $totalRevenue += $total;
}

$filename = 'resumen_membresias_' . date('Y-m-d_H-i-s');

// ============================================
// EXPORTAR A CSV
// ============================================
if ($format == 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeceras
    fputcsv($output, ['Membresía', 'Usuarios', 'Costo por Usuario', 'Total']);
    
    // Datos
    foreach ($summaryData as $row) {
        fputcsv($output, [
            $row['membership'],
            $row['users'],
            '$ ' . number_format($row['cost'], 0, ',', '.'),
            '$ ' . number_format($row['total'], 0, ',', '.')
        ]);
    }
    
    // Totales
    fputcsv($output, ['Totales', $totalUsers, '', '$ ' . number_format($totalRevenue, 0, ',', '.')]);
    
    fclose($output);
    exit;
}

// ============================================
// EXPORTAR A EXCEL
// ============================================
if ($format == 'excel') {
    try {
        require_once '../../vendor/autoload.php';
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen Membresías');
        
        // Estilos
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'c8a86b']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        // Cabeceras
        $sheet->setCellValue('A1', 'Membresía');
        $sheet->setCellValue('B1', 'Usuarios');
        $sheet->setCellValue('C1', 'Costo por Usuario');
        $sheet->setCellValue('D1', 'Total');
        
        $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
        
        // Datos
        $row = 2;
        foreach ($summaryData as $item) {
            $sheet->setCellValue('A' . $row, $item['membership']);
            $sheet->setCellValue('B' . $row, $item['users']);
            $sheet->setCellValue('C' . $row, '$ ' . number_format($item['cost'], 0, ',', '.'));
            $sheet->setCellValue('D' . $row, '$ ' . number_format($item['total'], 0, ',', '.'));
            $row++;
        }
        
        // Totales
        $sheet->setCellValue('A' . $row, 'Totales');
        $sheet->setCellValue('B' . $row, $totalUsers);
        $sheet->setCellValue('D' . $row, '$ ' . number_format($totalRevenue, 0, ',', '.'));
        $sheet->getStyle('A' . $row . ':D' . $row)->getFont()->setBold(true);
        
        // Ajustar columnas
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Alineación de números a la derecha
        $sheet->getStyle('B2:B' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle('D2:D' . $row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        die("Error al generar Excel: " . $e->getMessage());
    }
}

// ============================================
// EXPORTAR A PDF
// ============================================
if ($format == 'pdf') {
    try {
        require_once '../../vendor/autoload.php';
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font_size' => 10,
            'default_font' => 'dejavusans',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 15
        ]);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Resumen de Membresías</title>
            <style>
                body {
                    font-family: dejavusans, sans-serif;
                }
                h1 {
                    text-align: center;
                    color: #c8a86b;
                    margin-bottom: 5px;
                    font-size: 18px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #c8a86b;
                    padding-bottom: 10px;
                }
                .header p {
                    margin: 5px 0;
                    color: #666;
                    font-size: 11px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 15px;
                }
                th {
                    background-color: #c8a86b;
                    color: white;
                    padding: 10px 8px;
                    text-align: left;
                    border: 1px solid #a07e4a;
                }
                td {
                    padding: 8px;
                    border: 1px solid #ddd;
                    text-align: left;
                }
                .text-right {
                    text-align: right;
                }
                .font-bold {
                    font-weight: bold;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                    font-size: 9px;
                    color: #999;
                    border-top: 1px solid #eee;
                    padding-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Easy Car Luxury</h1>
                <h2 style="margin: 5px 0; font-size: 14px;">Resumen de Membresías</h2>
                <p>Fecha de generación: ' . date('d/m/Y H:i:s') . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Membresía</th>
                        <th class="text-right">Usuarios</th>
                        <th class="text-right">Costo por Usuario</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($summaryData as $item) {
            $html .= '
                    <tr>
                        <td>' . $item['membership'] . '</td>
                        <td class="text-right">' . number_format($item['users']) . '</td>
                        <td class="text-right">$ ' . number_format($item['cost'], 0, ',', '.') . '</td>
                        <td class="text-right">$ ' . number_format($item['total'], 0, ',', '.') . '</td>
                    </tr>';
        }
        
        $html .= '
                    <tr class="font-bold">
                        <td>Totales</td>
                        <td class="text-right">' . number_format($totalUsers) . '</td>
                        <td class="text-right"></td>
                        <td class="text-right">$ ' . number_format($totalRevenue, 0, ',', '.') . '</td>
                    </tr>
                </tbody>
             </table>
            
            <div class="footer">
                <p>Easy Car Luxury - Sistema de Gestión de Usuarios</p>
                <p>Reporte generado el ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        </html>';
        
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename . '.pdf', 'D');
        exit;
    } catch (Exception $e) {
        die("Error al generar PDF: " . $e->getMessage());
    }
}

die("Formato no válido");
?>