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
        die("Error: No tienes permisos de administrador para exportar usuarios.");
    }
} catch (Exception $e) {
    error_log("Error en verifyAdmin: " . $e->getMessage());
    die("Error de autenticación: " . $e->getMessage() . " - Por favor inicie sesión nuevamente.");
}

// Obtener parámetros de exportación
$export_format = isset($_GET['format']) ? $_GET['format'] : 'csv';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'todos';
$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role_filter'] ?? '';

error_log("Exportando: formato=$export_format, tab=$active_tab, search=$search, roleFilter=$roleFilter");

// ============================================
// FUNCIÓN PARA OBTENER USUARIOS SEGÚN EL TAB
// ============================================
function getUsersForExport($pdo, $tab, $search, $roleFilter) {
    $where = [];
    $params = [];
    
    // Condiciones según el tab
    switch($tab) {
        case 'todos':
            // Todos los usuarios (activos e inactivos)
            break;
        case 'free':
            $where[] = "u.tipo_cuenta = 'free'";
            $where[] = "u.activo = 1";
            break;
        case 'pro':
            $where[] = "u.tipo_cuenta = 'pro'";
            $where[] = "u.activo = 1";
            break;
        case 'premium':
            $where[] = "u.tipo_cuenta = 'premium'";
            $where[] = "u.activo = 1";
            break;
        case 'elite':
            $where[] = "u.tipo_cuenta = 'elite'";
            $where[] = "u.activo = 1";
            break;
        case 'sistema':
            $where[] = "r.nombre IN ('superadmin', 'ingeniero', 'contador', 'tecnico', 'asesor')";
            $where[] = "u.activo = 1";
            break;
        case 'activos':
            $where[] = "u.activo = 1";
            break;
        case 'inactivos':
            $where[] = "u.activo = 0";
            break;
        default:
            $where[] = "u.activo = 1";
            break;
    }
    
    // Búsqueda
    if (!empty($search)) {
        $where[] = "(u.nombre_completo LIKE ? OR u.email LIKE ? OR u.username LIKE ? OR u.id = ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = is_numeric($search) ? (int)$search : 0;
    }
    
    // Filtro por rol
    if (!empty($roleFilter)) {
        $where[] = "r.nombre = ?";
        $params[] = $roleFilter;
    }
    
    $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get users - sin límite para exportación completa
    $query = "SELECT u.id, u.nombre_completo as full_name, u.email, u.username, u.telefono as phone, 
              r.nombre as role, u.tipo_cuenta as membership_tier, 
              CASE WHEN u.activo = 1 THEN 'Activo' ELSE 'Inactivo' END as status,
              DATE_FORMAT(u.created_at, '%d/%m/%Y') as created_date,
              DATE_FORMAT(u.ultimo_acceso, '%d/%m/%Y %H:%i') as last_login,
              CASE WHEN u.email_verificado = 1 THEN 'Sí' ELSE 'No' END as email_verified
       FROM usuarios u 
       JOIN roles r ON u.rol_id = r.id 
       $whereClause 
       ORDER BY u.id ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return $users;
}

// Obtener datos
$users = getUsersForExport($pdo, $active_tab, $search, $roleFilter);

// Verificar si hay usuarios para exportar
if (empty($users)) {
    die("No hay usuarios para exportar con los filtros seleccionados.");
}

