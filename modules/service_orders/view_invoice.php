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
    redirect('index.php');
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
        redirect('index.php');
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

} catch (PDOException $e) {
    showError('Error al cargar los datos de la factura');
    redirect('index.php');
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Factura #<?php echo htmlspecialchars($invoice['invoice_number']); ?></h1>
        <div>
            <a href="view.php?id=<?php echo $invoice['id_order']; ?>" class="btn btn-outline-secondary me-2">
                <i class="fas fa-arrow-left"></i> Volver a la Orden
            </a>
            <?php if ($invoice['xml_path']): ?>
                <a href="<?php echo htmlspecialchars($invoice['xml_path']); ?>" class="btn btn-outline-primary me-2" download>
                    <i class="fas fa-file-code"></i> Descargar XML
                </a>
            <?php endif; ?>
            <?php if ($invoice['pdf_path']): ?>
                <a href="<?php echo htmlspecialchars($invoice['pdf_path']); ?>" class="btn btn-outline-primary me-2" download>
                    <i class="fas fa-file-pdf"></i> Descargar PDF
                </a>
            <?php endif; ?>
            <?php if ($invoice['status'] === 'paid'): ?>
                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                    <i class="fas fa-times-circle"></i> Cancelar Factura
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detalles de la Factura</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <!-- Emisor -->
                        <div class="col-md-6">
                            <h6>Emisor</h6>
                            <p class="mb-1"><strong>RFC:</strong> <?php echo htmlspecialchars($invoice['workshop_rfc']); ?></p>
                            <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($invoice['workshop_name']); ?></p>
                            <p class="mb-1"><strong>Dirección:</strong> <?php echo htmlspecialchars($invoice['workshop_address']); ?></p>
                            <p class="mb-1"><strong>Teléfono:</strong> <?php echo htmlspecialchars($invoice['workshop_phone']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($invoice['workshop_email']); ?></p>
                            <p class="mb-0"><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars($invoice['workshop_regimen_fiscal']); ?></p>
                        </div>

                        <!-- Receptor -->
                        <div class="col-md-6">
                            <h6>Receptor</h6>
                            <p class="mb-1"><strong>RFC:</strong> <?php echo htmlspecialchars($invoice['client_rfc']); ?></p>
                            <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($invoice['client_name']); ?></p>
                            <p class="mb-1"><strong>Teléfono:</strong> <?php echo htmlspecialchars($invoice['client_phone']); ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($invoice['client_email']); ?></p>
                            <p class="mb-0"><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars($invoice['client_regimen_fiscal']); ?></p>
                        </div>

                        <!-- Datos de la Factura -->
                        <div class="col-md-6">
                            <h6>Datos de la Factura</h6>
                            <p class="mb-1"><strong>Número:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                            <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($invoice['created_at'])); ?></p>
                            <p class="mb-1"><strong>Método de Pago:</strong> <?php echo htmlspecialchars($invoice['payment_method']); ?></p>
                            <p class="mb-1"><strong>Forma de Pago:</strong> <?php echo htmlspecialchars($invoice['payment_form']); ?></p>
                            <p class="mb-0"><strong>Uso del CFDI:</strong> <?php echo htmlspecialchars($invoice['cfdi_use']); ?></p>
                        </div>

                        <!-- Datos del Vehículo -->
                        <div class="col-md-6">
                            <h6>Datos del Vehículo</h6>
                            <p class="mb-1"><strong>Marca/Modelo:</strong> <?php echo htmlspecialchars($invoice['brand'] . ' ' . $invoice['model']); ?></p>
                            <p class="mb-1"><strong>Placas:</strong> <?php echo htmlspecialchars($invoice['plates']); ?></p>
                            <p class="mb-0"><strong>Orden de Servicio:</strong> <?php echo htmlspecialchars($invoice['order_number']); ?></p>
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
                                        <th class="text-end">$<?php echo number_format($invoice['total_amount'], 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <!-- Estado de la Factura -->
                    <div class="mt-4">
                        <h6>Estado de la Factura</h6>
                        <p class="mb-1">
                            <strong>Estado:</strong> 
                            <span class="badge bg-<?php 
                                echo $invoice['status'] === 'paid' ? 'success' : 
                                    ($invoice['status'] === 'pending' ? 'warning' : 'danger'); 
                            ?>">
                                <?php echo ucfirst($invoice['status']); ?>
                            </span>
                        </p>
                        <p class="mb-0">
                            <strong>Última Actualización:</strong> 
                            <?php echo date('d/m/Y H:i', strtotime($invoice['updated_at'])); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Cancelación -->
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Cancelar Factura</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de que desea cancelar esta factura?</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Importante:</strong> Esta acción no se puede deshacer. Se generará un CFDI de cancelación y se actualizará el estado de la factura.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <form method="POST" action="cancel_invoice.php">
                        <input type="hidden" name="id_invoice" value="<?php echo $invoice['id_invoice']; ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times-circle"></i> Confirmar Cancelación
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 