/**
 * Admin Advanced JavaScript
 * Functions for ads, communications, statistics
 */

$(document).ready(function() {
    // Auto-refresh statistics every 30 seconds if on stats page
    if (window.location.pathname.includes('statistics.php')) {
        setInterval(function() {
            location.reload();
        }, 30000);
    }
    
    // Preview message length
    $('#message').on('input', function() {
        const length = $(this).val().length;
        const preview = length > 100 ? $(this).val().substring(0, 100) + '...' : $(this).val();
        $('#messagePreview').html(preview);
        $('#charCount').text(length);
    });
    
    // Validate dates
    $('#start_date, #end_date').on('change', function() {
        const start = $('#start_date').val();
        const end = $('#end_date').val();
        
        if (start && end && new Date(start) > new Date(end)) {
            alert('La fecha de inicio no puede ser mayor a la fecha de fin');
            $(this).val('');
        }
    });
});

// Export chart as image
function exportChartAsImage(chartId, filename) {
    const canvas = document.getElementById(chartId);
    if (!canvas) return;
    
    const link = document.createElement('a');
    link.download = filename + '.png';
    link.href = canvas.toDataURL();
    link.click();
}

// Send test email
function sendTestEmail(email) {
    $.ajax({
        url: '/easycarluxury/api/v1/admin-advanced.php?action=test_email',
        method: 'POST',
        data: JSON.stringify({ email: email }),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showToast('Email de prueba enviado', 'success');
            } else {
                showToast('Error: ' + response.message, 'error');
            }
        }
    });
}

// Schedule mass communication
function scheduleCommunication() {
    const data = {
        type: $('#commType').val(),
        subject: $('#commSubject').val(),
        message: $('#commMessage').val(),
        target: $('#commTarget').val(),
        schedule_date: $('#scheduleDate').val()
    };
    
    $.ajax({
        url: '/easycarluxury/api/v1/admin-advanced.php?action=schedule_communication',
        method: 'POST',
        data: JSON.stringify(data),
        contentType: 'application/json',
        success: function(response) {
            if (response.success) {
                showToast('Comunicación programada exitosamente', 'success');
                location.reload();
            } else {
                showToast('Error: ' + response.message, 'error');
            }
        }
    });
}