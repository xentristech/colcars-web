<?php
/**
 * EASY CAR LUXURY - Página de Error
 * Ubicación: public/error.php
 * Maneja errores 404, 403, 500 y otros
 */

// Obtener el código de error
$errorCode = isset($_GET['code']) ? (int)$_GET['code'] : 404;

// Establecer el código de respuesta HTTP
http_response_code($errorCode);

// Definir mensajes según el error
$errorMessages = [
    400 => [
        'titulo' => 'Solicitud Incorrecta',
        'descripcion' => 'La solicitud no pudo ser procesada debido a un error en los datos enviados.',
        'icono' => 'fa-exclamation-triangle'
    ],
    401 => [
        'titulo' => 'No Autorizado',
        'descripcion' => 'Debes iniciar sesión para acceder a esta página.',
        'icono' => 'fa-lock'
    ],
    403 => [
        'titulo' => 'Acceso Denegado',
        'descripcion' => 'No tienes permiso para acceder a esta página o recurso.',
        'icono' => 'fa-ban'
    ],
    404 => [
        'titulo' => 'Página No Encontrada',
        'descripcion' => 'La página que estás buscando no existe o ha sido movida.',
        'icono' => 'fa-search'
    ],
    500 => [
        'titulo' => 'Error Interno del Servidor',
        'descripcion' => 'Ha ocurrido un error en el servidor. Por favor, intenta nuevamente más tarde.',
        'icono' => 'fa-cogs'
    ],
    503 => [
        'titulo' => 'Servicio No Disponible',
        'descripcion' => 'El servicio está temporalmente no disponible. Por favor, intenta más tarde.',
        'icono' => 'fa-clock'
    ]
];

// Si el código de error no está definido, usar 404 por defecto
if (!isset($errorMessages[$errorCode])) {
    $errorCode = 404;
}

$error = $errorMessages[$errorCode];

// Tema
$tema = 'light';
if (isset($_COOKIE['theme'])) {
    $tema = $_COOKIE['theme'];
}

