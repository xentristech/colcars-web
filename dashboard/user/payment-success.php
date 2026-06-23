<?php
/**
 * EASY CAR LUXURY - Éxito en el Pago
 * Ruta: /payment-success.php
 * MODIFICADO: Al confirmar pago exitoso, activa la cuenta del usuario automáticamente.
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../config/database.php';

$referencia = $_GET['ref'] ?? '';

// Activar la cuenta del usuario asociado a esta referencia de pago
if (!empty($referencia)) {
    $database = Database::getInstance();
    $pdo = $database->getConnection();
    
    // Buscar el pago por referencia
    $stmt = $pdo->prepare("SELECT user_id FROM payments WHERE reference = ? AND status = 'completed'");
    $stmt->execute([$referencia]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($pago && isset($pago['user_id'])) {
        // Activar la cuenta del usuario
        $update = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = ? AND activo = 0");
        $update->execute([$pago['user_id']]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Exitoso - Easy Car Luxury</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        .btn-dashboard {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h2>¡Pago Exitoso!</h2>
        <p>Tu membresía ha sido activada correctamente.</p>
        <p class="text-muted small">Referencia: <?php echo htmlspecialchars($referencia); ?></p>
        <p>Ya puedes disfrutar de todos los beneficios de tu plan.</p>
        <a href="index.php" class="btn btn-dashboard text-white">
            <i class="fas fa-tachometer-alt"></i> Ir al Dashboard
        </a>
        <div class="mt-3">
            <a href="invoices.php" class="text-decoration-none">
                <i class="fas fa-file-invoice"></i> Ver factura
            </a>
        </div>
    </div>
</body>
</html>