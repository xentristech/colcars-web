<?php
/**
 * Página de contacto
 * CORREGIDO: Estructura del navbar para móviles (logo izquierda, hamburguesa derecha)
 * CORREGIDO: Botón de tema flotante en esquina inferior derecha (como en index.php)
 * CORREGIDO: Ícono hamburguesa - negro en modo claro (#000000), blanco en modo oscuro (#ffffff)
 * MODIFICADO: Logo dinámico - modo claro: logo_d.png, modo oscuro: colcars_b.png
 * MODIFICADO: API endpoint actualizado a /api/v1/admin/contact_messages.php
 */

require_once '../config/database.php';
require_once '../includes/functions.php';

$database = Database::getInstance();
$pdo      = $database->getConnection();

// Tema
$tema = 'light';
if (isset($_COOKIE['theme'])) {
    $tema = $_COOKIE['theme'];
}

// Obtener categorías para el menú
$categories = $pdo->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacto - Colcars</title>
    <meta name="description" content="Contáctanos para más información sobre nuestros vehículos de lujo. Estamos para ayudarte.">

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/assets/imagenes/favicon/favicon.ico">

    <!-- Bootstrap CSS CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">

    <!-- Font Awesome CSS CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

    <!-- Estilos propios -->
    <link rel="stylesheet" href="/assets/css/dark-theme.css">
    <link rel="stylesheet" href="/assets/css/light-theme.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --azul-primary: #1a5276;
            --azul-primary-dark: #0e3a5c;
            --azul-gradient: linear-gradient(135deg, #1a5276, #2980b9, #3498db);
            --azul-gradient-hover: linear-gradient(135deg, #0e3a5c, #1a5276, #2471a3);
        }

        /* ========== NAVBAR (igual que index.php) ========== */
        .navbar { overflow: visible !important; z-index: 1030; }
        .navbar > .container { overflow: visible !important; display: flex; align-items: center; justify-content: space-between; }
        .navbar-left { display: flex; align-items: center; margin-left: 40px; padding: 8px 0; position: relative; }
        .navbar-brand { position: relative; display: flex; align-items: center; gap: 12px; font-weight: 700; font-size: 1.3rem; text-decoration: none; padding: 0; margin: 0; height: auto; }
        .navbar-logo-wrapper { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); z-index: 1040; padding: 6px; margin: 2px 0; pointer-events: auto; }
        .navbar-logo-wrapper img { height: 50px; width: auto; margin-top: -5px; display: block; transition: transform 0.3s ease, filter 0.3s ease; }
        .navbar-logo-wrapper img:hover { transform: scale(1.08); filter: drop-shadow(0 6px 12px rgba(0,0,0,0.5)); }
        .navbar-brand-text { margin-left: 100px; white-space: nowrap; color: #ffffff; position: relative; z-index: 1040; }
        body.light-theme .navbar-brand-text { color: #1a1a2e; }
        body.dark-theme .navbar-brand-text { color: #ffffff; }
        .navbar-right { display: flex; align-items: center; gap: 10px; }
        .navbar-collapse { flex-grow: 0; }
        .dropdown-menu-scroll { max-height: 300px; overflow-y: auto; scrollbar-width: thin; }
        
        .btn-register { padding: 8px 18px; border-radius: 30px; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 2px solid; }
        body.light-theme .btn-register { border-color: #1a5276; color: #1a5276; background: transparent; }
        body.light-theme .btn-register:hover { background: #1a5276; color: #ffffff; }
        body.dark-theme .btn-register { border-color: #ffffff; color: #ffffff; background: transparent; }
        body.dark-theme .btn-register:hover { background: #ffffff; color: #1a1a2e; }
        
        .nav-link { color: rgba(255,255,255,0.9) !important; font-weight: 500; transition: all 0.3s ease; }
        .nav-link:hover { color: #3498db !important; }
        .nav-link.active { color: #3498db !important; }

        /* ========== ÍCONO HAMBURGUESA (Negro en claro, Blanco en oscuro) ========== */
        .navbar-toggler {
            border: none;
            outline: none;
            transition: all 0.3s ease;
            background: transparent;
        }
        
        body.light-theme .navbar-toggler {
            color: #000000 !important;
        }
        
        body.light-theme .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(0, 0, 0, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }
        
        body.dark-theme .navbar-toggler {
            color: #ffffff !important;
        }
        
        body.dark-theme .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255, 255, 255, 1)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e") !important;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
            outline: none;
        }

        /* ========== BOTÓN DE TEMA FLOTANTE (igual que index.php) ========== */
        .theme-toggle-floating {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 9999;
            background: none;
            border: none;
            font-size: 1.3rem;
            cursor: pointer;
            padding: 12px;
            border-radius: 50%;
            transition: all 0.3s ease;
            width: 48px;
            height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        
        body.dark-theme .theme-toggle-floating {
            background-color: #2c2c3e;
            color: #ffffff;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        body.dark-theme .theme-toggle-floating:hover {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }
        
        body.light-theme .theme-toggle-floating {
            background-color: #ffffff;
            color: #1a1a2e !important;
            border: 1px solid rgba(0,0,0,0.1);
        }
        
        body.light-theme .theme-toggle-floating i {
            color: #1a1a2e !important;
        }
        
        body.light-theme .theme-toggle-floating:hover {
            background: rgba(0,0,0,0.1);
            transform: scale(1.1);
        }
        
        .theme-toggle-floating::before {
            content: attr(title);
            position: absolute;
            bottom: -30px;
            left: 50%;
            transform: translateX(-50%);
            background: #333;
            color: white;
            font-size: 0.7rem;
            padding: 4px 8px;
            border-radius: 4px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            pointer-events: none;
            z-index: 100;
        }
        
        .theme-toggle-floating:hover::before {
            opacity: 1;
            visibility: visible;
        }
        
        body.light-theme .theme-toggle-floating::before {
            background: #666;
        }

        /* ========== CONTACTO ========== */
        .contact-section { padding: 80px 0; background: #f8f9fa; }
        body.dark-theme .contact-section { background: #1a1a2e !important; }

        .section-title { font-size: 2rem; font-weight: 700; margin-bottom: 15px; color: #1a1a2e; }
        body.dark-theme .section-title { color: #ffffff; }

        .section-subtitle { color: #666; max-width: 600px; margin: 0 auto; }
        body.dark-theme .section-subtitle { color: #aaa !important; }

        .contact-form-wrapper { background: #fff; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); overflow: hidden; }
        body.dark-theme .contact-form-wrapper { background: #2c2c3e !important; box-shadow: 0 10px 40px rgba(0,0,0,0.3) !important; }

        .contact-info { background: linear-gradient(135deg, #1a5276, #2980b9); color: white; padding: 40px; }
        .contact-info h3 { font-size: 1.5rem; margin-bottom: 20px; }
        .contact-info p { margin-bottom: 30px; opacity: 0.9; }

        .contact-info-item { margin-bottom: 25px; }
        .contact-info-item > div { display: flex; align-items: center; gap: 15px; }
        .contact-info-item i { font-size: 1.3rem; min-width: 30px; }
        .contact-info-item strong { display: block; font-size: 0.9rem; }
        .contact-info-item small { font-size: 0.85rem; opacity: 0.9; }

        .form-control, .form-select { border-radius: 10px; padding: 12px 15px; }
        body.dark-theme .form-control, body.dark-theme .form-select { background: #3a3a4e !important; border-color: #4a4a5e !important; color: #ffffff !important; }
        body.dark-theme .form-control::placeholder { color: #999 !important; }
        .form-control:focus, .form-select:focus { border-color: #3498db; box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25); }

        .btn-primary { background: var(--azul-gradient) !important; border: none !important; padding: 14px; border-radius: 10px; font-weight: 600; font-size: 1rem; }
        .btn-primary:hover { background: var(--azul-gradient-hover) !important; }

        /* ========== FOOTER ========== */
        .footer { background-color: #1A1A2E !important; padding: 80px 0 60px; margin-top: 0; width: 100%; }
        .footer .container-fluid { max-width: 1400px; background-color: #1A1A2E !important; margin: 0 auto; padding: 0 40px; }
        .footer p { color: #b0b0b0; text-align: left; margin-bottom: 0; }
        .footer a { color: #3498db; text-decoration: none; transition: all 0.3s ease; }
        .footer a:hover { color: #5dade2; text-decoration: underline; padding-left: 5px; }
        .footer h5 { color: #ffffff; margin-bottom: 15px; font-size: 1.1rem; font-weight: 600; }
        .footer-brand { display: flex; align-items: center; justify-content: flex-start; gap: 12px; font-size: 1.3rem; font-weight: bold; color: #ffffff; margin-bottom: 15px; }
        .footer-brand img { height: 50px; width: auto; }
        .footer ul { list-style: none; padding: 0; margin: 0; }
        .footer ul li { margin-bottom: 8px; }
        .footer-bottom { text-align: center; padding-top: 25px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 30px; color: #b0b0b0; }

        @media (max-width: 768px) {
            .contact-info { text-align: center; }
            .contact-info-item > div { justify-content: center; }
            .navbar-brand-text { margin-left: 65px; font-size: 0.9rem; }
            .navbar-logo-wrapper img { height: 55px; }
            .contact-section { padding: 60px 0; }
            .contact-info { padding: 30px; }
            .footer .container-fluid { padding: 0 20px; }
        }

        @media (max-width: 480px) {
            .navbar-brand-text { margin-left: 55px; font-size: 0.8rem; }
            .navbar-logo-wrapper img { height: 45px; }
            .contact-section { padding: 40px 0; }
        }
    </style>
</head>
<body class="<?php echo $tema === 'dark' ? 'dark-theme' : 'light-theme'; ?>">

    <!-- NAVBAR - ESTRUCTURA CORREGIDA (logo izquierda, hamburguesa derecha) -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <div class="navbar-left">
                <a class="navbar-brand" href="/">
                    <span class="navbar-logo-wrapper">
                        <?php if ($tema === 'dark'): ?>
                            <img src="/assets/imagenes/logos/colcars_b.png" alt="Colcars Logo">
                        <?php else: ?>
                            <img src="/assets/imagenes/logos/logo_d.png" alt="Colcars Logo">
                        <?php endif; ?>
                    </span>
                    <span class="navbar-brand-text"></span>
                </a>
            </div>
            
            <!-- Contenido del navbar: botón hamburguesa + collapse DENTRO de navbar-right -->
            <div class="navbar-right">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Inicio</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/catalog">Catálogo</a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Categorías
                            </a>
                            <ul class="dropdown-menu dropdown-menu-scroll">
                                <?php foreach ($categories as $cat): ?>
                                <li><a class="dropdown-item" href="/catalog/category/<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="/contacto">Contacto</a>
                        </li>
                    </ul>
                    
                    <!-- Botones de usuario -->
                    <div class="d-flex align-items-center ms-2">
                        <a class="nav-link" href="/login">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                        <a class="btn-register ms-2" href="/register">
                            <i class="fas fa-user-plus"></i> Registrarse
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- SECCIÓN DE CONTACTO -->
    <section class="contact-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="section-title">
                    <i class="fas fa-envelope" style="color: #3498db;"></i> Contáctanos
                </h2>
                <p class="section-subtitle">
                    ¿Tienes alguna pregunta? Escríbenos y te responderemos lo antes posible.
                </p>
            </div>

            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="contact-form-wrapper">
                        <div class="row g-0">
                            <!-- Información de contacto -->
                            <div class="col-md-5 contact-info">
                                <h3><i class="fas fa-phone-alt"></i> Información</h3>
                                <p>Estamos aquí para ayudarte. Contáctanos por cualquiera de estos medios:</p>

                                <div class="contact-info-item">
                                    <div>
                                        <i class="fas fa-map-marker-alt"></i>
                                        <div>
                                            <strong>Dirección</strong><br>
                                            <small>Calle 123 #45-67, Bogotá, Colombia</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="contact-info-item">
                                    <div>
                                        <i class="fas fa-phone"></i>
                                        <div>
                                            <strong>Teléfono</strong><br>
                                            <small>+57 300 123 4567</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="contact-info-item">
                                    <div>
                                        <i class="fas fa-envelope"></i>
                                        <div>
                                            <strong>Email</strong><br>
                                            <small>info@colcars.com</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="contact-info-item">
                                    <div>
                                        <i class="fab fa-whatsapp"></i>
                                        <div>
                                            <strong>WhatsApp</strong><br>
                                            <small>+57 300 123 4567</small>
                                        </div>
                                    </div>
                                </div>

                                <hr style="margin: 30px 0; border-color: rgba(255,255,255,0.2);">

                                <div>
                                    <h5><i class="fas fa-clock"></i> Horario de atención</h5>
                                    <p style="margin-bottom: 5px;">
                                        <strong>Lunes a Viernes:</strong> 8:00 AM - 8:00 PM<br>
                                        <strong>Sábados:</strong> 9:00 AM - 2:00 PM<br>
                                        <strong>Domingos:</strong> Cerrado
                                    </p>
                                </div>
                            </div>

                            <!-- Formulario -->
                            <div class="col-md-7" style="padding: 40px;">
                                <form id="contactForm" method="POST">
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label" style="font-weight: 600;">
                                                <i class="fas fa-user"></i> Nombre completo *
                                            </label>
                                            <input type="text" name="nombre_completo" id="contact_nombre" class="form-control" placeholder="Tu nombre completo" required>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" style="font-weight: 600;">
                                                <i class="fas fa-envelope"></i> Email *
                                            </label>
                                            <input type="email" name="email" id="contact_email" class="form-control" placeholder="tu@email.com" required>
                                        </div>

                                        <div class="col-md-6 mb-3">
                                            <label class="form-label" style="font-weight: 600;">
                                                <i class="fas fa-phone"></i> Teléfono
                                            </label>
                                            <input type="tel" name="telefono" id="contact_telefono" class="form-control" placeholder="+57 300 123 4567">
                                        </div>

                                        <div class="col-md-12 mb-3">
                                            <label class="form-label" style="font-weight: 600;">
                                                <i class="fas fa-tag"></i> Asunto *
                                            </label>
                                            <select name="asunto" id="contact_asunto" class="form-select" required>
                                                <option value="">Selecciona un asunto</option>
                                                <option value="Consulta general">Consulta general</option>
                                                <option value="Venta de vehículo">Venta de vehículo</option>
                                                <option value="Compra de vehículo">Compra de vehículo</option>
                                                <option value="Membresía">Membresía</option>
                                                <option value="Soporte técnico">Soporte técnico</option>
                                                <option value="Reclamo">Reclamo</option>
                                                <option value="Otro">Otro</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label" style="font-weight: 600;">
                                            <i class="fas fa-comment"></i> Mensaje *
                                        </label>
                                        <textarea name="mensaje" id="contact_mensaje" class="form-control" rows="5" placeholder="Escribe tu mensaje aquí..." required></textarea>
                                    </div>

                                    <div id="contactMessage" class="alert" style="display: none;"></div>

                                    <button type="submit" id="contactSubmitBtn" class="btn btn-primary w-100">
                                        <i class="fas fa-paper-plane"></i> Enviar mensaje
                                    </button>
                                </form>

                                <p class="text-muted text-center mt-3" style="font-size: 0.75rem;">
                                    <i class="fas fa-lock"></i> Tus datos están seguros. No compartiremos tu información con terceros.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="footer">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-4">
                    <div class="footer-brand">
                        <?php if ($tema === 'dark'): ?>
                            <img src="/assets/imagenes/logos/colcars_b.png" alt="Colcars Logo">
                        <?php else: ?>
                            <img src="/assets/imagenes/logos/logo_d.png" alt="Colcars Logo">
                        <?php endif; ?>
                        <span>Colcars</span>
                    </div>
                    <p>La plataforma líder en compra y venta de vehículos de lujo en Colombia.</p>
                </div>
                <div class="col-md-2">
                    <h5>Enlaces</h5>
                    <ul>
                        <li><a href="/">Inicio</a></li>
                        <li><a href="/catalog">Catálogo</a></li>
                        <li><a href="/contacto">Contacto</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Legal</h5>
                    <ul>
                        <li><a href="/terminos-condiciones">Términos y condiciones</a></li>
                        <li><a href="/politica-privacidad">Política de privacidad</a></li>
                        <li><a href="/politica-cookies">Política de cookies</a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h5>Horario de atención</h5>
                    <p>Lunes a Viernes: 8:00 - 20:00<br>
                    Sábados: 9:00 - 14:00<br>
                    Domingos: Cerrado</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Colcars. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- BOTÓN DE TEMA FLOTANTE (esquina inferior derecha) -->
    <button class="theme-toggle-floating" id="themeToggleFloating" title="<?php echo $tema === 'dark' ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'; ?>">
        <i class="fas <?php echo $tema === 'dark' ? 'fa-sun' : 'fa-moon'; ?>"></i>
    </button>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ==========================================
        // CONTROLADOR DEL BOTÓN DE TEMA FLOTANTE
        // ==========================================
        (function() {
            const themeToggle = document.getElementById('themeToggleFloating');
            const currentTheme = document.body.classList.contains('dark-theme') ? 'dark' : 'light';
            const icon = themeToggle.querySelector('i');
            
            function setTheme(theme) {
                if (theme === 'dark') {
                    document.body.classList.remove('light-theme');
                    document.body.classList.add('dark-theme');
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                    themeToggle.title = 'Cambiar a modo claro';
                    document.cookie = "theme=dark; path=/; max-age=" + (365 * 24 * 60 * 60);
                    // Cambiar logos a colcars_b.png
                    const navbarLogo = document.querySelector('.navbar-logo-wrapper img');
                    const footerLogo = document.querySelector('.footer-brand img');
                    if (navbarLogo) navbarLogo.src = '/assets/imagenes/logos/colcars_b.png';
                    if (footerLogo) footerLogo.src = '/assets/imagenes/logos/colcars_b.png';
                } else {
                    document.body.classList.remove('dark-theme');
                    document.body.classList.add('light-theme');
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                    themeToggle.title = 'Cambiar a modo oscuro';
                    document.cookie = "theme=light; path=/; max-age=" + (365 * 24 * 60 * 60);
                    // Cambiar logos a logo_d.png
                    const navbarLogo = document.querySelector('.navbar-logo-wrapper img');
                    const footerLogo = document.querySelector('.footer-brand img');
                    if (navbarLogo) navbarLogo.src = '/assets/imagenes/logos/logo_d.png';
                    if (footerLogo) footerLogo.src = '/assets/imagenes/logos/logo_d.png';
                }
            }
            
            if (themeToggle) {
                themeToggle.addEventListener('click', function() {
                    const isDark = document.body.classList.contains('dark-theme');
                    setTheme(isDark ? 'light' : 'dark');
                });
            }
        })();

        // ==========================================
        // FORMULARIO DE CONTACTO (API ACTUALIZADA)
        // ==========================================
        const contactForm = document.getElementById('contactForm');
        const contactMessage = document.getElementById('contactMessage');
        const submitBtn = document.getElementById('contactSubmitBtn');

        if (contactForm) {
            contactForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const originalText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enviando...';
                contactMessage.style.display = 'none';

                const formData = {
                    action: 'create',
                    nombre_completo: document.getElementById('contact_nombre').value.trim(),
                    email: document.getElementById('contact_email').value.trim(),
                    telefono: document.getElementById('contact_telefono').value.trim(),
                    asunto: document.getElementById('contact_asunto').value,
                    mensaje: document.getElementById('contact_mensaje').value.trim()
                };

                try {
                    const response = await fetch('/api/v1/admin/contact_messages.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(formData)
                    });

                    const result = await response.json();

                    if (result.success) {
                        contactMessage.className = 'alert alert-success';
                        contactMessage.innerHTML = '<i class="fas fa-check-circle"></i> ' + result.message;
                        contactMessage.style.display = 'block';
                        contactForm.reset();
                        contactMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        setTimeout(() => { contactMessage.style.display = 'none'; }, 5000);
                    } else {
                        let errorMessage = '';
                        if (result.errors) {
                            errorMessage = result.errors.join('<br>');
                        } else {
                            errorMessage = result.error || 'Error al enviar el mensaje. Intenta de nuevo.';
                        }
                        contactMessage.className = 'alert alert-danger';
                        contactMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> ' + errorMessage;
                        contactMessage.style.display = 'block';
                    }
                } catch (error) {
                    contactMessage.className = 'alert alert-danger';
                    contactMessage.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error de conexión. Verifica tu internet e intenta de nuevo.';
                    contactMessage.style.display = 'block';
                } finally {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        }
        
        // ==========================================
        // CORRECCIÓN MENÚ HAMBURGUESA (opcional, por compatibilidad)
        // ==========================================
        (function() {
            if (typeof bootstrap !== 'undefined') {
                const navbarToggler = document.querySelector('.navbar-toggler');
                const navbarCollapse = document.getElementById('navbarNav');
                
                if (navbarToggler && navbarCollapse) {
                    navbarToggler.addEventListener('click', function() {
                        setTimeout(function() {
                            if (navbarCollapse.classList.contains('show')) {
                                navbarCollapse.style.display = 'block';
                            } else {
                                navbarCollapse.style.display = '';
                            }
                        }, 10);
                    });
                    
                    const observer = new MutationObserver(function(mutations) {
                        mutations.forEach(function(mutation) {
                            if (mutation.attributeName === 'class') {
                                if (navbarCollapse.classList.contains('show')) {
                                    navbarCollapse.style.display = 'block';
                                } else {
                                    navbarCollapse.style.display = '';
                                }
                            }
                        });
                    });
                    observer.observe(navbarCollapse, { attributes: true });
                }
            }
        })();
    </script>
</body>
</html>