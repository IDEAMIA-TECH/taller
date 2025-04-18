<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    redirect('templates/login.php');
}

// Obtener parámetros de filtro
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? '';
$id_workshop = $_GET['id_workshop'] ?? 0;

// Construir consulta base
$query = "
    SELECT 
        ws.*,
        w.name as workshop_name,
        sp.name as plan_name,
        sp.price as plan_price,
        COUNT(p.id_payment) as total_payments,
        SUM(p.amount) as total_revenue
    FROM workshop_subscriptions ws
    JOIN workshops w ON ws.id_workshop = w.id_workshop
    JOIN subscription_plans sp ON ws.id_plan = sp.id_plan
    LEFT JOIN payments p ON ws.id_subscription = p.id_subscription
    WHERE 1=1
";

$params = [];

// Aplicar filtros
if ($start_date) {
    $query .= " AND ws.start_date >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND ws.end_date <= ?";
    $params[] = $end_date;
}

if ($status) {
    $query .= " AND ws.status = ?";
    $params[] = $status;
}

if ($id_workshop > 0) {
    $query .= " AND ws.id_workshop = ?";
    $params[] = $id_workshop;
}

$query .= " GROUP BY ws.id_subscription ORDER BY ws.start_date DESC";

// Ejecutar consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$subscriptions = $stmt->fetchAll();

// Obtener talleres para el filtro
$stmt = $db->query("SELECT id_workshop, name FROM workshops ORDER BY name");
$workshops = $stmt->fetchAll();

// Incluir el encabezado
include '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Reportes de Suscripciones</h1>
                <div>
                    <button type="button" class="btn btn-primary" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Exportar a Excel
                    </button>
                </div>
            </div>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label for="start_date" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" 
                                value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="end_date" class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" 
                                value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="status" class="form-label">Estado</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Todos</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activo</option>
                                <option value="suspended" <?php echo $status === 'suspended' ? 'selected' : ''; ?>>Suspendido</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="id_workshop" class="form-label">Taller</label>
                            <select class="form-select" id="id_workshop" name="id_workshop">
                                <option value="">Todos</option>
                                <?php foreach ($workshops as $workshop): ?>
                                    <option value="<?php echo $workshop['id_workshop']; ?>" 
                                        <?php echo $id_workshop == $workshop['id_workshop'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($workshop['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrar
                            </button>
                            <a href="reports.php" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Limpiar
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Resumen -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Suscripciones</h5>
                                    <p class="card-text h2"><?php echo count($subscriptions); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Suscripciones Activas</h5>
                                    <p class="card-text h2">
                                        <?php echo count(array_filter($subscriptions, fn($s) => $s['status'] === 'active')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Suscripciones Suspendidas</h5>
                                    <p class="card-text h2">
                                        <?php echo count(array_filter($subscriptions, fn($s) => $s['status'] === 'suspended')); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Ingresos Totales</h5>
                                    <p class="card-text h2">
                                        $<?php echo number_format(array_sum(array_column($subscriptions, 'total_revenue')), 2); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Suscripciones -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="subscriptionsTable">
                            <thead>
                                <tr>
                                    <th>Taller</th>
                                    <th>Plan</th>
                                    <th>Estado</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Fin</th>
                                    <th>Pagos</th>
                                    <th>Ingresos</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $subscription): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($subscription['workshop_name']); ?></td>
                                        <td><?php echo htmlspecialchars($subscription['plan_name']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($subscription['status']) {
                                                    'active' => 'success',
                                                    'suspended' => 'warning',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($subscription['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d/m/Y', strtotime($subscription['start_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($subscription['end_date'])); ?></td>
                                        <td><?php echo $subscription['total_payments']; ?></td>
                                        <td>$<?php echo number_format($subscription['total_revenue'], 2); ?></td>
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

<script>
function exportToExcel() {
    const table = document.getElementById('subscriptionsTable');
    const html = table.outerHTML;
    const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
    const link = document.createElement('a');
    link.download = 'reporte_suscripciones.xls';
    link.href = url;
    link.click();
}
</script>

<?php include '../../../includes/footer.php'; ?> 