// Determinar si mostrar diagnóstico (solo en desarrollo)
$showDiagnostic = false;
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    $showDiagnostic = true;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo $errorCode; ?> - Easy Car Luxury</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">
    
    <!-- Bootstrap CSS - CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Font Awesome CSS - CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a5276, #2980b9);
            position: relative;
            padding: 20px;
        }
        
        body.dark-theme {
            background: linear-gradient(135deg, #08011e, #1f0c5b, #03045C);
        }
        
        /* Logo flotante */
        .floating-logo {
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1000;
        }
        
        .floating-logo img {
            height: 150px;
            width: auto;
            transition: transform 0.3s ease;
        }
        
        .floating-logo img:hover {
            transform: scale(1.05);
        }
        
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 550px;
            width: 100%;
            padding: 40px;
            text-align: center;
            transition: background 0.3s ease, color 0.3s ease;
        }
        
        body.dark-theme .error-card {
            background: #050128;
            color: white;
        }
        
        .error-icon {
            font-size: 80px;
            color: #2980b9;
            margin-bottom: 20px;
            display: block;
        }
        
        body.dark-theme .error-icon {
            color: #5dade2;
        }
        
        .error-code {
            font-size: 72px;
            font-weight: 800;
            background: linear-gradient(135deg, #1a5276, #2980b9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1;
            margin-bottom: 10px;
        }
        
        body.dark-theme .error-code {
            background: linear-gradient(135deg, #5dade2, #85c1e9);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .error-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a5276;
            margin-bottom: 15px;
        }
        
        body.dark-theme .error-title {
            color: #5dade2;
        }
        
        .error-description {
            font-size: 16px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        body.dark-theme .error-description {
            color: #aaa;
        }
        
        .btn-home {
            background: linear-gradient(135deg, #1a5276, #2980b9);
            border: none;
            border-radius: 50px;
            padding: 12px 40px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-home:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(41, 128, 185, 0.4);
            color: white;
            background: linear-gradient(135deg, #0e3a5c, #1a5276);
        }
        
        body.dark-theme .btn-home {
            background: linear-gradient(135deg, #010132, #2980b9);
        }
        
        body.dark-theme .btn-home:hover {
            box-shadow: 0 10px 30px rgba(41, 128, 185, 0.6);
        }
        
        .btn-secondary-action {
            background: transparent;
            border: 2px solid #2980b9;
            border-radius: 50px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 16px;
            color: #2980b9;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-left: 10px;
        }
        
        .btn-secondary-action:hover {
            background: #2980b9;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(41, 128, 185, 0.3);
        }
        
        body.dark-theme .btn-secondary-action {
            border-color: #5dade2;
            color: #5dade2;
        }
        
        body.dark-theme .btn-secondary-action:hover {
            background: #5dade2;
            color: #050128;
        }
        
        .error-divider {
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #1a5276, #2980b9);
            border-radius: 2px;
            margin: 20px auto;
        }
        
        body.dark-theme .error-divider {
            background: linear-gradient(135deg, #5dade2, #85c1e9);
        }
        
        .diagnostic-box {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: left;
            font-size: 13px;
            color: #333;
            border: 1px solid #e0e0e0;
            max-height: 200px;
            overflow-y: auto;
        }
        
        body.dark-theme .diagnostic-box {
            background: #1a1a2e;
            color: #ccc;
            border-color: #2a2a3e;
        }
        
        .diagnostic-box code {
            color: #2980b9;
            font-family: 'Courier New', monospace;
        }
        
        body.dark-theme .diagnostic-box code {
            color: #5dade2;
        }
        
        .theme-toggle {
            position: fixed;
            top: 15px;
            right: 15px;
            background: rgba(0,0,0,0.5);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            border: none;
            font-size: 1.1rem;
            z-index: 1000;
        }
        
        .theme-toggle:hover {
            background: rgba(0,0,0,0.7);
            transform: scale(1.05);
        }
        
        body.dark-theme .theme-toggle {
            background: rgba(255,255,255,0.2);
        }
        
        body.dark-theme .theme-toggle:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .btn-group-error {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
        }
        
        @media (max-width: 480px) {
            .floating-logo img {
                height: 45px;
            }
            
            .error-card {
                padding: 30px 20px;
            }
            
            .error-code {
                font-size: 56px;
            }
            
            .error-title {
                font-size: 22px;
            }
            
            .error-icon {
                font-size: 60px;
            }
            
            .btn-home {
                padding: 10px 25px;
                font-size: 14px;
            }
            
            .btn-secondary-action {
                padding: 10px 20px;
                font-size: 14px;
                margin-left: 0;
            }
            
            .btn-group-error {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">
    
    <!-- Logo flotante -->
    <div class="floating-logo">
        <img src="/assets/imagenes/logos/colcars_b.png" alt="Easy Car Luxury">
    </div>
    
    <button class="theme-toggle" id="themeToggle" title="Cambiar tema">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <div class="error-card">
        <!-- Icono de error -->
        <i class="fas <?php echo $error['icono']; ?> error-icon"></i>
        
        <!-- Código de error -->
        <div class="error-code"><?php echo $errorCode; ?></div>
        
        <!-- Título del error -->
        <h1 class="error-title"><?php echo $error['titulo']; ?></h1>
        
        <!-- Divisor -->
        <div class="error-divider"></div>
        
        <!-- Descripción del error -->
        <p class="error-description">
            <?php echo $error['descripcion']; ?>
        </p>
        
        <!-- Botones de acción -->
        <div class="btn-group-error">
            <a href="/" class="btn-home">
                <i class="fas fa-home"></i> Volver al Inicio
            </a>
            <a href="javascript:history.back()" class="btn-secondary-action">
                <i class="fas fa-arrow-left"></i> Volver Atrás
            </a>
        </div>
        
        <!-- Diagnóstico (solo visible con ?debug=1) -->
        <?php if ($showDiagnostic): ?>
        <div class="diagnostic-box">
            <strong><i class="fas fa-bug"></i> Diagnóstico Técnico:</strong><br>
            <code>DocumentRoot: <?php echo $_SERVER["DOCUMENT_ROOT"] ?? 'no definido'; ?></code><br>
            <code>Script Filename: <?php echo $_SERVER["SCRIPT_FILENAME"] ?? 'no definido'; ?></code><br>
            <code>Request URI: <?php echo $_SERVER["REQUEST_URI"] ?? 'no definido'; ?></code><br>
            <code>PHP_SELF: <?php echo $_SERVER["PHP_SELF"] ?? 'no definido'; ?></code><br>
            <code>Error Code: <?php echo $errorCode; ?></code>
        </div>
        <?php endif; ?>
        
        <!-- Mensaje de contacto (opcional) -->
        <div style="margin-top: 20px; font-size: 13px; color: #999;">
            <i class="fas fa-envelope"></i> 
            ¿Necesitas ayuda? Contáctanos en 
            <a href="/soporte.php" style="color: #2980b9; text-decoration: none;">
                soporte@easycarluxury.com
            </a>
        </div>
    </div>

    <!-- Bootstrap JS Bundle - CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Tema
        const themeToggle = document.getElementById('themeToggle');
        const body = document.body;
        
        themeToggle?.addEventListener('click', () => {
            const isDark = body.classList.contains('dark-theme');
            if (isDark) {
                body.classList.remove('dark-theme');
                body.classList.add('light-theme');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i>';
                document.cookie = "theme=light; path=/";
            } else {
                body.classList.remove('light-theme');
                body.classList.add('dark-theme');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
                document.cookie = "theme=dark; path=/";
            }
        });
        
        // Auto-redirección después de 10 segundos (opcional)
        // setTimeout(function() {
        //     window.location.href = '/';
        // }, 10000);
    </script>
</body>
</html>