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
$reminder_type = $_GET['reminder_type'] ?? '';
$id_vehicle = $_GET['id_vehicle'] ?? '';

// Construir consulta base para recordatorios
$query = "
    SELECT 
        r.*,
        v.brand,
        v.model,
        v.year,
        v.plates,
        c.name as client_name,
        s.name as service_name,
        s.description as service_description,
        DATEDIFF(r.due_date, CURDATE()) as days_remaining,
        CASE 
            WHEN r.reminder_type = 'mileage' THEN 
                v.last_mileage - r.due_mileage
            ELSE 
                DATEDIFF(r.due_date, CURDATE())
        END as remaining_value
    FROM reminders r
    JOIN vehicles v ON r.id_vehicle = v.id_vehicle
    JOIN clients c ON v.id_client = c.id_client
    JOIN services s ON r.id_service = s.id_service
    WHERE v.id_workshop = ?
    AND (
        (r.reminder_type = 'date' AND r.due_date BETWEEN ? AND ?)
        OR 
        (r.reminder_type = 'mileage' AND r.due_mileage <= v.last_mileage + 1000)
    )
";

$params = [$id_workshop, $start_date, $end_date];

if ($status) {
    $query .= " AND r.status = ?";
    $params[] = $status;
}

if ($reminder_type) {
    $query .= " AND r.reminder_type = ?";
    $params[] = $reminder_type;
}

if ($id_vehicle) {
    $query .= " AND r.id_vehicle = ?";
    $params[] = $id_vehicle;
}

$query .= " ORDER BY r.due_date ASC, r.due_mileage ASC";

// Ejecutar consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$reminders = $stmt->fetchAll();

