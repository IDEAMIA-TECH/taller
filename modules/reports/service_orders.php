<?php
require_once '../../includes/config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    redirect('auth/login.php');
}

// Obtener permisos del usuario
$user_role = $_SESSION['role'];
$id_workshop = $_SESSION['id_workshop'];

// Obtener parámetros de filtro
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';
$id_mechanic = $_GET['id_mechanic'] ?? '';

// Construir consulta base
$query = "
    SELECT 
        so.*,
        c.name as client_name,
        v.brand,
        v.model,
        v.plates,
        u.full_name as mechanic_name,
        COUNT(od.id_detail) as total_services,
        SUM(od.subtotal) as total_amount
    FROM service_orders so
    JOIN clients c ON so.id_client = c.id_client
    JOIN vehicles v ON so.id_vehicle = v.id_vehicle
    LEFT JOIN users u ON so.id_user_assigned = u.id_user
    LEFT JOIN order_details od ON so.id_order = od.id_order
    WHERE so.id_workshop = ?
    AND so.created_at BETWEEN ? AND ?
";

$params = [$id_workshop, $start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if ($status) {
    $query .= " AND so.status = ?";
    $params[] = $status;
}

if ($id_mechanic) {
    $query .= " AND so.id_user_assigned = ?";
    $params[] = $id_mechanic;
}

$query .= " GROUP BY so.id_order ORDER BY so.created_at DESC";

// Ejecutar consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Obtener mecánicos para el filtro
$stmt = $db->prepare("
    SELECT id_user, full_name 
    FROM users 
    WHERE id_workshop = ? AND role = 'mechanic'
");
$stmt->execute([$id_workshop]);
$mechanics = $stmt->fetchAll();

// Calcular estadísticas
$total_orders = count($orders);
$total_amount = array_sum(array_column($orders, 'total_amount'));
$status_counts = array_count_values(array_column($orders, 'status'));

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Reporte de Órdenes de Servicio</h1>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Abierta</option>
                        <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>En Proceso</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completada</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="id_mechanic" class="form-label">Mecánico</label>
                    <select class="form-select" id="id_mechanic" name="id_mechanic">
                        <option value="">Todos</option>
                        <?php foreach ($mechanics as $mechanic): ?>
                            <option value="<?php echo $mechanic['id_user']; ?>" 
                                    <?php echo $id_mechanic == $mechanic['id_user'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($mechanic['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="service_orders.php" class="btn btn-secondary">Limpiar</a>
                    <button type="button" class="btn btn-success" onclick="exportToExcel()">Exportar a Excel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Órdenes</h5>
                    <p class="card-text display-6"><?php echo $total_orders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Ingresos</h5>
                    <p class="card-text display-6">$<?php echo number_format($total_amount, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Órdenes Completadas</h5>
                    <p class="card-text display-6"><?php echo $status_counts['completed'] ?? 0; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Órdenes en Proceso</h5>
                    <p class="card-text display-6"><?php echo $status_counts['in_progress'] ?? 0; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Órdenes -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="ordersTable">
                    <thead>
                        <tr>
                            <th># Orden</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Vehículo</th>
                            <th>Mecánico</th>
                            <th>Servicios</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
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
                                <td><?php echo htmlspecialchars($order['mechanic_name'] ?? 'No asignado'); ?></td>
                                <td><?php echo $order['total_services']; ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $status_class = [
                                        'open' => 'warning',
                                        'in_progress' => 'info',
                                        'completed' => 'success',
                                        'cancelled' => 'danger'
                                    ][$order['status']] ?? 'secondary';
                                    $status_text = [
                                        'open' => 'Abierta',
                                        'in_progress' => 'En Proceso',
                                        'completed' => 'Completada',
                                        'cancelled' => 'Cancelada'
                                    ][$order['status']] ?? $order['status'];
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="../service_orders/view.php?id=<?php echo $order['id_order']; ?>" 
                                       class="btn btn-sm btn-primary" title="Ver detalles">
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
</div>

<script>
function exportToExcel() {
    // Crear una tabla temporal para la exportación
    const table = document.getElementById('ordersTable');
    const html = table.outerHTML;
    
    // Crear un blob con el contenido
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    
    // Crear un enlace para descargar
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_ordenes_<?php echo date('Y-m-d'); ?>.xls';
    
    // Simular clic en el enlace
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../../includes/footer.php'; ?> 