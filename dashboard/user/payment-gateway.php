<?php
/**
 * EASY CAR LUXURY - Pasarela de Pagos
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

requireAuth();

// Verificar que hay una compra en proceso
if (!isset($_SESSION['purchase_plan'])) {
    header('Location: membership.php');
    exit;
}

$db = Database::getInstance();
$user_id = $_SESSION['user_id'];
$user = $db->getOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);

$plan = $_SESSION['purchase_plan'];
$auto_renovable = $_SESSION['purchase_auto_renovable'] ?? 0;
$monto = $_SESSION['purchase_monto'];

// Precios de planes
$precios = [
    'pro' => 49900,
    'premium' => 89900,
    'elite' => 168000
];

$iva = $monto * (IVA_PERCENTAGE / 100);
$total = $monto + $iva;

// Generar referencia única
$referencia = 'MEM-' . time() . '-' . $user_id;

// Guardar en la base de datos
$pago_id = $db->insert('pagos', [
    'usuario_id' => $user_id,
    'referencia_pago' => $referencia,
    'monto' => $total,
    'estado' => 'pendiente',
    'tipo_pasarela' => null
]);

$error = '';

// Procesar pago (simulación - aquí iría integración real con Wompi/PayU)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $metodo = $_POST['metodo'] ?? '';
    
    if ($metodo === 'pse') {
        // Simulación de pago exitoso
        // En producción: Integrar API de Wompi o PayU
        
        // Actualizar pago
        $db->update('pagos', [
            'estado' => 'aprobado',
            'fecha_pago' => date('Y-m-d H:i:s'),
            'tipo_pasarela' => 'pse',
            'transaccion_id' => 'TXN-' . uniqid()
        ], 'id = ?', [$pago_id]);
        
        // Actualizar membresía del usuario
        $nueva_fecha = date('Y-m-d', strtotime('+30 days'));
        $limite = $plan === 'pro' ? 999999 : ($plan === 'premium' ? 999999 : 999999);
        
        $db->update('usuarios', [
            'tipo_cuenta' => $plan,
            'fecha_expiracion' => $nueva_fecha,
            'limite_publicaciones_int' => $limite
        ], 'id = ?', [$user_id]);
        
        // Registrar membresía contratada
        $db->insert('membresias_contratadas', [
            'usuario_id' => $user_id,
            'tipo_membresia' => $plan,
            'fecha_inicio' => date('Y-m-d'),
            'fecha_fin' => $nueva_fecha,
            'auto_renovable' => $auto_renovable,
            'pago_id' => $pago_id
        ]);
        
        // Generar factura
        $numero_factura = 'FAC-' . date('Ymd') . '-' . $user_id . '-' . time();
        $factura_id = $db->insert('facturas', [
            'usuario_id' => $user_id,
            'numero_factura' => $numero_factura,
            'tipo_documento' => 'factura_venta',
            'subtotal' => $monto,
            'iva' => $iva,
            'total' => $total,
            'estado_dian' => 'pendiente'
        ]);
        
        // Actualizar factura en pago
        $db->update('pagos', ['factura_id' => $factura_id], 'id = ?', [$pago_id]);
        
        logAudit($user_id, 'UPGRADE', 'usuarios', $user_id, ['tipo_cuenta' => $user['tipo_cuenta']], ['tipo_cuenta' => $plan]);
        
        // Limpiar sesión
        unset($_SESSION['purchase_plan']);
        unset($_SESSION['purchase_auto_renovable']);
        unset($_SESSION['purchase_monto']);
        
        // Redirigir a éxito
        header('Location: payment-success.php?ref=' . $referencia);
        exit;
        
    } elseif ($metodo === 'tarjeta') {
        // Similar al PSE
        // En producción: Integrar con Wompi Checkout
    } else {
        $error = 'Selecciona un método de pago';
    }
}

$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="es" data-theme="<?php echo $_COOKIE['user_theme'] ?? 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar Membresía - Easy Car Luxury</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #f8f9fa;
            --bg-secondary: #ffffff;
            --text-primary: #212529;
        }
        [data-theme="dark"] {
            --bg-primary: #1a1a2e;
            --bg-secondary: #16213e;
            --text-primary: #ffffff;
        }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .payment-card {
            background: var(--bg-secondary);
            border-radius: 20px;
            max-width: 500px;
            margin: 0 auto;
            overflow: hidden;
        }
        .payment-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .payment-body {
            padding: 30px;
        }
        .method-option {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .method-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }
        .method-option:hover {
            transform: translateX(5px);
        }
        .btn-pay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="payment-card">
        <div class="payment-header">
            <i class="fas fa-credit-card fa-3x mb-3"></i>
            <h3>Pagar Membresía</h3>
            <p>Completa tu compra de forma segura</p>
        </div>
        <div class="payment-body">
            <div class="mb-4 text-center">
                <h4>Resumen del pedido</h4>
                <div class="alert alert-info">
                    <strong>Plan: <?php echo strtoupper($plan); ?></strong><br>
                    Subtotal: <?php echo formatMoney($monto); ?><br>
                    IVA (<?php echo IVA_PERCENTAGE; ?>%): <?php echo formatMoney($iva); ?><br>
                    <hr>
                    <strong>Total: <?php echo formatMoney($total); ?></strong>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="paymentForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="metodo" id="selectedMethod" value="">
                
                <h5>Selecciona método de pago</h5>
                
                <div class="method-option" onclick="selectMethod('pse')" id="method-pse">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-university fa-2x me-3"></i>
                        <div>
                            <strong>PSE - Pagos Seguros en Línea</strong><br>
                            <small>Bancolombia, Davivienda, BBVA, etc.</small>
                        </div>
                        <div class="ms-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="method_radio" value="pse">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="method-option" onclick="selectMethod('tarjeta')" id="method-tarjeta">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-credit-card fa-2x me-3"></i>
                        <div>
                            <strong>Tarjeta de Crédito/Débito</strong><br>
                            <small>Visa, Mastercard, American Express</small>
                        </div>
                        <div class="ms-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="method_radio" value="tarjeta">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="method-option" onclick="selectMethod('nequi')" id="method-nequi">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-mobile-alt fa-2x me-3"></i>
                        <div>
                            <strong>Nequi / Daviplata</strong><br>
                            <small>Paga desde tu celular</small>
                        </div>
                        <div class="ms-auto">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="method_radio" value="nequi">
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-pay w-100 mt-4">
                    <i class="fas fa-lock"></i> Pagar <?php echo formatMoney($total); ?>
                </button>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-shield-alt"></i> Pago 100% seguro<br>
                    Tus datos están protegidos
                </small>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        function selectMethod(method) {
            document.querySelectorAll('.method-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            document.getElementById(`method-${method}`).classList.add('selected');
            document.getElementById('selectedMethod').value = method;
            document.querySelectorAll('input[name="method_radio"]').forEach(radio => {
                radio.checked = false;
            });
            document.querySelector(`input[value="${method}"]`).checked = true;
        }
        
        // Aceptar los términos al pagar
        $('#paymentForm').on('submit', function(e) {
            const method = document.getElementById('selectedMethod').value;
            if (!method) {
                e.preventDefault();
                alert('Selecciona un método de pago');
            }
        });
    </script>
</body>
</html>