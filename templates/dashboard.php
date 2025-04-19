<?php
require_once '../includes/config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    redirect('templates/login.php');
}

// Obtener estadísticas del taller
try {
    $pdo = $db->getConnection();
    
    // Total de clientes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clients WHERE id_workshop = ?");
    $stmt->execute([getCurrentWorkshop()]);
    $totalClients = $stmt->fetch()['total'];

    // Total de vehículos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM vehicles WHERE id_workshop = ?");
    $stmt->execute([getCurrentWorkshop()]);
    $totalVehicles = $stmt->fetch()['total'];

    // Órdenes abiertas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM service_orders 
                         WHERE id_workshop = ? AND status IN ('open', 'in_progress')");
    $stmt->execute([getCurrentWorkshop()]);
    $openOrders = $stmt->fetch()['total'];

    // Ingresos del mes
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
                         FROM service_orders 
                         WHERE id_workshop = ? 
                         AND MONTH(created_at) = MONTH(CURRENT_DATE())
                         AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute([getCurrentWorkshop()]);
    $monthlyIncome = $stmt->fetch()['total'];

    // Órdenes recientes
    $stmt = $pdo->prepare("SELECT so.*, c.name as client_name, v.brand, v.model 
                         FROM service_orders so
                         JOIN clients c ON so.id_client = c.id_client
                         JOIN vehicles v ON so.id_vehicle = v.id_vehicle
                         WHERE so.id_workshop = ?
                         ORDER BY so.created_at DESC
                         LIMIT 5");
    $stmt->execute([getCurrentWorkshop()]);
    $recentOrders = $stmt->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar el dashboard. Por favor, intente más tarde.');
}
?>

<?php include '../includes/header.php'; ?>

<div class="row">
    <!-- Tarjeta de Clientes -->
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Clientes</h6>
                        <h2 class="card-title mb-0"><?php echo $totalClients; ?></h2>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="fas fa-users fa-2x text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Vehículos -->
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Vehículos</h6>
                        <h2 class="card-title mb-0"><?php echo $totalVehicles; ?></h2>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="fas fa-car fa-2x text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Órdenes Abiertas -->
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Órdenes Abiertas</h6>
                        <h2 class="card-title mb-0"><?php echo $openOrders; ?></h2>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="fas fa-tools fa-2x text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tarjeta de Ingresos -->
    <div class="col-md-3 mb-4">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-subtitle mb-2 text-muted">Ingresos del Mes</h6>
                        <h2 class="card-title mb-0">$<?php echo number_format($monthlyIncome, 2); ?></h2>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="fas fa-dollar-sign fa-2x text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Órdenes Recientes -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-clock"></i> Órdenes Recientes
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th># Orden</th>
                        <th>Cliente</th>
                        <th>Vehículo</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                    <tr>
                        <td><?php echo $order['order_number']; ?></td>
                        <td><?php echo $order['client_name']; ?></td>
                        <td><?php echo $order['brand'] . ' ' . $order['model']; ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $order['status'] === 'open' ? 'warning' : 
                                    ($order['status'] === 'in_progress' ? 'info' : 'success'); 
                            ?>">
                                <?php 
                                echo $order['status'] === 'open' ? 'Abierta' : 
                                    ($order['status'] === 'in_progress' ? 'En Proceso' : 'Finalizada'); 
                                ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <a href="<?php echo APP_URL; ?>/modules/services/order_details.php?id=<?php echo $order['id_order']; ?>" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
