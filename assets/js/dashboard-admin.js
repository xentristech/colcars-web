/**
 * Admin Dashboard JavaScript
 * Common functions for admin panel
 */

$(document).ready(function() {
    // Mobile sidebar toggle
    const mobileToggle = $('#mobileToggle');
    const adminSidebar = $('.admin-sidebar');
    
    if (mobileToggle.length) {
        mobileToggle.click(function() {
            adminSidebar.toggleClass('mobile-open');
        });
    }
    
    // Close sidebar when clicking outside on mobile
    $(document).click(function(event) {
        if (window.innerWidth <= 768) {
            if (!adminSidebar.is(event.target) && adminSidebar.has(event.target).length === 0 && 
                !mobileToggle.is(event.target) && mobileToggle.has(event.target).length === 0) {
                adminSidebar.removeClass('mobile-open');
            }
        }
    });
    
    // Auto-hide alerts after 5 seconds
    $('.alert').delay(5000).fadeOut(300, function() {
        $(this).remove();
    });
    
    // Confirm dangerous actions
    $('.btn-danger, .delete, .suspend').click(function(e) {
        if (!confirm('¿Estás seguro de realizar esta acción?')) {
            e.preventDefault();
            return false;
        }
    });
});

// Format currency
function formatCurrency(amount) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    }).format(amount);
}

// Format date
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('es-CO', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

// Show toast notification
function showToast(message, type = 'success') {
    const toastHtml = `
        <div class="toast-notification toast-${type}">
            <i class="fas fa-${type === 'success' ? 'check-circle' : (type === 'error' ? 'exclamation-circle' : 'info-circle')}"></i>
            <span>${message}</span>
        </div>
    `;
    
    $('body').append(toastHtml);
    
    setTimeout(() => {
        $('.toast-notification').fadeOut(300, function() {
            $(this).remove();
        });
    }, 3000);
}

// Export table to CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [];
        const cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
            let data = cols[j].innerText.replace(/,/g, ';');
            row.push('"' + data + '"');
        }
        
        csv.push(row.join(','));
    }
    
    const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', filename + '_' + new Date().toISOString().split('T')[0] + '.csv');
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Debounce function for search inputs
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// AJAX wrapper with token
function apiRequest(endpoint, method, data, successCallback, errorCallback) {
    const token = localStorage.getItem('auth_token');
    
    $.ajax({
        url: '/easycarluxury/api/v1/' + endpoint,
        method: method,
        headers: {
            'Authorization': 'Bearer ' + token,
            'Content-Type': 'application/json'
        },
        data: data ? JSON.stringify(data) : null,
        success: function(response) {
            if (successCallback) successCallback(response);
        },
        error: function(xhr) {
            if (xhr.status === 401) {
                window.location.href = '/easycarluxury/public/login.php';
            } else if (errorCallback) {
                errorCallback(xhr);
            } else {
                showToast('Error: ' + (xhr.responseJSON?.error || 'Unknown error'), 'error');
            }
        }
    });
}

// Toast notification styles
const toastStyles = `
    <style>
        .toast-notification {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
            z-index: 10000;
            animation: slideIn 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .toast-success {
            background: #00b894;
        }
        
        .toast-error {
            background: #d63031;
        }
        
        .toast-info {
            background: #0984e3;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
`;

$('head').append(toastStyles);