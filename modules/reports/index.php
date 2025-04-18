<?php
require_once '../../includes/config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    redirect('auth/login.php');
}

// Obtener permisos del usuario
$user_role = $_SESSION['role'];
$id_workshop = $_SESSION['id_workshop'];

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h1 class="h3 mb-4">Reportes</h1>
        </div>
    </div>

    <div class="row">
        <!-- Reporte de Órdenes de Servicio -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Órdenes de Servicio</h5>
                    <p class="card-text">Reporte detallado de órdenes de servicio por período, estado y mecánico.</p>
                    <a href="service_orders.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>

        <!-- Reporte de Ingresos -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Ingresos</h5>
                    <p class="card-text">Reporte de ingresos por período, servicio y forma de pago.</p>
                    <a href="revenue.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>

        <!-- Reporte de Clientes -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Clientes</h5>
                    <p class="card-text">Reporte de clientes activos, vehículos y servicios frecuentes.</p>
                    <a href="clients.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>

        <!-- Reporte de Servicios -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Servicios</h5>
                    <p class="card-text">Reporte de servicios más solicitados y rendimiento por mecánico.</p>
                    <a href="services.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>

        <!-- Reporte de Inventario -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Inventario</h5>
                    <p class="card-text">Reporte de inventario, movimientos y niveles de stock.</p>
                    <a href="inventory.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>

        <!-- Reporte de Mantenimiento -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Mantenimiento</h5>
                    <p class="card-text">Reporte de recordatorios de mantenimiento y servicios programados.</p>
                    <a href="maintenance.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>

        <?php if ($user_role === 'admin'): ?>
        <!-- Reporte de Usuarios -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Usuarios</h5>
                    <p class="card-text">Reporte de actividad de usuarios y rendimiento por rol.</p>
                    <a href="users.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($user_role === 'super_admin'): ?>
        <!-- Reporte de Talleres -->
        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title">Talleres</h5>
                    <p class="card-text">Reporte global de talleres, suscripciones y rendimiento.</p>
                    <a href="workshops.php" class="btn btn-primary">Ver Reporte</a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 