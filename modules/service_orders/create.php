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

$errors = [];
$success = false;

// Obtener datos necesarios para el formulario
try {
    // Obtener lista de clientes
    $stmt = $db->prepare("SELECT id_client, name FROM clients WHERE id_workshop = ? ORDER BY name");
    $stmt->execute([getCurrentWorkshop()]);
    $clients = $stmt->fetchAll();

    // Obtener lista de mecánicos
    $stmt = $db->prepare("SELECT id_user, full_name FROM users WHERE id_workshop = ? AND role = 'mechanic' AND status = 'active' ORDER BY full_name");
    $stmt->execute([getCurrentWorkshop()]);
    $mechanics = $stmt->fetchAll();

    // Obtener lista de servicios activos
    $stmt = $db->prepare("SELECT id_service, name, price, duration FROM services WHERE id_workshop = ? AND status = 'active' ORDER BY name");
    $stmt->execute([getCurrentWorkshop()]);
    $services = $stmt->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar los datos del formulario');
    redirect('index.php');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $id_client = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
    $id_vehicle = isset($_POST['id_vehicle']) ? (int)$_POST['id_vehicle'] : 0;
    $id_user_assigned = isset($_POST['id_user_assigned']) ? (int)$_POST['id_user_assigned'] : null;
    $notes = trim($_POST['notes'] ?? '');
    $services_data = isset($_POST['services']) ? $_POST['services'] : [];

    // Validaciones
    if (!$id_client) {
        $errors[] = 'Debe seleccionar un cliente';
    }

    if (!$id_vehicle) {
        $errors[] = 'Debe seleccionar un vehículo';
    }

    if (empty($services_data)) {
        $errors[] = 'Debe agregar al menos un servicio';
    }

    // Si no hay errores, crear la orden
    if (empty($errors)) {
        try {
            // Iniciar transacción
            $db->beginTransaction();

            // Generar número de orden
            $order_number = generateOrderNumber();

            // Insertar orden
            $stmt = $db->prepare("
                INSERT INTO service_orders (
                    id_workshop, id_client, id_vehicle, id_user_created, 
                    id_user_assigned, order_number, status, notes
                ) VALUES (?, ?, ?, ?, ?, ?, 'open', ?)
            ");
            $stmt->execute([
                getCurrentWorkshop(),
                $id_client,
                $id_vehicle,
                getCurrentUserId(),
                $id_user_assigned,
                $order_number,
                $notes
            ]);

            $id_order = $db->lastInsertId();

            // Insertar detalles de servicios
            $stmt = $db->prepare("
                INSERT INTO order_details (
                    id_order, id_service, quantity, unit_price, subtotal
                ) VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($services_data as $service) {
                $quantity = (int)$service['quantity'];
                $unit_price = (float)$service['price'];
                $subtotal = $quantity * $unit_price;

                $stmt->execute([
                    $id_order,
                    $service['id'],
                    $quantity,
                    $unit_price,
                    $subtotal
                ]);
            }

            // Confirmar transacción
            $db->commit();
            $success = true;
            showSuccess('Orden de servicio creada correctamente');
            redirect('view.php?id=' . $id_order);

        } catch (PDOException $e) {
            // Revertir transacción en caso de error
            $db->rollBack();
            $errors[] = 'Error al crear la orden de servicio. Por favor, intente más tarde.';
        }
    }
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Nueva Orden de Servicio</h1>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST" id="orderForm">
                <div class="row g-3">
                    <!-- Selección de Cliente -->
                    <div class="col-md-6">
                        <label for="id_client" class="form-label">Cliente *</label>
                        <select class="form-select" id="id_client" name="id_client" required>
                            <option value="">Seleccione un cliente</option>
                            <?php foreach ($clients as $client): ?>
                                <option value="<?php echo $client['id_client']; ?>" 
                                    <?php echo isset($_POST['id_client']) && $_POST['id_client'] == $client['id_client'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($client['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Selección de Vehículo -->
                    <div class="col-md-6">
                        <label for="id_vehicle" class="form-label">Vehículo *</label>
                        <select class="form-select" id="id_vehicle" name="id_vehicle" required>
                            <option value="">Seleccione un vehículo</option>
                        </select>
                    </div>

                    <!-- Selección de Mecánico -->
                    <div class="col-md-6">
                        <label for="id_user_assigned" class="form-label">Mecánico Asignado</label>
                        <select class="form-select" id="id_user_assigned" name="id_user_assigned">
                            <option value="">Sin asignar</option>
                            <?php foreach ($mechanics as $mechanic): ?>
                                <option value="<?php echo $mechanic['id_user']; ?>"
                                    <?php echo isset($_POST['id_user_assigned']) && $_POST['id_user_assigned'] == $mechanic['id_user'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($mechanic['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Notas -->
                    <div class="col-md-6">
                        <label for="notes" class="form-label">Notas</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>

                    <!-- Lista de Servicios -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Servicios</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table" id="servicesTable">
                                        <thead>
                                            <tr>
                                                <th>Servicio</th>
                                                <th>Precio</th>
                                                <th>Cantidad</th>
                                                <th>Subtotal</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Los servicios se agregarán dinámicamente -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5">
                                                    <button type="button" class="btn btn-outline-primary" id="addService">
                                                        <i class="fas fa-plus"></i> Agregar Servicio
                                                    </button>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Crear Orden
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para selección de servicios -->
<div class="modal fade" id="serviceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Servicio</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="serviceSelect" class="form-label">Servicio *</label>
                    <select class="form-select" id="serviceSelect">
                        <option value="">Seleccione un servicio</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id_service']; ?>" 
                                    data-price="<?php echo $service['price']; ?>">
                                <?php echo htmlspecialchars($service['name']); ?> 
                                ($<?php echo number_format($service['price'], 2); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="serviceQuantity" class="form-label">Cantidad *</label>
                    <input type="number" class="form-control" id="serviceQuantity" min="1" value="1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveService">Agregar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Cargar vehículos del cliente seleccionado
document.getElementById('id_client').addEventListener('change', function() {
    const clientId = this.value;
    const vehicleSelect = document.getElementById('id_vehicle');
    
    if (!clientId) {
        vehicleSelect.innerHTML = '<option value="">Seleccione un vehículo</option>';
        return;
    }

    fetch(`get_vehicles.php?client_id=${clientId}`)
        .then(response => response.json())
        .then(data => {
            let options = '<option value="">Seleccione un vehículo</option>';
            data.forEach(vehicle => {
                options += `<option value="${vehicle.id_vehicle}">${vehicle.brand} ${vehicle.model} - ${vehicle.plates}</option>`;
            });
            vehicleSelect.innerHTML = options;
        })
        .catch(error => console.error('Error:', error));
});

// Variables para el manejo de servicios
let services = [];
const serviceModal = new bootstrap.Modal(document.getElementById('serviceModal'));

// Agregar servicio
document.getElementById('addService').addEventListener('click', function() {
    serviceModal.show();
});

// Guardar servicio
document.getElementById('saveService').addEventListener('click', function() {
    const serviceSelect = document.getElementById('serviceSelect');
    const serviceId = serviceSelect.value;
    const serviceOption = serviceSelect.options[serviceSelect.selectedIndex];
    const serviceName = serviceOption.text.split('(')[0].trim();
    const servicePrice = parseFloat(serviceOption.dataset.price);
    const quantity = parseInt(document.getElementById('serviceQuantity').value);

    if (!serviceId || !quantity) {
        alert('Por favor complete todos los campos');
        return;
    }

    // Agregar servicio a la lista
    services.push({
        id: serviceId,
        name: serviceName,
        price: servicePrice,
        quantity: quantity,
        subtotal: servicePrice * quantity
    });

    // Actualizar tabla
    updateServicesTable();

    // Cerrar modal y resetear campos
    serviceModal.hide();
    serviceSelect.value = '';
    document.getElementById('serviceQuantity').value = '1';
});

// Actualizar tabla de servicios
function updateServicesTable() {
    const tbody = document.querySelector('#servicesTable tbody');
    tbody.innerHTML = '';

    services.forEach((service, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${service.name}</td>
            <td>$${service.price.toFixed(2)}</td>
            <td>${service.quantity}</td>
            <td>$${service.subtotal.toFixed(2)}</td>
            <td>
                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeService(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });

    // Actualizar campos ocultos del formulario
    const servicesInput = document.createElement('input');
    servicesInput.type = 'hidden';
    servicesInput.name = 'services';
    servicesInput.value = JSON.stringify(services);
    
    const existingInput = document.querySelector('input[name="services"]');
    if (existingInput) {
        existingInput.remove();
    }
    document.getElementById('orderForm').appendChild(servicesInput);
}

// Eliminar servicio
function removeService(index) {
    services.splice(index, 1);
    updateServicesTable();
}

// Validar formulario antes de enviar
document.getElementById('orderForm').addEventListener('submit', function(e) {
    if (services.length === 0) {
        e.preventDefault();
        alert('Debe agregar al menos un servicio');
        return;
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 