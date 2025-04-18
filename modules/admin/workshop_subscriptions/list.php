<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    redirect('templates/login.php');
}

// Obtener suscripciones con información de talleres y planes
$stmt = $db->prepare("
    SELECT ws.*, w.name as workshop_name, sp.name as plan_name, sp.price
    FROM workshop_subscriptions ws
    JOIN workshops w ON ws.id_workshop = w.id_workshop
    JOIN subscription_plans sp ON ws.id_plan = sp.id_plan
    ORDER BY ws.start_date DESC
");
$stmt->execute();
$subscriptions = $stmt->fetchAll();

// Incluir el encabezado
include '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Suscripciones de Talleres</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subscriptionModal">
                    <i class="fas fa-plus"></i> Nueva Suscripción
                </button>
            </div>

            <!-- Mensajes de éxito/error -->
            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Lista de Suscripciones -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Taller</th>
                                    <th>Plan</th>
                                    <th>Inicio</th>
                                    <th>Fin</th>
                                    <th>Estado</th>
                                    <th>Próximo Pago</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subscriptions as $sub): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($sub['workshop_name']); ?></td>
                                        <td><?php echo htmlspecialchars($sub['plan_name']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($sub['start_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($sub['end_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($sub['status']) {
                                                    'active' => 'success',
                                                    'suspended' => 'warning',
                                                    'cancelled' => 'danger',
                                                    'pending_payment' => 'info',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($sub['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($sub['next_payment_date']): ?>
                                                <?php echo date('d/m/Y', strtotime($sub['next_payment_date'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="viewSubscription(<?php echo $sub['id_subscription']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="editSubscription(<?php echo $sub['id_subscription']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="cancelSubscription(<?php echo $sub['id_subscription']; ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
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

<!-- Modal para Agregar/Editar Suscripción -->
<div class="modal fade" id="subscriptionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Suscripción de Taller</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="subscriptionForm" action="save_subscription.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_subscription" id="id_subscription">
                    
                    <div class="mb-3">
                        <label for="id_workshop" class="form-label">Taller</label>
                        <select class="form-select" id="id_workshop" name="id_workshop" required>
                            <option value="">Seleccionar taller</option>
                            <?php
                            $stmt = $db->query("SELECT id_workshop, name FROM workshops ORDER BY name");
                            while ($workshop = $stmt->fetch()): ?>
                                <option value="<?php echo $workshop['id_workshop']; ?>">
                                    <?php echo htmlspecialchars($workshop['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="id_plan" class="form-label">Plan</label>
                        <select class="form-select" id="id_plan" name="id_plan" required>
                            <option value="">Seleccionar plan</option>
                            <?php
                            $stmt = $db->query("SELECT id_plan, name, price FROM subscription_plans WHERE status = 'active' ORDER BY price");
                            while ($plan = $stmt->fetch()): ?>
                                <option value="<?php echo $plan['id_plan']; ?>" data-price="<?php echo $plan['price']; ?>">
                                    <?php echo htmlspecialchars($plan['name']); ?> - $<?php echo number_format($plan['price'], 2); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="start_date" class="form-label">Fecha de Inicio</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="end_date" class="form-label">Fecha de Fin</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Método de Pago</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Seleccionar método</option>
                            <option value="credit_card">Tarjeta de Crédito</option>
                            <option value="bank_transfer">Transferencia Bancaria</option>
                            <option value="cash">Efectivo</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Activo</option>
                            <option value="suspended">Suspendido</option>
                            <option value="cancelled">Cancelado</option>
                            <option value="pending_payment">Pago Pendiente</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Función para ver una suscripción
function viewSubscription(id) {
    window.location.href = `view_subscription.php?id=${id}`;
}

// Función para editar una suscripción
function editSubscription(id) {
    // Obtener datos de la suscripción mediante AJAX
    fetch(`get_subscription.php?id=${id}`)
        .then(response => response.json())
        .then(subscription => {
            // Llenar el formulario con los datos
            document.getElementById('id_subscription').value = subscription.id_subscription;
            document.getElementById('id_workshop').value = subscription.id_workshop;
            document.getElementById('id_plan').value = subscription.id_plan;
            document.getElementById('start_date').value = subscription.start_date;
            document.getElementById('end_date').value = subscription.end_date;
            document.getElementById('payment_method').value = subscription.payment_method;
            document.getElementById('status').value = subscription.status;

            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('subscriptionModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos de la suscripción');
        });
}

// Función para cancelar una suscripción
function cancelSubscription(id) {
    if (confirm('¿Está seguro de cancelar esta suscripción?')) {
        // Crear formulario para enviar la petición POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'cancel_subscription.php';

        // Agregar campo oculto con el ID de la suscripción
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id_subscription';
        input.value = id;
        form.appendChild(input);

        // Agregar el formulario al documento y enviarlo
        document.body.appendChild(form);
        form.submit();
    }
}

// Calcular fecha de fin basada en la duración del plan
document.getElementById('id_plan').addEventListener('change', function() {
    const startDate = document.getElementById('start_date').value;
    if (startDate) {
        const selectedOption = this.options[this.selectedIndex];
        const planId = selectedOption.value;
        
        // Obtener duración del plan mediante AJAX
        fetch(`get_plan_duration.php?id=${planId}`)
            .then(response => response.json())
            .then(data => {
                if (data.duration_months) {
                    const start = new Date(startDate);
                    const end = new Date(start);
                    end.setMonth(start.getMonth() + data.duration_months);
                    document.getElementById('end_date').value = end.toISOString().split('T')[0];
                }
            })
            .catch(error => console.error('Error:', error));
    }
});
</script>

<?php include '../../../includes/footer.php'; ?> 