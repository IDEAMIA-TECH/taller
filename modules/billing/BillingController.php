<?php
require_once '../../includes/config.php';
require_once '../../includes/billing_config.php';

class BillingController {
    private $db;
    private $config;
    private $workshop;
    
    public function __construct($db, $workshop_id) {
        $this->db = $db;
        $this->config = require '../../includes/billing_config.php';
        $this->loadWorkshopData($workshop_id);
    }
    
    private function loadWorkshopData($workshop_id) {
        $stmt = $this->db->prepare("
            SELECT w.*, f.* 
            FROM workshops w
            LEFT JOIN fiscal_data f ON w.id_workshop = f.id_workshop
            WHERE w.id_workshop = ?
        ");
        $stmt->execute([$workshop_id]);
        $this->workshop = $stmt->fetch();
        
        if (!$this->workshop) {
            throw new Exception("Taller no encontrado");
        }
    }
    
    public function generateInvoice($order_id) {
        // Validar datos del taller
        $this->validateFiscalData();
        
        // Obtener datos de la orden
        $order = $this->getOrderData($order_id);
        
        // Generar XML
        $xml = $this->generateXML($order);
        
        // Timbrar con PAC
        $stamped = $this->stampWithPAC($xml);
        
        // Generar PDF
        $pdf = $this->generatePDF($stamped);
        
        // Guardar en base de datos
        $invoice_id = $this->saveInvoice($order_id, $stamped, $pdf);
        
        // Enviar por correo si está habilitado
        if ($this->config['email']['enabled']) {
            $this->sendInvoiceEmail($invoice_id);
        }
        
        return $invoice_id;
    }
    
    private function validateFiscalData() {
        $required = [
            'rfc',
            'business_name',
            'fiscal_address',
            'regimen_fiscal',
            'certificate_number',
            'certificate_path',
            'key_path'
        ];
        
        foreach ($required as $field) {
            if (empty($this->workshop[$field])) {
                throw new Exception("Dato fiscal requerido faltante: $field");
            }
        }
    }
    
    private function getOrderData($order_id) {
        $stmt = $this->db->prepare("
            SELECT o.*, c.*, v.*, 
                   GROUP_CONCAT(CONCAT(s.name, '|', od.quantity, '|', od.unit_price) SEPARATOR '||') as services
            FROM service_orders o
            JOIN clients c ON o.id_client = c.id_client
            JOIN vehicles v ON o.id_vehicle = v.id_vehicle
            JOIN order_details od ON o.id_order = od.id_order
            JOIN services s ON od.id_service = s.id_service
            WHERE o.id_order = ?
            AND o.id_workshop = ?
            GROUP BY o.id_order
        ");
        
        $stmt->execute([$order_id, $this->workshop['id_workshop']]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("Orden no encontrada");
        }
        
        // Procesar servicios
        $services = [];
        foreach (explode('||', $order['services']) as $service) {
            list($name, $quantity, $price) = explode('|', $service);
            $services[] = [
                'name' => $name,
                'quantity' => $quantity,
                'price' => $price
            ];
        }
        $order['services'] = $services;
        
        return $order;
    }
    
    private function generateXML($order) {
        // Crear estructura base del CFDI
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante/>');
        $xml->addAttribute('xmlns:cfdi', 'http://www.sat.gob.mx/cfd/4');
        $xml->addAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        
        // Agregar datos del emisor
        $emisor = $xml->addChild('cfdi:Emisor');
        $emisor->addAttribute('Rfc', $this->workshop['rfc']);
        $emisor->addAttribute('Nombre', $this->workshop['business_name']);
        $emisor->addAttribute('RegimenFiscal', $this->workshop['regimen_fiscal']);
        
        // Agregar datos del receptor
        $receptor = $xml->addChild('cfdi:Receptor');
        $receptor->addAttribute('Rfc', $order['rfc']);
        $receptor->addAttribute('Nombre', $order['name']);
        $receptor->addAttribute('UsoCFDI', $order['cfdi_use']);
        
        // Agregar conceptos
        $conceptos = $xml->addChild('cfdi:Conceptos');
        foreach ($order['services'] as $service) {
            $concepto = $conceptos->addChild('cfdi:Concepto');
            $concepto->addAttribute('ClaveProdServ', '84111506'); // Código genérico
            $concepto->addAttribute('Cantidad', $service['quantity']);
            $concepto->addAttribute('ClaveUnidad', 'E48'); // Unidad de servicio
            $concepto->addAttribute('Descripcion', $service['name']);
            $concepto->addAttribute('ValorUnitario', $service['price']);
            $concepto->addAttribute('Importe', $service['quantity'] * $service['price']);
        }
        
        // Calcular totales
        $subtotal = array_sum(array_map(function($s) {
            return $s['quantity'] * $s['price'];
        }, $order['services']));
        
        $iva = $subtotal * 0.16;
        $total = $subtotal + $iva;
        
        $xml->addAttribute('SubTotal', $subtotal);
        $xml->addAttribute('Total', $total);
        $xml->addAttribute('Moneda', $this->config['currency']);
        $xml->addAttribute('TipoCambio', '1');
        $xml->addAttribute('FormaPago', $order['payment_method']);
        $xml->addAttribute('MetodoPago', $order['payment_form']);
        
        return $xml->asXML();
    }
    
    private function stampWithPAC($xml) {
        if (!$this->config['pac']['enabled']) {
            throw new Exception("Servicio de PAC no habilitado");
        }
        
        $endpoint = $this->config['pac']['test_mode'] 
            ? $this->config['pac']['endpoint']['test']
            : $this->config['pac']['endpoint']['production'];
        
        // Crear cliente SOAP
        $client = new SoapClient($endpoint, [
            'trace' => true,
            'exceptions' => true
        ]);
        
        // Preparar parámetros
        $params = [
            'username' => $this->config['pac']['username'],
            'password' => $this->config['pac']['password'],
            'xml' => $xml
        ];
        
        // Intentar timbrar
        $attempts = 0;
        $last_error = null;
        
        while ($attempts < $this->config['stamping']['max_retries']) {
            try {
                $result = $client->stamp($params);
                if ($result->status == 'success') {
                    return $result->xml;
                }
                throw new Exception($result->message);
            } catch (Exception $e) {
                $last_error = $e;
                $attempts++;
                if ($attempts < $this->config['stamping']['max_retries']) {
                    sleep($this->config['stamping']['retry_interval']);
                }
            }
        }
        
        throw new Exception("Error al timbrar: " . $last_error->getMessage());
    }
    
    private function generatePDF($xml) {
        // Convertir XML a PDF usando una plantilla
        $template = file_get_contents($this->config['paths']['templates'] . 'invoice.php');
        
        // Procesar plantilla con datos del XML
        $pdf = $this->processTemplate($template, $xml);
        
        // Guardar PDF
        $filename = uniqid('invoice_') . '.pdf';
        $path = $this->config['paths']['pdf'] . $filename;
        file_put_contents($path, $pdf);
        
        return $filename;
    }
    
    private function saveInvoice($order_id, $xml, $pdf) {
        $stmt = $this->db->prepare("
            INSERT INTO invoices (
                id_order,
                id_workshop,
                invoice_number,
                rfc_issuer,
                rfc_receiver,
                total_amount,
                payment_method,
                payment_form,
                cfdi_use,
                xml_path,
                pdf_path,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        // Generar número de factura
        $invoice_number = $this->generateInvoiceNumber();
        
        // Guardar XML
        $xml_filename = uniqid('invoice_') . '.xml';
        $xml_path = $this->config['paths']['xml'] . $xml_filename;
        file_put_contents($xml_path, $xml);
        
        $stmt->execute([
            $order_id,
            $this->workshop['id_workshop'],
            $invoice_number,
            $this->workshop['rfc'],
            $this->getOrderData($order_id)['rfc'],
            $this->getOrderData($order_id)['total_amount'],
            $this->getOrderData($order_id)['payment_method'],
            $this->getOrderData($order_id)['payment_form'],
            $this->getOrderData($order_id)['cfdi_use'],
            $xml_filename,
            $pdf,
            'pending'
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function generateInvoiceNumber() {
        $stmt = $this->db->prepare("
            SELECT MAX(CAST(SUBSTRING(invoice_number, 5) AS UNSIGNED)) as last_number
            FROM invoices
            WHERE id_workshop = ?
        ");
        $stmt->execute([$this->workshop['id_workshop']]);
        $result = $stmt->fetch();
        
        $last_number = $result['last_number'] ?? 0;
        $next_number = str_pad($last_number + 1, 6, '0', STR_PAD_LEFT);
        
        return 'FAC-' . $next_number;
    }
    
    private function sendInvoiceEmail($invoice_id) {
        $stmt = $this->db->prepare("
            SELECT i.*, c.email as client_email
            FROM invoices i
            JOIN service_orders o ON i.id_order = o.id_order
            JOIN clients c ON o.id_client = c.id_client
            WHERE i.id_invoice = ?
        ");
        $stmt->execute([$invoice_id]);
        $invoice = $stmt->fetch();
        
        if (!$invoice || !$invoice['client_email']) {
            return;
        }
        
        // Preparar correo
        $to = $invoice['client_email'];
        $subject = $this->config['email']['subject'];
        $message = $this->getEmailTemplate($invoice);
        $headers = [
            'From' => $this->workshop['email'],
            'Content-Type' => 'text/html; charset=UTF-8'
        ];
        
        // Adjuntar archivos
        $attachments = [
            $this->config['paths']['xml'] . $invoice['xml_path'],
            $this->config['paths']['pdf'] . $invoice['pdf_path']
        ];
        
        // Enviar correo
        mail($to, $subject, $message, $headers, implode(' ', $attachments));
    }
    
    private function getEmailTemplate($invoice) {
        $template = file_get_contents($this->config['paths']['templates'] . $this->config['email']['template']);
        
        // Reemplazar variables en la plantilla
        $replacements = [
            '{invoice_number}' => $invoice['invoice_number'],
            '{client_name}' => $this->getOrderData($invoice['id_order'])['name'],
            '{total_amount}' => number_format($invoice['total_amount'], 2),
            '{date}' => date('d/m/Y', strtotime($invoice['created_at']))
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }
} 