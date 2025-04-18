<?php
require_once '../../includes/config.php';
require_once '../../vendor/tecnickcom/tcpdf/tcpdf.php';

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
            w.logo_path,
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
        SELECT od.*, s.name as service_name
        FROM order_details od
        JOIN services s ON od.id_service = s.id_service
        WHERE od.id_order = ?
    ");
    $stmt->execute([$invoice['id_order']]);
    $order_details = $stmt->fetchAll();

    // Crear nuevo documento PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Configurar información del documento
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($invoice['workshop_name']);
    $pdf->SetTitle('Factura ' . $invoice['invoice_number']);
    $pdf->SetSubject('Factura de Servicios');

    // Configurar márgenes
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(TRUE, 15);

    // Agregar página
    $pdf->AddPage();

    // Establecer fuente
    $pdf->SetFont('helvetica', '', 10);

    // Logo del taller (si existe)
    if ($invoice['logo_path'] && file_exists($invoice['logo_path'])) {
        $pdf->Image($invoice['logo_path'], 15, 15, 30, 0, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
    }

    // Encabezado
    $pdf->SetY(15);
    $pdf->SetX(50);
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'FACTURA', 0, 1, 'R');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetX(50);
    $pdf->Cell(0, 5, 'Número: ' . $invoice['invoice_number'], 0, 1, 'R');
    $pdf->SetX(50);
    $pdf->Cell(0, 5, 'Fecha: ' . date('d/m/Y H:i', strtotime($invoice['created_at'])), 0, 1, 'R');
    $pdf->SetX(50);
    $pdf->Cell(0, 5, 'Orden de Servicio: ' . $invoice['order_number'], 0, 1, 'R');

    // Datos del taller
    $pdf->SetY(50);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, $invoice['workshop_name'], 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->MultiCell(0, 5, $invoice['workshop_address'], 0, 'L');
    $pdf->Cell(0, 5, 'Tel: ' . $invoice['workshop_phone'], 0, 1);
    $pdf->Cell(0, 5, 'Email: ' . $invoice['workshop_email'], 0, 1);
    $pdf->Cell(0, 5, 'RFC: ' . $invoice['workshop_rfc'], 0, 1);
    $pdf->Cell(0, 5, 'Régimen Fiscal: ' . $invoice['workshop_regimen_fiscal'], 0, 1);

    // Datos del cliente
    $pdf->SetY(90);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, 'Datos del Cliente', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Nombre: ' . $invoice['client_name'], 0, 1);
    $pdf->Cell(0, 5, 'RFC: ' . $invoice['client_rfc'], 0, 1);
    $pdf->Cell(0, 5, 'Régimen Fiscal: ' . $invoice['client_regimen_fiscal'], 0, 1);
    $pdf->Cell(0, 5, 'Tel: ' . $invoice['client_phone'], 0, 1);
    $pdf->Cell(0, 5, 'Email: ' . $invoice['client_email'], 0, 1);

    // Datos del vehículo
    $pdf->SetY(120);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, 'Datos del Vehículo', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Marca/Modelo: ' . $invoice['brand'] . ' ' . $invoice['model'], 0, 1);
    $pdf->Cell(0, 5, 'Placas: ' . $invoice['plates'], 0, 1);

    // Tabla de detalles
    $pdf->SetY(140);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(20, 7, 'Cantidad', 1, 0, 'C');
    $pdf->Cell(80, 7, 'Descripción', 1, 0, 'C');
    $pdf->Cell(40, 7, 'Precio Unitario', 1, 0, 'C');
    $pdf->Cell(40, 7, 'Subtotal', 1, 1, 'C');

    $pdf->SetFont('helvetica', '', 10);
    foreach ($order_details as $detail) {
        $pdf->Cell(20, 7, $detail['quantity'], 1, 0, 'C');
        $pdf->Cell(80, 7, $detail['service_name'], 1, 0, 'L');
        $pdf->Cell(40, 7, '$' . number_format($detail['unit_price'], 2), 1, 0, 'R');
        $pdf->Cell(40, 7, '$' . number_format($detail['subtotal'], 2), 1, 1, 'R');
    }

    // Total
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 7, 'Total:', 1, 0, 'R');
    $pdf->Cell(40, 7, '$' . number_format($invoice['total_amount'], 2), 1, 1, 'R');

    // Información adicional
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, 'Información Adicional', 0, 1);
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Método de Pago: ' . $invoice['payment_method'], 0, 1);
    $pdf->Cell(0, 5, 'Forma de Pago: ' . $invoice['payment_form'], 0, 1);
    $pdf->Cell(0, 5, 'Uso del CFDI: ' . $invoice['cfdi_use'], 0, 1);

    // Firmas
    $pdf->SetY($pdf->GetY() + 20);
    $pdf->Cell(80, 5, 'Firma del Cliente', 0, 0, 'C');
    $pdf->Cell(80, 5, 'Firma del Representante', 0, 1, 'C');
    $pdf->Cell(80, 20, '', 'T', 0, 'C');
    $pdf->Cell(80, 20, '', 'T', 1, 'C');

    // Pie de página
    $pdf->SetY($pdf->GetY() + 10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->MultiCell(0, 5, 'Este documento es una representación impresa de un CFDI. Para consultar el documento electrónico, escanee el código QR o visite el portal del SAT.', 0, 'C');
    $pdf->Cell(0, 5, 'Fecha y hora de impresión: ' . date('d/m/Y H:i'), 0, 1, 'C');

    // Generar nombre de archivo
    $filename = 'factura_' . $invoice['invoice_number'] . '.pdf';

    // Guardar PDF en el servidor
    $pdf_path = '../../uploads/invoices/' . $filename;
    $pdf->Output($pdf_path, 'F');

    // Actualizar ruta del PDF en la base de datos
    $stmt = $db->prepare("UPDATE invoices SET pdf_path = ? WHERE id_invoice = ?");
    $stmt->execute([$pdf_path, $id_invoice]);

    // Enviar PDF al navegador
    $pdf->Output($filename, 'D');

} catch (PDOException $e) {
    showError('Error al generar el PDF de la factura');
    redirect('list_invoices.php');
} catch (Exception $e) {
    showError('Error al generar el PDF: ' . $e->getMessage());
    redirect('list_invoices.php');
} 