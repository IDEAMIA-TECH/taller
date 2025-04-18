<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    redirect('templates/login.php');
}

// Obtener planes de suscripción
$stmt = $db->prepare("
    SELECT * FROM subscription_plans 
    ORDER BY price ASC
");
$stmt->execute();
$plans = $stmt->fetchAll();

// Incluir el encabezado
include '../../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Planes de Suscripción</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#planModal">
                    <i class="fas fa-plus"></i> Nuevo Plan
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

            <!-- Lista de Planes -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Precio</th>
                                    <th>Duración</th>
                                    <th>Usuarios</th>
                                    <th>Vehículos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($plans as $plan): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($plan['name']); ?></td>
                                        <td><?php echo htmlspecialchars($plan['description']); ?></td>
                                        <td>$<?php echo number_format($plan['price'], 2); ?></td>
                                        <td><?php echo $plan['duration_months']; ?> meses</td>
                                        <td><?php echo $plan['max_users']; ?></td>
                                        <td><?php echo $plan['max_vehicles']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $plan['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($plan['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        onclick="editPlan(<?php echo $plan['id_plan']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="deletePlan(<?php echo $plan['id_plan']; ?>)">
                                                    <i class="fas fa-trash"></i>
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

<!-- Modal para Agregar/Editar Plan -->
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Plan de Suscripción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="planForm" action="save_plan.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="id_plan" id="id_plan">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Nombre</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="price" class="form-label">Precio Mensual</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="price" name="price" 
                                           step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="duration_months" class="form-label">Duración (meses)</label>
                                <input type="number" class="form-control" id="duration_months" 
                                       name="duration_months" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_users" class="form-label">Máximo de Usuarios</label>
                                <input type="number" class="form-control" id="max_users" 
                                       name="max_users" min="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="max_vehicles" class="form-label">Máximo de Vehículos</label>
                                <input type="number" class="form-control" id="max_vehicles" 
                                       name="max_vehicles" min="1" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="features" class="form-label">Características</label>
                        <div id="featuresContainer">
                            <!-- Las características se agregarán dinámicamente -->
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" 
                                onclick="addFeature()">
                            <i class="fas fa-plus"></i> Agregar Característica
                        </button>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Estado</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
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
// Función para agregar una característica
function addFeature() {
    const container = document.getElementById('featuresContainer');
    const div = document.createElement('div');
    div.className = 'input-group mb-2';
    div.innerHTML = `
        <input type="text" class="form-control" name="features[]" required>
        <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    container.appendChild(div);
}

// Función para editar un plan
function editPlan(id) {
    // Obtener datos del plan mediante AJAX
    fetch(`get_plan.php?id=${id}`)
        .then(response => response.json())
        .then(plan => {
            // Llenar el formulario con los datos del plan
            document.getElementById('id_plan').value = plan.id_plan;
            document.getElementById('name').value = plan.name;
            document.getElementById('description').value = plan.description;
            document.getElementById('price').value = plan.price;
            document.getElementById('duration_months').value = plan.duration_months;
            document.getElementById('max_users').value = plan.max_users;
            document.getElementById('max_vehicles').value = plan.max_vehicles;
            document.getElementById('status').value = plan.status;

            // Limpiar y agregar características
            const container = document.getElementById('featuresContainer');
            container.innerHTML = '';
            const features = JSON.parse(plan.features);
            features.forEach(feature => {
                const div = document.createElement('div');
                div.className = 'input-group mb-2';
                div.innerHTML = `
                    <input type="text" class="form-control" name="features[]" value="${feature}" required>
                    <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                container.appendChild(div);
            });

            // Mostrar el modal
            const modal = new bootstrap.Modal(document.getElementById('planModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los datos del plan');
        });
}

// Función para eliminar un plan
function deletePlan(id) {
    if (confirm('¿Está seguro de eliminar este plan?')) {
        // Crear formulario para enviar la petición POST
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'delete_plan.php';

        // Agregar campo oculto con el ID del plan
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'id_plan';
        input.value = id;
        form.appendChild(input);

        // Agregar el formulario al documento y enviarlo
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?php include '../../../includes/footer.php'; ?> 