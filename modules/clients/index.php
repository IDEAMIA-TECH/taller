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
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

try {
    // Construir la consulta base
    $query = "SELECT c.*, COUNT(v.id_vehicle) as total_vehicles 
              FROM clients c 
              LEFT JOIN vehicles v ON c.id_client = v.id_client 
              WHERE c.id_workshop = ?";
    $params = [getCurrentWorkshop()];

    // Agregar búsqueda si existe
    if (!empty($search)) {
        $query .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)";
        $searchParam = "%$search%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    }

    // Agregar agrupación y orden
    $query .= " GROUP BY c.id_client ORDER BY c.name ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;

    // Obtener clientes
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $clients = $stmt->fetchAll();

    // Obtener total de registros para paginación
    $countQuery = "SELECT COUNT(*) as total FROM clients WHERE id_workshop = ?";
    if (!empty($search)) {
        $countQuery .= " AND (name LIKE ? OR phone LIKE ? OR email LIKE ?)";
    }
    $stmt = $db->prepare($countQuery);
    $stmt->execute(array_slice($params, 0, count($params) - 2));
    $totalClients = $stmt->fetch()['total'];
    $totalPages = ceil($totalClients / $limit);

} catch (PDOException $e) {
    showError('Error al cargar los clientes. Por favor, intente más tarde.');
    $clients = [];
    $totalPages = 1;
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Clientes</h1>
        <a href="create.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Cliente
        </a>
    </div>

    <!-- Barra de búsqueda -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" 
                               value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Buscar por nombre, teléfono o correo">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-sync"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de clientes -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Teléfono</th>
                            <th>Correo</th>
                            <th>Vehículos</th>
                            <th>Fecha Registro</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                            <tr>
                                <td colspan="6" class="text-center">No se encontraron clientes</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($clients as $client): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                    <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                    <td><?php echo htmlspecialchars($client['email']); ?></td>
                                    <td><?php echo $client['total_vehicles']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($client['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view.php?id=<?php echo $client['id_client']; ?>" 
                                               class="btn btn-sm btn-info" title="Ver">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $client['id_client']; ?>" 
                                               class="btn btn-sm btn-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    title="Eliminar" 
                                                    onclick="confirmDelete(<?php echo $client['id_client']; ?>)">
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
                                <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
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
    if (confirm('¿Está seguro que desea eliminar este cliente?')) {
        window.location.href = 'delete.php?id=' + id;
    }
}
</script>

<?php include '../../includes/footer.php'; ?> 