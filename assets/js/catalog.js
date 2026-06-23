/**
 * Catalog JavaScript
 * Functions for public catalog and landing page
 */

$(document).ready(function() {
    // Smooth scroll for anchor links
    $('a[href^="#"]').on('click', function(e) {
        e.preventDefault();
        const target = $(this.hash);
        if (target.length) {
            $('html, body').animate({
                scrollTop: target.offset().top - 70
            }, 800);
        }
    });
    
    // Navbar scroll effect
    $(window).scroll(function() {
        if ($(window).scrollTop() > 100) {
            $('.navbar').addClass('navbar-scrolled');
        } else {
            $('.navbar').removeClass('navbar-scrolled');
        }
    });
    
    // Auto-hide alerts
    $('.alert').delay(5000).fadeOut(300, function() {
        $(this).remove();
    });
    
    // Live search suggestions
    let searchTimeout;
    $('input[name="q"]').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val();
        
        if (query.length >= 2) {
            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: '/easycarluxury/api/v1/public.php?action=search_suggestions&q=' + encodeURIComponent(query),
                    method: 'GET',
                    success: function(results) {
                        showSuggestions(results);
                    }
                });
            }, 300);
        } else {
            $('.suggestions-dropdown').remove();
        }
    });
    
    function showSuggestions(results) {
        $('.suggestions-dropdown').remove();
        
        if (results.length === 0) return;
        
        let html = '<div class="suggestions-dropdown">';
        results.forEach(item => {
            html += `
                <a href="/easycarluxury/public/catalog/detail.php?id=${item.id}" class="suggestion-item">
                    ${item.image ? `<img src="${item.image}" width="40" height="40">` : '<i class="fas fa-car"></i>'}
                    <div>
                        <strong>${escapeHtml(item.title)}</strong>
                        <small>$${item.price.toLocaleString('es-CO')}</small>
                    </div>
                </a>
            `;
        });
        html += '</div>';
        
        $('input[name="q"]').after(html);
        
        $(document).one('click', function() {
            $('.suggestions-dropdown').remove();
        });
    }
    
    function escapeHtml(str) {
        if (!str) return '';
        return str.replace(/[&<>]/g, function(m) {
            if (m === '&') return '&amp;';
            if (m === '<') return '&lt;';
            if (m === '>') return '&gt;';
            return m;
        });
    }
    
    // Price range slider (if implemented)
    if ($('#priceRange').length) {
        const minPrice = parseInt($('#minPrice').val()) || 0;
        const maxPrice = parseInt($('#maxPrice').val()) || 100000000;
        
        $('#priceRange').slider({
            range: true,
            min: 0,
            max: 100000000,
            step: 1000000,
            values: [minPrice, maxPrice],
            slide: function(event, ui) {
                $('#minPriceDisplay').text('$' + ui.values[0].toLocaleString('es-CO'));
                $('#maxPriceDisplay').text('$' + ui.values[1].toLocaleString('es-CO'));
                $('#minPrice').val(ui.values[0]);
                $('#maxPrice').val(ui.values[1]);
            }
        });
    }
    
    // Lazy loading images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    const src = img.dataset.src;
                    if (src) {
                        img.src = src;
                        img.removeAttribute('data-src');
                    }
                    observer.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Add to favorites (if user is logged in)
    $('.favorite-btn').click(function(e) {
        e.preventDefault();
        const pubId = $(this).data('id');
        
        $.ajax({
            url: '/easycarluxury/api/v1/public.php',
            method: 'POST',
            data: JSON.stringify({
                action: 'toggle_like',
                publication_id: pubId
            }),
            contentType: 'application/json',
            success: function(response) {
                if (response.redirect) {
                    window.location.href = response.redirect;
                } else if (response.success) {
                    const $btn = $('.favorite-btn[data-id="' + pubId + '"]');
                    if (response.liked) {
                        $btn.addClass('liked');
                        $btn.find('i').removeClass('far').addClass('fas');
                    } else {
                        $btn.removeClass('liked');
                        $btn.find('i').removeClass('fas').addClass('far');
                    }
                    $btn.find('.count').text(response.count);
                    
                    // Show toast notification
                    showToast(response.liked ? 'Agregado a favoritos' : 'Eliminado de favoritos', 'success');
                }
            }
        });
    });
    
    // Toast notification
    function showToast(message, type) {
        const toast = $('<div class="toast-notification"></div>')
            .addClass('toast-' + type)
            .html('<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'info-circle') + '"></i> ' + message)
            .appendTo('body');
        
        setTimeout(() => {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }
    
    // Add toast styles if not present
    if (!$('#toastStyles').length) {
        const styles = `
            <style id="toastStyles">
                .toast-notification {
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    padding: 12px 20px;
                    background: #2c3e50;
                    color: white;
                    border-radius: 8px;
                    z-index: 10000;
                    animation: slideIn 0.3s ease;
                    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
                }
                .toast-success { background: #00b894; }
                .toast-error { background: #d63031; }
                .toast-info { background: #0984e3; }
                @keyframes slideIn {
                    from { transform: translateX(100%); opacity: 0; }
                    to { transform: translateX(0); opacity: 1; }
                }
            </style>
        `;
        $('head').append(styles);
    }
});