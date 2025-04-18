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

// Obtener y validar el ID de la orden
$id_order = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_order) {
    showError('ID de orden no válido');
    redirect('index.php');
}

try {
    // Obtener datos del taller
    $stmt = $db->prepare("
        SELECT 
            w.name as workshop_name,
            w.address,
            w.phone,
            w.email,
            w.rfc,
            w.logo_path,
            w.regimen_fiscal
        FROM workshops w
        WHERE w.id_workshop = ?
    ");
    $stmt->execute([getCurrentWorkshop()]);
    $workshop = $stmt->fetch();

    // Obtener datos de la orden
    $stmt = $db->prepare("
        SELECT 
            so.*,
            c.name as client_name,
            c.phone as client_phone,
            c.email as client_email,
            c.rfc as client_rfc,
            c.regimen_fiscal as client_regimen_fiscal,
            c.cfdi_use as client_cfdi_use,
            v.brand,
            v.model,
            v.plates
        FROM service_orders so
        JOIN clients c ON so.id_client = c.id_client
        JOIN vehicles v ON so.id_vehicle = v.id_vehicle
        WHERE so.id_order = ? AND so.id_workshop = ?
    ");
    $stmt->execute([$id_order, getCurrentWorkshop()]);
    $order = $stmt->fetch();

    if (!$order) {
        showError('Orden no encontrada');
        redirect('index.php');
    }

    // Verificar que la orden esté completada
    if ($order['status'] !== 'completed') {
        showError('La orden debe estar completada para generar la factura');
        redirect('view.php?id=' . $id_order);
    }

    // Verificar que el cliente tenga RFC
    if (!$order['client_rfc']) {
        showError('El cliente no tiene RFC registrado');
        redirect('view.php?id=' . $id_order);
    }

    // Obtener detalles de la orden
    $stmt = $db->prepare("
        SELECT od.*, s.name as service_name
        FROM order_details od
        JOIN services s ON od.id_service = s.id_service
        WHERE od.id_order = ?
    ");
    $stmt->execute([$id_order]);
    $order_details = $stmt->fetchAll();

    // Verificar si ya existe una factura para esta orden
    $stmt = $db->prepare("
        SELECT * FROM invoices 
        WHERE id_order = ?
    ");
    $stmt->execute([$id_order]);
    $existing_invoice = $stmt->fetch();

    if ($existing_invoice) {
        // Si la factura ya existe, redirigir a la vista de la factura
        redirect('view_invoice.php?id=' . $existing_invoice['id_invoice']);
    }

} catch (PDOException $e) {
    showError('Error al cargar los datos de la orden');
    redirect('index.php');
}

// Procesar el formulario de facturación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Obtener y validar datos del formulario
        $payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : '';
        $payment_form = isset($_POST['payment_form']) ? $_POST['payment_form'] : '';
        $cfdi_use = isset($_POST['cfdi_use']) ? $_POST['cfdi_use'] : '';

        // Validar datos requeridos
        if (!$payment_method || !$payment_form || !$cfdi_use) {
            throw new Exception('Todos los campos son requeridos');
        }

        // Iniciar transacción
        $db->beginTransaction();

        // Generar número de factura
        $invoice_number = generateInvoiceNumber($db, getCurrentWorkshop());

        // Insertar factura
        $stmt = $db->prepare("
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
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $id_order,
            getCurrentWorkshop(),
            $invoice_number,
            $workshop['rfc'],
            $order['client_rfc'],
            $order['total_amount'],
            $payment_method,
            $payment_form,
            $cfdi_use
        ]);

        $id_invoice = $db->lastInsertId();

        // Generar CFDI
        $cfdi = generateCFDI([
            'issuer' => [
                'rfc' => $workshop['rfc'],
                'name' => $workshop['workshop_name'],
                'regimen_fiscal' => $workshop['regimen_fiscal']
            ],
            'receiver' => [
                'rfc' => $order['client_rfc'],
                'name' => $order['client_name'],
                'regimen_fiscal' => $order['client_regimen_fiscal'],
                'cfdi_use' => $cfdi_use
            ],
            'invoice' => [
                'number' => $invoice_number,
                'date' => date('Y-m-d\TH:i:s'),
                'total' => $order['total_amount'],
                'payment_method' => $payment_method,
                'payment_form' => $payment_form
            ],
            'items' => array_map(function($detail) {
                return [
                    'description' => $detail['service_name'],
                    'quantity' => $detail['quantity'],
                    'unit_price' => $detail['unit_price'],
                    'subtotal' => $detail['subtotal']
                ];
            }, $order_details)
        ]);

        // Timbrar CFDI con el PAC
        $stamped_cfdi = stampCFDI($cfdi);

        // Guardar archivos XML y PDF
        $xml_path = saveXML($stamped_cfdi, $invoice_number);
        $pdf_path = generatePDF($stamped_cfdi, $invoice_number);

        // Actualizar factura con rutas de archivos
        $stmt = $db->prepare("
            UPDATE invoices 
            SET xml_path = ?, pdf_path = ?, status = 'paid'
            WHERE id_invoice = ?
        ");
        $stmt->execute([$xml_path, $pdf_path, $id_invoice]);

        // Confirmar transacción
        $db->commit();

        // Mostrar mensaje de éxito
        showSuccess('Factura generada correctamente');

        // Redirigir a la vista de la factura
        redirect('view_invoice.php?id=' . $id_invoice);

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $db->rollBack();
        showError('Error al generar la factura: ' . $e->getMessage());
    }
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Generar Factura</h1>
        <a href="view.php?id=<?php echo $id_order; ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Datos de Facturación</h5>
                </div>
                <div class="card-body">
                    <form method="POST" id="invoiceForm">
                        <div class="row g-3">
                            <!-- Emisor -->
                            <div class="col-md-6">
                                <h6>Emisor</h6>
                                <p class="mb-1"><strong>RFC:</strong> <?php echo htmlspecialchars($workshop['rfc']); ?></p>
                                <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($workshop['workshop_name']); ?></p>
                                <p class="mb-0"><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars($workshop['regimen_fiscal']); ?></p>
                            </div>

                            <!-- Receptor -->
                            <div class="col-md-6">
                                <h6>Receptor</h6>
                                <p class="mb-1"><strong>RFC:</strong> <?php echo htmlspecialchars($order['client_rfc']); ?></p>
                                <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($order['client_name']); ?></p>
                                <p class="mb-0"><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars($order['client_regimen_fiscal']); ?></p>
                            </div>

                            <!-- Datos de Facturación -->
                            <div class="col-md-6">
                                <label for="payment_method" class="form-label">Método de Pago *</label>
                                <select class="form-select" id="payment_method" name="payment_method" required>
                                    <option value="">Seleccione un método</option>
                                    <option value="PUE">Pago en una sola exhibición</option>
                                    <option value="PPD">Pago en parcialidades o diferido</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="payment_form" class="form-label">Forma de Pago *</label>
                                <select class="form-select" id="payment_form" name="payment_form" required>
                                    <option value="">Seleccione una forma</option>
                                    <option value="01">Efectivo</option>
                                    <option value="02">Cheque nominativo</option>
                                    <option value="03">Transferencia electrónica de fondos</option>
                                    <option value="04">Tarjeta de crédito</option>
                                    <option value="28">Tarjeta de débito</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="cfdi_use" class="form-label">Uso del CFDI *</label>
                                <select class="form-select" id="cfdi_use" name="cfdi_use" required>
                                    <option value="">Seleccione un uso</option>
                                    <option value="G03">Gastos en general</option>
                                    <option value="P01">Por definir</option>
                                </select>
                            </div>
                        </div>

                        <!-- Servicios -->
                        <div class="mt-4">
                            <h6>Servicios</h6>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Servicio</th>
                                            <th class="text-end">Precio Unitario</th>
                                            <th class="text-center">Cantidad</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_details as $detail): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($detail['service_name']); ?></td>
                                                <td class="text-end">$<?php echo number_format($detail['unit_price'], 2); ?></td>
                                                <td class="text-center"><?php echo $detail['quantity']; ?></td>
                                                <td class="text-end">$<?php echo number_format($detail['subtotal'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="3" class="text-end">Total:</th>
                                            <th class="text-end">$<?php echo number_format($order['total_amount'], 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-file-invoice"></i> Generar Factura
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('invoiceForm').addEventListener('submit', function(e) {
    if (!confirm('¿Está seguro de generar la factura? Esta acción no se puede deshacer.')) {
        e.preventDefault();
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 