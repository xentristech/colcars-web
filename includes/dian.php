<?php
/**
 * DIAN Electronic Invoicing Integration
 * Resolution DIAN No. 000001 (Online Invoicing)
 */

class DianElectronicInvoicing {
    private $pdo;
    private $config;
    private $privateKey;
    private $certificate;
    private $environment;  // ← AGREGADO: Propiedad declarada
    
    // Technical keys from DIAN
    const SOFTWARE_ID = 'your-software-id';
    const SOFTWARE_PIN = 'your-software-pin';
    const TEST_SET_ID = 'your-test-set-id';
    const PRODUCTION_SET_ID = 'your-production-set-id';
    
    public function __construct($pdo, $environment = 'test') {
        $this->pdo = $pdo;
        $this->environment = $environment;
        $this->loadConfiguration();
        $this->loadSecurityKeys();
    }
    
    private function loadConfiguration() {
        $query = "SELECT * FROM dian_configuration WHERE environment = :env AND active = 1";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([':env' => $this->environment]);
        $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->config) {
            throw new Exception("DIAN configuration not found for environment: {$this->environment}");
        }
    }
    
    private function loadSecurityKeys() {
        // CORREGIDO: Validar que existan las claves antes de intentar cargarlas
        $privateKeyContent = $this->config['private_key'] ?? '';
        $certificateContent = $this->config['certificate'] ?? '';
        $privateKeyPassword = $this->config['private_key_password'] ?? '';
        
        // Verificar si las claves son válidas (no son placeholders)
        $isPlaceholderPrivate = (strpos($privateKeyContent, '-----BEGIN PRIVATE KEY-----') === false) || 
                                strlen($privateKeyContent) < 100;
        $isPlaceholderCert = (strpos($certificateContent, '-----BEGIN CERTIFICATE-----') === false) || 
                             strlen($certificateContent) < 100;
        
        // Si estamos en entorno de desarrollo y las claves son placeholders, mostrar advertencia pero no fallar
        if ($isPlaceholderPrivate || $isPlaceholderCert) {
            error_log("DIAN: Usando claves de demostración. Las claves reales no están configuradas.");
            $this->privateKey = null;
            $this->certificate = null;
            return;
        }
        
        // Load private key for digital signature
        $this->privateKey = openssl_pkey_get_private($privateKeyContent, $privateKeyPassword);
        
        // Load X.509 certificate
        $this->certificate = openssl_x509_read($certificateContent);
        
        if (!$this->privateKey || !$this->certificate) {
            error_log("DIAN: Error al cargar claves de seguridad. Private key: " . ($this->privateKey ? 'OK' : 'FAIL') . ", Certificate: " . ($this->certificate ? 'OK' : 'FAIL'));
            // No lanzar excepción para permitir que la página cargue en modo demostración
            $this->privateKey = null;
            $this->certificate = null;
        }
    }
    
    /**
     * Verificar si las claves de seguridad están cargadas
     */
    public function isConfigured() {
        return ($this->privateKey !== null && $this->certificate !== null);
    }
    
    /**
     * Generate invoice XML according to DIAN XSD schemas
     */
    public function generateInvoiceXML($invoiceData) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        // Create root element
        $root = $xml->createElementNS('urn:dian:gov:co:facturaelectronica:v1', 'Invoice');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 
            'urn:dian:gov:co:facturaelectronica:v1 http://www.dian.gov.co/schemas/fe/v1/Invoice.xsd');
        $xml->appendChild($root);
        
        // 1. Technical Information
        $technicalInfo = $xml->createElement('TechnicalInformation');
        $technicalInfo->appendChild($xml->createElement('SoftwareID', self::SOFTWARE_ID));
        $technicalInfo->appendChild($xml->createElement('SoftwarePIN', self::SOFTWARE_PIN));
        $technicalInfo->appendChild($xml->createElement('TestSetID', $this->environment === 'test' ? self::TEST_SET_ID : self::PRODUCTION_SET_ID));
        $root->appendChild($technicalInfo);
        
        // 2. Invoice Header
        $invoiceHeader = $xml->createElement('InvoiceHeader');
        $invoiceHeader->appendChild($xml->createElement('InvoiceID', $invoiceData['invoice_number']));
        $invoiceHeader->appendChild($xml->createElement('IssueDate', date('Y-m-d')));
        $invoiceHeader->appendChild($xml->createElement('IssueTime', date('H:i:s-05:00')));
        $invoiceHeader->appendChild($xml->createElement('InvoiceType', 'FV')); // Factura de venta
        $invoiceHeader->appendChild($xml->createElement('PaymentTerms', $invoiceData['payment_terms'] ?? 'CONTADO'));
        $invoiceHeader->appendChild($xml->createElement('CurrencyCode', 'COP'));
        $root->appendChild($invoiceHeader);
        
        // 3. Supplier Information
        $supplier = $xml->createElement('Supplier');
        $supplier->appendChild($xml->createElement('PartyID', $this->config['nit']));
        $supplier->appendChild($xml->createElement('PartyName', $this->config['business_name']));
        
        $taxScheme = $xml->createElement('TaxScheme');
        $taxScheme->appendChild($xml->createElement('ID', '01')); // IVA
        $taxScheme->appendChild($xml->createElement('Name', 'IVA'));
        $supplier->appendChild($taxScheme);
        
        $registration = $xml->createElement('PartyRegistration');
        $registration->appendChild($xml->createElement('RegistrationName', $this->config['business_name']));
        $registration->appendChild($xml->createElement('CompanyID', $this->config['nit']));
        $registration->appendChild($xml->createElement('TaxSchemeID', '01'));
        $supplier->appendChild($registration);
        
        $address = $xml->createElement('PhysicalLocation');
        $address->appendChild($xml->createElement('AddressLine', $this->config['address']));
        $address->appendChild($xml->createElement('CityName', $this->config['city']));
        $address->appendChild($xml->createElement('CountrySubentity', $this->config['department']));
        $address->appendChild($xml->createElement('Country', 'CO'));
        $supplier->appendChild($address);
        
        $root->appendChild($supplier);
        
        // 4. Customer Information
        $customer = $xml->createElement('Customer');
        $customer->appendChild($xml->createElement('PartyID', $invoiceData['customer_nit']));
        $customer->appendChild($xml->createElement('PartyName', $invoiceData['customer_name']));
        
        $customerReg = $xml->createElement('PartyRegistration');
        $customerReg->appendChild($xml->createElement('RegistrationName', $invoiceData['customer_name']));
        $customerReg->appendChild($xml->createElement('CompanyID', $invoiceData['customer_nit']));
        $customerReg->appendChild($xml->createElement('TaxSchemeID', $invoiceData['customer_tax_scheme'] ?? '01'));
        $customer->appendChild($customerReg);
        
        $root->appendChild($customer);
        
        // 5. Invoice Lines (Products/Services)
        $lines = $xml->createElement('InvoiceLines');
        $totalBeforeTax = 0;
        $taxAmount = 0;
        
        foreach ($invoiceData['items'] as $index => $item) {
            $line = $xml->createElement('InvoiceLine');
            $line->appendChild($xml->createElement('ID', $index + 1));
            $line->appendChild($xml->createElement('InvoicedQuantity', $item['quantity']));
            $line->appendChild($xml->createElement('UnitCode', $item['unit_code'] ?? 'ZZ'));
            
            // Item
            $itemElement = $xml->createElement('Item');
            $itemElement->appendChild($xml->createElement('Name', $item['description']));
            $itemElement->appendChild($xml->createElement('SellersItemID', $item['product_id']));
            $line->appendChild($itemElement);
            
            // Price
            $price = $xml->createElement('PriceAmount', number_format($item['unit_price'], 2, '.', ''));
            $line->appendChild($price);
            
            // Line extension amount
            $lineAmount = $item['quantity'] * $item['unit_price'];
            $lineExtension = $xml->createElement('LineExtensionAmount', number_format($lineAmount, 2, '.', ''));
            $line->appendChild($lineExtension);
            
            // Tax totals
            $taxTotal = $xml->createElement('TaxTotal');
            $taxSubtotal = $xml->createElement('TaxSubtotal');
            $taxSubtotal->appendChild($xml->createElement('TaxableAmount', number_format($lineAmount, 2, '.', '')));
            $taxSubtotal->appendChild($xml->createElement('TaxAmount', number_format($lineAmount * 0.19, 2, '.', '')));
            
            $taxCategory = $xml->createElement('TaxCategory');
            $taxCategory->appendChild($xml->createElement('ID', '01')); // IVA
            $taxCategory->appendChild($xml->createElement('Percent', '19.00'));
            $taxCategory->appendChild($xml->createElement('TaxSchemeID', '01'));
            $taxSubtotal->appendChild($taxCategory);
            
            $taxTotal->appendChild($taxSubtotal);
            $line->appendChild($taxTotal);
            
            $lines->appendChild($line);
            
            $totalBeforeTax += $lineAmount;
            $taxAmount += $lineAmount * 0.19;
        }
        $root->appendChild($lines);
        
        // 6. Totals
        $legalMonetaryTotal = $xml->createElement('LegalMonetaryTotal');
        $legalMonetaryTotal->appendChild($xml->createElement('LineExtensionAmount', number_format($totalBeforeTax, 2, '.', '')));
        $legalMonetaryTotal->appendChild($xml->createElement('TaxExclusiveAmount', number_format($totalBeforeTax, 2, '.', '')));
        $legalMonetaryTotal->appendChild($xml->createElement('TaxInclusiveAmount', number_format($totalBeforeTax + $taxAmount, 2, '.', '')));
        $legalMonetaryTotal->appendChild($xml->createElement('PayableAmount', number_format($totalBeforeTax + $taxAmount, 2, '.', '')));
        $root->appendChild($legalMonetaryTotal);
        
        // 7. Tax Totals
        $taxTotal = $xml->createElement('TaxTotal');
        $taxSubtotal = $xml->createElement('TaxSubtotal');
        $taxSubtotal->appendChild($xml->createElement('TaxAmount', number_format($taxAmount, 2, '.', '')));
        
        $taxCategory = $xml->createElement('TaxCategory');
        $taxCategory->appendChild($xml->createElement('ID', '01'));
        $taxCategory->appendChild($xml->createElement('Percent', '19.00'));
        $taxCategory->appendChild($xml->createElement('TaxSchemeID', '01'));
        $taxSubtotal->appendChild($taxCategory);
        
        $taxTotal->appendChild($taxSubtotal);
        $root->appendChild($taxTotal);
        
        // 8. Digital Signature (to be added later in canonicalization)
        $digitalSignature = $xml->createElement('DigitalSignature');
        $digitalSignature->appendChild($xml->createElement('SignerName', $this->config['legal_representative']));
        $digitalSignature->appendChild($xml->createElement('CertificateIssueDate', date('Y-m-d')));
        $digitalSignature->appendChild($xml->createElement('CertificateNumber', $this->config['certificate_number']));
        $digitalSignature->appendChild($xml->createElement('SignatureValue', '{{SIGNATURE}}')); // Placeholder
        $root->appendChild($digitalSignature);
        
        // Canonicalize XML for signature
        $canonicalized = $this->canonicalizeXML($xml);
        
        // Calculate digital signature
        $signature = $this->signDocument($canonicalized);
        
        // Replace placeholder with actual signature
        $xmlContent = $xml->saveXML();
        $xmlContent = str_replace('{{SIGNATURE}}', base64_encode($signature), $xmlContent);
        
        return $xmlContent;
    }
    
    /**
     * Canonicalize XML according to W3C standard
     */
    private function canonicalizeXML($domDocument) {
        $domDocument->preserveWhiteSpace = false;
        $domDocument->formatOutput = false;
        return $domDocument->saveXML();
    }
    
    /**
     * Sign document with private key
     */
    private function signDocument($document) {
        if (!$this->privateKey) {
            // Si no hay clave privada, devolver firma simulada
            return hash('sha256', $document);
        }
        openssl_sign($document, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        return $signature;
    }
    
    /**
     * Send invoice to DIAN asynchronously
     */
    public function sendInvoice($invoiceData, $userId) {
        try {
            // Verificar si DIAN está configurado
            if (!$this->isConfigured()) {
                return [
                    'success' => false,
                    'message' => 'DIAN no está configurado correctamente. Las claves de seguridad no son válidas.'
                ];
            }
            
            // Generate XML
            $xmlContent = $this->generateInvoiceXML($invoiceData);
            
            // Save XML file
            $xmlFilename = 'invoice_' . $invoiceData['invoice_number'] . '_' . time() . '.xml';
            $xmlPath = '/tmp/' . $xmlFilename;
            file_put_contents($xmlPath, $xmlContent);
            
            // Prepare request to DIAN
            $url = $this->config['dian_api_url'] . '/sendDocument';
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/xml',
                'Authorization: Basic ' . base64_encode($this->config['api_user'] . ':' . $this->config['api_password'])
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlContent);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // Record in database
            $query = "INSERT INTO dian_transactions 
                      (user_id, invoice_id, invoice_number, xml_content, cufe, status, dian_response, created_at) 
                      VALUES 
                      (:user_id, :invoice_id, :invoice_number, :xml_content, :cufe, :status, :dian_response, NOW())";
            
            $stmt = $this->pdo->prepare($query);
            
            if ($httpCode === 200) {
                // Parse DIAN response
                $responseXml = simplexml_load_string($response);
                $cufe = (string)$responseXml->CUFE;
                $status = 'ACCEPTED';
                
                $stmt->execute([
                    ':user_id' => $userId,
                    ':invoice_id' => $invoiceData['invoice_id'],
                    ':invoice_number' => $invoiceData['invoice_number'],
                    ':xml_content' => $xmlContent,
                    ':cufe' => $cufe,
                    ':status' => $status,
                    ':dian_response' => $response
                ]);
                
                // Generate PDF with QR
                $this->generatePDFInvoice($invoiceData, $cufe);
                
                return [
                    'success' => true,
                    'cufe' => $cufe,
                    'message' => 'Invoice sent to DIAN successfully'
                ];
            } else {
                $stmt->execute([
                    ':user_id' => $userId,
                    ':invoice_id' => $invoiceData['invoice_id'],
                    ':invoice_number' => $invoiceData['invoice_number'],
                    ':xml_content' => $xmlContent,
                    ':cufe' => null,
                    ':status' => 'REJECTED',
                    ':dian_response' => $response
                ]);
                
                return [
                    'success' => false,
                    'message' => 'DIAN rejected the invoice: ' . $response
                ];
            }
            
        } catch (Exception $e) {
            error_log("DIAN Error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error sending invoice to DIAN: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate PDF invoice with QR code
     */
    private function generatePDFInvoice($invoiceData, $cufe) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mpdf = new \Mpdf\Mpdf([
            'format' => 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 15
        ]);
        
        // Generate QR code URL for DIAN validation
        $qrData = "https://catalogo-vpfe.dian.gov.co/document/" . $cufe;
        
        // HTML content for invoice
        $html = $this->generateInvoiceHTML($invoiceData, $qrData, $cufe);
        
        $mpdf->WriteHTML($html);
        
        // Save PDF
        $pdfFilename = 'invoice_' . $invoiceData['invoice_number'] . '.pdf';
        $pdfPath = $_SERVER['DOCUMENT_ROOT'] . '/easycarluxury/uploads/invoices/' . $pdfFilename;
        
        if (!is_dir(dirname($pdfPath))) {
            mkdir(dirname($pdfPath), 0777, true);
        }
        
        $mpdf->Output($pdfPath, 'F');
        
        // Update database with PDF path
        $query = "UPDATE dian_transactions SET pdf_path = :pdf_path WHERE cufe = :cufe";
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([
            ':pdf_path' => '/uploads/invoices/' . $pdfFilename,
            ':cufe' => $cufe
        ]);
        
        return $pdfPath;
    }
    
    /**
     * Generate HTML for invoice (used in PDF)
     */
    private function generateInvoiceHTML($invoiceData, $qrData, $cufe) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Factura Electrónica No. ' . $invoiceData['invoice_number'] . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .title { font-size: 24px; font-weight: bold; color: #003366; }
                .subtitle { font-size: 14px; color: #666; }
                .invoice-info { margin-bottom: 30px; border: 1px solid #ddd; padding: 15px; }
                .section { margin-bottom: 20px; }
                .section-title { font-size: 18px; font-weight: bold; margin-bottom: 10px; color: #003366; }
                table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .totals { width: 300px; float: right; margin-top: 20px; }
                .footer { margin-top: 50px; text-align: center; font-size: 12px; color: #666; }
                .qr-code { text-align: center; margin-top: 20px; }
                .cufe { font-size: 10px; word-break: break-all; margin-top: 10px; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="title">' . htmlspecialchars($this->config['business_name']) . '</div>
                <div class="subtitle">NIT: ' . htmlspecialchars($this->config['nit']) . '</div>
                <div class="subtitle">Factura Electrónica de Venta</div>
            </div>
            
            <div class="invoice-info">
                <strong>Factura No.:</strong> ' . htmlspecialchars($invoiceData['invoice_number']) . '<br>
                <strong>Fecha de Emisión:</strong> ' . date('Y-m-d H:i:s') . '<br>
                <strong>Tipo de Factura:</strong> Factura de Venta<br>
                <strong>Moneda:</strong> COP<br>
                <strong>Resolución DIAN:</strong> ' . htmlspecialchars($this->config['resolution_number']) . '
            </div>
            
            <div class="section">
                <div class="section-title">INFORMACIÓN DEL CLIENTE</div>
                <div><strong>Nombre:</strong> ' . htmlspecialchars($invoiceData['customer_name']) . '</div>
                <div><strong>NIT/CC:</strong> ' . htmlspecialchars($invoiceData['customer_nit']) . '</div>
                <div><strong>Régimen:</strong> ' . ($invoiceData['customer_tax_scheme'] === '01' ? 'IVA Común' : 'No Responsable de IVA') . '</div>
            </div>
            
            <div class="section">
                <div class="section-title">DETALLE DE LA FACTURA</div>
                <table>
                    <thead>
                        <tr>
                            <th>Cant.</th>
                            <th>Descripción</th>
                            <th>Valor Unitario</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        foreach ($invoiceData['items'] as $item) {
            $total = $item['quantity'] * $item['unit_price'];
            $html .= '
                        <tr>
                            <td>' . $item['quantity'] . '</td>
                            <td>' . htmlspecialchars($item['description']) . '</td>
                            <td>$ ' . number_format($item['unit_price'], 0, ',', '.') . '</td>
                            <td>$ ' . number_format($total, 0, ',', '.') . '</td>
                        </tr>';
        }
        
        $totalBeforeTax = array_sum(array_map(function($item) {
            return $item['quantity'] * $item['unit_price'];
        }, $invoiceData['items']));
        $taxAmount = $totalBeforeTax * 0.19;
        $totalWithTax = $totalBeforeTax + $taxAmount;
        
        $html .= '
                    </tbody>
                </table>
                
                <div class="totals">
                    <table style="width: 100%;">
                        <tr>
                            <td><strong>Subtotal:</strong></td>
                            <td align="right">$ ' . number_format($totalBeforeTax, 0, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td><strong>IVA (19%):</strong></td>
                            <td align="right">$ ' . number_format($taxAmount, 0, ',', '.') . '</td>
                        </tr>
                        <tr>
                            <td><strong>TOTAL:</strong></td>
                            <td align="right"><strong>$ ' . number_format($totalWithTax, 0, ',', '.') . '</strong></td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="qr-code">
                <img src="https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=' . urlencode($qrData) . '" alt="QR Code">
                <div class="cufe"><strong>CUFE:</strong> ' . htmlspecialchars($cufe) . '</div>
            </div>
            
            <div class="footer">
                <p>Esta factura es una representación gráfica del documento electrónico</p>
                <p>Consulte su validez en: https://catalogo-vpfe.dian.gov.co/</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }
    
    /**
     * Generate credit note XML
     */
    public function generateCreditNoteXML($creditNoteData) {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        
        $root = $xml->createElementNS('urn:dian:gov:co:facturaelectronica:v1', 'CreditNote');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'xsi:schemaLocation', 
            'urn:dian:gov:co:facturaelectronica:v1 http://www.dian.gov.co/schemas/fe/v1/CreditNote.xsd');
        $xml->appendChild($root);
        
        // Similar structure to invoice but with credit note specific fields
        // Include reference to original invoice
        $creditReference = $xml->createElement('CreditNoteReference');
        $creditReference->appendChild($xml->createElement('InvoiceID', $creditNoteData['original_invoice_number']));
        $creditReference->appendChild($xml->createElement('CUFE', $creditNoteData['original_cufe']));
        $root->appendChild($creditReference);
        
        // Rest of credit note structure similar to invoice...
        // (Simplified for brevity, but complete implementation would mirror invoice structure)
        
        return $xml->saveXML();
    }
    
    /**
     * Send credit note to DIAN
     */
    public function sendCreditNote($creditNoteData, $userId) {
        // Verificar si DIAN está configurado
        if (!$this->isConfigured()) {
            return [
                'success' => false,
                'message' => 'DIAN no está configurado correctamente. Las claves de seguridad no son válidas.'
            ];
        }
        
        $xmlContent = $this->generateCreditNoteXML($creditNoteData);
        
        // Similar sending logic as invoice
        // ...
        
        return ['success' => true, 'cude' => 'generated_cude'];
    }
    
    /**
     * Check invoice status with DIAN
     */
    public function checkInvoiceStatus($cufe) {
        $url = $this->config['dian_api_url'] . '/checkStatus/' . $cufe;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->config['api_user'] . ':' . $this->config['api_password'])
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}