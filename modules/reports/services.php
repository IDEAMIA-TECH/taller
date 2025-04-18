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
$id_mechanic = $_GET['id_mechanic'] ?? '';
$min_quantity = $_GET['min_quantity'] ?? '';

// Construir consulta base para servicios
$query = "
    SELECT 
        s.*,
        COUNT(DISTINCT od.id_detail) as total_orders,
        SUM(od.quantity) as total_quantity,
        SUM(od.subtotal) as total_revenue,
        AVG(od.unit_price) as avg_price,
        u.full_name as mechanic_name,
        COUNT(DISTINCT CASE WHEN so.status = 'completed' THEN so.id_order END) as completed_orders,
        COUNT(DISTINCT CASE WHEN so.status = 'cancelled' THEN so.id_order END) as cancelled_orders
    FROM services s
    LEFT JOIN order_details od ON s.id_service = od.id_service
    LEFT JOIN service_orders so ON od.id_order = so.id_order
    LEFT JOIN users u ON so.id_user_assigned = u.id_user
    WHERE s.id_workshop = ?
    AND s.status = 'active'
    AND so.created_at BETWEEN ? AND ?
";

$params = [$id_workshop, $start_date . ' 00:00:00', $end_date . ' 23:59:59'];

if ($id_mechanic) {
    $query .= " AND so.id_user_assigned = ?";
    $params[] = $id_mechanic;
}

$query .= " GROUP BY s.id_service";

if ($min_quantity) {
    $query .= " HAVING total_quantity >= ?";
    $params[] = $min_quantity;
}

$query .= " ORDER BY total_revenue DESC";

// Ejecutar consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll();

// Obtener mecánicos para el filtro
$stmt = $db->prepare("
    SELECT id_user, full_name 
    FROM users 
    WHERE id_workshop = ? AND role = 'mechanic'
");
$stmt->execute([$id_workshop]);
$mechanics = $stmt->fetchAll();

// Calcular estadísticas
$total_services = count($services);
$total_quantity = array_sum(array_column($services, 'total_quantity'));
$total_revenue = array_sum(array_column($services, 'total_revenue'));
$avg_price = $total_quantity ? $total_revenue / $total_quantity : 0;

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Reporte de Servicios</h1>
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
                <div class="col-md-3">
                    <label for="min_quantity" class="form-label">Mín. Cantidad</label>
                    <input type="number" class="form-control" id="min_quantity" name="min_quantity" 
                           value="<?php echo htmlspecialchars($min_quantity); ?>" min="0">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="services.php" class="btn btn-secondary">Limpiar</a>
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
                    <h5 class="card-title">Total Servicios</h5>
                    <p class="card-text display-6"><?php echo $total_services; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Realizados</h5>
                    <p class="card-text display-6"><?php echo $total_quantity; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Ingresos</h5>
                    <p class="card-text display-6">$<?php echo number_format($total_revenue, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Precio Promedio</h5>
                    <p class="card-text display-6">$<?php echo number_format($avg_price, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Gráfico de Servicios -->
    <div class="card mb-4">
        <div class="card-body">
            <canvas id="servicesChart"></canvas>
        </div>
    </div>

    <!-- Tabla de Servicios -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="servicesTable">
                    <thead>
                        <tr>
                            <th>Servicio</th>
                            <th>Descripción</th>
                            <th>Precio</th>
                            <th>Cantidad</th>
                            <th>Ingresos</th>
                            <th>Órdenes</th>
                            <th>Completadas</th>
                            <th>Canceladas</th>
                            <th>Mecánico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['name']); ?></td>
                                <td><?php echo htmlspecialchars($service['description']); ?></td>
                                <td>$<?php echo number_format($service['avg_price'], 2); ?></td>
                                <td><?php echo $service['total_quantity']; ?></td>
                                <td>$<?php echo number_format($service['total_revenue'], 2); ?></td>
                                <td><?php echo $service['total_orders']; ?></td>
                                <td><?php echo $service['completed_orders']; ?></td>
                                <td><?php echo $service['cancelled_orders']; ?></td>
                                <td><?php echo htmlspecialchars($service['mechanic_name'] ?? 'No asignado'); ?></td>
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
const labels = <?php echo json_encode(array_column($services, 'name')); ?>;
const quantities = <?php echo json_encode(array_column($services, 'total_quantity')); ?>;
const revenues = <?php echo json_encode(array_column($services, 'total_revenue')); ?>;

// Crear gráfico
const ctx = document.getElementById('servicesChart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: labels,
        datasets: [{
            label: 'Cantidad',
            data: quantities,
            backgroundColor: 'rgba(54, 162, 235, 0.5)',
            borderColor: 'rgb(54, 162, 235)',
            borderWidth: 1
        }, {
            label: 'Ingresos',
            data: revenues,
            backgroundColor: 'rgba(75, 192, 192, 0.5)',
            borderColor: 'rgb(75, 192, 192)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Servicios por Cantidad e Ingresos'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Cantidad'
                }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Ingresos ($)'
                },
                grid: {
                    drawOnChartArea: false
                }
            }
        }
    }
});

function exportToExcel() {
    // Crear una tabla temporal para la exportación
    const table = document.getElementById('servicesTable');
    const html = table.outerHTML;
    
    // Crear un blob con el contenido
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    
    // Crear un enlace para descargar
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_servicios_<?php echo date('Y-m-d'); ?>.xls';
    
    // Simular clic en el enlace
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../../includes/footer.php'; ?> 