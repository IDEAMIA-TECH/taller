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

// Obtener ID del servicio
$id_service = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_service) {
    showError('ID de servicio no válido');
    redirect('index.php');
}

try {
    // Obtener datos del servicio
    $stmt = $db->prepare("SELECT * FROM services WHERE id_service = ? AND id_workshop = ?");
    $stmt->execute([$id_service, getCurrentWorkshop()]);
    $service = $stmt->fetch();

    if (!$service) {
        showError('Servicio no encontrado');
        redirect('index.php');
    }

    // Obtener estadísticas de uso
    $stmt = $db->prepare("
        SELECT 
            COUNT(od.id_detail) as total_orders,
            SUM(od.quantity) as total_quantity,
            SUM(od.subtotal) as total_revenue,
            AVG(od.unit_price) as average_price
        FROM order_details od
        JOIN service_orders so ON od.id_order = so.id_order
        WHERE od.id_service = ? AND so.id_workshop = ?
    ");
    $stmt->execute([$id_service, getCurrentWorkshop()]);
    $stats = $stmt->fetch();

    // Obtener últimas órdenes donde se usó el servicio
    $stmt = $db->prepare("
        SELECT 
            so.order_number,
            so.created_at,
            so.status,
            v.brand,
            v.model,
            v.plates,
            c.name as client_name,
            od.quantity,
            od.unit_price,
            od.subtotal
        FROM order_details od
        JOIN service_orders so ON od.id_order = so.id_order
        JOIN vehicles v ON so.id_vehicle = v.id_vehicle
        JOIN clients c ON so.id_client = c.id_client
        WHERE od.id_service = ? AND so.id_workshop = ?
        ORDER BY so.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$id_service, getCurrentWorkshop()]);
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar los datos del servicio');
    redirect('index.php');
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Detalles del Servicio</h1>
        <div>
            <a href="edit.php?id=<?php echo $id_service; ?>" class="btn btn-outline-primary me-2">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Información del Servicio -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información General</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($service['name']); ?></dd>

                        <dt class="col-sm-4">Precio</dt>
                        <dd class="col-sm-8">$<?php echo number_format($service['price'], 2); ?></dd>

                        <dt class="col-sm-4">Duración</dt>
                        <dd class="col-sm-8"><?php echo $service['duration']; ?> minutos</dd>

                        <dt class="col-sm-4">Estado</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?php echo $service['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($service['status']); ?>
                            </span>
                        </dd>

                        <dt class="col-sm-4">Descripción</dt>
                        <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($service['description'])); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Estadísticas</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-6">Total de Órdenes</dt>
                        <dd class="col-sm-6"><?php echo $stats['total_orders']; ?></dd>

                        <dt class="col-sm-6">Total de Veces</dt>
                        <dd class="col-sm-6"><?php echo $stats['total_quantity']; ?></dd>

                        <dt class="col-sm-6">Ingresos Totales</dt>
                        <dd class="col-sm-6">$<?php echo number_format($stats['total_revenue'], 2); ?></dd>

                        <dt class="col-sm-6">Precio Promedio</dt>
                        <dd class="col-sm-6">$<?php echo number_format($stats['average_price'], 2); ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Historial de Órdenes -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Últimas Órdenes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <p class="text-muted">No hay órdenes registradas para este servicio.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Vehículo</th>
                                        <th>Cantidad</th>
                                        <th>Precio</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['client_name']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($order['plates']); ?></small>
                                            </td>
                                            <td><?php echo $order['quantity']; ?></td>
                                            <td>$<?php echo number_format($order['unit_price'], 2); ?></td>
                                            <td>$<?php echo number_format($order['subtotal'], 2); ?></td>
                                            <td>
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
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 