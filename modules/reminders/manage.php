<?php
require_once '../../includes/config.php';

// Verificar autenticación y permisos
if (!isAuthenticated()) {
    redirect('templates/login.php');
}

// Obtener ID del taller
$id_workshop = $_SESSION['id_workshop'];

// Obtener vehículos del taller
$stmt = $db->prepare("
    SELECT v.*, c.name as client_name, c.email as client_email, c.phone as client_phone
    FROM vehicles v
    JOIN clients c ON v.id_client = c.id_client
    WHERE v.id_workshop = ?
    ORDER BY v.brand, v.model
");
$stmt->execute([$id_workshop]);
$vehicles = $stmt->fetchAll();

// Obtener servicios del taller
$stmt = $db->prepare("
    SELECT * FROM services 
    WHERE id_workshop = ? AND status = 'active'
    ORDER BY name
");
$stmt->execute([$id_workshop]);
$services = $stmt->fetchAll();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id_vehicle = (int)$_POST['id_vehicle'];
        $id_service = (int)$_POST['id_service'];
        $reminder_type = $_POST['reminder_type'];
        $due_date = $reminder_type === 'date' ? $_POST['due_date'] : null;
        $due_mileage = $reminder_type === 'mileage' ? (int)$_POST['due_mileage'] : null;
        $notes = $_POST['notes'] ?? null;

        // Validaciones
        if ($id_vehicle <= 0 || $id_service <= 0 || empty($reminder_type)) {
            throw new Exception('Todos los campos requeridos son obligatorios');
        }

        if ($reminder_type === 'date' && empty($due_date)) {
            throw new Exception('La fecha de vencimiento es requerida');
        }

        if ($reminder_type === 'mileage' && empty($due_mileage)) {
            throw new Exception('El kilometraje de vencimiento es requerido');
        }

        // Insertar recordatorio
        $stmt = $db->prepare("
            INSERT INTO reminders 
            (id_vehicle, id_service, reminder_type, due_date, due_mileage, notes, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([
            $id_vehicle,
            $id_service,
            $reminder_type,
            $due_date,
            $due_mileage,
            $notes
        ]);

        $_SESSION['success_message'] = 'Recordatorio creado exitosamente';
        redirect('manage.php');

    } catch (Exception $e) {
        $_SESSION['error_message'] = 'Error al crear el recordatorio: ' . $e->getMessage();
    }
}

// Obtener recordatorios existentes
$stmt = $db->prepare("
    SELECT r.*, v.brand, v.model, v.plates, v.last_mileage,
           s.name as service_name, c.name as client_name
    FROM reminders r
    JOIN vehicles v ON r.id_vehicle = v.id_vehicle
    JOIN services s ON r.id_service = s.id_service
    JOIN clients c ON v.id_client = c.id_client
    WHERE v.id_workshop = ?
    ORDER BY r.due_date ASC, r.due_mileage ASC
");
$stmt->execute([$id_workshop]);
$reminders = $stmt->fetchAll();

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Recordatorios de Mantenimiento</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addReminderModal">
                    <i class="fas fa-plus"></i> Nuevo Recordatorio
                </button>
            </div>

            <!-- Lista de Recordatorios -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Vehículo</th>
                                    <th>Servicio</th>
                                    <th>Tipo</th>
                                    <th>Vencimiento</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reminders as $reminder): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($reminder['client_name']); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($reminder['brand'] . ' ' . $reminder['model']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($reminder['plates']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($reminder['service_name']); ?></td>
                                        <td>
                                            <?php echo $reminder['reminder_type'] === 'date' ? 'Fecha' : 'Kilometraje'; ?>
                                        </td>
                                        <td>
                                            <?php if ($reminder['reminder_type'] === 'date'): ?>
                                                <?php echo date('d/m/Y', strtotime($reminder['due_date'])); ?>
                                            <?php else: ?>
                                                <?php echo number_format($reminder['due_mileage']); ?> km
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo match($reminder['status']) {
                                                    'pending' => 'warning',
                                                    'completed' => 'success',
                                                    'cancelled' => 'danger',
                                                    default => 'secondary'
                                                };
                                            ?>">
                                                <?php echo ucfirst($reminder['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-success" 
                                                onclick="markAsCompleted(<?php echo $reminder['id_reminder']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                onclick="cancelReminder(<?php echo $reminder['id_reminder']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
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

<!-- Modal para agregar recordatorio -->
<div class="modal fade" id="addReminderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nuevo Recordatorio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="id_vehicle" class="form-label">Vehículo</label>
                        <select class="form-select" id="id_vehicle" name="id_vehicle" required>
                            <option value="">Seleccionar vehículo</option>
                            <?php foreach ($vehicles as $vehicle): ?>
                                <option value="<?php echo $vehicle['id_vehicle']; ?>">
                                    <?php echo htmlspecialchars($vehicle['brand'] . ' ' . $vehicle['model'] . ' - ' . $vehicle['plates']); ?>
                                    (<?php echo htmlspecialchars($vehicle['client_name']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="id_service" class="form-label">Servicio</label>
                        <select class="form-select" id="id_service" name="id_service" required>
                            <option value="">Seleccionar servicio</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?php echo $service['id_service']; ?>">
                                    <?php echo htmlspecialchars($service['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="reminder_type" class="form-label">Tipo de Recordatorio</label>
                        <select class="form-select" id="reminder_type" name="reminder_type" required>
                            <option value="">Seleccionar tipo</option>
                            <option value="date">Por Fecha</option>
                            <option value="mileage">Por Kilometraje</option>
                        </select>
                    </div>
                    <div class="mb-3" id="dateField" style="display: none;">
                        <label for="due_date" class="form-label">Fecha de Vencimiento</label>
                        <input type="date" class="form-control" id="due_date" name="due_date">
                    </div>
                    <div class="mb-3" id="mileageField" style="display: none;">
                        <label for="due_mileage" class="form-label">Kilometraje de Vencimiento</label>
                        <input type="number" class="form-control" id="due_mileage" name="due_mileage" min="0">
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
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
// Mostrar/ocultar campos según el tipo de recordatorio
document.getElementById('reminder_type').addEventListener('change', function() {
    const dateField = document.getElementById('dateField');
    const mileageField = document.getElementById('mileageField');
    
    if (this.value === 'date') {
        dateField.style.display = 'block';
        mileageField.style.display = 'none';
    } else if (this.value === 'mileage') {
        dateField.style.display = 'none';
        mileageField.style.display = 'block';
    } else {
        dateField.style.display = 'none';
        mileageField.style.display = 'none';
    }
});

// Función para marcar como completado
function markAsCompleted(id_reminder) {
    if (confirm('¿Está seguro de marcar este recordatorio como completado?')) {
        window.location.href = `complete.php?id=${id_reminder}`;
    }
}

// Función para cancelar recordatorio
function cancelReminder(id_reminder) {
    if (confirm('¿Está seguro de cancelar este recordatorio?')) {
        window.location.href = `cancel.php?id=${id_reminder}`;
    }
}
</script>

<?php include '../../includes/footer.php'; ?> 