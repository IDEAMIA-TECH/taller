<?php
require_once '../includes/config.php';

// Array para almacenar los logs
$logs = [];
$logs[] = "=== Inicio del proceso de dashboard ===";
$logs[] = "URL actual: " . $_SERVER['REQUEST_URI'];
$logs[] = "Método de solicitud: " . $_SERVER['REQUEST_METHOD'];
$logs[] = "Estado de la sesión: " . (session_status() === PHP_SESSION_ACTIVE ? "Activa" : "Inactiva");
$logs[] = "Datos de sesión: " . print_r($_SESSION, true);

// Verificar autenticación
if (!isAuthenticated()) {
    $logs[] = "Usuario no autenticado, redirigiendo a login";
    redirect('login.php');
}

$logs[] = "Usuario autenticado: " . $_SESSION['username'];
$logs[] = "Rol del usuario: " . $_SESSION['role'];
$logs[] = "ID del taller: " . $_SESSION['id_workshop'];

// Obtener estadísticas del taller
try {
    $logs[] = "Intentando conectar a la base de datos...";
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $logs[] = "Conexión a la base de datos exitosa";
    
    // Total de clientes
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM clients WHERE id_workshop = ?");
    $stmt->execute([getCurrentWorkshop()]);
    $totalClients = $stmt->fetch()['total'];

    // Total de vehículos
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM vehicles WHERE id_workshop = ?");
    $stmt->execute([getCurrentWorkshop()]);
    $totalVehicles = $stmt->fetch()['total'];

    // Órdenes abiertas
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM service_orders 
                         WHERE id_workshop = ? AND status IN ('open', 'in_progress')");
    $stmt->execute([getCurrentWorkshop()]);
    $openOrders = $stmt->fetch()['total'];

    // Ingresos del mes
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount), 0) as total 
                         FROM service_orders 
                         WHERE id_workshop = ? 
                         AND MONTH(created_at) = MONTH(CURRENT_DATE())
                         AND YEAR(created_at) = YEAR(CURRENT_DATE())");
    $stmt->execute([getCurrentWorkshop()]);
    $monthlyIncome = $stmt->fetch()['total'];

    // Órdenes recientes
    $stmt = $pdo->prepare("SELECT so.*, c.name as client_name, v.brand, v.model 
                         FROM service_orders so
                         JOIN clients c ON so.id_client = c.id_client
                         JOIN vehicles v ON so.id_vehicle = v.id_vehicle
                         WHERE so.id_workshop = ?
                         ORDER BY so.created_at DESC
                         LIMIT 5");
    $stmt->execute([getCurrentWorkshop()]);
    $recentOrders = $stmt->fetchAll();

} catch (PDOException $e) {
    $logs[] = "Error de conexión a la base de datos: " . $e->getMessage();
    showError('Error al cargar el dashboard. Por favor, intente más tarde.');
}

// Guardar logs en un archivo
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    if (!mkdir($logDir, 0755, true)) {
        $logs[] = "Error: No se pudo crear el directorio de logs";
    }
}

$logFile = $logDir . '/dashboard_' . date('Y-m-d') . '.log';
if (is_writable($logDir)) {
    if (!file_put_contents($logFile, implode("\n", $logs) . "\n\n", FILE_APPEND)) {
        $logs[] = "Error: No se pudo escribir en el archivo de log";
    }
} else {
    $logs[] = "Error: El directorio de logs no tiene permisos de escritura";
}

// Mostrar logs en la consola del navegador
echo "<script>console.log('Logs de dashboard:', " . json_encode($logs) . ");</script>";

// Incluir el header
$logs[] = "Incluyendo header.php";
include '../includes/header.php';
$logs[] = "Header incluido correctamente";
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
                        <a class="nav-link active" href="<?php echo APP_URL; ?>/templates/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <?php 
                    $logs[] = "Verificando roles para mostrar menú";
                    if (hasRole('admin') || hasRole('receptionist') || hasRole('super_admin')): 
                        $logs[] = "Mostrando opciones para admin/recepcionista/super_admin";
                    ?>
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
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/orders/">
                            <i class="fas fa-clipboard-list"></i> Órdenes
                        </a>
                    </li>
                    <?php 
                    endif; 
                    if (hasRole('admin') || hasRole('super_admin')): 
                        $logs[] = "Mostrando opciones para admin/super_admin";
                    ?>
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
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="fas fa-plus"></i> Nuevo
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/clients/create.php">
                                    <i class="fas fa-user-plus"></i> Cliente
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/vehicles/create.php">
                                    <i class="fas fa-car"></i> Vehículo
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/services/create.php">
                                    <i class="fas fa-tools"></i> Servicio
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?php echo APP_URL; ?>/modules/orders/create.php">
                                    <i class="fas fa-clipboard-list"></i> Orden de Servicio
                                </a>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <div class="btn-group me-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary">Exportar</button>
                    </div>
                </div>
            </div>

            <!-- Tarjetas de estadísticas con botones de acción -->
            <div class="row">
                <div class="col-md-3 mb-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title">Clientes</h5>
                                    <h2 class="card-text"><?php echo $totalClients; ?></h2>
                                </div>
                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                <a href="<?php echo APP_URL; ?>/modules/clients/create.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title">Vehículos</h5>
                                    <h2 class="card-text"><?php echo $totalVehicles; ?></h2>
                                </div>
                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                <a href="<?php echo APP_URL; ?>/modules/vehicles/create.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title">Órdenes Abiertas</h5>
                                    <h2 class="card-text"><?php echo $openOrders; ?></h2>
                                </div>
                                <?php if (hasRole('admin') || hasRole('receptionist')): ?>
                                <a href="<?php echo APP_URL; ?>/modules/orders/create.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 mb-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="card-title">Ingresos del Mes</h5>
                                    <h2 class="card-text">$<?php echo number_format($monthlyIncome, 2); ?></h2>
                                </div>
                                <?php if (hasRole('admin')): ?>
                                <a href="<?php echo APP_URL; ?>/modules/services/create.php" class="btn btn-light btn-sm">
                                    <i class="fas fa-plus"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de órdenes recientes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Órdenes Recientes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th># Orden</th>
                                    <th>Cliente</th>
                                    <th>Vehículo</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentOrders as $order): ?>
                                <tr>
                                    <td><?php echo $order['order_number']; ?></td>
                                    <td><?php echo $order['client_name']; ?></td>
                                    <td><?php echo $order['brand'] . ' ' . $order['model']; ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $order['status'] === 'open' ? 'warning' : 
                                                ($order['status'] === 'in_progress' ? 'info' : 'success'); 
                                        ?>">
                                            <?php 
                                            echo $order['status'] === 'open' ? 'Abierta' : 
                                                ($order['status'] === 'in_progress' ? 'En Proceso' : 'Finalizada'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <a href="<?php echo APP_URL; ?>/modules/orders/view.php?id=<?php echo $order['id_order']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
$logs[] = "Incluyendo footer.php";
include '../includes/footer.php';
$logs[] = "Footer incluido correctamente";
?>
