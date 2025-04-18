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
$category = $_GET['category'] ?? '';
$min_stock = $_GET['min_stock'] ?? '';
$max_stock = $_GET['max_stock'] ?? '';
$status = $_GET['status'] ?? '';

// Construir consulta base para inventario
$query = "
    SELECT 
        i.*,
        c.name as category_name,
        COUNT(DISTINCT m.id_movement) as total_movements,
        SUM(CASE WHEN m.type = 'in' THEN m.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN m.type = 'out' THEN m.quantity ELSE 0 END) as total_out,
        SUM(CASE WHEN m.type = 'in' THEN m.quantity ELSE -m.quantity END) as net_movement,
        AVG(m.unit_price) as avg_price
    FROM inventory i
    LEFT JOIN categories c ON i.id_category = c.id_category
    LEFT JOIN inventory_movements m ON i.id_item = m.id_item
    WHERE i.id_workshop = ?
";

$params = [$id_workshop];

if ($search) {
    $query .= " AND (i.name LIKE ? OR i.code LIKE ? OR i.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category) {
    $query .= " AND i.id_category = ?";
    $params[] = $category;
}

if ($min_stock) {
    $query .= " AND i.current_stock >= ?";
    $params[] = $min_stock;
}

if ($max_stock) {
    $query .= " AND i.current_stock <= ?";
    $params[] = $max_stock;
}

if ($status) {
    $query .= " AND i.status = ?";
    $params[] = $status;
}

$query .= " GROUP BY i.id_item ORDER BY i.current_stock ASC";

// Ejecutar consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Obtener categorías para el filtro
$stmt = $db->prepare("
    SELECT id_category, name 
    FROM categories 
    WHERE id_workshop = ?
");
$stmt->execute([$id_workshop]);
$categories = $stmt->fetchAll();

// Calcular estadísticas
$total_items = count($inventory);
$total_stock = array_sum(array_column($inventory, 'current_stock'));
$total_value = array_sum(array_map(function($item) {
    return $item['current_stock'] * $item['avg_price'];
}, $inventory));
$low_stock = array_filter($inventory, function($item) {
    return $item['current_stock'] <= $item['min_stock'];
});

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Reporte de Inventario</h1>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Nombre, código o descripción">
                </div>
                <div class="col-md-2">
                    <label for="category" class="form-label">Categoría</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">Todas</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id_category']; ?>" 
                                    <?php echo $category == $cat['id_category'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="min_stock" class="form-label">Stock Mín.</label>
                    <input type="number" class="form-control" id="min_stock" name="min_stock" 
                           value="<?php echo htmlspecialchars($min_stock); ?>" min="0">
                </div>
                <div class="col-md-2">
                    <label for="max_stock" class="form-label">Stock Máx.</label>
                    <input type="number" class="form-control" id="max_stock" name="max_stock" 
                           value="<?php echo htmlspecialchars($max_stock); ?>" min="0">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Estado</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">Todos</option>
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Activo</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactivo</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <a href="inventory.php" class="btn btn-secondary">Limpiar</a>
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
                    <h5 class="card-title">Total Items</h5>
                    <p class="card-text display-6"><?php echo $total_items; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Stock Total</h5>
                    <p class="card-text display-6"><?php echo $total_stock; ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Valor Total</h5>
                    <p class="card-text display-6">$<?php echo number_format($total_value, 2); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5 class="card-title">Bajo Stock</h5>
                    <p class="card-text display-6"><?php echo count($low_stock); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alertas de Bajo Stock -->
    <?php if (count($low_stock) > 0): ?>
    <div class="alert alert-warning mb-4">
        <h5 class="alert-heading">¡Atención! Items con bajo stock</h5>
        <ul class="mb-0">
            <?php foreach ($low_stock as $item): ?>
                <li>
                    <?php echo htmlspecialchars($item['name']); ?> 
                    (Stock actual: <?php echo $item['current_stock']; ?>, 
                    Mínimo: <?php echo $item['min_stock']; ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- Tabla de Inventario -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="inventoryTable">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Categoría</th>
                            <th>Stock Actual</th>
                            <th>Stock Mín.</th>
                            <th>Stock Máx.</th>
                            <th>Precio Prom.</th>
                            <th>Valor Total</th>
                            <th>Movimientos</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventory as $item): ?>
                            <tr class="<?php echo $item['current_stock'] <= $item['min_stock'] ? 'table-warning' : ''; ?>">
                                <td><?php echo htmlspecialchars($item['code']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td><?php echo $item['current_stock']; ?></td>
                                <td><?php echo $item['min_stock']; ?></td>
                                <td><?php echo $item['max_stock']; ?></td>
                                <td>$<?php echo number_format($item['avg_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['current_stock'] * $item['avg_price'], 2); ?></td>
                                <td>
                                    <span class="badge bg-success">+<?php echo $item['total_in']; ?></span>
                                    <span class="badge bg-danger">-<?php echo $item['total_out']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                        <?php echo $item['status'] === 'active' ? 'Activo' : 'Inactivo'; ?>
                                    </span>
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
    const table = document.getElementById('inventoryTable');
    const html = table.outerHTML;
    
    // Crear un blob con el contenido
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    
    // Crear un enlace para descargar
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'reporte_inventario_<?php echo date('Y-m-d'); ?>.xls';
    
    // Simular clic en el enlace
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include '../../includes/footer.php'; ?> 