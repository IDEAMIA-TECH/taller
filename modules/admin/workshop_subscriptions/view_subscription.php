<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    redirect('templates/login.php');
}

// Obtener y validar ID de la suscripción
$id_subscription = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_subscription <= 0) {
    $_SESSION['error_message'] = 'ID de suscripción inválido';
    redirect('list.php');
}

// Obtener datos de la suscripción
$stmt = $db->prepare("
    SELECT ws.*, w.name as workshop_name, w.email as workshop_email, 
           w.phone as workshop_phone, w.address as workshop_address,
           sp.name as plan_name, sp.price, sp.duration_months,
           sp.max_users, sp.max_vehicles, sp.features
    FROM workshop_subscriptions ws
    JOIN workshops w ON ws.id_workshop = w.id_workshop
    JOIN subscription_plans sp ON ws.id_plan = sp.id_plan
    WHERE ws.id_subscription = ?
");
$stmt->execute([$id_subscription]);
$subscription = $stmt->fetch();

if (!$subscription) {
    $_SESSION['error_message'] = 'Suscripción no encontrada';
    redirect('list.php');
}

// Obtener historial de pagos
$stmt = $db->prepare("
    SELECT * FROM payments 
    WHERE id_subscription = ?
    ORDER BY payment_date DESC
");
$stmt->execute([$id_subscription]);
$payments = $stmt->fetchAll();

// Obtener notificaciones
$stmt = $db->prepare("
    SELECT * FROM payment_notifications 
    WHERE id_workshop = ?
    ORDER BY created_at DESC
");
$stmt->execute([$subscription['id_workshop']]);
$notifications = $stmt->fetchAll();

// Incluir el encabezado
include '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Detalles de Suscripción</h1>
                <div>
                    <a href="list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <button type="button" class="btn btn-warning" onclick="editSubscription(<?php echo $subscription['id_subscription']; ?>)">
                        <i class="fas fa-edit"></i> Editar
                    </button>
                </div>
            </div>

            <!-- Información General -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Información General</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Taller:</strong> <?php echo htmlspecialchars($subscription['workshop_name']); ?></p>
                            <p><strong>Plan:</strong> <?php echo htmlspecialchars($subscription['plan_name']); ?></p>
                            <p><strong>Estado:</strong> 
                                <span class="badge bg-<?php 
                                    echo match($subscription['status']) {
                                        'active' => 'success',
                                        'suspended' => 'warning',
                                        'cancelled' => 'danger',
                                        'pending_payment' => 'info',
                                        default => 'secondary'
                                    };
                                ?>">
                                    <?php echo ucfirst($subscription['status']); ?>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Fecha de Inicio:</strong> <?php echo date('d/m/Y', strtotime($subscription['start_date'])); ?></p>
                            <p><strong>Fecha de Fin:</strong> <?php echo date('d/m/Y', strtotime($subscription['end_date'])); ?></p>
                            <p><strong>Próximo Pago:</strong> 
                                <?php if ($subscription['next_payment_date']): ?>
                                    <?php echo date('d/m/Y', strtotime($subscription['next_payment_date'])); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detalles del Plan -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Detalles del Plan</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Precio:</strong> $<?php echo number_format($subscription['price'], 2); ?></p>
                            <p><strong>Duración:</strong> <?php echo $subscription['duration_months']; ?> meses</p>
                            <p><strong>Máximo de Usuarios:</strong> <?php echo $subscription['max_users']; ?></p>
                            <p><strong>Máximo de Vehículos:</strong> <?php echo $subscription['max_vehicles']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Características:</strong></p>
                            <ul>
                                <?php 
                                $features = json_decode($subscription['features'], true);
                                foreach ($features as $feature): ?>
                                    <li><?php echo htmlspecialchars($feature); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historial de Pagos -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Historial de Pagos</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Método</th>
                                    <th>Estado</th>
                                    <th>Comprobante</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                        <td>$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td><?php echo ucfirst($payment['payment_method']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($payment['status']) {
                                                    'completed' => 'success',
                                                    'pending' => 'warning',
                                                    'failed' => 'danger',
                                                    'refunded' => 'info',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($payment['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($payment['receipt_path']): ?>
                                                <a href="<?php echo $payment['receipt_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                                    <i class="fas fa-file-pdf"></i> Ver
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Notificaciones -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Historial de Notificaciones</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Mensaje</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y H:i', strtotime($notification['created_at'])); ?></td>
                                        <td><?php echo ucfirst($notification['notification_type']); ?></td>
                                        <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($notification['status']) {
                                                    'sent' => 'success',
                                                    'pending' => 'warning',
                                                    'failed' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($notification['status']); ?>
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
    </div>
</div>

<script>
// Función para editar una suscripción
function editSubscription(id) {
    window.location.href = `list.php?edit=${id}`;
}
</script>

<?php include '../../../includes/footer.php'; ?> 