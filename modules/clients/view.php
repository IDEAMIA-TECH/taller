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

$client = null;
$vehicles = [];
$orders = [];

// Obtener el ID del cliente
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    showError('Cliente no válido');
    redirect('index.php');
}

try {
    // Obtener datos del cliente
    $stmt = $db->prepare("SELECT * FROM clients WHERE id_client = ? AND id_workshop = ?");
    $stmt->execute([$id, getCurrentWorkshop()]);
    $client = $stmt->fetch();

    if (!$client) {
        showError('Cliente no encontrado');
        redirect('index.php');
    }

    // Obtener vehículos del cliente
    $stmt = $db->prepare("SELECT * FROM vehicles WHERE id_client = ? AND id_workshop = ? ORDER BY created_at DESC");
    $stmt->execute([$id, getCurrentWorkshop()]);
    $vehicles = $stmt->fetchAll();

    // Obtener órdenes de servicio recientes
    $stmt = $db->prepare("SELECT so.*, v.brand, v.model, v.plates 
                         FROM service_orders so
                         JOIN vehicles v ON so.id_vehicle = v.id_vehicle
                         WHERE so.id_client = ? AND so.id_workshop = ?
                         ORDER BY so.created_at DESC
                         LIMIT 5");
    $stmt->execute([$id, getCurrentWorkshop()]);
    $orders = $stmt->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar los datos del cliente');
    redirect('index.php');
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Detalles del Cliente</h1>
        <div>
            <a href="edit.php?id=<?php echo $id; ?>" class="btn btn-primary">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <div class="row">
        <!-- Información del Cliente -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Nombre</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($client['name']); ?></dd>

                        <dt class="col-sm-4">Teléfono</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($client['phone']); ?></dd>

                        <dt class="col-sm-4">Correo</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($client['email']); ?></dd>

                        <dt class="col-sm-4">RFC</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($client['rfc']); ?></dd>

                        <dt class="col-sm-4">Dirección</dt>
                        <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($client['address'])); ?></dd>

                        <dt class="col-sm-4">Registro</dt>
                        <dd class="col-sm-8"><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Vehículos del Cliente -->
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Vehículos</h5>
                    <a href="../vehicles/create.php?client_id=<?php echo $id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Nuevo Vehículo
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($vehicles)): ?>
                        <p class="text-muted mb-0">No hay vehículos registrados.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Marca</th>
                                        <th>Modelo</th>
                                        <th>Año</th>
                                        <th>Placas</th>
                                        <th>Color</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($vehicles as $vehicle): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($vehicle['brand']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                                            <td><?php echo $vehicle['year']; ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['plates']); ?></td>
                                            <td><?php echo htmlspecialchars($vehicle['color']); ?></td>
                                            <td>
                                                <a href="../vehicles/view.php?id=<?php echo $vehicle['id_vehicle']; ?>" 
                                                   class="btn btn-sm btn-info" title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Órdenes de Servicio Recientes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Órdenes de Servicio Recientes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <p class="text-muted mb-0">No hay órdenes de servicio registradas.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th># Orden</th>
                                        <th>Vehículo</th>
                                        <th>Estado</th>
                                        <th>Fecha</th>
                                        <th>Total</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                            <td><?php echo htmlspecialchars($order['brand'] . ' ' . $order['model'] . ' (' . $order['plates'] . ')'); ?></td>
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
                                                <a href="../services/order_details.php?id=<?php echo $order['id_order']; ?>" 
                                                   class="btn btn-sm btn-info" title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
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