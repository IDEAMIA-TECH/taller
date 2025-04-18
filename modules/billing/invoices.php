<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once 'BillingController.php';

// Verificar permisos
if (!hasPermission('admin')) {
    header('Location: /dashboard.php');
    exit;
}

// Inicializar controlador
$billing = new BillingController($db, $_SESSION['workshop_id']);

// Procesar acciones
if (isset($_GET['action'])) {
    try {
        switch ($_GET['action']) {
            case 'generate':
                if (empty($_GET['order_id'])) {
                    throw new Exception("ID de orden no especificado");
                }
                $invoice_id = $billing->generateInvoice($_GET['order_id']);
                $success = "Factura generada correctamente";
                break;
                
            case 'download':
                if (empty($_GET['id'])) {
                    throw new Exception("ID de factura no especificado");
                }
                $this->downloadInvoice($_GET['id']);
                exit;
                break;
                
            case 'cancel':
                if (empty($_GET['id'])) {
                    throw new Exception("ID de factura no especificado");
                }
                $this->cancelInvoice($_GET['id']);
                $success = "Factura cancelada correctamente";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener facturas
$stmt = $db->prepare("
    SELECT i.*, o.order_number, c.name as client_name
    FROM invoices i
    JOIN service_orders o ON i.id_order = o.id_order
    JOIN clients c ON o.id_client = c.id_client
    WHERE i.id_workshop = ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$_SESSION['workshop_id']]);
$invoices = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="h3 mb-4">Facturas</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Orden</th>
                                    <th>Cliente</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo $invoice['invoice_number']; ?></td>
                                        <td><?php echo $invoice['order_number']; ?></td>
                                        <td><?php echo $invoice['client_name']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></td>
                                        <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            $status_text = '';
                                            switch ($invoice['status']) {
                                                case 'pending':
                                                    $status_class = 'warning';
                                                    $status_text = 'Pendiente';
                                                    break;
                                                case 'paid':
                                                    $status_class = 'success';
                                                    $status_text = 'Pagada';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'danger';
                                                    $status_text = 'Cancelada';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo $status_text; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="?action=download&id=<?php echo $invoice['id_invoice']; ?>" 
                                                   class="btn btn-sm btn-primary" title="Descargar">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                
                                                <?php if ($invoice['status'] == 'pending'): ?>
                                                    <a href="?action=cancel&id=<?php echo $invoice['id_invoice']; ?>" 
                                                       class="btn btn-sm btn-danger" title="Cancelar"
                                                       onclick="return confirm('¿Está seguro de cancelar esta factura?');">
                                                        <i class="fas fa-times"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 