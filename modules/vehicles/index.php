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
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Construir la consulta base
    $query = "SELECT v.*, c.name as client_name 
              FROM vehicles v 
              JOIN clients c ON v.id_client = c.id_client 
              WHERE v.id_workshop = " . $db->quote(getCurrentWorkshop());

    // Agregar filtro por cliente si existe
    if ($client_id > 0) {
        $query .= " AND v.id_client = " . $db->quote($client_id);
    }

    // Agregar búsqueda si existe
    if (!empty($search)) {
        $searchParam = $db->quote("%$search%");
        $query .= " AND (v.brand LIKE $searchParam OR v.model LIKE $searchParam OR v.plates LIKE $searchParam OR c.name LIKE $searchParam)";
    }

    // Agregar orden y límite
    $query .= " ORDER BY v.created_at DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

    // Obtener vehículos
    $result = $db->query($query);
    $vehicles = $result->fetchAll(PDO::FETCH_ASSOC);

    // Obtener total de registros para paginación
    $countQuery = "SELECT COUNT(*) as total 
                   FROM vehicles v 
                   JOIN clients c ON v.id_client = c.id_client 
                   WHERE v.id_workshop = " . $db->quote(getCurrentWorkshop());

    if ($client_id > 0) {
        $countQuery .= " AND v.id_client = " . $db->quote($client_id);
    }

    if (!empty($search)) {
        $searchParam = $db->quote("%$search%");
        $countQuery .= " AND (v.brand LIKE $searchParam OR v.model LIKE $searchParam OR v.plates LIKE $searchParam OR c.name LIKE $searchParam)";
    }

    $result = $db->query($countQuery);
    $totalVehicles = $result->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalVehicles / $limit);

} catch (PDOException $e) {
    error_log("Error en index.php: " . $e->getMessage());
    showError('Error al cargar los vehículos. Por favor, intente más tarde.');
    $vehicles = [];
    $totalPages = 1;
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Vehículos</h1>
        <a href="create.php<?php echo $client_id ? '?client_id=' . $client_id : ''; ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Vehículo
        </a>
    </div>

    <!-- Barra de búsqueda -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <?php if ($client_id): ?>
                    <input type="hidden" name="client_id" value="<?php echo $client_id; ?>">
                <?php endif; ?>
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Buscar por marca, modelo, placas o cliente">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <a href="index.php<?php echo $client_id ? '?client_id=' . $client_id : ''; ?>" 
                       class="btn btn-outline-secondary">
                        <i class="fas fa-sync"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de vehículos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Año</th>
                            <th>Placas</th>
                            <th>Cliente</th>
                            <th>Último Kilometraje</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vehicles)): ?>
                            <tr>
                                <td colspan="7" class="text-center">No se encontraron vehículos</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($vehicle['brand']); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['model']); ?></td>
                                    <td><?php echo $vehicle['year']; ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['plates']); ?></td>
                                    <td><?php echo htmlspecialchars($vehicle['client_name']); ?></td>
                                    <td><?php echo $vehicle['last_mileage'] ? number_format($vehicle['last_mileage']) : '-'; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $vehicle['id_vehicle']; ?>" 
                                               class="btn btn-sm btn-info" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $vehicle['id_vehicle']; ?>" 
                                               class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    title="Eliminar" 
                                                    onclick="confirmDelete(<?php echo $vehicle['id_vehicle']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?><?php echo $client_id ? '&client_id=' . $client_id : ''; ?>">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm('¿Está seguro que desea eliminar este vehículo?')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include '../../includes/footer.php'; ?> 