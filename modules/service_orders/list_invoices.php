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

// Obtener parámetros de filtrado
$status = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Configurar paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Construir consulta base
$query = "
    SELECT 
        i.*,
        c.name as client_name,
        c.rfc as client_rfc,
        v.brand,
        v.model,
        v.plates,
        so.order_number
    FROM invoices i
    JOIN service_orders so ON i.id_order = so.id_order
    JOIN clients c ON so.id_client = c.id_client
    JOIN vehicles v ON so.id_vehicle = v.id_vehicle
    WHERE i.id_workshop = ?
";

$params = [getCurrentWorkshop()];

// Agregar filtros
if ($status) {
    $query .= " AND i.status = ?";
    $params[] = $status;
}

if ($start_date) {
    $query .= " AND DATE(i.created_at) >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $query .= " AND DATE(i.created_at) <= ?";
    $params[] = $end_date;
}

if ($search) {
    $query .= " AND (
        i.invoice_number LIKE ? OR 
        c.name LIKE ? OR 
        c.rfc LIKE ? OR 
        v.plates LIKE ?
    )";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

// Obtener total de registros para paginación
$count_query = "SELECT COUNT(*) FROM ($query) as total";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Agregar ordenamiento y límite
$query .= " ORDER BY i.created_at DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;

// Obtener facturas
$stmt = $db->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Facturas</h1>

            <!-- Filtros -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="status" class="form-label">Estado</label>
                            <select name="status" id="status" class="form-select">
                                <option value="">Todos</option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Pagada</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="start_date" class="form-label">Fecha Inicio</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="end_date" class="form-label">Fecha Fin</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="search" class="form-label">Buscar</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="Número, cliente, RFC o placas" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Facturas -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Vehículo</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($invoices as $invoice): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                        <td><?php echo date('d/m/Y H:i', strtotime($invoice['created_at'])); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($invoice['client_name']); ?><br>
                                            <small class="text-muted">RFC: <?php echo htmlspecialchars($invoice['client_rfc']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($invoice['brand'] . ' ' . $invoice['model']); ?><br>
                                            <small class="text-muted">Placas: <?php echo htmlspecialchars($invoice['plates']); ?></small>
                                        </td>
                                        <td>$<?php echo number_format($invoice['total_amount'], 2); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch ($invoice['status']) {
                                                case 'pending':
                                                    $status_class = 'warning';
                                                    break;
                                                case 'paid':
                                                    $status_class = 'success';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'danger';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php
                                                switch ($invoice['status']) {
                                                    case 'pending':
                                                        echo 'Pendiente';
                                                        break;
                                                    case 'paid':
                                                        echo 'Pagada';
                                                        break;
                                                    case 'cancelled':
                                                        echo 'Cancelada';
                                                        break;
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="view_invoice.php?id=<?php echo $invoice['id_invoice']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($invoice['status'] === 'pending'): ?>
                                                    <a href="stamp_invoice.php?id=<?php echo $invoice['id_invoice']; ?>" class="btn btn-sm btn-success" onclick="return confirm('¿Está seguro de timbrar esta factura?');">
                                                        <i class="fas fa-stamp"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($invoice['status'] === 'paid'): ?>
                                                    <form action="cancel_invoice.php" method="POST" class="d-inline">
                                                        <input type="hidden" name="id_invoice" value="<?php echo $invoice['id_invoice']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro de cancelar esta factura?');">
                                                            <i class="fas fa-ban"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo $status; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&search=<?php echo urlencode($search); ?>">Anterior</a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo $status; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&search=<?php echo urlencode($search); ?>">Siguiente</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 