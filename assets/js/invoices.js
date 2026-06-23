$(document).ready(function() {
    const token = localStorage.getItem('auth_token');
    
    // Toggle between new and existing customer
    $('#customerType').change(function() {
        if ($(this).val() === 'new') {
            $('#newCustomerFields').show();
            $('#existingCustomerFields').hide();
        } else {
            $('#newCustomerFields').hide();
            $('#existingCustomerFields').show();
            loadExistingCustomers();
        }
    });
    
    // Load existing customers
    function loadExistingCustomers() {
        $.ajax({
            url: '/easycarluxury/api/v1/customers.php',
            method: 'GET',
            headers: {
                'Authorization': 'Bearer ' + token
            },
            success: function(response) {
                let options = '<option value="">Seleccionar...</option>';
                response.data.forEach(customer => {
                    options += `<option value="${customer.id}" data-nit="${customer.nit}" data-name="${customer.name}" data-tax-scheme="${customer.tax_scheme}">
                        ${customer.name} - ${customer.nit}
                    </option>`;
                });
                $('#existingCustomer').html(options);
            }
        });
    }
    
    // Auto-calculate total
    $('#quantity, #unitPrice').on('input', function() {
        const quantity = $('#quantity').val() || 0;
        const price = $('#unitPrice').val() || 0;
        const total = quantity * price;
        const iva = total * 0.19;
        const totalWithIva = total + iva;
        
        $('#totalPreview').remove();
        $('.modal-body').append(`
            <div class="alert alert-info mt-3" id="totalPreview">
                <strong>Resumen:</strong><br>
                Subtotal: $ ${formatNumber(total)}<br>
                IVA (19%): $ ${formatNumber(iva)}<br>
                <strong>Total: $ ${formatNumber(totalWithIva)}</strong>
            </div>
        `);
    });
    
    // Generate and send invoice
    $('#generateInvoiceBtn').click(function() {
        const customerType = $('#customerType').val();
        let customerNit, customerName, taxScheme;
        
        if (customerType === 'new') {
            customerNit = $('#customerNit').val();
            customerName = $('#customerName').val();
            taxScheme = $('#taxScheme').val();
            
            if (!customerNit || !customerName) {
                alert('Complete los datos del cliente');
                return;
            }
        } else {
            const selected = $('#existingCustomer option:selected');
            if (!selected.val()) {
                alert('Seleccione un cliente');
                return;
            }
            customerNit = selected.data('nit');
            customerName = selected.data('name');
            taxScheme = selected.data('tax-scheme');
        }
        
        const invoiceData = {
            action: 'send_invoice',
            invoice_id: null, // Will be created first
            customer_nit: customerNit,
            customer_name: customerName,
            customer_tax_scheme: taxScheme,
            items: [{
                product_id: $('#productType').val(),
                description: $('#description').val(),
                quantity: parseInt($('#quantity').val()),
                unit_price: parseFloat($('#unitPrice').val()),
                unit_code: 'ZZ'
            }]
        };
        
        // First create the invoice in our system
        $.ajax({
            url: '/easycarluxury/api/v1/invoices.php',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                amount: invoiceData.items[0].quantity * invoiceData.items[0].unit_price * 1.19,
                concept: invoiceData.items[0].description,
                invoice_type: 'membership'
            }),
            success: function(response) {
                // Then send to DIAN
                invoiceData.invoice_id = response.invoice_id;
                
                $.ajax({
                    url: '/easycarluxury/api/v1/dian.php',
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + token,
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify(invoiceData),
                    success: function(result) {
                        if (result.success) {
                            alert('✓ Factura generada y enviada a la DIAN exitosamente');
                            location.reload();
                        } else {
                            alert('Error: ' + result.message);
                        }
                    },
                    error: function(xhr) {
                        alert('Error al enviar a DIAN: ' + xhr.responseJSON?.error || 'Error desconocido');
                    }
                });
            },
            error: function(xhr) {
                alert('Error al crear factura: ' + xhr.responseJSON?.error || 'Error desconocido');
            }
        });
    });
    
    // Send pending invoice to DIAN
    $('.send-invoice').click(function() {
        const invoiceId = $(this).data('invoice-id');
        
        if (confirm('¿Enviar esta factura a la DIAN para su validación?')) {
            $.ajax({
                url: '/easycarluxury/api/v1/dian.php',
                method: 'POST',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'Content-Type': 'application/json'
                },
                data: JSON.stringify({
                    action: 'send_invoice',
                    invoice_id: invoiceId,
                    // For existing invoices, fetch customer data from DB
                    customer_nit: '900000001', // This should come from DB
                    customer_name: 'Cliente Test',
                    items: []
                }),
                success: function(result) {
                    if (result.success) {
                        alert('Factura enviada exitosamente');
                        location.reload();
                    } else {
                        alert('Error: ' + result.message);
                    }
                },
                error: function(xhr) {
                    alert('Error: ' + xhr.responseJSON?.error || 'Error de conexión');
                }
            });
        }
    });
    
    // Credit note
    $('.credit-note').click(function() {
        const invoiceId = $(this).data('invoice-id');
        const cufe = $(this).data('cufe');
        
        $('#creditInvoiceId').val(invoiceId);
        $('#creditCufe').val(cufe);
        $('#creditNoteModal').modal('show');
    });
    
    $('#generateCreditNoteBtn').click(function() {
        const invoiceId = $('#creditInvoiceId').val();
        const cufe = $('#creditCufe').val();
        const reason = $('#creditReason').val();
        const amount = $('#creditAmount').val();
        
        if (!amount || amount <= 0) {
            alert('Ingrese un valor válido');
            return;
        }
        
        $.ajax({
            url: '/easycarluxury/api/v1/dian.php',
            method: 'POST',
            headers: {
                'Authorization': 'Bearer ' + token,
                'Content-Type': 'application/json'
            },
            data: JSON.stringify({
                action: 'send_credit_note',
                original_invoice_id: invoiceId,
                original_cufe: cufe,
                reason: reason,
                amount: parseFloat(amount)
            }),
            success: function(result) {
                if (result.success) {
                    alert('Nota crédito generada y enviada a la DIAN');
                    location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            },
            error: function(xhr) {
                alert('Error: ' + xhr.responseJSON?.error || 'Error de conexión');
            }
        });
    });
    
    // Helper function
    function formatNumber(num) {
        return num.toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0});
    }
});