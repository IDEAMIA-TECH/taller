<?php
require_once '../../includes/config.php';

// Verificar autenticación y permisos
if (!isAuthenticated()) {
    redirect('templates/login.php');
}

// Verificar si el taller está activo
if (!isWorkshopActive()) {
    showError('El taller no está activo. Por favor, contacte al administrador.');
    redirect('templates/dashboard.php');
}

// Obtener y validar el ID de la factura
$id_invoice = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_invoice) {
    showError('ID de factura no válido');
    redirect('list_invoices.php');
}

try {
    // Obtener datos de la factura
    $stmt = $db->prepare("
        SELECT 
            i.*,
            w.name as workshop_name,
            w.address as workshop_address,
            w.phone as workshop_phone,
            w.email as workshop_email,
            w.rfc as workshop_rfc,
            w.regimen_fiscal as workshop_regimen_fiscal,
            w.certificate_path,
            w.key_path,
            w.key_password,
            c.name as client_name,
            c.phone as client_phone,
            c.email as client_email,
            c.rfc as client_rfc,
            c.regimen_fiscal as client_regimen_fiscal,
            v.brand,
            v.model,
            v.plates,
            so.order_number,
            so.created_at as order_date
        FROM invoices i
        JOIN workshops w ON i.id_workshop = w.id_workshop
        JOIN service_orders so ON i.id_order = so.id_order
        JOIN clients c ON so.id_client = c.id_client
        JOIN vehicles v ON so.id_vehicle = v.id_vehicle
        WHERE i.id_invoice = ? AND i.id_workshop = ?
    ");
    $stmt->execute([$id_invoice, getCurrentWorkshop()]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        showError('Factura no encontrada');
        redirect('list_invoices.php');
    }

    // Obtener detalles de la orden
    $stmt = $db->prepare("
        SELECT od.*, s.name as service_name, s.clave_prod_serv
        FROM order_details od
        JOIN services s ON od.id_service = s.id_service
        WHERE od.id_order = ?
    ");
    $stmt->execute([$invoice['id_order']]);
    $order_details = $stmt->fetchAll();

    // Crear el documento XML
    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    // Crear el elemento raíz
    $cfdi = $xml->createElementNS('http://www.sat.gob.mx/cfd/4', 'cfdi:Comprobante');
    $cfdi->setAttribute('Version', '4.0');
    $cfdi->setAttribute('Serie', 'A');
    $cfdi->setAttribute('Folio', $invoice['invoice_number']);
    $cfdi->setAttribute('Fecha', date('Y-m-d\TH:i:s'));
    $cfdi->setAttribute('Sello', ''); // Se llenará después del sellado
    $cfdi->setAttribute('FormaPago', $invoice['payment_form']);
    $cfdi->setAttribute('NoCertificado', ''); // Se llenará con el número de certificado
    $cfdi->setAttribute('Certificado', ''); // Se llenará con el certificado en base64
    $cfdi->setAttribute('SubTotal', number_format($invoice['total_amount'], 2, '.', ''));
    $cfdi->setAttribute('Moneda', 'MXN');
    $cfdi->setAttribute('Total', number_format($invoice['total_amount'], 2, '.', ''));
    $cfdi->setAttribute('TipoDeComprobante', 'I');
    $cfdi->setAttribute('Exportacion', '01');
    $cfdi->setAttribute('MetodoPago', $invoice['payment_method']);
    $cfdi->setAttribute('LugarExpedicion', '99999'); // Código postal del taller

    // Agregar emisor
    $emisor = $xml->createElement('cfdi:Emisor');
    $emisor->setAttribute('Rfc', $invoice['workshop_rfc']);
    $emisor->setAttribute('Nombre', $invoice['workshop_name']);
    $emisor->setAttribute('RegimenFiscal', $invoice['workshop_regimen_fiscal']);
    $cfdi->appendChild($emisor);

    // Agregar receptor
    $receptor = $xml->createElement('cfdi:Receptor');
    $receptor->setAttribute('Rfc', $invoice['client_rfc']);
    $receptor->setAttribute('Nombre', $invoice['client_name']);
    $receptor->setAttribute('DomicilioFiscalReceptor', '99999'); // Código postal del cliente
    $receptor->setAttribute('RegimenFiscalReceptor', $invoice['client_regimen_fiscal']);
    $receptor->setAttribute('UsoCFDI', $invoice['cfdi_use']);
    $cfdi->appendChild($receptor);

    // Agregar conceptos
    $conceptos = $xml->createElement('cfdi:Conceptos');
    foreach ($order_details as $detail) {
        $concepto = $xml->createElement('cfdi:Concepto');
        $concepto->setAttribute('ClaveProdServ', $detail['clave_prod_serv']);
        $concepto->setAttribute('Cantidad', $detail['quantity']);
        $concepto->setAttribute('ClaveUnidad', 'E48'); // Unidad de servicio
        $concepto->setAttribute('Unidad', 'SERVICIO');
        $concepto->setAttribute('Descripcion', $detail['service_name']);
        $concepto->setAttribute('ValorUnitario', number_format($detail['unit_price'], 2, '.', ''));
        $concepto->setAttribute('Importe', number_format($detail['subtotal'], 2, '.', ''));
        $conceptos->appendChild($concepto);
    }
    $cfdi->appendChild($conceptos);

    // Agregar impuestos
    $impuestos = $xml->createElement('cfdi:Impuestos');
    $impuestos->setAttribute('TotalImpuestosTrasladados', '0.00');
    $cfdi->appendChild($impuestos);

    // Agregar complemento de pago
    $complemento = $xml->createElement('cfdi:Complemento');
    $pago = $xml->createElement('pago20:Pagos');
    $pago->setAttribute('xmlns:pago20', 'http://www.sat.gob.mx/Pagos20');
    $pago->setAttribute('Version', '2.0');

    $totales = $xml->createElement('pago20:Totales');
    $totales->setAttribute('MontoTotalPagos', number_format($invoice['total_amount'], 2, '.', ''));
    $pago->appendChild($totales);

    $pago_detalle = $xml->createElement('pago20:Pago');
    $pago_detalle->setAttribute('FechaPago', date('Y-m-d\TH:i:s'));
    $pago_detalle->setAttribute('FormaDePagoP', $invoice['payment_form']);
    $pago_detalle->setAttribute('MonedaP', 'MXN');
    $pago_detalle->setAttribute('Monto', number_format($invoice['total_amount'], 2, '.', ''));
    $pago->appendChild($pago_detalle);

    $complemento->appendChild($pago);
    $cfdi->appendChild($complemento);

    $xml->appendChild($cfdi);

    // Firmar el XML
    $certificado = file_get_contents($invoice['certificate_path']);
    $llave_privada = file_get_contents($invoice['key_path']);
    $password = $invoice['key_password'];

    // Aquí iría la lógica para firmar el XML con el certificado y llave privada
    // Esto requiere una biblioteca especializada como phpseclib o similar

    // Generar nombre de archivo
    $filename = 'factura_' . $invoice['invoice_number'] . '.xml';

    // Guardar XML en el servidor
    $xml_path = '../../uploads/invoices/' . $filename;
    $xml->save($xml_path);

    // Actualizar ruta del XML en la base de datos
    $stmt = $db->prepare("UPDATE invoices SET xml_path = ? WHERE id_invoice = ?");
    $stmt->execute([$xml_path, $id_invoice]);

    // Enviar XML al navegador
    header('Content-Type: application/xml');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $xml->saveXML();

} catch (PDOException $e) {
    showError('Error al generar el XML de la factura');
    redirect('list_invoices.php');
} catch (Exception $e) {
    showError('Error al generar el XML: ' . $e->getMessage());
    redirect('list_invoices.php');
} 