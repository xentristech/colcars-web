<?php
    // ============================================
    // payments.php - Gestión de Pagos con tema claro/oscuro
    // ESTRUCTURA IGUAL QUE audit.php
    // RUTA: /admin/pages/payments.php
    // CORREGIDO: Usa CDN en lugar de archivos locales (igual que audit.php)
    // CORREGIDO: Tabla sin saltos de línea, íconos uno al lado del otro
    // CORREGIDO: Tabs funcionando correctamente (con fade)
    // CORREGIDO: Pie de página reorganizado
    // ============================================
    session_start();
    require_once '../../config/database.php';
    require_once __DIR__ . '/../../includes/admin-auth.php';

    // Obtener conexión
    $database = Database::getInstance();
    $pdo      = $database->getConnection();

    $adminAuth = new AdminAuth($pdo);
    $admin     = $adminAuth->verifyAdmin();

    // Guardar información del admin en sesión para el sidebar
    $_SESSION['admin_name'] = $admin['full_name'];
    $_SESSION['admin_role'] = $admin['role'];

    // Obtener el tema del administrador
    $theme = $_COOKIE['admin_theme'] ?? 'light';

    // ==========================================================
    // Lógica PHP para responder a peticiones AJAX
    // ==========================================================
    if (isset($_GET['action'])) {
        $action = $_GET['action'];

        if ($action === 'fetch') {
            header('Content-Type: application/json');
            $page         = max(1, intval($_GET['page'] ?? 1));
            $limit        = intval($_GET['limit'] ?? 6);
            $allowed_limits = [6, 10, 20, 50];
            if (!in_array($limit, $allowed_limits)) {
                $limit = 6;
            }
            $offset       = ($page - 1) * $limit;
            $user_id      = intval($_GET['user_id'] ?? 0);
            $fecha_inicio = $_GET['fecha_inicio'] ?? '';
            $fecha_fin    = $_GET['fecha_fin'] ?? '';

            $where  = "1=1";
            $params = [];
            if ($user_id > 0) {
                $where    .= " AND p.user_id = ?";
                $params[]  = $user_id;
            }
            if (! empty($fecha_inicio)) {
                $where    .= " AND DATE(p.payment_date) >= ?";
                $params[]  = $fecha_inicio;
            }
            if (! empty($fecha_fin)) {
                $where    .= " AND DATE(p.payment_date) <= ?";
                $params[]  = $fecha_fin;
            }

            $sqlCount  = "SELECT COUNT(*) as total FROM payments p WHERE $where";
            $stmtCount = $pdo->prepare($sqlCount);
            $stmtCount->execute($params);
            $total     = $stmtCount->fetchColumn();
            $stmtCount = null;

            $sql = "SELECT p.*, u.nombre_completo as usuario_nombre, u.id as usuario_id, u.activo as usuario_activo, m.name as membership_name
                FROM payments p
                LEFT JOIN usuarios u ON p.user_id = u.id
                LEFT JOIN memberships m ON p.membership_id = m.id
                WHERE $where
                ORDER BY p.payment_date DESC, p.id DESC
                LIMIT ? OFFSET ?";
            $stmt     = $pdo->prepare($sql);
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt  = null;

            $sqlTotal  = "SELECT COALESCE(SUM(amount),0) as total FROM payments p WHERE $where AND status='completed'";
            $stmtTotal = $pdo->prepare($sqlTotal);
            $paramsSum = array_slice($params, 0, count($params) - 2);
            $stmtTotal->execute($paramsSum);
            $totalIngresos = $stmtTotal->fetchColumn();
            $stmtTotal     = null;

            $response = [
                'total' => $total,
                'desde' => $offset + 1,
                'hasta' => min($offset + $limit, $total),
                'totalIngresos' => '$ ' . number_format($totalIngresos, 0, ',', '.') . ' COP',
                'periodoLabel' => (! empty($fecha_inicio) || ! empty($fecha_fin) ? 'Filtro personalizado' : 'Todos los pagos'),
                'pagos' => []
            ];

            foreach ($pagos as $p) {
                $payment_date      = $p['payment_date'] ?? $p['created_at'];
                $vencimiento_fecha = date('Y-m-d', strtotime($payment_date . ' +3 months'));
                $hoy               = date('Y-m-d');
                if ($vencimiento_fecha < $hoy) {
                    $clase_vencimiento = 'badge-vencido';
                    $texto_vencimiento = 'Vencido';
                } elseif (strtotime($vencimiento_fecha) - strtotime($hoy) <= 7 * 86400) {
                    $clase_vencimiento = 'badge-proximo';
                    $texto_vencimiento = 'Próximo a vencer';
                } else {
                    $clase_vencimiento = '';
                    $texto_vencimiento = 'Vigente';
                }

                $mostrar_desactivar = ($p['usuario_activo'] == 1 && $p['user_id'] != 1) ? true : false;

                $response['pagos'][] = [
                    'id' => $p['id'],
                    'user_id' => $p['user_id'],
                    'usuario_id' => $p['usuario_id'],
                    'usuario_nombre' => $p['usuario_nombre'] ?? 'N/A',
                    'usuario_activo' => $p['usuario_activo'] ?? 0,
                    'mostrar_desactivar' => $mostrar_desactivar,
                    'membership_id' => $p['membership_id'],
                    'membership_name' => $p['membership_name'] ?? 'Desconocido',
                    'amount' => '$ ' . number_format($p['amount'], 0, ',', '.'),
                    'payment_method' => $p['payment_method'],
                    'reference' => $p['reference'],
                    'status' => $p['status'],
                    'transaction_id' => $p['transaction_id'],
                    'payment_date' => $payment_date,
                    'response_data' => htmlspecialchars(substr($p['response_data'] ?? '', 0, 50)),
                    'created_at' => $p['created_at'],
                    'updated_at' => $p['updated_at'],
                    'vencimiento_fecha' => $vencimiento_fecha,
                    'clase_vencimiento' => $clase_vencimiento,
                    'texto_vencimiento' => $texto_vencimiento
                ];
            }

            echo json_encode($response);
            exit;
        }

        if ($action === 'proximos') {
            header('Content-Type: text/html');
            $sql = "SELECT u.nombre_completo, u.email, p.amount, p.payment_date,
                DATE_ADD(p.payment_date, INTERVAL 3 MONTH) as venc,
                DATEDIFF(DATE_ADD(p.payment_date, INTERVAL 3 MONTH), CURDATE()) as dias
                FROM payments p
                JOIN usuarios u ON p.user_id = u.id
                WHERE p.status = 'completed'
                AND DATE_ADD(p.payment_date, INTERVAL 3 MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY dias ASC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) === 0) {
                echo '<tr><td colspan="6" class="text-center">No hay pagos próximos a vencer (próximos 7 días).</div></td>';
            } else {
                foreach ($rows as $row) {
                    echo '<tr>
                            <td>' . htmlspecialchars($row['nombre_completo']) . '</td>
                            <td>' . htmlspecialchars($row['email']) . '</td>
                            <td>$ ' . number_format($row['amount'], 0, ',', '.') . '</td>
                            <td>' . $row['payment_date'] . '</td>
                            <td>' . $row['venc'] . '</td>
                            <td>' . $row['dias'] . ' días</td>
                        </tr>';
                }
            }
            exit;
        }

        if ($action === 'bloqueadas') {
            header('Content-Type: text/html');
            $sql = "SELECT u.id as usuario_id, u.nombre_completo, u.email, 
                           COALESCE(m.name, 'Sin membresía') as membresia,
                           um.end_date as fecha_fin_membresia
                    FROM usuarios u
                    LEFT JOIN user_memberships um ON u.id = um.user_id AND um.status = 'active'
                    LEFT JOIN memberships m ON um.membership_id = m.id
                    WHERE u.activo = 0 AND u.rol_id = 6
                    ORDER BY u.created_at DESC";
            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (count($rows) === 0) {
                echo '<tr><td colspan="5" class="text-center">No hay cuentas bloqueadas. </div></td>';
            } else {
                foreach ($rows as $row) {
                    echo '</tr>
                            <td>' . htmlspecialchars($row['nombre_completo']) . '</td>
                            <td>' . htmlspecialchars($row['email']) . '</td>
                            <td>' . htmlspecialchars($row['membresia']) . '</td>
                            <td>' . ($row['fecha_fin_membresia'] ?? 'N/A') . '</td>
                            <td>
                                <span class="badge bg-danger">Bloqueada</span>
                                <button class="btn btn-sm btn-success activar-cuenta-btn" data-user-id="' . $row['usuario_id'] . '" data-user-name="' . htmlspecialchars($row['nombre_completo']) . '" title="Activar cuenta">
                                    <i class="fas fa-check-circle"></i>
                                </button>
                            </div>
                        </tr>';
                }
            }
            exit;
        }
    }

    // ============================================
    // CONFIGURACIÓN DE LA PÁGINA
    // ============================================
    $page_title    = isset($page_title) ? $page_title : 'Panel de Administración de pagos';
    $page_icon     = isset($page_icon) ? $page_icon : 'fas fa-tachometer-alt';
    $page_subtitle = isset($page_subtitle) ? $page_subtitle : 'Bienvenido, ' . htmlspecialchars($admin['full_name']);

    // ========== Estadísticas ==========
    $stmt_hoy = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE status='completed' AND DATE(payment_date) = CURDATE()");
    $stmt_hoy->execute();
    $total_hoy = $stmt_hoy->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt_hoy  = null;

    $stmt_semana = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE status='completed' AND YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)");
    $stmt_semana->execute();
    $total_semana = $stmt_semana->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt_semana  = null;

    $stmt_mes = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM payments WHERE status='completed' AND MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
    $stmt_mes->execute();
    $total_mes = $stmt_mes->fetch(PDO::FETCH_ASSOC)['total'];
    $stmt_mes  = null;

    $stmt_vencidos = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE status='completed' AND DATE_ADD(payment_date, INTERVAL 3 MONTH) < CURDATE()");
    $stmt_vencidos->execute();
    $vencidos_count = $stmt_vencidos->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt_vencidos  = null;

    $stmt_proximos = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE status='completed' AND DATE_ADD(payment_date, INTERVAL 3 MONTH) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)");
    $stmt_proximos->execute();
    $proximos_count = $stmt_proximos->fetch(PDO::FETCH_ASSOC)['count'];
    $stmt_proximos  = null;

    $success_msg = '';
    $error_msg   = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $id     = intval($_POST['id'] ?? 0);

        if ($action === 'edit' && $id > 0) {
            $amount         = floatval($_POST['amount'] ?? 0);
            $status         = $_POST['status'] ?? '';
            $payment_method = $_POST['payment_method'] ?? '';
            $reference      = trim($_POST['reference'] ?? '');
            $transaction_id = trim($_POST['transaction_id'] ?? '');

            $allowed_status  = ['pending', 'completed', 'failed', 'refunded'];
            $allowed_methods = ['pse', 'credit_card', 'debit_card', 'wompi', 'payu', 'epayco'];

            if (! in_array($status, $allowed_status)) {
                $status = 'pending';
            }

            if (! in_array($payment_method, $allowed_methods)) {
                $payment_method = 'wompi';
            }

            $stmt_user = $pdo->prepare("SELECT user_id FROM payments WHERE id = ?");
            $stmt_user->execute([$id]);
            $user_id_pago = $stmt_user->fetchColumn();
            $stmt_user = null;

            $sql  = "UPDATE payments SET amount = ?, status = ?, payment_method = ?, reference = ?, transaction_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$amount, $status, $payment_method, $reference, $transaction_id, $id]);
            if ($stmt->rowCount() > 0) {
                $success_msg = "Pago actualizado correctamente.";
                if ($status === 'completed' && $user_id_pago) {
                    $activate = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = ? AND activo = 0");
                    $activate->execute([$user_id_pago]);
                    if ($activate->rowCount() > 0) {
                        $success_msg .= " La cuenta ha sido activada automáticamente.";
                    }
                }
            } else {
                $error_msg = "Error al actualizar: No se realizaron cambios.";
            }
            $stmt = null;
        } elseif ($action === 'delete' && $id > 0) {
            $sql  = "DELETE FROM payments WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            if ($stmt->rowCount() > 0) {
                $success_msg = "Pago eliminado correctamente.";
            } else {
                $error_msg = "Error al eliminar: No se encontró el registro.";
            }
            $stmt = null;
        }

        header("Location: payments.php?msg=" . urlencode($success_msg) . "&err=" . urlencode($error_msg));
        exit;
    }

    if (isset($_GET['msg'])) {
        $success_msg = htmlspecialchars($_GET['msg']);
    }
    if (isset($_GET['err'])) {
        $error_msg = htmlspecialchars($_GET['err']);
    }
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title><?php echo htmlspecialchars($page_title); ?> - Colcars</title>
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CSS - CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        /* ============================================
           VARIABLES DE TEMA CLARO
           ============================================ */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #f0f2f5;
            --bg-secondary: #ffffff;
            --text-primary: #1a1a2e;
            --text-secondary: #666666;
            --border-color: #e0e0e0;
            --card-bg: #ffffff;
            --table-hover: #f8f9fa;
            --input-bg: #ffffff;
            --input-border: #dddddd;
            --header-bg: #ffffff;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #ffffff;
            --text-secondary: #a0a0a0;
            --border-color: #2a2a3e;
            --card-bg: #16213e;
            --table-hover: #1f2a4a;
            --input-bg: #222F58;
            --input-border: #4a4a5e;
            --header-bg: #16213e;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            overflow-x: hidden;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
            width: 100%;
            max-width: 100%;
            overflow-x: hidden;
        }

        .sidebar-column {
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .admin-main {
            flex: 1;
            width: auto;
            padding: 15px 15px;
            background: var(--bg-primary);
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow-x: hidden;
        }

        /* Ajuste para que el contenido use todo el ancho disponible sin desbordar */
        .admin-main > * {
            max-width: 100%;
        }

        /* Cuando el sidebar está contraído, el contenido se expande automáticamente */
        body.sidebar-collapsed .admin-main,
        .sidebar-column.collapsed ~ .admin-main {
            margin-left: 0;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding: 12px 20px;
            background: var(--header-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid var(--border-color);
        }

        .header-title h1 {
            font-size: 1.3rem;
            margin: 0 0 3px;
            color: var(--text-primary);
        }

        .header-title p {
            margin: 0;
            color: var(--text-secondary);
            font-size: 0.8rem;
        }

        .filters-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .filters-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--text-secondary);
            letter-spacing: 0.5px;
        }

        .form-select, .form-control {
            padding: 6px 12px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.8rem;
            min-width: 150px;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-select:focus, .form-control:focus {
            outline: none;
            border-color: #c8a86b;
        }

        .stats-mini {
            display: flex;
            gap: 20px;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 10px 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
            flex-wrap: wrap;
        }

        .stat-mini {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat-mini .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        .stat-mini .stat-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: #c8a86b;
        }

        .data-card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .card-body {
            padding: 0;
        }

        /* ============================================
           SCROLL HORIZONTAL SOLO EN LA TABLA
           ============================================ */
        .table-responsive {
            overflow-x: auto !important;
            overflow-y: visible !important;
            position: relative;
            width: 100%;
        }

        /* La tabla puede tener un ancho mayor que el contenedor para activar el scroll */
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1400px;
        }

        .admin-table th,
        .admin-table td {
            padding: 12px 8px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-primary);
        }

        .admin-table th {
            background: var(--bg-secondary);
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.75rem;
            position: sticky;
            top: 0;
        }

        .admin-table tr:hover {
            background: var(--table-hover);
        }

        /* PAGINATION CONTAINER - NUEVO ORDEN */
        .pagination-container {
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border-top: 1px solid var(--border-color);
            background: var(--card-bg);
            margin-top: 20px;
            border-radius: 12px;
        }

        .pagination-info {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .pagination {
            display: flex;
            gap: 4px;
            list-style: none;
            flex-wrap: wrap;
            margin: 0;
        }

        .page-link {
            padding: 6px 12px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            color: var(--text-primary);
            transition: all 0.3s;
            font-size: 0.75rem;
            cursor: pointer;
        }

        .page-link:hover, .page-item.active .page-link {
            background: #c8a86b;
            color: white;
            border-color: #c8a86b;
        }

        .limit-selector {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.75rem;
            color: var(--text-primary);
            flex-wrap: wrap;
        }

        .limit-selector select {
            padding: 5px 8px;
            border: 1px solid var(--input-border);
            border-radius: 6px;
            font-size: 0.75rem;
            background: var(--input-bg);
            color: var(--text-primary);
            cursor: pointer;
        }

        .stats-cards-row {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .stats-card {
            flex: 1;
            min-width: 150px;
            background: var(--card-bg);
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .stats-card h6 {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-secondary);
            margin-bottom: 8px;
        }

        .stats-card h3 {
            font-size: 1.3rem;
            margin: 0;
            font-weight: 600;
            color: #c8a86b;
            word-break: break-word;
        }

        .stats-card small {
            font-size: 0.65rem;
            color: var(--text-secondary);
        }

        .summary-card {
            background: var(--card-bg);
            border-left: 4px solid #c8a86b;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .summary-card .card-body {
            padding: 15px 20px !important;
        }

        .summary-card h6 {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-secondary);
            margin-bottom: 5px;
        }

        .summary-card h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #c8a86b;
            margin: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 0.8rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(200,168,107,0.3);
        }

        .btn-secondary {
            background: #6c757d;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 0.8rem;
        }

        [data-theme="dark"] .btn-secondary {
            background: #4a4a5e;
        }

        .btn-danger {
            background: #dc3545;
            border: none;
            padding: 6px 14px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .btn-export {
            background: #28a745;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            color: white;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.3s;
        }

        .btn-export:hover {
            background: #1e7e34;
            transform: translateY(-1px);
        }

        .btn-export.csv { background: #2c3e50; }
        .btn-export.excel { background: #1e7145; }
        .btn-export.pdf { background: #e74c3c; }

        .btn-theme {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #c8a86b, #a07e4a);
            color: white;
            border: none;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .btn-theme:hover {
            transform: scale(1.1);
            box-shadow: 0 5px 15px rgba(200,168,107,0.4);
        }

        .badge-vencido { background-color: #e74a3b; color: white; }
        .badge-proximo { background-color: #f6c23e; color: #1a1a1a; }

        /* NAV TABS CORREGIDOS */
        .nav-tabs {
            border-bottom: 1px solid var(--border-color);
            padding: 0 15px;
            padding-top: 10px;
            flex-wrap: wrap;
        }

        .nav-tabs .nav-item {
            margin-bottom: -1px;
        }

        .nav-tabs .nav-link {
            color: var(--text-secondary);
            border: none;
            padding: 8px 16px;
            font-size: 0.8rem;
            background: transparent;
            cursor: pointer;
        }

        @media (max-width: 576px) {
            .nav-tabs .nav-link {
                padding: 6px 10px;
                font-size: 0.7rem;
            }
        }

        .nav-tabs .nav-link:hover {
            color: #c8a86b;
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: #c8a86b;
            background: transparent;
            border-bottom: 2px solid #c8a86b;
        }

        .tab-content {
            padding: 15px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.show.active {
            display: block;
        }

        .tab-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .print-btn {
            background: #6c757d;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            color: white;
            font-size: 0.7rem;
            cursor: pointer;
        }

        /* CONTROL DE TAMAÑO DE COLUMNAS - CORREGIDO: SIN SALTOS DE LÍNEA */
        .col-numero { width: 50px; min-width: 50px; max-width: 50px; text-align: center; }
        .col-usuario { width: 200px; min-width: 180px; max-width: 250px; }
        .col-membresia { width: 150px; min-width: 130px; max-width: 180px; }
        .col-importe { width: 120px; min-width: 110px; max-width: 140px; text-align: right; }
        .col-metodo { width: 120px; min-width: 100px; max-width: 150px; }
        .col-referencia { width: 150px; min-width: 120px; max-width: 200px; }
        .col-estado { width: 110px; min-width: 90px; max-width: 130px; }
        .col-transaccion { width: 160px; min-width: 130px; max-width: 220px; }
        .col-fecha { width: 130px; min-width: 110px; max-width: 160px; }
        .col-respuesta { width: 140px; min-width: 100px; max-width: 180px; }
        .col-creado { width: 130px; min-width: 110px; max-width: 160px; }
        .col-actualizado { width: 130px; min-width: 110px; max-width: 160px; }
        .col-vencimiento { width: 120px; min-width: 100px; max-width: 150px; }
        .col-acciones { width: 180px; min-width: 160px; max-width: 220px; text-align: center; white-space: nowrap; }

        /* Botones en columna acciones - uno al lado del otro sin saltos */
        .col-acciones .btn {
            display: inline-flex !important;
            margin: 0 3px !important;
            white-space: nowrap !important;
        }

        .col-acciones .btn i {
            margin: 0 !important;
        }

        /* CORREGIDO: Eliminado overflow hidden y text-overflow ellipsis */
        .admin-table tr {
            min-height: 40px;
            max-height: 60px;
        }

        .admin-table td {
            white-space: nowrap !important;
            overflow: visible !important;
            text-overflow: clip !important;
        }

        /* BARRA DE DESPLAZAMIENTO HORIZONTAL PERSONALIZADA SOLO EN TABLA */
        .table-responsive::-webkit-scrollbar {
            height: 8px;
        }
        .table-responsive::-webkit-scrollbar-track {
            background: var(--border-color);
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb {
            background: #c8a86b;
            border-radius: 4px;
        }
        .table-responsive::-webkit-scrollbar-thumb:hover {
            background: #a07e4a;
        }

        /* Indicador de scroll horizontal en tabla */
        .table-responsive {
            position: relative;
        }
        
        .table-responsive::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            width: 30px;
            background: linear-gradient(to right, transparent, var(--card-bg));
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .table-responsive.scrolling::after {
            opacity: 1;
        }

        /* ============================================
           MEJORAS RESPONSIVE PARA MÓVILES
           ============================================ */
        @media (max-width: 768px) {
            .admin-main {
                max-width: 100%;
                padding: 10px;
            }
            
            .admin-table th,
            .admin-table td {
                padding: 8px 4px;
                font-size: 0.7rem;
            }
            
            .col-numero { width: 40px; min-width: 40px; }
            .col-usuario { width: 140px; min-width: 120px; }
            .col-membresia { width: 120px; min-width: 100px; }
            .col-importe { width: 100px; min-width: 90px; }
            .col-metodo { width: 100px; min-width: 80px; }
            .col-referencia { width: 110px; min-width: 90px; }
            .col-estado { width: 90px; min-width: 80px; }
            .col-transaccion { width: 120px; min-width: 100px; }
            .col-fecha { width: 110px; min-width: 90px; }
            .col-respuesta { width: 100px; min-width: 80px; }
            .col-creado { width: 110px; min-width: 90px; }
            .col-actualizado { width: 110px; min-width: 90px; }
            .col-vencimiento { width: 100px; min-width: 85px; }
            .col-acciones { width: 140px; min-width: 130px; }
            
            .btn-sm {
                padding: 4px 6px !important;
                font-size: 0.6rem !important;
            }
            
            .btn-sm i {
                font-size: 0.65rem;
            }
            
            .stats-cards-row {
                gap: 10px;
            }
            
            .stats-card {
                min-width: 100%;
                padding: 10px;
            }
            
            .stats-card h3 {
                font-size: 1rem;
            }
            
            .filter-group {
                width: 100%;
            }
            
            .filter-group select,
            .filter-group input {
                width: 100%;
                min-width: auto;
            }
            
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats-mini {
                flex-direction: column;
                gap: 8px;
            }
            
            .stat-mini {
                justify-content: space-between;
            }

            .summary-card .card-body {
                padding: 12px 15px !important;
            }

            .summary-card h3 {
                font-size: 1.2rem;
            }

            .pagination-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .pagination-info {
                justify-content: center;
            }
        }

        /* ============================================
           RESPONSIVE: Ajustes de audit.php (para el sidebar en móvil)
           ============================================ */
        @media (max-width: 992px) {
            .admin-main {
                margin-top: 30px !important;
                padding: 60px 10px 10px;
            }
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group select,
            .filter-group input,
            .filter-group button {
                width: 100%;
            }
            .header-actions .btn-export {
                margin-bottom: 8px !important;
            }
            .filter-group .btn-secondary {
                margin-top: 10px !important;
            }
        }

        @media (max-width: 480px) {
            .admin-main {
                padding: 10px 8px;
            }
            
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .header-actions > div {
                flex-wrap: wrap;
                gap: 5px;
            }
            
            .btn-export {
                padding: 4px 8px;
                font-size: 0.65rem;
            }

            .summary-card .card-body {
                padding: 10px 12px !important;
            }

            .summary-card h3 {
                font-size: 1rem;
            }
        }

        /* Modal en modo oscuro */
        [data-theme="dark"] .modal-content {
            background-color: #16213e;
            border-color: #2a2a3e;
        }
        [data-theme="dark"] .modal-header {
            border-bottom-color: #2a2a3e;
        }
        [data-theme="dark"] .modal-footer {
            border-top-color: #2a2a3e;
        }
        [data-theme="dark"] .btn-close {
            filter: invert(1);
        }

        /* Footer con el mismo ancho */
        footer {
            width: 100%;
            max-width: 100%;
        }
    </style>
</head>
<body>
<div class="admin-container">
    
        <?php include_once __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="admin-main">
        <!-- ADMIN HEADER -->
        <div class="admin-header">
            <div class="header-title">
                <h1><i class="fas fa-credit-card"></i> Gestión de Pagos</h1>
                <p><?php echo htmlspecialchars($page_subtitle); ?></p>
            </div>
            <div class="header-actions">
                <div style="display: inline-flex; gap: 5px;">
                    <button id="exportCSV" class="btn-export csv" title="Exportar a CSV"><i class="fas fa-file-csv"></i> CSV</button>
                    <button id="exportExcel" class="btn-export excel" title="Exportar a Excel"><i class="fas fa-file-excel"></i> Excel</button>
                    <button id="exportPDF" class="btn-export pdf" title="Exportar a PDF"><i class="fas fa-file-pdf"></i> PDF</button>
                    <button id="exportAll" class="btn-export" style="background-color: #17a2b8;" title="Exportar todos los registros"><i class="fas fa-download"></i> Exportar todo</button>
                </div>
            </div>
        </div>

        <!-- Mensajes de éxito/error -->
        <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $success_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error_msg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
        <?php endif; ?>

        <!-- TARJETAS DE ESTADÍSTICAS (hoy, semana, mes) -->
        <div class="stats-cards-row">
            <div class="stats-card">
                <h6><i class="fas fa-calendar-day"></i> Hoy</h6>
                <h3>$ <?php echo number_format($total_hoy, 0, ',', '.'); ?> COP</h3>
                <small>Pagos completados</small>
            </div>
            <div class="stats-card">
                <h6><i class="fas fa-calendar-week"></i> Esta Semana</h6>
                <h3>$ <?php echo number_format($total_semana, 0, ',', '.'); ?> COP</h3>
                <small>Lunes a domingo</small>
            </div>
            <div class="stats-card">
                <h6><i class="fas fa-calendar-alt"></i> Este Mes</h6>
                <h3>$ <?php echo number_format($total_mes, 0, ',', '.'); ?> COP</h3>
                <small><?php echo date('F Y'); ?></small>
            </div>
        </div>

        <!-- Resumen de ingresos (summary-card) -->
        <div class="summary-card shadow-sm">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1"><i class="fas fa-chart-line"></i> Total recaudado en el período</h6>
                        <h3 class="mb-0" id="totalIngresosSummary">$0 COP</h3>
                    </div>
                    <div>
                        <span class="badge bg-secondary" id="periodoLabelSummary">Filtro actual</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILTERS CARD -->
        <div class="filters-card">
            <div class="filters-form">
                <div class="filter-group">
                    <label><i class="fas fa-user"></i> Buscar usuario</label>
                    <input type="text" id="searchUser" class="form-control" list="userList" placeholder="Nombre o email...">
                    <datalist id="userList">
                        <?php
                        $stmt  = $pdo->query("SELECT id, nombre_completo, email FROM usuarios WHERE activo = 1 ORDER BY nombre_completo LIMIT 100");
                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($users as $u) {
                            echo "<option value=\"{$u['nombre_completo']} ({$u['email']})\" data-id=\"{$u['id']}\">";
                        }
                        ?>
                    </datalist>
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> Fecha inicio</label>
                    <input type="date" id="fechaInicio" class="form-control">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-calendar"></i> Fecha fin</label>
                    <input type="date" id="fechaFin" class="form-control">
                </div>
                <div class="filter-group">
                    <label><i class="fas fa-filter"></i> Filtro rápido</label>
                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                        <button class="btn btn-sm btn-outline-secondary filter-quick" data-filter="today" title="Filtrar por hoy">Hoy</button>
                        <button class="btn btn-sm btn-outline-secondary filter-quick" data-filter="week" title="Filtrar por esta semana">Semana</button>
                        <button class="btn btn-sm btn-outline-secondary filter-quick" data-filter="month" title="Filtrar por este mes">Mes</button>
                        <button class="btn btn-sm btn-outline-secondary filter-quick" data-filter="year" title="Filtrar por este año">Año</button>
                    </div>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <div style="display: flex; gap: 8px;">
                        <button id="btnFiltrar" class="btn btn-primary" title="Aplicar filtros"><i class="fas fa-search"></i> Filtrar</button>
                        <button id="btnReset" class="btn btn-secondary" title="Limpiar todos los filtros"><i class="fas fa-undo-alt"></i> Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DATA CARD CON TABS CORREGIDOS (con fade) -->
        <div class="data-card">
            <div class="card-body">
                <!-- Nav Tabs -->
                <ul class="nav nav-tabs" id="paymentsTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="historial-tab" data-bs-toggle="tab" data-bs-target="#historial" type="button" role="tab" aria-controls="historial" aria-selected="true">
                            <i class="fas fa-list"></i> Historial de Pagos
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="proximos-tab" data-bs-toggle="tab" data-bs-target="#proximos" type="button" role="tab" aria-controls="proximos" aria-selected="false">
                            <i class="fas fa-clock"></i> Próximos a vencer
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="bloqueadas-tab" data-bs-toggle="tab" data-bs-target="#bloqueadas" type="button" role="tab" aria-controls="bloqueadas" aria-selected="false">
                            <i class="fas fa-ban"></i> Cuentas bloqueadas
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Panels - CORREGIDOS: añadida clase fade y show active -->
                <div class="tab-content" id="paymentsTabContent">
                    <!-- TAB 1: Historial -->
                    <div class="tab-pane fade show active" id="historial" role="tabpanel" aria-labelledby="historial-tab">
                        <div class="tab-header">
                            <h3 class="h5 mb-0">Registros de pagos</h3>
                            <button class="print-btn" data-table="historial" title="Imprimir tabla"><i class="fas fa-print"></i> Imprimir tabla</button>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table" id="tablaPagos">
                                <thead>
                                    <tr>
                                        <th class="col-numero">#</th>
                                        <th class="col-usuario">Usuario</th>
                                        <th class="col-membresia">Membresía</th>
                                        <th class="col-importe">Importe</th>
                                        <th class="col-metodo">Método</th>
                                        <th class="col-referencia">Referencia</th>
                                        <th class="col-estado">Estado</th>
                                        <th class="col-transaccion">ID Transacción</th>
                                        <th class="col-fecha">Fecha Pago</th>
                                        <th class="col-respuesta">Datos Respuesta</th>
                                        <th class="col-creado">Creado</th>
                                        <th class="col-actualizado">Actualizado</th>
                                        <th class="col-vencimiento">Vencimiento</th>
                                        <th class="col-acciones">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPagos">
                                    <tr><td colspan="14" class="text-center">Cargando datos...</div></td>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- TAB 2: Próximos a vencer -->
                    <div class="tab-pane fade" id="proximos" role="tabpanel" aria-labelledby="proximos-tab">
                        <div class="tab-header">
                            <h3 class="h5 mb-0">Pagos próximos a vencer (7 días)</h3>
                            <button class="print-btn" data-table="proximos" title="Imprimir tabla"><i class="fas fa-print"></i> Imprimir tabla</button>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Monto</th>
                                        <th>Fecha Pago</th>
                                        <th>Fecha Vencimiento</th>
                                        <th>Días restantes</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyProximos"></tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- TAB 3: Cuentas bloqueadas -->
                    <div class="tab-pane fade" id="bloqueadas" role="tabpanel" aria-labelledby="bloqueadas-tab">
                        <div class="tab-header">
                            <h3 class="h5 mb-0">Cuentas bloqueadas</h3>
                            <button class="print-btn" data-table="bloqueadas" title="Imprimir tabla"><i class="fas fa-print"></i> Imprimir tabla</button>
                        </div>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Membresía</th>
                                        <th>Fecha fin membresía</th>
                                        <th>Acciones</th>
                                    <tr>
                                </thead>
                                <tbody id="tbodyBloqueadas"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <!-- PAGINATION CONTAINER -->
                <div class="pagination-container">
                    <div class="pagination-info">
                        <div class="limit-selector">
                            <span>Mostrar:</span>
                            <select id="perPageSelect" title="Registros por página">
                                <option value="6" selected>6</option>
                                <option value="10">10</option>
                                <option value="20">20</option>
                                <option value="50">50</option>
                            </select>
                            <span>registros</span>
                        </div>
                        
                        <div class="total-registros">
                            <span>Total registros: <strong id="totalRegistrosFooter">0</strong></span>
                        </div>
                        
                        <div class="pagination-range">
                            <span>Mostrando <span id="desdeRegistro">0</span> - <span id="hastaRegistro">0</span> de <span id="totalRegistros">0</span></span>
                        </div>
                    </div>
                    
                    <nav>
                        <ul class="pagination">
                            <li class="page-item" id="prevPageItem">
                                <a class="page-link" href="#" id="btnPrev" title="Página anterior">Anterior</a>
                            </li>
                            <li class="page-item" id="nextPageItem">
                                <a class="page-link" href="#" id="btnNext" title="Página siguiente">Siguiente</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Botón tema claro/oscuro -->
<button class="btn-theme" onclick="toggleTheme()" title="Cambiar tema claro/oscuro">
    <i class="fas fa-moon"></i>
</button>

<!-- Modal Editar Pago -->
<div class="modal fade" id="modalEditarPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Editar Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formEditarPago" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-body">
                    <div class="mb-2"><label>Importe</label><input type="number" step="0.01" name="amount" id="edit_amount" class="form-control" required></div>
                    <div class="mb-2"><label>Estado</label><select name="status" id="edit_status" class="form-select"><option value="pending">Pendiente</option><option value="completed">Completado</option><option value="failed">Fallido</option><option value="refunded">Reembolsado</option></select></div>
                    <div class="mb-2"><label>Método de pago</label><select name="payment_method" id="edit_method" class="form-select"><option value="pse">PSE</option><option value="credit_card">Tarjeta Crédito</option><option value="debit_card">Tarjeta Débito</option><option value="wompi">Wompi</option><option value="payu">PayU</option><option value="epayco">ePayco</option></select></div>
                    <div class="mb-2"><label>Referencia</label><input type="text" name="reference" id="edit_reference" class="form-control"></div>
                    <div class="mb-2"><label>ID Transacción</label><input type="text" name="transaction_id" id="edit_transaction" class="form-control"></div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../includes/admin-footer.php'; ?>

<!-- jQuery - CDN (necesario para Bootstrap) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Bootstrap JS Bundle - CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- SweetAlert2 JS - CDN -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', newTheme);
        document.cookie = `admin_theme=${newTheme}; path=/; max-age=31536000`;
        
        const btnIcon = document.querySelector('.btn-theme i');
        if (btnIcon) {
            if (newTheme === 'dark') {
                btnIcon.classList.remove('fa-moon');
                btnIcon.classList.add('fa-sun');
            } else {
                btnIcon.classList.remove('fa-sun');
                btnIcon.classList.add('fa-moon');
            }
        }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
        const btnIcon = document.querySelector('.btn-theme i');
        if (btnIcon) {
            if (currentTheme === 'dark') {
                btnIcon.classList.remove('fa-moon');
                btnIcon.classList.add('fa-sun');
            } else {
                btnIcon.classList.remove('fa-sun');
                btnIcon.classList.add('fa-moon');
            }
        }
        
        // ============================================
        // INICIALIZACIÓN CORREGIDA DE TABS
        // ============================================
        // Usar jQuery para inicializar los tabs (más compatible)
        if (typeof $.fn.tab !== 'undefined') {
            $('#paymentsTab button[data-bs-toggle="tab"]').on('click', function(e) {
                e.preventDefault();
                $(this).tab('show');
            });
        }
        
        // También inicializar con Bootstrap nativo
        var triggerTabList = [].slice.call(document.querySelectorAll('#paymentsTab button[data-bs-toggle="tab"]'));
        triggerTabList.forEach(function(triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl);
            triggerEl.addEventListener('click', function(event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });
        
        // Activar el tab que tiene la clase 'active' por defecto
        var activeTab = document.querySelector('#paymentsTab button.active');
        if (activeTab) {
            var activeTabTrigger = new bootstrap.Tab(activeTab);
            activeTabTrigger.show();
        }
        
        // Sincronizar los paneles cuando se cambia de tab
        var tabEls = document.querySelectorAll('#paymentsTab button[data-bs-toggle="tab"]');
        tabEls.forEach(function(tabEl) {
            tabEl.addEventListener('shown.bs.tab', function(event) {
                tabEls.forEach(function(el) {
                    el.setAttribute('aria-selected', 'false');
                });
                event.target.setAttribute('aria-selected', 'true');
            });
        });
        
        // Detectar scroll horizontal en tablas
        const tables = document.querySelectorAll('.table-responsive');
        tables.forEach(table => {
            table.addEventListener('scroll', function() {
                if (this.scrollLeft > 0) {
                    this.classList.add('scrolling');
                } else {
                    this.classList.remove('scrolling');
                }
            });
        });
    });

    function toggleUserStatus(userId, action, userName) {
        const actionText = action === 'activate' ? 'activar' : 'desactivar';
        Swal.fire({
            title: `¿${actionText === 'activar' ? 'Activar' : 'Desactivar'} cuenta de ${userName}?`,
            text: `La cuenta quedará ${actionText === 'activar' ? 'activa' : 'inactiva'}.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: action === 'activate' ? '#28a745' : '#d33',
            cancelButtonColor: '#6c757d',
            confirmButtonText: `Sí, ${actionText}`
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'update-user-status.php',
                    method: 'POST',
                    data: { user_id: userId, action: action },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            Swal.fire('Éxito', res.message, 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire('Error', res.error || 'No se pudo cambiar el estado', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Error de conexión', 'error');
                    }
                });
            }
        });
    }

    $(function() {
        <?php if ($vencidos_count > 0): ?>
            Swal.fire({
                icon: 'warning',
                title: 'Pagos vencidos',
                text: 'Hay <?= $vencidos_count ?> pagos con período vencido (más de 3 meses).',
                confirmButtonColor: '#e74a3b'
            });
        <?php endif; ?>
        <?php if ($proximos_count > 0): ?>
            Swal.fire({
                icon: 'info',
                title: 'Pagos próximos a vencer',
                text: 'Hay <?= $proximos_count ?> pagos que vencerán en los próximos 7 días.',
                confirmButtonColor: '#f6c23e'
            });
        <?php endif; ?>
    });

    let currentPage = 1;
    let currentLimit = 6;
    let filters = { user_id: '', fecha_inicio: '', fecha_fin: '' };
    let totalPages = 1;

    function loadPayments(page = 1) {
        currentPage = page;
        const params = new URLSearchParams({
            action: 'fetch',
            page: currentPage,
            limit: currentLimit,
            user_id: filters.user_id,
            fecha_inicio: filters.fecha_inicio,
            fecha_fin: filters.fecha_fin
        });
        $.ajax({
            url: 'payments.php?' + params.toString(),
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                $('#totalRegistros').text(data.total);
                $('#totalRegistrosFooter').text(data.total);
                $('#desdeRegistro').text(data.desde);
                $('#hastaRegistro').text(data.hasta);
                $('#totalIngresosSummary').text(data.totalIngresos);
                $('#periodoLabelSummary').text(data.periodoLabel);
                
                let tbodyHtml = '';
                if (data.pagos.length === 0) {
                    tbodyHtml = '<td><td colspan="14" class="text-center">No hay pagos que coincidan con los filtros aplicados.</div></td>';
                } else {
                    data.pagos.forEach((p, idx) => {
                        let rowNum = (currentPage - 1) * currentLimit + idx + 1;
                        let accionesHtml = `
                            <button class='btn btn-sm btn-primary btn-editar' data-id='${p.id}' data-amount='${p.amount.replace(/[^0-9]/g, '')}' data-status='${p.status}' data-method='${p.payment_method}' data-reference='${p.reference}' data-transaction='${p.transaction_id || ''}' title='Editar pago'><i class='fas fa-edit'></i></button>
                            <button class='btn btn-sm btn-danger btn-eliminar' data-id='${p.id}' title='Eliminar pago'><i class='fas fa-trash'></i></button>
                        `;
                        if (p.mostrar_desactivar) {
                            accionesHtml += `<button class='btn btn-sm btn-warning desactivar-cuenta-btn' data-user-id='${p.usuario_id}' data-user-name='${p.usuario_nombre}' title='Desactivar cuenta del usuario'><i class='fas fa-ban'></i></button>`;
                        }
                        tbodyHtml += `<tr>
                            <td class="col-numero">${rowNum}</td>
                            <td class="col-usuario">${p.usuario_nombre}</td>
                            <td class="col-membresia">${p.membership_name}</td>
                            <td class="col-importe">${p.amount}</td>
                            <td class="col-metodo">${p.payment_method}</td>
                            <td class="col-referencia">${p.reference}</td>
                            <td class="col-estado">${p.status}</td>
                            <td class="col-transaccion">${p.transaction_id || ''}</td>
                            <td class="col-fecha">${p.payment_date}</td>
                            <td class="col-respuesta">${p.response_data}</td>
                            <td class="col-creado">${p.created_at}</td>
                            <td class="col-actualizado">${p.updated_at}</td>
                            <td class="col-vencimiento">${p.vencimiento_fecha}</td>
                            <td class="col-acciones">${accionesHtml}</td>
                        </tr>`;
                    });
                }
                $('#tbodyPagos').html(tbodyHtml);
                
                totalPages = Math.ceil(data.total / currentLimit);
                $('#btnPrev').prop('disabled', currentPage === 1);
                $('#btnNext').prop('disabled', currentPage === totalPages || totalPages === 0);
            },
            error: function(xhr) {
                console.error('Error AJAX:', xhr.responseText);
                Swal.fire('Error', 'No se pudieron cargar los pagos.', 'error');
            }
        });
    }

    function loadProximosVencer() {
        $.get('payments.php', { action: 'proximos' }, function(data) {
            $('#tbodyProximos').html(data);
        }).fail(function() {
            $('#tbodyProximos').html('<tr><td colspan="6" class="text-center">Error al cargar datos</div></tr>');
        });
    }
    
    function loadBloqueadas() {
        $.get('payments.php', { action: 'bloqueadas' }, function(data) {
            $('#tbodyBloqueadas').html(data);
        }).fail(function() {
            $('#tbodyBloqueadas').html('<tr><td colspan="5" class="text-center">Error al cargar datos</div></td>');
        });
    }

    function exportToCSV(useAll = false) {
        let params = {
            action: 'fetch',
            limit: useAll ? 999999 : currentLimit,
            user_id: filters.user_id,
            fecha_inicio: filters.fecha_inicio,
            fecha_fin: filters.fecha_fin
        };
        if (useAll) params.page = 1;
        else params.page = currentPage;
        $.ajax({
            url: 'payments.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(data) {
                if (!data.pagos || data.pagos.length === 0) {
                    Swal.fire('Error', 'No hay datos para exportar', 'warning');
                    return;
                }
                const headers = ['Usuario', 'Membresía', 'Importe', 'Método', 'Referencia', 'Estado', 'ID Transacción', 'Fecha Pago', 'Datos Respuesta', 'Creado', 'Actualizado', 'Vencimiento'];
                const rows = data.pagos.map(p => [
                    p.usuario_nombre, p.membership_name, p.amount.replace('$ ', '').replace(/\./g, ''),
                    p.payment_method, p.reference, p.status, p.transaction_id || '',
                    p.payment_date, p.response_data, p.created_at, p.updated_at, p.vencimiento_fecha
                ]);
                const csvContent = [headers, ...rows].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');
                const blob = new Blob(["\uFEFF" + csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.href = url;
                link.setAttribute('download', useAll ? 'pagos_todos.csv' : 'pagos.csv');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            },
            error: function() {
                Swal.fire('Error', 'No se pudieron obtener los datos para exportar', 'error');
            }
        });
    }

    function exportToExcel(useAll = false) {
        let params = {
            action: 'fetch',
            limit: useAll ? 999999 : currentLimit,
            user_id: filters.user_id,
            fecha_inicio: filters.fecha_inicio,
            fecha_fin: filters.fecha_fin
        };
        if (useAll) params.page = 1;
        else params.page = currentPage;
        $.ajax({
            url: 'payments.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(data) {
                if (!data.pagos || data.pagos.length === 0) {
                    Swal.fire('Error', 'No hay datos para exportar', 'warning');
                    return;
                }
                let html = '<table border="1">';
                html += '<td><th>Usuario</th><th>Membresía</th><th>Importe</th><th>Método</th><th>Referencia</th><th>Estado</th><th>ID Transacción</th><th>Fecha Pago</th><th>Datos Respuesta</th><th>Creado</th><th>Actualizado</th><th>Vencimiento</th></tr>';
                data.pagos.forEach(p => {
                    html += `<tr>
                        <td>${p.usuario_nombre}</td>
                        <td>${p.membership_name}</td>
                        <td>${p.amount}</td>
                        <td>${p.payment_method}</td>
                        <td>${p.reference}</td>
                        <td>${p.status}</td>
                        <td>${p.transaction_id || ''}</td>
                        <td>${p.payment_date}</td>
                        <td>${p.response_data}</td>
                        <td>${p.created_at}</td>
                        <td>${p.updated_at}</td>
                        <td>${p.vencimiento_fecha}</td>
                    </tr>`;
                });
                html += '</table>';
                const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
                const link = document.createElement('a');
                const url = URL.createObjectURL(blob);
                link.href = url;
                link.setAttribute('download', useAll ? 'pagos_todos.xls' : 'pagos.xls');
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);
            },
            error: function() {
                Swal.fire('Error', 'No se pudieron obtener los datos para exportar', 'error');
            }
        });
    }

    function exportToPDF(useAll = false) {
        let params = {
            action: 'fetch',
            limit: useAll ? 999999 : currentLimit,
            user_id: filters.user_id,
            fecha_inicio: filters.fecha_inicio,
            fecha_fin: filters.fecha_fin
        };
        if (useAll) params.page = 1;
        else params.page = currentPage;
        $.ajax({
            url: 'payments.php',
            type: 'GET',
            data: params,
            dataType: 'json',
            success: function(data) {
                if (!data.pagos || data.pagos.length === 0) {
                    Swal.fire('Error', 'No hay datos para exportar', 'warning');
                    return;
                }
                let printWindow = window.open('', '_blank');
                printWindow.document.write('<html><head><title>Pagos</title>');
                printWindow.document.write('<style>');
                printWindow.document.write('table { border-collapse: collapse; width: 100%; }');
                printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
                printWindow.document.write('th { background-color: #f2f2f2; }');
                printWindow.document.write('</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write('<h2>Listado de Pagos</h2>');
                printWindow.document.write('<table>');
                printWindow.document.write('<tr><th>Usuario</th><th>Membresía</th><th>Importe</th><th>Método</th><th>Referencia</th><th>Estado</th><th>ID Transacción</th><th>Fecha Pago</th><th>Datos Respuesta</th><th>Creado</th><th>Actualizado</th><th>Vencimiento</th></tr>');
                data.pagos.forEach(p => {
                    printWindow.document.write(`<tr>
                        <td>${p.usuario_nombre}</td>
                        <td>${p.membership_name}</td>
                        <td>${p.amount}</td>
                        <td>${p.payment_method}</td>
                        <td>${p.reference}</td>
                        <td>${p.status}</td>
                        <td>${p.transaction_id || ''}</td>
                        <td>${p.payment_date}</td>
                        <td>${p.response_data}</td>
                        <td>${p.created_at}</td>
                        <td>${p.updated_at}</td>
                        <td>${p.vencimiento_fecha}</td>
                    <tr>`);
                });
                printWindow.document.write('</table></body></html>');
                printWindow.document.close();
                printWindow.print();
            },
            error: function() {
                Swal.fire('Error', 'No se pudieron obtener los datos para exportar', 'error');
            }
        });
    }

    function printTable(tableId) {
        let tableHtml = '';
        if (tableId === 'historial') {
            tableHtml = document.getElementById('tablaPagos').outerHTML;
        } else if (tableId === 'proximos') {
            const table = document.querySelector('#proximos .admin-table');
            if (table) tableHtml = table.outerHTML;
        } else if (tableId === 'bloqueadas') {
            const table = document.querySelector('#bloqueadas .admin-table');
            if (table) tableHtml = table.outerHTML;
        }
        if (!tableHtml) return;
        let printWindow = window.open('', '_blank');
        printWindow.document.write('<html><head><title>Imprimir</title>');
        printWindow.document.write('<style>');
        printWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
        printWindow.document.write('table { border-collapse: collapse; width: 100%; }');
        printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
        printWindow.document.write('th { background-color: #f2f2f2; }');
        printWindow.document.write('</style>');
        printWindow.document.write('</head><body>');
        printWindow.document.write('<h2>Reporte de Pagos</h2>');
        printWindow.document.write(tableHtml);
        printWindow.document.write('</body></html>');
        printWindow.document.close();
        printWindow.print();
    }

    $(function() {
        loadPayments(1);
        loadProximosVencer();
        loadBloqueadas();

        $('#perPageSelect').on('change', function() {
            currentLimit = parseInt($(this).val());
            loadPayments(1);
        });

        $('#exportCSV').click(() => exportToCSV(false));
        $('#exportExcel').click(() => exportToExcel(false));
        $('#exportPDF').click(() => exportToPDF(false));
        $('#exportAll').click(() => {
            Swal.fire({
                title: 'Exportar todos los registros',
                text: 'Se exportarán todos los pagos según los filtros actuales.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, exportar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    exportToCSV(true);
                }
            });
        });

        $('.print-btn').click(function() {
            let table = $(this).data('table');
            printTable(table);
        });

        $('#searchUser').on('change', function() {
            const val = $(this).val();
            const option = $(`#userList option[value="${val}"]`);
            const userId = option.data('id') || '';
            filters.user_id = userId;
            loadPayments(1);
        });

        $('.filter-quick').click(function() {
            const filter = $(this).data('filter');
            let today = new Date();
            let start = new Date(), end = new Date();
            switch(filter) {
                case 'today':
                    start = today; end = today;
                    break;
                case 'week':
                    start = new Date(today.getFullYear(), today.getMonth(), today.getDate() - today.getDay());
                    end = new Date(today.getFullYear(), today.getMonth(), today.getDate() + (6 - today.getDay()));
                    break;
                case 'month':
                    start = new Date(today.getFullYear(), today.getMonth(), 1);
                    end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                    break;
                case 'year':
                    start = new Date(today.getFullYear(), 0, 1);
                    end = new Date(today.getFullYear(), 11, 31);
                    break;
            }
            $('#fechaInicio').val(start.toISOString().split('T')[0]);
            $('#fechaFin').val(end.toISOString().split('T')[0]);
            filters.fecha_inicio = $('#fechaInicio').val();
            filters.fecha_fin = $('#fechaFin').val();
            loadPayments(1);
        });

        $('#btnFiltrar').click(function() {
            filters.fecha_inicio = $('#fechaInicio').val();
            filters.fecha_fin = $('#fechaFin').val();
            loadPayments(1);
        });

        $('#btnReset').click(function() {
            $('#searchUser').val('');
            $('#fechaInicio').val('');
            $('#fechaFin').val('');
            filters = { user_id: '', fecha_inicio: '', fecha_fin: '' };
            loadPayments(1);
            $('#perPageSelect').val('6');
            currentLimit = 6;
        });

        $('#btnPrev').click(() => loadPayments(currentPage - 1));
        $('#btnNext').click(() => loadPayments(currentPage + 1));

        $(document).on('click', '.btn-editar', function() {
            $('#edit_id').val($(this).data('id'));
            $('#edit_amount').val($(this).data('amount'));
            $('#edit_status').val($(this).data('status'));
            $('#edit_method').val($(this).data('method'));
            $('#edit_reference').val($(this).data('reference'));
            $('#edit_transaction').val($(this).data('transaction'));
            $('#modalEditarPago').modal('show');
        });

        $(document).on('click', '.btn-eliminar', function() {
            const id = $(this).data('id');
            Swal.fire({
                title: '¿Eliminar pago?',
                text: "Esta acción no se puede deshacer",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('<form>', {
                        method: 'POST',
                        html: `<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="${id}">`
                    }).appendTo('body').submit();
                }
            });
        });

        $(document).on('click', '.desactivar-cuenta-btn', function() {
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            toggleUserStatus(userId, 'deactivate', userName);
        });

        $(document).on('click', '.activar-cuenta-btn', function() {
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            toggleUserStatus(userId, 'activate', userName);
        });
    });
</script>
</body>
</html>