<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once '../../config/database.php';
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../includes/dian.php';

$auth = new Authentication($pdo);
$user = $auth->verifyToken();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$dian = new DianElectronicInvoicing($pdo, DIAN_ENVIRONMENT);

switch ($method) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['action'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Action is required']);
            exit;
        }
        
        switch ($data['action']) {
            case 'send_invoice':
                // Verify user has permission to send invoices
                if ($user['membership_tier'] === 'free') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Free tier cannot generate invoices. Upgrade to Pro or higher.']);
                    exit;
                }
                
                // Validate invoice data
                $required = ['invoice_id', 'customer_nit', 'customer_name', 'items'];
                foreach ($required as $field) {
                    if (!isset($data[$field])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Missing field: $field"]);
                        exit;
                    }
                }
                
                // Get invoice details from database
                $query = "SELECT i.*, p.payment_date 
                          FROM invoices i 
                          JOIN payments p ON i.payment_id = p.id 
                          WHERE i.id = :invoice_id AND i.user_id = :user_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':invoice_id' => $data['invoice_id'],
                    ':user_id' => $user['id']
                ]);
                $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$invoice) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Invoice not found']);
                    exit;
                }
                
                // Prepare data for DIAN
                $invoiceData = [
                    'invoice_id' => $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'payment_terms' => 'CONTADO',
                    'customer_nit' => $data['customer_nit'],
                    'customer_name' => $data['customer_name'],
                    'customer_tax_scheme' => $data['customer_tax_scheme'] ?? '01',
                    'items' => $data['items']
                ];
                
                $result = $dian->sendInvoice($invoiceData, $user['id']);
                
                if ($result['success']) {
                    // Update invoice status
                    $query = "UPDATE invoices SET dian_status = 'SENT', cufe = :cufe WHERE id = :invoice_id";
                    $stmt = $pdo->prepare($query);
                    $stmt->execute([
                        ':cufe' => $result['cufe'],
                        ':invoice_id' => $invoice['id']
                    ]);
                    
                    echo json_encode($result);
                } else {
                    http_response_code(500);
                    echo json_encode($result);
                }
                break;
                
            case 'check_status':
                if (!isset($data['cufe'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'CUFE is required']);
                    exit;
                }
                
                $status = $dian->checkInvoiceStatus($data['cufe']);
                echo json_encode($status);
                break;
                
                case 'send_credit_note':
                if ($user['membership_tier'] === 'free') {
                    http_response_code(403);
                    echo json_encode(['error' => 'Free tier cannot generate credit notes']);
                    exit;
                }
                
                $required = ['original_invoice_id', 'original_cufe', 'reason', 'amount'];
                foreach ($required as $field) {
                    if (!isset($data[$field])) {
                        http_response_code(400);
                        echo json_encode(['error' => "Missing field: $field"]);
                        exit;
                    }
                }
                
                // Verify original invoice belongs to user
                $query = "SELECT * FROM invoices WHERE id = :invoice_id AND user_id = :user_id";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    ':invoice_id' => $data['original_invoice_id'],
                    ':user_id' => $user['id']
                ]);
                $originalInvoice = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$originalInvoice) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Original invoice not found']);
                    exit;
                }
                
                $creditNoteData = [
                    'original_invoice_number' => $originalInvoice['invoice_number'],
                    'original_cufe' => $data['original_cufe'],
                    'credit_reason' => $data['reason'],
                    'credit_amount' => $data['amount']
                ];
                
                $result = $dian->sendCreditNote($creditNoteData, $user['id']);
                echo json_encode($result);
                break;
        }
        break;
        
    case 'GET':
        if (isset($_GET['cufe'])) {
            // Get specific invoice status
            $query = "SELECT * FROM dian_transactions WHERE cufe = :cufe AND user_id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':cufe' => $_GET['cufe'],
                ':user_id' => $user['id']
            ]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($transaction) {
                echo json_encode($transaction);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Transaction not found']);
            }
        } elseif (isset($_GET['invoice_id'])) {
            // Get all transactions for an invoice
            $query = "SELECT * FROM dian_transactions WHERE invoice_id = :invoice_id AND user_id = :user_id";
            $stmt = $pdo->prepare($query);
            $stmt->execute([
                ':invoice_id' => $_GET['invoice_id'],
                ':user_id' => $user['id']
            ]);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($transactions);
        } else {
            // Get all user transactions with pagination
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 20;
            $offset = ($page - 1) * $limit;
            
            $query = "SELECT * FROM dian_transactions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($query);
            $stmt->bindParam(':user_id', $user['id']);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countQuery = "SELECT COUNT(*) as total FROM dian_transactions WHERE user_id = :user_id";
            $countStmt = $pdo->prepare($countQuery);
            $countStmt->execute([':user_id' => $user['id']]);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            echo json_encode([
                'data' => $transactions,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $limit,
                    'total' => $total,
                    'last_page' => ceil($total / $limit)
                ]
            ]);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}