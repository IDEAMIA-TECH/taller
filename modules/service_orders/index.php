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

// Procesar búsqueda y filtros
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Configurar paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Construir consulta base
    $query = "SELECT 
                so.*,
                c.name as client_name,
                v.brand,
                v.model,
                v.plates,
                u.full_name as assigned_mechanic
              FROM service_orders so
              JOIN clients c ON so.id_client = c.id_client
              JOIN vehicles v ON so.id_vehicle = v.id_vehicle
              LEFT JOIN users u ON so.id_user_assigned = u.id_user
              WHERE so.id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";

    // Agregar filtros
    if (!empty($search)) {
        $query .= " AND (
            so.order_number LIKE '%" . addslashes($search) . "%' OR 
            c.name LIKE '%" . addslashes($search) . "%' OR 
            v.plates LIKE '%" . addslashes($search) . "%' OR 
            v.brand LIKE '%" . addslashes($search) . "%' OR 
            v.model LIKE '%" . addslashes($search) . "%'
        )";
    }

    if ($status !== 'all') {
        $query .= " AND so.status = '" . addslashes($status) . "'";
    }

    if (!empty($date_from)) {
        $query .= " AND DATE(so.created_at) >= '" . addslashes($date_from) . "'";
    }

    if (!empty($date_to)) {
        $query .= " AND DATE(so.created_at) <= '" . addslashes($date_to) . "'";
    }

    // Agregar ordenamiento
    $query .= " ORDER BY so.created_at DESC";

    // Obtener total de registros
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as count_query";
    $result = $db->query($countQuery);
    $total = $result->fetch()['total'];
    $totalPages = ceil($total / $limit);

    // Agregar límite y offset
    $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    // Ejecutar consulta
    $result = $db->query($query);
    $orders = $result->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar las órdenes de servicio');
    $orders = [];
    $totalPages = 0;
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
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/services/">
                            <i class="fas fa-tools"></i> Servicios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo APP_URL; ?>/modules/service_orders/">
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
                <h1 class="h3 mb-0">Órdenes de Servicio</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nueva Orden
                </a>
            </div>

            <!-- Filtros y Búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Buscar por orden, cliente o vehículo">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>

                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos los estados</option>
                                <option value="open" <?php echo $status === 'open' ? 'selected' : ''; ?>>Abierta</option>
                                <option value="in_progress" <?php echo $status === 'in_progress' ? 'selected' : ''; ?>>En Proceso</option>
                                <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completada</option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?php echo $date_from; ?>" 
                                   placeholder="Desde">
                        </div>

                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?php echo $date_to; ?>" 
                                   placeholder="Hasta">
                        </div>

                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Órdenes -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($orders)): ?>
                        <p class="text-muted">No se encontraron órdenes de servicio.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Orden</th>
                                        <th>Fecha</th>
                                        <th>Cliente</th>
                                        <th>Vehículo</th>
                                        <th>Mecánico</th>
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
                                            <td><?php echo $order['assigned_mechanic'] ? htmlspecialchars($order['assigned_mechanic']) : 'Sin asignar'; ?></td>
                                            <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
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
                                            <td>
                                                <a href="view.php?id=<?php echo $order['id_order']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $order['id_order']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($order['status'] === 'open'): ?>
                                                    <a href="delete.php?id=<?php echo $order['id_order']; ?>" 
                                                       class="btn btn-sm btn-outline-danger" title="Eliminar"
                                                       onclick="return confirm('¿Está seguro de eliminar esta orden?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Page navigation" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>">
                                                <i class="fas fa-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 