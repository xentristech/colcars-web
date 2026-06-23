/**
 * theme-switcher.js - Selector de Tema Claro/Oscuro
 * Botón con ícono de luna/sol y tooltip
 */

document.addEventListener('DOMContentLoaded', function() {
    const themeToggle = document.getElementById('themeToggle');
    
    if (!themeToggle) {
        console.log('Botón de tema no encontrado');
        return;
    }
    
    const body = document.body;
    const icon = themeToggle.querySelector('i');
    
    // Función para actualizar el tooltip
    function updateTooltip(theme) {
        if (theme === 'light') {
            themeToggle.setAttribute('title', 'Cambiar a modo oscuro');
        } else {
            themeToggle.setAttribute('title', 'Cambiar a modo claro');
        }
    }
    
    // Función para cambiar tema
    function setTheme(theme) {
        if (theme === 'light') {
            body.classList.remove('dark-theme');
            body.classList.add('light-theme');
            icon.className = 'fas fa-moon';
            updateTooltip('light');
            document.cookie = "theme=light; path=/; max-age=" + (60*60*24*365);
            console.log('Tema cambiado a claro');
        } else {
            body.classList.remove('light-theme');
            body.classList.add('dark-theme');
            icon.className = 'fas fa-sun';
            updateTooltip('dark');
            document.cookie = "theme=dark; path=/; max-age=" + (60*60*24*365);
            console.log('Tema cambiado a oscuro');
        }
    }
    
    // Evento click
    themeToggle.addEventListener('click', function(e) {
        e.preventDefault();
        if (body.classList.contains('dark-theme')) {
            setTheme('light');
        } else {
            setTheme('dark');
        }
    });
    
    // Inicializar tooltip según tema actual
    if (body.classList.contains('light-theme')) {
        updateTooltip('light');
    } else {
        updateTooltip('dark');
    }
    
    console.log('Selector de tema inicializado - Tema actual: ' + (body.classList.contains('light-theme') ? 'claro' : 'oscuro'));
});