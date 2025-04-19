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

// Configurar paginación
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Construir consulta base
    $query = "SELECT s.*, 
              COUNT(od.id_detail) as times_used,
              SUM(od.quantity) as total_quantity
              FROM services s
              LEFT JOIN order_details od ON s.id_service = od.id_service
              WHERE s.id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";

    // Agregar filtros
    if (!empty($search)) {
        $query .= " AND (s.name LIKE '%" . addslashes($search) . "%' OR s.description LIKE '%" . addslashes($search) . "%')";
    }

    if ($status !== 'all') {
        $query .= " AND s.status = '" . addslashes($status) . "'";
    }

    // Agregar agrupación y ordenamiento
    $query .= " GROUP BY s.id_service ORDER BY s.name ASC";

    // Obtener total de registros
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as count_query";
    $result = $db->query($countQuery);
    $total = $result->fetch()['total'];
    $totalPages = ceil($total / $limit);

    // Agregar límite y offset
    $query .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    // Ejecutar consulta
    $result = $db->query($query);
    $services = $result->fetchAll();

} catch (PDOException $e) {
    error_log("Error en services/index.php: " . $e->getMessage());
    showError('Error al cargar los servicios');
    $services = [];
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
                        <a class="nav-link active" href="<?php echo APP_URL; ?>/modules/services/">
                            <i class="fas fa-tools"></i> Servicios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/service_orders/">
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
                <h1 class="h3 mb-0">Servicios</h1>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Nuevo Servicio
                </a>
            </div>

            <!-- Filtros y Búsqueda -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Buscar por nombre o descripción">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" name="status">
                                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos los estados</option>
                                <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activos</option>
                                <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivos</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Lista de Servicios -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($services)): ?>
                        <p class="text-muted">No se encontraron servicios.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Descripción</th>
                                        <th>Precio</th>
                                        <th>Duración</th>
                                        <th>Veces Usado</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($services as $service): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($service['name']); ?></td>
                                            <td><?php echo htmlspecialchars($service['description']); ?></td>
                                            <td>$<?php echo number_format($service['price'], 2); ?></td>
                                            <td><?php echo $service['duration']; ?> min</td>
                                            <td><?php echo $service['times_used']; ?> (<?php echo $service['total_quantity']; ?> unidades)</td>
                                            <td>
                                                <span class="badge bg-<?php echo $service['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo ucfirst($service['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="view.php?id=<?php echo $service['id_service']; ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Ver">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="edit.php?id=<?php echo $service['id_service']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="delete.php?id=<?php echo $service['id_service']; ?>" 
                                                   class="btn btn-sm btn-outline-danger" title="Eliminar"
                                                   onclick="return confirm('¿Está seguro de eliminar este servicio?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
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
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status; ?>">
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