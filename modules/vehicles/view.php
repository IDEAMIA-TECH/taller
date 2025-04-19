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

// Obtener el ID del vehículo
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    showError('Vehículo no válido');
    redirect('index.php');
}

try {
    // Obtener información del vehículo
    $query = "SELECT v.*, c.name as client_name, c.phone as client_phone, c.email as client_email
              FROM vehicles v 
              JOIN clients c ON v.id_client = c.id_client 
              WHERE v.id_vehicle = '" . addslashes($id) . "' 
              AND v.id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";
    
    $result = $db->query($query);
    $vehicle = $result->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        showError('Vehículo no encontrado');
        redirect('index.php');
    }

    // Obtener historial de servicios
    $historyQuery = "SELECT so.*, u.full_name as mechanic_name 
                    FROM service_orders so 
                    LEFT JOIN users u ON so.id_user_assigned = u.id_user 
                    WHERE so.id_vehicle = '" . addslashes($id) . "' 
                    AND so.id_workshop = '" . addslashes(getCurrentWorkshop()) . "'
                    ORDER BY so.created_at DESC";
    
    $result = $db->query($historyQuery);
    $serviceHistory = $result->fetchAll(PDO::FETCH_ASSOC);

    // Obtener recordatorios pendientes
    $remindersQuery = "SELECT r.*, s.name as service_name 
                      FROM reminders r 
                      JOIN services s ON r.id_service = s.id_service 
                      WHERE r.id_vehicle = '" . addslashes($id) . "' 
                      AND r.status = 'pending' 
                      ORDER BY r.due_date ASC";
    
    $result = $db->query($remindersQuery);
    $reminders = $result->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error en view.php: " . $e->getMessage());
    showError('Error al cargar la información del vehículo. Por favor, intente más tarde.');
    redirect('index.php');
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Detalles del Vehículo</h1>
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
        <!-- Información del Vehículo -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información del Vehículo</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Marca:</strong> <?php echo htmlspecialchars($vehicle['brand']); ?></p>
                            <p><strong>Modelo:</strong> <?php echo htmlspecialchars($vehicle['model']); ?></p>
                            <p><strong>Año:</strong> <?php echo $vehicle['year']; ?></p>
                            <p><strong>Color:</strong> <?php echo htmlspecialchars($vehicle['color']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Placas:</strong> <?php echo htmlspecialchars($vehicle['plates']); ?></p>
                            <p><strong>Número de Serie (VIN):</strong> <?php echo htmlspecialchars($vehicle['vin']); ?></p>
                            <p><strong>Último Kilometraje:</strong> <?php echo number_format($vehicle['last_mileage']); ?> km</p>
                            <p><strong>Fecha de Registro:</strong> <?php echo date('d/m/Y', strtotime($vehicle['created_at'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Información del Cliente -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($vehicle['client_name']); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($vehicle['client_phone']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($vehicle['client_email']); ?></p>
                    <a href="../clients/view.php?id=<?php echo $vehicle['id_client']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-user"></i> Ver Cliente
                    </a>
                </div>
            </div>
        </div>

        <!-- Historial de Servicios -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Historial de Servicios</h5>
                    <a href="../orders/create.php?vehicle_id=<?php echo $id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Nueva Orden
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($serviceHistory)): ?>
                        <p class="text-muted">No hay servicios registrados para este vehículo.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Fecha</th>
                                        <th>Mecánico</th>
                                        <th>Estado</th>
                                        <th>Total</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($serviceHistory as $order): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($order['mechanic_name'] ?? 'No asignado'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo match($order['status']) {
                                                        'open' => 'warning',
                                                        'in_progress' => 'info',
                                                        'completed' => 'success',
                                                        'cancelled' => 'danger',
                                                        default => 'secondary'
                                                    };
                                                ?>">
                                                    <?php echo ucfirst($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td>
                                                <a href="../orders/view.php?id=<?php echo $order['id_order']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <a href="../orders/index.php?vehicle_id=<?php echo $id; ?>" class="btn btn-outline-primary">
                                Ver Historial Completo
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recordatorios de Mantenimiento -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Recordatorios de Mantenimiento</h5>
                    <a href="../reminders/create.php?vehicle_id=<?php echo $id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Nuevo Recordatorio
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($reminders)): ?>
                        <p class="text-muted">No hay recordatorios pendientes para este vehículo.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Servicio</th>
                                        <th>Tipo</th>
                                        <th>Fecha/Km</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reminders as $reminder): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($reminder['service_name']); ?></td>
                                            <td><?php echo ucfirst($reminder['reminder_type']); ?></td>
                                            <td>
                                                <?php if ($reminder['reminder_type'] === 'date'): ?>
                                                    <?php echo date('d/m/Y', strtotime($reminder['due_date'])); ?>
                                                <?php else: ?>
                                                    <?php echo number_format($reminder['due_mileage']); ?> km
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning">Pendiente</span>
                                            </td>
                                            <td>
                                                <a href="../reminders/edit.php?id=<?php echo $reminder['id_reminder']; ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
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