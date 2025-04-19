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
    $sql = "SELECT * FROM services WHERE id_service = '" . addslashes($id_service) . "' AND id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";
    $result = $db->query($sql);
    $service = $result->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        showError('Servicio no encontrado');
        redirect('index.php');
    }

    // Obtener estadísticas de uso
    $sql = "
        SELECT 
            COUNT(od.id_detail) as total_orders,
            SUM(od.quantity) as total_quantity,
            SUM(od.subtotal) as total_revenue,
            AVG(od.unit_price) as average_price
        FROM order_details od
        JOIN service_orders so ON od.id_order = so.id_order
        WHERE od.id_service = '" . addslashes($id_service) . "' AND so.id_workshop = '" . addslashes(getCurrentWorkshop()) . "'
    ";
    $result = $db->query($sql);
    $stats = $result->fetch(PDO::FETCH_ASSOC);

    // Obtener últimas órdenes donde se usó el servicio
    $sql = "
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
        WHERE od.id_service = '" . addslashes($id_service) . "' AND so.id_workshop = '" . addslashes(getCurrentWorkshop()) . "'
        ORDER BY so.created_at DESC
        LIMIT 10
    ";
    $result = $db->query($sql);
    $orders = $result->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    showError('Error al cargar los datos del servicio');
    redirect('index.php');
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<style>
/* Estilos para el sidebar */
.sidebar {
    position: fixed;
    top: 56px; /* Altura del navbar */
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 20px 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    background-color: #343a40;
    width: 250px;
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 56px);
    padding-top: .5rem;
    overflow-x: hidden;
    overflow-y: auto;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #adb5bd;
    padding: .75rem 1rem;
    display: flex;
    align-items: center;
}

.sidebar .nav-link:hover {
    color: #fff;
    background-color: rgba(255, 255, 255, .1);
}

.sidebar .nav-link.active {
    color: #fff;
    background-color: rgba(255, 255, 255, .1);
}

.sidebar .nav-link i {
    margin-right: .5rem;
    width: 20px;
    text-align: center;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: calc(100vh - 56px);
}

@media (max-width: 767.98px) {
    .sidebar {
        position: static;
        height: auto;
        padding-top: 0;
        width: 100%;
    }
    .main-content {
        margin-left: 0;
    }
}
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/templates/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <?php if (hasRole('admin') || hasRole('receptionist') || hasRole('super_admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/clients/">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/vehicles/">
                            <i class="fas fa-car"></i> Vehículos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo APP_URL; ?>/modules/services/">
                            <i class="fas fa-tools"></i> Servicios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/orders/">
                            <i class="fas fa-clipboard-list"></i> Órdenes
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasRole('admin') || hasRole('super_admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/reports/">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/settings/">
                            <i class="fas fa-cog"></i> Configuración
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>

        <!-- Contenido principal -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
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
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 