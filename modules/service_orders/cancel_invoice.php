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

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Método no permitido');
    redirect('index.php');
}

// Obtener y validar el ID de la factura
$id_invoice = isset($_POST['id_invoice']) ? (int)$_POST['id_invoice'] : 0;

if (!$id_invoice) {
    showError('ID de factura no válido');
    redirect('index.php');
}

try {
    // Obtener datos de la factura
    $stmt = $db->prepare("
        SELECT 
            i.*,
            w.rfc as workshop_rfc,
            w.regimen_fiscal as workshop_regimen_fiscal,
            c.rfc as client_rfc
        FROM invoices i
        JOIN workshops w ON i.id_workshop = w.id_workshop
        JOIN service_orders so ON i.id_order = so.id_order
        JOIN clients c ON so.id_client = c.id_client
        WHERE i.id_invoice = ? AND i.id_workshop = ?
    ");
    $stmt->execute([$id_invoice, getCurrentWorkshop()]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        showError('Factura no encontrada');
        redirect('index.php');
    }

    // Verificar que la factura no esté ya cancelada
    if ($invoice['status'] === 'cancelled') {
        showError('La factura ya está cancelada');
        redirect('view_invoice.php?id=' . $id_invoice);
    }

    // Verificar que la factura esté pagada
    if ($invoice['status'] !== 'paid') {
        showError('Solo se pueden cancelar facturas pagadas');
        redirect('view_invoice.php?id=' . $id_invoice);
    }

    // Iniciar transacción
    $db->beginTransaction();

    // Generar CFDI de cancelación
    $cancellation_cfdi = generateCancellationCFDI([
        'issuer' => [
            'rfc' => $invoice['workshop_rfc'],
            'regimen_fiscal' => $invoice['workshop_regimen_fiscal']
        ],
        'invoice' => [
            'uuid' => $invoice['uuid'],
            'rfc_receiver' => $invoice['client_rfc'],
            'total' => $invoice['total_amount']
        ]
    ]);

    // Timbrar CFDI de cancelación con el PAC
    $stamped_cancellation = stampCancellationCFDI($cancellation_cfdi);

    // Guardar archivo XML de cancelación
    $cancellation_xml_path = saveCancellationXML($stamped_cancellation, $invoice['invoice_number']);

    // Actualizar factura
    $stmt = $db->prepare("
        UPDATE invoices 
        SET 
            status = 'cancelled',
            cancellation_xml_path = ?,
            cancelled_at = NOW()
        WHERE id_invoice = ?
    ");
    $stmt->execute([$cancellation_xml_path, $id_invoice]);

    // Confirmar transacción
    $db->commit();

    // Mostrar mensaje de éxito
    showSuccess('Factura cancelada correctamente');

    // Redirigir a la vista de la factura
    redirect('view_invoice.php?id=' . $id_invoice);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $db->rollBack();
    showError('Error al cancelar la factura: ' . $e->getMessage());
    redirect('view_invoice.php?id=' . $id_invoice);
} 