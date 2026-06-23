<?php
session_start();

// ============================================
// VERIFICAR AUTENTICACIÓN POR SESIÓN PHP
// ============================================

// Verificar que el usuario ha iniciado sesión
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    die("Error: No has iniciado sesión. Por favor, inicia sesión nuevamente.");
}

require_once '../../config/database.php';

// Verificar que $pdo existe
if (!isset($pdo) || $pdo === null) {
    die("Error: No se pudo establecer conexión con la base de datos");
}

require_once __DIR__ . '/../../includes/admin-auth.php';

$adminAuth = new AdminAuth($pdo);

// Verificar que el usuario sea administrador
try {
    $admin = $adminAuth->verifyAdmin();
    if (!$admin || empty($admin)) {
        die("Error: No tienes permisos de administrador para exportar auditoría.");
    }
} catch (Exception $e) {
    error_log("Error en verifyAdmin: " . $e->getMessage());
    die("Error de autenticación: " . $e->getMessage() . " - Por favor inicie sesión nuevamente.");
}

// Obtener parámetros de exportación
$export_format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$actionFilter = $_GET['action'] ?? '';
$userFilter = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// ============================================
// FUNCIÓN PARA OBTENER LOGS DE AUDITORÍA
// ============================================
function getAuditLogsForExport($pdo, $actionFilter, $userFilter, $dateFrom, $dateTo) {
    $whereConditions = ["1=1"];
    $params = [];

    if ($actionFilter) {
        $whereConditions[] = "a.action = :action";
        $params[':action'] = $actionFilter;
    }

    if ($userFilter) {
        $whereConditions[] = "a.admin_id = :user_id";
        $params[':user_id'] = $userFilter;
    }

    if ($dateFrom) {
        $whereConditions[] = "DATE(a.created_at) >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo) {
        $whereConditions[] = "DATE(a.created_at) <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    $whereClause = implode(" AND ", $whereConditions);

    $query = "SELECT a.*, u.nombre_completo as admin_name, u.email as admin_email
                FROM admin_audit_log a
                JOIN usuarios u ON a.admin_id = u.id
                WHERE $whereClause
                ORDER BY a.created_at DESC";
    
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Función para traducir acciones a español
function translateActionExport($action) {
    $actions = [
        'CREATE' => 'Creación',
        'UPDATE' => 'Actualización',
        'DELETE' => 'Eliminación',
        'LOGIN' => 'Inicio de sesión',
        'LOGOUT' => 'Cierre de sesión',
        'SUSPEND' => 'Suspensión',
        'RESTORE' => 'Restauración',
        'UPGRADE' => 'Mejora de membresía',
        'DOWNGRADE' => 'Degradación de membresía',
        'ACTIVATE' => 'Activación',
        'DEACTIVATE' => 'Desactivación',
        'FEATURE' => 'Marcar como destacado',
        'UNFEATURE' => 'Quitar destacado',
        'IMPERSONATE' => 'Suplantación de identidad',
        'RESET_PASSWORD' => 'Restablecimiento de contraseña',
        'SOFT_DELETE' => 'Desactivación de cuenta',
        'VIEW' => 'Visualización',
        'READ' => 'Lectura',
        'LOGIN_FAILED' => 'Inicio de sesión fallido',
        'LOGIN_ERROR' => 'Error de inicio de sesión',
        'TEST_EMAIL' => 'Email de prueba'
    ];
    return $actions[$action] ?? $action;
}

// Función para traducir tipos de target
function translateTargetTypeExport($targetType) {
    $types = [
        'usuario' => 'Usuario',
        'categoria' => 'Categoría',
        'membresia' => 'Membresía',
        'publicacion' => 'Publicación',
        'page' => 'Página',
        'session' => 'Sesión'
    ];
    return $types[$targetType] ?? $targetType;
}

// Obtener datos
$auditLogs = getAuditLogsForExport($pdo, $actionFilter, $userFilter, $dateFrom, $dateTo);

// Verificar si hay datos para exportar
if (empty($auditLogs)) {
    die("No hay registros de auditoría para exportar con los filtros seleccionados.");
}

// Nombre del archivo
$filename = 'auditoria_' . date('Y-m-d_H-i-s');

// ============================================
// EXPORTAR A CSV
// ============================================
if ($export_format == 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');
    
    $output = fopen('php://output', 'w');
    
    // Agregar BOM para UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Cabeceras
    fputcsv($output, [
        'ID', 'Fecha/Hora', 'Administrador', 'Email Admin', 'Acción', 'Tipo Target', 'ID Target', 
        'Información Adicional', 'IP', 'Detalles'
    ]);
    
    // Datos
    foreach ($auditLogs as $log) {
        $details = json_decode($log['details'], true);
        $additionalInfo = $details['additional_info'] ?? '';
        
        fputcsv($output, [
            $log['id'],
            date('d/m/Y H:i:s', strtotime($log['created_at'])),
            $log['admin_name'],
            $log['admin_email'],
            translateActionExport($log['action']),
            translateTargetTypeExport($log['target_type']),
            $log['target_id'],
            $additionalInfo,
            $log['ip_address'] ?? 'N/A',
            $log['details']
        ]);
    }
    
    fclose($output);
    exit;
}

// ============================================
// EXPORTAR A EXCEL (XLSX usando PHPSpreadsheet)
// ============================================
if ($export_format == 'excel') {
    try {
        require_once '../../vendor/autoload.php';
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Auditoría');
        
        // Estilos para cabecera
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'c8a86b']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        // Cabeceras
        $headers = ['ID', 'Fecha/Hora', 'Administrador', 'Email Admin', 'Acción', 'Tipo Target', 'ID Target', 'Información Adicional', 'IP', 'Detalles'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        
        // Datos
        $row = 2;
        foreach ($auditLogs as $log) {
            $details = json_decode($log['details'], true);
            $additionalInfo = $details['additional_info'] ?? '';
            
            $sheet->setCellValue('A' . $row, $log['id']);
            $sheet->setCellValue('B' . $row, date('d/m/Y H:i:s', strtotime($log['created_at'])));
            $sheet->setCellValue('C' . $row, $log['admin_name']);
            $sheet->setCellValue('D' . $row, $log['admin_email']);
            $sheet->setCellValue('E' . $row, translateActionExport($log['action']));
            $sheet->setCellValue('F' . $row, translateTargetTypeExport($log['target_type']));
            $sheet->setCellValue('G' . $row, $log['target_id']);
            $sheet->setCellValue('H' . $row, $additionalInfo);
            $sheet->setCellValue('I' . $row, $log['ip_address'] ?? 'N/A');
            $sheet->setCellValue('J' . $row, $log['details']);
            $row++;
        }
        
        // Aplicar bordes a toda la tabla
        $lastRow = $row - 1;
        $lastCol = 'J';
        $borderStyle = [
            'borders' => [
                'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]
            ]
        ];
        $sheet->getStyle('A1:' . $lastCol . $lastRow)->applyFromArray($borderStyle);
        
        // Auto-filtro
        $sheet->setAutoFilter('A1:' . $lastCol . '1');
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');
        
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        error_log("Error Excel: " . $e->getMessage());
        die("Error al generar el archivo Excel: " . $e->getMessage() . " - Verifique que PHPSpreadsheet esté instalado.");
    }
}

// ============================================
// EXPORTAR A PDF usando mPDF
// ============================================
if ($export_format == 'pdf') {
    try {
        require_once '../../vendor/autoload.php';
        
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'default_font_size' => 9,
            'default_font' => 'dejavusans',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 15,
            'margin_bottom' => 15
        ]);
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Auditoría</title>
            <style>
                body {
                    font-family: dejavusans, sans-serif;
                    margin: 0;
                    padding: 0;
                }
                h1 {
                    text-align: center;
                    color: #c8a86b;
                    margin-bottom: 5px;
                    font-size: 18px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 15px;
                    border-bottom: 2px solid #c8a86b;
                    padding-bottom: 8px;
                }
                .header p {
                    margin: 3px 0;
                    color: #666;
                    font-size: 10px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                    font-size: 8px;
                }
                th {
                    background-color: #c8a86b;
                    color: white;
                    padding: 6px 4px;
                    text-align: left;
                    border: 1px solid #a07e4a;
                }
                td {
                    padding: 5px 4px;
                    border: 1px solid #ddd;
                    text-align: left;
                }
                tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                .footer {
                    text-align: center;
                    margin-top: 15px;
                    font-size: 8px;
                    color: #999;
                    border-top: 1px solid #eee;
                    padding-top: 8px;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Colcars</h1>
                <h2 style="margin: 5px 0; font-size: 14px;">Reporte de Auditoría</h2>
                <p>Fecha de generación: ' . date('d/m/Y H:i:s') . '</p>
                <p>Total de registros: ' . count($auditLogs) . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha/Hora</th>
                        <th>Administrador</th>
                        <th>Email</th>
                        <th>Acción</th>
                        <th>Tipo Target</th>
                        <th>ID Target</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($auditLogs as $log) {
            $html .= '
                    <tr>
                        <td>' . $log['id'] . '</td>
                        <td>' . date('d/m/Y H:i:s', strtotime($log['created_at'])) . '</td>
                        <td>' . htmlspecialchars($log['admin_name'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($log['admin_email'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . translateActionExport($log['action']) . '</td>
                        <td>' . translateTargetTypeExport($log['target_type']) . '</td>
                        <td>' . ($log['target_id'] ?? '') . '</td>
                        <td>' . htmlspecialchars($log['ip_address'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <div class="footer">
                <p>Colcars - Sistema de Gestión de Usuarios</p>
                <p>Reporte generado el ' . date('d/m/Y H:i:s') . '</p>
            </div>
        </body>
        </html>';
        
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename . '.pdf', 'D');
        exit;
    } catch (Exception $e) {
        error_log("Error PDF: " . $e->getMessage());
        die("Error al generar el archivo PDF: " . $e->getMessage() . " - Verifique que mPDF esté instalado.");
    }
}

// Si llegamos aquí, el formato no es válido
die("Formato de exportación no válido. Use csv, excel o pdf.");
?>