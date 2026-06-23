<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/admin-auth.php';
require_once '../../includes/backup.php';

$auth = new Authentication($pdo);
$adminAuth = new AdminAuth($pdo);
$admin = $adminAuth->verifyAdmin();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        switch ($action) {
            case 'get_ad':
                $adId = $_GET['id'] ?? null;
                if (!$adId) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Ad ID required']);
                    exit;
                }
                
                $query = "SELECT * FROM advertisements WHERE id = :id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([':id' => $adId]);
                $ad = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ad) {
                    echo json_encode(['success' => true, 'data' => $ad]);
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Advertisement not found']);
                }
                break;
                
            case 'restore_backup':
                // Solo superadmin puede restaurar (rol_id = 1)
                if ($admin['rol_id'] != 1) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Only superadmin can restore backups']);
                    exit;
                }
                
                $filename = $_GET['file'] ?? '';
                if (empty($filename)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Filename required']);
                    exit;
                }
                
                $backup = new DatabaseBackup($pdo);
                $result = $backup->restoreBackup($filename);
                
                if ($result['success']) {
                    // Registrar en auditoría si existe la función
                    if (function_exists('logAdminAction')) {
                        logAdminAction($admin['id'], 'restore_backup', 'system', null, json_encode(['file' => $filename]));
                    }
                    echo json_encode(['success' => true, 'message' => 'Backup restored successfully']);
                } else {
                    echo json_encode(['error' => $result['error']]);
                }
                break;
                
            case 'delete_backup':
                // Solo superadmin puede eliminar backups (rol_id = 1)
                if ($admin['rol_id'] != 1) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Solo superadministradores pueden eliminar backups']);
                    exit;
                }
                
                $filename = $_GET['file'] ?? '';
                if (empty($filename)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Nombre de archivo requerido']);
                    exit;
                }
                
                // Validar que el archivo es un backup (por seguridad)
                if (!preg_match('/^backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $filename)) {
                    echo json_encode(['error' => 'Nombre de archivo inválido']);
                    exit;
                }
                
                $backup = new DatabaseBackup($pdo);
                $result = $backup->deleteBackup($filename);
                
                if ($result['success']) {
                    echo json_encode(['success' => true, 'message' => $result['message']]);
                } else {
                    echo json_encode(['error' => $result['error']]);
                }
                break;
                
            case 'export_report':
                $type = $_GET['type'] ?? 'users';
                $period = $_GET['period'] ?? 'month';
                $year = $_GET['year'] ?? date('Y');
                $month = $_GET['month'] ?? date('m');
                
                switch ($type) {
                    case 'users':
                        // CORREGIDO: usando columnas reales de la tabla usuarios
                        $query = "SELECT id, nombre_completo as full_name, email, telefono as phone, rol_id as role, tipo_cuenta as membership_tier, activo as status, created_at, ultimo_acceso as last_login 
                                  FROM usuarios WHERE activo = 1";
                        $filename = "users_export_" . date('Y-m-d') . ".csv";
                        break;
                    case 'payments':
                        // CORREGIDO: usando columnas reales
                        $query = "SELECT p.id, u.nombre_completo as full_name, p.amount, p.payment_method, p.status, p.payment_date, m.name as membership
                                  FROM payments p
                                  JOIN usuarios u ON p.user_id = u.id
                                  LEFT JOIN memberships m ON p.membership_id = m.id
                                  WHERE p.status = 'completed'";
                        $filename = "payments_export_" . date('Y-m-d') . ".csv";
                        break;
                    case 'publications':
                        // CORREGIDO: usando columnas reales de publicaciones
                        $query = "SELECT p.id, u.nombre_completo as full_name, p.titulo as title, p.precio as price, c.nombre as category, p.status, p.visitas as views_count, p.created_at
                                  FROM publicaciones p
                                  JOIN usuarios u ON p.usuario_id = u.id
                                  LEFT JOIN categorias c ON p.categoria_id = c.id
                                  WHERE p.status != 'deleted'";
                        $filename = "publications_export_" . date('Y-m-d') . ".csv";
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid export type']);
                        exit;
                }
                
                $stmt = $pdo->query($query);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                
                $output = fopen('php://output', 'w');
                if (!empty($data)) {
                    fputcsv($output, array_keys($data[0]));
                    foreach ($data as $row) {
                        fputcsv($output, $row);
                    }
                }
                fclose($output);
                exit;
                
            case 'pdf_report':
                // Generate PDF report (requires mpdf)
                require_once '../../vendor/autoload.php';
                
                $mpdf = new \Mpdf\Mpdf();
                $html = '<h1>Reporte Easy Car Luxury</h1>';
                $html .= '<p>Fecha: ' . date('d/m/Y H:i:s') . '</p>';
                $html .= '<h2>Estadísticas</h2>';
                
                // Add statistics - CORREGIDO: usando columnas reales
                $statsQuery = "SELECT 
                                (SELECT COUNT(*) FROM usuarios WHERE activo = 1) as total_users,
                                (SELECT COUNT(*) FROM publicaciones WHERE status = 'active') as active_publications,
                                (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND YEAR(payment_date) = YEAR(CURDATE())) as year_revenue";
                $stats = $pdo->query($statsQuery)->fetch(PDO::FETCH_ASSOC);
                
                $html .= '<table border="1" cellpadding="5">';
                $html .= '<tr><th>Métrica</th><th>Valor</th></tr>';
                $html .= '<tr><td>Usuarios totales</td><td>' . number_format($stats['total_users']) . '</td></tr>';
                $html .= '<tr><td>Publicaciones activas</td><td>' . number_format($stats['active_publications']) . '</td></tr>';
                $html .= '<tr><td>Ingresos anuales</td><td>$ ' . number_format($stats['year_revenue'], 0, ',', '.') . '</td></tr>';
                $html .= '</table>';
                
                $mpdf->WriteHTML($html);
                $mpdf->Output('reporte_' . date('Y-m-d') . '.pdf', 'D');
                break;
                
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid GET action']);
                break;
        }
        break;
        
    case 'POST':
        if (isset($_GET['action']) && $_GET['action'] === 'update_ad') {
            // Update advertisement
            $input = json_decode(file_get_contents('php://input'), true);
            $query = "UPDATE advertisements SET 
                      title = :title, description = :description, link_url = :link_url,
                      position = :position, start_date = :start_date, end_date = :end_date,
                      target_role = :target_role, status = :status
                      WHERE id = :id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':title' => $input['title'],
                ':description' => $input['description'],
                ':link_url' => $input['link_url'],
                ':position' => $input['position'],
                ':start_date' => $input['start_date'],
                ':end_date' => $input['end_date'],
                ':target_role' => $input['target_role'],
                ':status' => $input['status'],
                ':id' => $input['id']
            ]);
            
            if (function_exists('logAdminAction')) {
                logAdminAction($admin['id'], 'update_advertisement', 'ad', $input['id']);
            }
            echo json_encode(['success' => true]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}
?>