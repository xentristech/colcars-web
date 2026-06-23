<?php
/**
 * Colcars - Footer del Usuario
 * ESTE ES EL ARCHIVO ESTÁNDAR PARA TODOS LOS DASHBOARDS
 */

// Asegurar que la sesión está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!-- ESTILOS DEL FOOTER -->
<style>
    .dashboard-footer {
        background: var(--bg-secondary);
        border-top: 1px solid var(--border-color);
        padding: 15px 0;
        text-align: center;
        font-size: 0.75rem;
        color: var(--text-secondary);
        width: 100%;
        transition: all 0.3s ease;
        clear: both;
    }
    .dashboard-footer a {
        color: #667eea;
        text-decoration: none;
        transition: color 0.3s ease;
    }
    .dashboard-footer a:hover {
        text-decoration: underline;
        color: #764ba2;
    }
    .dashboard-footer p {
        margin-bottom: 5px;
    }
    .dashboard-footer .footer-social {
        margin-top: 10px;
    }
    .dashboard-footer .footer-social a {
        display: inline-block;
        width: 30px;
        height: 30px;
        line-height: 30px;
        text-align: center;
        margin: 0 5px;
        border-radius: 50%;
        background: rgba(102, 126, 234, 0.1);
        transition: all 0.3s ease;
    }
    .dashboard-footer .footer-social a:hover {
        background: rgba(102, 126, 234, 0.2);
        transform: translateY(-2px);
    }
    @media (max-width: 768px) {
        .dashboard-footer {
            padding: 12px 0;
            font-size: 0.7rem;
        }
        .dashboard-footer .footer-social a {
            width: 25px;
            height: 25px;
            line-height: 25px;
            font-size: 0.7rem;
        }
    }
</style>

<footer class="dashboard-footer">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <p>&copy; <?php echo date('Y'); ?> Colcars - Todos los derechos reservados. By Software and Games Cel: 3151056434</p>
                <p class="mb-0">
                    <a href="/easycarluxury/terms">Términos y condiciones</a> | 
                    <a href="/easycarluxury/privacy">Política de privacidad</a> | 
                    <a href="/easycarluxury/contact">Contacto</a>
                </p>
                <div class="footer-social">
                    <a href="#" target="_blank"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" target="_blank"><i class="fab fa-instagram"></i></a>
                    <a href="#" target="_blank"><i class="fab fa-whatsapp"></i></a>
                    <a href="#" target="_blank"><i class="fab fa-youtube"></i></a>
                </div>
            </div>
        </div>
    </div>
</footer>