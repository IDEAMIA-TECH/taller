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
$payment_method = $_GET['payment_method'] ?? '';
$group_by = $_GET['group_by'] ?? 'day'; // day, week, month, service

// Construir consulta base para ingresos
$query = "
    SELECT 
        DATE(so.created_at) as date,
        s.name as service_name,
        so.payment_method,
        COUNT(DISTINCT so.id_order) as total_orders,
        SUM(od.subtotal) as total_amount
    FROM service_orders so
    JOIN order_details od ON so.id_order = od.id_order
    JOIN services s ON od.id_service = s.id_service
    WHERE so.id_workshop = ?
    AND so.status = 'completed'
    AND so.created_at BETWEEN ? AND ?
";

$params = [$id_workshop, $start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if ($payment_method) {
    $query .= " AND so.payment_method = ?";
    $params[] = $payment_method;
}

// Agrupar según el parámetro seleccionado
switch ($group_by) {
    case 'week':
        $query .= " GROUP BY YEARWEEK(so.created_at), s.id_service, so.payment_method";
        break;
    case 'month':
        $query .= " GROUP BY DATE_FORMAT(so.created_at, '%Y-%m'), s.id_service, so.payment_method";
        break;
    case 'service':
        $query .= " GROUP BY s.id_service, so.payment_method";
        break;
    default: // day
        $query .= " GROUP BY DATE(so.created_at), s.id_service, so.payment_method";
}

$query .= " ORDER BY date DESC, total_amount DESC";

// Ejecutar consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$revenue_data = $stmt->fetchAll();

// Calcular estadísticas
$total_revenue = array_sum(array_column($revenue_data, 'total_amount'));
$total_orders = array_sum(array_column($revenue_data, 'total_orders'));

// Obtener métodos de pago únicos
$stmt = $db->prepare("
    SELECT DISTINCT payment_method 
    FROM service_orders 
    WHERE id_workshop = ? 
    AND status = 'completed'
");
$stmt->execute([$id_workshop]);
$payment_methods = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Reporte de Ingresos</h1>
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
                    <label for="payment_method" class="form-label">Método de Pago</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <option value="">Todos</option>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?php echo htmlspecialchars($method); ?>" 
                                    <?php echo $payment_method === $method ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($method); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="group_by" class="form-label">Agrupar por</label>
                    <select class="form-select" id="group_by" name="group_by">
                        <option value="day" <?php echo $group_by === 'day' ? 'selected' : ''; ?>>Día</option>
                        <option value="week" <?php echo $group_by === 'week' ? 'selected' : ''; ?>>Semana</option>
                        <option value="month" <?php echo $group_by === 'month' ? 'selected' : ''; ?>>Mes</option>
                        <option value="service" <?php echo $group_by === 'service' ? 'selected' : ''; ?>>Servicio</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="revenue.php" class="btn btn-secondary">Limpiar</a>
                    <button type="button" class="btn btn-success" onclick="exportToExcel()">Exportar a Excel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Ingresos</h5>
                    <p class="card-text display-6">$<?php echo number_format($total_revenue, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Órdenes</h5>
                    <p class="card-text display-6"><?php echo $total_orders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Ticket Promedio</h5>
                    <p class="card-text display-6">$<?php echo number_format($total_orders ? $total_revenue / $total_orders : 0, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico -->
    <div class="card mb-4">
        <div class="card-body">
            <canvas id="revenueChart"></canvas>
        </div>
    </div>

    <!-- Tabla de Ingresos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="revenueTable">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Servicio</th>
                            <th>Método de Pago</th>
                            <th>Órdenes</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($revenue_data as $row): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($row['date'])); ?></td>
                                <td><?php echo htmlspecialchars($row['service_name']); ?></td>
                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                <td><?php echo $row['total_orders']; ?></td>
                                <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Preparar datos para el gráfico
const labels = <?php echo json_encode(array_column($revenue_data, 'date')); ?>;
const amounts = <?php echo json_encode(array_column($revenue_data, 'total_amount')); ?>;

// Crear gráfico
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: labels,
        datasets: [{
            label: 'Ingresos',
            data: amounts,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Ingresos por Período'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

function exportToExcel() {
    // Crear una tabla temporal para la exportación
    const table = document.getElementById('revenueTable');
    const html = table.outerHTML;
    
    // Crear un blob con el contenido
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    
    // Crear un enlace para descargar
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_ingresos_<?php echo date('Y-m-d'); ?>.xls';
    
    // Simular clic en el enlace
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../../includes/footer.php'; ?> 