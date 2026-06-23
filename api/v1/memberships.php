<?php
/**
 * EASY CAR LUXURY - API de Membresías
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Autenticación
$headers = getallheaders();
$auth_header = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $auth_header);

if (empty($token)) {
    jsonError('Token no proporcionado', 401);
}

try {
    $decoded = JWT::decode($token, new Key(JWT_SECRET, 'HS256'));
    $user_id = $decoded->user_id;
} catch (Exception $e) {
    jsonError('Token inválido', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$db = Database::getInstance();
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        // Obtener membresía actual del usuario
        $user = $db->getOne("SELECT tipo_cuenta, fecha_expiracion, limite_publicaciones_int FROM usuarios WHERE id = ?", [$user_id]);
        
        $membresia = $db->getOne("
            SELECT * FROM membresias_contratadas 
            WHERE usuario_id = ? AND fecha_fin >= CURDATE() 
            ORDER BY fecha_fin DESC LIMIT 1
        ", [$user_id]);
        
        jsonResponse([
            'current' => $user,
            'history' => $membresia
        ]);
        break;
        
    case 'POST':
        // Comprar membresía
        $plan = $input['plan'] ?? '';
        $auto_renovable = $input['auto_renovable'] ?? 0;
        
        $planes_validos = ['pro', 'premium', 'elite'];
        if (!in_array($plan, $planes_validos)) {
            jsonError('Plan no válido', 400);
        }
        
        $precios = [
            'pro' => 49900,
            'premium' => 89900,
            'elite' => 168000
        ];
        
        $monto = $precios[$plan];
        $iva = $monto * (IVA_PERCENTAGE / 100);
        $total = $monto + $iva;
        $referencia = 'MEM-' . time() . '-' . $user_id;
        
        // Crear pago pendiente
        $pago_id = $db->insert('pagos', [
            'usuario_id' => $user_id,
            'referencia_pago' => $referencia,
            'monto' => $total,
            'estado' => 'pendiente',
            'tipo_pasarela' => null
        ]);
        
        jsonResponse([
            'pago_id' => $pago_id,
            'referencia' => $referencia,
            'monto' => $total,
            'plan' => $plan
        ], 'Iniciar pago');
        break;
        
    case 'PUT':
        // Actualizar auto renovación
        $auto_renovable = $input['auto_renovable'] ?? 0;
        $membresia_id = $input['membresia_id'] ?? null;
        
        if ($membresia_id) {
            $db->update('membresias_contratadas', ['auto_renovable' => $auto_renovable], 'id = ? AND usuario_id = ?', [$membresia_id, $user_id]);
        }
        
        jsonResponse(null, 'Configuración actualizada');
        break;
        
    default:
        jsonError('Método no permitido', 405);
        break;
}