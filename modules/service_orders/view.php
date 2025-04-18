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
    // Obtener datos de la orden
    $stmt = $db->prepare("
        SELECT 
            so.*,
            c.name as client_name,
            c.phone as client_phone,
            c.email as client_email,
            v.brand,
            v.model,
            v.plates,
            v.year,
            v.color,
            v.vin,
            v.last_mileage,
            u_created.full_name as created_by,
            u_assigned.full_name as assigned_to
        FROM service_orders so
        JOIN clients c ON so.id_client = c.id_client
        JOIN vehicles v ON so.id_vehicle = v.id_vehicle
        JOIN users u_created ON so.id_user_created = u_created.id_user
        LEFT JOIN users u_assigned ON so.id_user_assigned = u_assigned.id_user
        WHERE so.id_order = ? AND so.id_workshop = ?
    ");
    $stmt->execute([$id_order, getCurrentWorkshop()]);
    $order = $stmt->fetch();

    if (!$order) {
        showError('Orden no encontrada');
        redirect('index.php');
    }

    // Obtener detalles de la orden
    $stmt = $db->prepare("
        SELECT od.*, s.name as service_name, s.duration
        FROM order_details od
        JOIN services s ON od.id_service = s.id_service
        WHERE od.id_order = ?
    ");
    $stmt->execute([$id_order]);
    $order_details = $stmt->fetchAll();

    // Obtener historial de cambios de estado
    $stmt = $db->prepare("
        SELECT 
            sh.*,
            u.full_name as changed_by
        FROM service_order_history sh
        JOIN users u ON sh.id_user = u.id_user
        WHERE sh.id_order = ?
        ORDER BY sh.created_at DESC
    ");
    $stmt->execute([$id_order]);
    $history = $stmt->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar los datos de la orden');
    redirect('index.php');
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Orden de Servicio #<?php echo $order['order_number']; ?></h1>
        <div>
            <a href="edit.php?id=<?php echo $id_order; ?>" class="btn btn-outline-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Información Principal -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detalles de la Orden</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Estado:</strong> 
                                <span class="badge bg-<?php 
                                    echo match($order['status']) {
                                        'completed' => 'success',
                                        'in_progress' => 'warning',
                                        'cancelled' => 'danger',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </p>
                            <p><strong>Fecha de Creación:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                            <p><strong>Última Actualización:</strong> <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
                            <p><strong>Creada por:</strong> <?php echo htmlspecialchars($order['created_by']); ?></p>
                            <p><strong>Mecánico Asignado:</strong> <?php echo $order['assigned_to'] ? htmlspecialchars($order['assigned_to']) : 'Sin asignar'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Cliente:</strong> <?php echo htmlspecialchars($order['client_name']); ?></p>
                            <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($order['client_phone']); ?></p>
                            <p><strong>Email:</strong> <?php echo htmlspecialchars($order['client_email']); ?></p>
                            <p><strong>Vehículo:</strong> <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model'] . ' - ' . $order['plates']); ?></p>
                            <p><strong>Kilometraje:</strong> <?php echo number_format($order['last_mileage']); ?> km</p>
                        </div>
                    </div>

                    <?php if ($order['notes']): ?>
                        <div class="mt-3">
                            <h6>Notas:</h6>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Servicios -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Servicios</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Servicio</th>
                                    <th>Precio Unitario</th>
                                    <th>Cantidad</th>
                                    <th>Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order_details as $detail): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($detail['service_name']); ?></td>
                                        <td>$<?php echo number_format($detail['unit_price'], 2); ?></td>
                                        <td><?php echo $detail['quantity']; ?></td>
                                        <td>$<?php echo number_format($detail['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="3" class="text-end">Total:</th>
                                    <th>$<?php echo number_format($order['total_amount'], 2); ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Historial -->
            <?php if (!empty($history)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Historial de Cambios</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($history as $entry): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <div class="d-flex justify-content-between">
                                            <h6 class="mb-1"><?php echo ucfirst($entry['status']); ?></h6>
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($entry['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1"><?php echo htmlspecialchars($entry['notes']); ?></p>
                                        <small class="text-muted">Cambiado por: <?php echo htmlspecialchars($entry['changed_by']); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Acciones y Documentos -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Acciones</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <?php if ($order['status'] === 'open'): ?>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#startOrderModal">
                                <i class="fas fa-play"></i> Iniciar Orden
                            </button>
                        <?php endif; ?>

                        <?php if ($order['status'] === 'in_progress'): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#completeOrderModal">
                                <i class="fas fa-check"></i> Completar Orden
                            </button>
                        <?php endif; ?>

                        <?php if (in_array($order['status'], ['open', 'in_progress'])): ?>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                                <i class="fas fa-times"></i> Cancelar Orden
                            </button>
                        <?php endif; ?>

                        <a href="print.php?id=<?php echo $id_order; ?>" class="btn btn-outline-secondary" target="_blank">
                            <i class="fas fa-print"></i> Imprimir Orden
                        </a>
                    </div>
                </div>
            </div>

            <!-- Documentos -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Documentos</h5>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="print.php?id=<?php echo $id_order; ?>" class="list-group-item list-group-item-action" target="_blank">
                            <i class="fas fa-file-alt"></i> Orden de Servicio
                        </a>
                        <?php if ($order['status'] === 'completed'): ?>
                            <a href="invoice.php?id=<?php echo $id_order; ?>" class="list-group-item list-group-item-action" target="_blank">
                                <i class="fas fa-file-invoice"></i> Factura
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para iniciar orden -->
<div class="modal fade" id="startOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Iniciar Orden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_status.php" method="POST">
                <input type="hidden" name="id_order" value="<?php echo $id_order; ?>">
                <input type="hidden" name="status" value="in_progress">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Iniciar Orden</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para completar orden -->
<div class="modal fade" id="completeOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Completar Orden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_status.php" method="POST">
                <input type="hidden" name="id_order" value="<?php echo $id_order; ?>">
                <input type="hidden" name="status" value="completed">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Completar Orden</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para cancelar orden -->
<div class="modal fade" id="cancelOrderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cancelar Orden</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="update_status.php" method="POST">
                <input type="hidden" name="id_order" value="<?php echo $id_order; ?>">
                <input type="hidden" name="status" value="cancelled">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Motivo de Cancelación</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 20px;
}

.timeline-item {
    position: relative;
    padding-bottom: 20px;
}

.timeline-item:last-child {
    padding-bottom: 0;
}

.timeline-marker {
    position: absolute;
    left: -20px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: #6c757d;
}

.timeline-content {
    padding-left: 10px;
    border-left: 2px solid #dee2e6;
}
</style>

<?php include '../../includes/footer.php'; ?> 