// Obtener vehículos para el filtro
$stmt = $db->prepare("
    SELECT v.id_vehicle, v.brand, v.model, v.year, v.plates, c.name as client_name
    FROM vehicles v
    JOIN clients c ON v.id_client = c.id_client
    WHERE v.id_workshop = ?
    ORDER BY c.name, v.brand, v.model
");
$stmt->execute([$id_workshop]);
$vehicles = $stmt->fetchAll();

// Calcular estadísticas
$total_reminders = count($reminders);
$pending_reminders = array_filter($reminders, function($r) { return $r['status'] === 'pending'; });
$completed_reminders = array_filter($reminders, function($r) { return $r['status'] === 'completed'; });
$overdue_reminders = array_filter($reminders, function($r) { 
    return $r['status'] === 'pending' && (
        ($r['reminder_type'] === 'date' && $r['days_remaining'] < 0) ||
        ($r['reminder_type'] === 'mileage' && $r['remaining_value'] < 0)
    );
});

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Reporte de Mantenimiento</h1>
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
                <div class="col-md-2">
                    <label for="status" class="form-label">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completado</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="reminder_type" class="form-label">Tipo</label>
                    <select class="form-select" id="reminder_type" name="reminder_type">
                        <option value="">Todos</option>
                        <option value="date" <?php echo $reminder_type === 'date' ? 'selected' : ''; ?>>Por Fecha</option>
                        <option value="mileage" <?php echo $reminder_type === 'mileage' ? 'selected' : ''; ?>>Por Kilometraje</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="id_vehicle" class="form-label">Vehículo</label>
                    <select class="form-select" id="id_vehicle" name="id_vehicle">
                        <option value="">Todos</option>
                        <?php foreach ($vehicles as $vehicle): ?>
                            <option value="<?php echo $vehicle['id_vehicle']; ?>" 
                                    <?php echo $id_vehicle == $vehicle['id_vehicle'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vehicle['client_name'] . ' - ' . 
                                    $vehicle['brand'] . ' ' . $vehicle['model'] . ' (' . $vehicle['plates'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="maintenance.php" class="btn btn-secondary">Limpiar</a>
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
                    <h5 class="card-title">Total Recordatorios</h5>
                    <p class="card-text display-6"><?php echo $total_reminders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Pendientes</h5>
                    <p class="card-text display-6"><?php echo count($pending_reminders); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Completados</h5>
                    <p class="card-text display-6"><?php echo count($completed_reminders); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Vencidos</h5>
                    <p class="card-text display-6"><?php echo count($overdue_reminders); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas de Vencidos -->
    <?php if (count($overdue_reminders) > 0): ?>
    <div class="alert alert-danger mb-4">
        <h5 class="alert-heading">¡Atención! Recordatorios vencidos</h5>
        <ul class="mb-0">
            <?php foreach ($overdue_reminders as $reminder): ?>
                <li>
                    <?php echo htmlspecialchars($reminder['client_name'] . ' - ' . 
                        $reminder['brand'] . ' ' . $reminder['model'] . ' (' . $reminder['plates'] . ')'); ?>:
                    <?php echo htmlspecialchars($reminder['service_name']); ?>
                    <?php if ($reminder['reminder_type'] === 'date'): ?>
                        (Vencido hace <?php echo abs($reminder['days_remaining']); ?> días)
                    <?php else: ?>
                        (Excedido en <?php echo abs($reminder['remaining_value']); ?> km)
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Tabla de Recordatorios -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="maintenanceTable">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Vehículo</th>
                            <th>Servicio</th>
                            <th>Tipo</th>
                            <th>Fecha/Km</th>
                            <th>Restante</th>
                            <th>Estado</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reminders as $reminder): ?>
                            <tr class="<?php 
                                echo $reminder['status'] === 'completed' ? 'table-success' : 
                                    ($reminder['status'] === 'cancelled' ? 'table-secondary' : 
                                    (($reminder['reminder_type'] === 'date' && $reminder['days_remaining'] < 0) || 
                                    ($reminder['reminder_type'] === 'mileage' && $reminder['remaining_value'] < 0) ? 
                                    'table-danger' : ''));
                            ?>">
                                <td><?php echo htmlspecialchars($reminder['client_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($reminder['brand'] . ' ' . $reminder['model']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($reminder['plates']); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($reminder['service_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($reminder['service_description']); ?></small>
                                </td>
                                <td>
                                    <?php echo $reminder['reminder_type'] === 'date' ? 'Fecha' : 'Kilometraje'; ?>
                                </td>
                                <td>
                                    <?php if ($reminder['reminder_type'] === 'date'): ?>
                                        <?php echo date('d/m/Y', strtotime($reminder['due_date'])); ?>
                                    <?php else: ?>
                                        <?php echo number_format($reminder['due_mileage']); ?> km
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($reminder['reminder_type'] === 'date'): ?>
                                        <?php echo $reminder['days_remaining']; ?> días
                                    <?php else: ?>
                                        <?php echo $reminder['remaining_value']; ?> km
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php 
                                        echo $reminder['status'] === 'completed' ? 'success' : 
                                            ($reminder['status'] === 'cancelled' ? 'secondary' : 
                                            (($reminder['reminder_type'] === 'date' && $reminder['days_remaining'] < 0) || 
                                            ($reminder['reminder_type'] === 'mileage' && $reminder['remaining_value'] < 0) ? 
                                            'danger' : 'warning'));
                                    ?>">
                                        <?php 
                                        echo $reminder['status'] === 'completed' ? 'Completado' : 
                                            ($reminder['status'] === 'cancelled' ? 'Cancelado' : 
                                            (($reminder['reminder_type'] === 'date' && $reminder['days_remaining'] < 0) || 
                                            ($reminder['reminder_type'] === 'mileage' && $reminder['remaining_value'] < 0) ? 
                                            'Vencido' : 'Pendiente'));
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($reminder['notes']); ?></td>
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
    const table = document.getElementById('maintenanceTable');
    const html = table.outerHTML;
    
    // Crear un blob con el contenido
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    
    // Crear un enlace para descargar
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_mantenimiento_<?php echo date('Y-m-d'); ?>.xls';
    
    // Simular clic en el enlace
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../../includes/footer.php'; ?> 