// Nombre del archivo
$filename = 'usuarios_' . $active_tab . '_' . date('Y-m-d_H-i-s');

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
        'ID', 'Nombre Completo', 'Email', 'Usuario', 'Teléfono', 'Rol', 
        'Membresía', 'Estado', 'Fecha Registro', 'Último Acceso', 'Email Verificado'
    ]);
    
    // Datos
    foreach ($users as $user) {
        fputcsv($output, [
            $user['id'],
            $user['full_name'],
            $user['email'],
            $user['username'],
            $user['phone'] ?? 'N/A',
            ucfirst($user['role']),
            ucfirst($user['membership_tier']),
            $user['status'],
            $user['created_date'],
            $user['last_login'] ?? 'Nunca',
            $user['email_verified']
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
        $sheet->setTitle('Usuarios');
        
        // Estilos para cabecera
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['rgb' => 'c8a86b']],
            'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER]
        ];
        
        // Cabeceras
        $headers = ['ID', 'Nombre Completo', 'Email', 'Usuario', 'Teléfono', 'Rol', 'Membresía', 'Estado', 'Fecha Registro', 'Último Acceso', 'Email Verificado'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->applyFromArray($headerStyle);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
        
        // Datos
        $row = 2;
        foreach ($users as $user) {
            $sheet->setCellValue('A' . $row, $user['id']);
            $sheet->setCellValue('B' . $row, $user['full_name']);
            $sheet->setCellValue('C' . $row, $user['email']);
            $sheet->setCellValue('D' . $row, $user['username']);
            $sheet->setCellValue('E' . $row, $user['phone'] ?? 'N/A');
            $sheet->setCellValue('F' . $row, ucfirst($user['role']));
            $sheet->setCellValue('G' . $row, ucfirst($user['membership_tier']));
            $sheet->setCellValue('H' . $row, $user['status']);
            $sheet->setCellValue('I' . $row, $user['created_date']);
            $sheet->setCellValue('J' . $row, $user['last_login'] ?? 'Nunca');
            $sheet->setCellValue('K' . $row, $user['email_verified']);
            $row++;
        }
        
        // Aplicar bordes a toda la tabla
        $lastRow = $row - 1;
        $lastCol = 'K';
        $borderStyle = [
            'borders' => [
                'allBorders' => ['borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN, 'color' => ['rgb' => 'DDDDDD']]
            ]
        ];
        $sheet->getStyle('A1:' . $lastCol . $lastRow)->applyFromArray($borderStyle);
        
        // Centrar columnas específicas
        $sheet->getStyle('A:A')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('H:H')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('K:K')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        
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
        
        $tab_titles = [
            'todos' => 'Todos los Usuarios',
            'free' => 'Usuarios Free',
            'pro' => 'Usuarios Pro',
            'premium' => 'Usuarios Premium',
            'elite' => 'Usuarios Elite',
            'sistema' => 'Usuarios del Sistema',
            'activos' => 'Usuarios Activos',
            'inactivos' => 'Usuarios Inactivos'
        ];
        
        $title = isset($tab_titles[$active_tab]) ? $tab_titles[$active_tab] : 'Usuarios';
        
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Usuarios</title>
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
                .badge-active {
                    color: #155724;
                    background-color: #d4edda;
                    padding: 2px 6px;
                    border-radius: 12px;
                    display: inline-block;
                }
                .badge-inactive {
                    color: #721c24;
                    background-color: #f8d7da;
                    padding: 2px 6px;
                    border-radius: 12px;
                    display: inline-block;
                }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Colcars</h1>
                <h2 style="margin: 5px 0; font-size: 14px;">Reporte de ' . $title . '</h2>
                <p>Fecha de generación: ' . date('d/m/Y H:i:s') . '</p>
                <p>Total de usuarios: ' . count($users) . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Completo</th>
                        <th>Email</th>
                        <th>Usuario</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>Membresía</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th>Email Verif.</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($users as $user) {
            $status_class = $user['status'] == 'Activo' ? 'badge-active' : 'badge-inactive';
            $status_span = '<span class="' . $status_class . '">' . $user['status'] . '</span>';
            
            $html .= '
                    <tr>
                        <td>' . $user['id'] . '</td>
                        <td>' . htmlspecialchars($user['full_name'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($user['username'], ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . htmlspecialchars($user['phone'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>
                        <td>' . ucfirst($user['role']) . '</td>
                        <td>' . ucfirst($user['membership_tier']) . '</td>
                        <td>' . $status_span . '</td>
                        <td>' . $user['created_date'] . '</td>
                        <td>' . $user['email_verified'] . '</td>
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