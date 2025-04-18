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
$search = $_GET['search'] ?? '';
$min_orders = $_GET['min_orders'] ?? '';
$min_amount = $_GET['min_amount'] ?? '';
$last_visit = $_GET['last_visit'] ?? ''; // 30, 60, 90, 180, 365

// Construir consulta base para clientes
$query = "
    SELECT 
        c.*,
        COUNT(DISTINCT v.id_vehicle) as total_vehicles,
        COUNT(DISTINCT so.id_order) as total_orders,
        SUM(od.subtotal) as total_spent,
        MAX(so.created_at) as last_visit
    FROM clients c
    LEFT JOIN vehicles v ON c.id_client = v.id_client
    LEFT JOIN service_orders so ON c.id_client = so.id_client
    LEFT JOIN order_details od ON so.id_order = od.id_order
    WHERE c.id_workshop = ?
";

$params = [$id_workshop];

if ($search) {
    $query .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($min_orders) {
    $query .= " HAVING total_orders >= ?";
    $params[] = $min_orders;
}

if ($min_amount) {
    $query .= " HAVING total_spent >= ?";
    $params[] = $min_amount;
}

if ($last_visit) {
    $query .= " HAVING last_visit >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    $params[] = $last_visit;
}

$query .= " GROUP BY c.id_client ORDER BY total_spent DESC";

// Ejecutar consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll();

// Calcular estadísticas
$total_clients = count($clients);
$total_vehicles = array_sum(array_column($clients, 'total_vehicles'));
$total_orders = array_sum(array_column($clients, 'total_orders'));
$total_spent = array_sum(array_column($clients, 'total_spent'));

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Reporte de Clientes</h1>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Nombre, teléfono o email">
                </div>
                <div class="col-md-2">
                    <label for="min_orders" class="form-label">Mín. Órdenes</label>
                    <input type="number" class="form-control" id="min_orders" name="min_orders" 
                           value="<?php echo htmlspecialchars($min_orders); ?>" min="0">
                </div>
                <div class="col-md-2">
                    <label for="min_amount" class="form-label">Mín. Gasto ($)</label>
                    <input type="number" class="form-control" id="min_amount" name="min_amount" 
                           value="<?php echo htmlspecialchars($min_amount); ?>" min="0" step="0.01">
                </div>
                <div class="col-md-2">
                    <label for="last_visit" class="form-label">Última Visita</label>
                    <select class="form-select" id="last_visit" name="last_visit">
                        <option value="">Todos</option>
                        <option value="30" <?php echo $last_visit === '30' ? 'selected' : ''; ?>>Últimos 30 días</option>
                        <option value="60" <?php echo $last_visit === '60' ? 'selected' : ''; ?>>Últimos 60 días</option>
                        <option value="90" <?php echo $last_visit === '90' ? 'selected' : ''; ?>>Últimos 90 días</option>
                        <option value="180" <?php echo $last_visit === '180' ? 'selected' : ''; ?>>Últimos 180 días</option>
                        <option value="365" <?php echo $last_visit === '365' ? 'selected' : ''; ?>>Último año</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="clients.php" class="btn btn-secondary">Limpiar</a>
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
                    <h5 class="card-title">Total Clientes</h5>
                    <p class="card-text display-6"><?php echo $total_clients; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Vehículos</h5>
                    <p class="card-text display-6"><?php echo $total_vehicles; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Órdenes</h5>
                    <p class="card-text display-6"><?php echo $total_orders; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Gasto</h5>
                    <p class="card-text display-6">$<?php echo number_format($total_spent, 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Clientes -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="clientsTable">
                    <thead>
                        <tr>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Vehículos</th>
                            <th>Órdenes</th>
                            <th>Total Gasto</th>
                            <th>Última Visita</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                    <?php if ($client['rfc']): ?>
                                        <br>
                                        <small class="text-muted">RFC: <?php echo htmlspecialchars($client['rfc']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($client['phone']): ?>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($client['phone']); ?><br>
                                    <?php endif; ?>
                                    <?php if ($client['email']): ?>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($client['email']); ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $client['total_vehicles']; ?></td>
                                <td><?php echo $client['total_orders']; ?></td>
                                <td>$<?php echo number_format($client['total_spent'], 2); ?></td>
                                <td>
                                    <?php if ($client['last_visit']): ?>
                                        <?php echo date('d/m/Y', strtotime($client['last_visit'])); ?>
                                    <?php else: ?>
                                        Nunca
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="../clients/view.php?id=<?php echo $client['id_client']; ?>" 
                                       class="btn btn-sm btn-primary" title="Ver detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="../clients/history.php?id=<?php echo $client['id_client']; ?>" 
                                       class="btn btn-sm btn-info" title="Historial">
                                        <i class="fas fa-history"></i>
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
    const table = document.getElementById('clientsTable');
    const html = table.outerHTML;
    
    // Crear un blob con el contenido
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    
    // Crear un enlace para descargar
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_clientes_<?php echo date('Y-m-d'); ?>.xls';
    
    // Simular clic en el enlace
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../../includes/footer.php'; ?> 