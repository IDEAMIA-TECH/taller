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
$vehicle = null;

// Obtener el ID del vehículo
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    showError('Vehículo no válido');
    redirect('index.php');
}

try {
    // Obtener información del vehículo
    $query = "SELECT v.*, c.name as client_name, c.phone as client_phone, c.email as client_email
              FROM vehicles v 
              JOIN clients c ON v.id_client = c.id_client 
              WHERE v.id_vehicle = '" . addslashes($id) . "' 
              AND v.id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";
    
    $result = $db->query($query);
    $vehicle = $result->fetch(PDO::FETCH_ASSOC);

    if (!$vehicle) {
        showError('Vehículo no encontrado');
        redirect('index.php');
    }

    // Obtener lista de clientes para el select
    $clientsQuery = "SELECT id_client, name FROM clients 
                    WHERE id_workshop = '" . addslashes(getCurrentWorkshop()) . "' 
                    ORDER BY name";
    $result = $db->query($clientsQuery);
    $clients = $result->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error en edit.php: " . $e->getMessage());
    showError('Error al cargar la información del vehículo. Por favor, intente más tarde.');
    redirect('index.php');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $id_client = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
    $brand = trim($_POST['brand'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 0;
    $color = trim($_POST['color'] ?? '');
    $plates = trim($_POST['plates'] ?? '');
    $vin = trim($_POST['vin'] ?? '');
    $last_mileage = isset($_POST['last_mileage']) ? (int)$_POST['last_mileage'] : null;

    // Validaciones
    if ($id_client <= 0) {
        $errors[] = 'Debe seleccionar un cliente';
    }

    if (empty($brand)) {
        $errors[] = 'La marca es requerida';
    }

    if (empty($model)) {
        $errors[] = 'El modelo es requerido';
    }

    if ($year < 1900 || $year > date('Y')) {
        $errors[] = 'El año no es válido';
    }

    if (!empty($vin) && strlen($vin) != 17) {
        $errors[] = 'El número de serie (VIN) debe tener 17 caracteres';
    }

    // Si no hay errores, actualizar el vehículo
    if (empty($errors)) {
        try {
            $sql = "UPDATE vehicles 
                    SET id_client = '" . addslashes($id_client) . "',
                        brand = '" . addslashes($brand) . "',
                        model = '" . addslashes($model) . "',
                        year = '" . addslashes($year) . "',
                        color = '" . addslashes($color) . "',
                        plates = '" . addslashes($plates) . "',
                        vin = '" . addslashes($vin) . "',
                        last_mileage = " . ($last_mileage !== null ? "'" . addslashes($last_mileage) . "'" : "NULL") . "
                    WHERE id_vehicle = '" . addslashes($id) . "' 
                    AND id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";

            $db->query($sql);

            $_SESSION['alert'] = [
                'type' => 'success',
                'message' => 'Vehículo actualizado correctamente'
            ];
            redirect('view.php?id=' . $id);

        } catch (PDOException $e) {
            error_log("Error en edit.php: " . $e->getMessage());
            $errors[] = 'Error al actualizar el vehículo. Por favor, intente más tarde.';
        }
    }
}

// Incluir el encabezado
include '../../includes/header.php';
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
                        <a class="nav-link" href="<?php echo APP_URL; ?>/templates/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <?php if (hasRole('admin') || hasRole('receptionist') || hasRole('super_admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/clients/">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo APP_URL; ?>/modules/vehicles/">
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
                    <?php endif; ?>
                    <?php if (hasRole('admin') || hasRole('super_admin')): ?>
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Editar Vehículo</h1>
                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
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
                    <form method="POST" id="vehicleForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="id_client" class="form-label">Cliente *</label>
                                <select class="form-select" id="id_client" name="id_client" required>
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id_client']; ?>" 
                                                <?php echo (isset($_POST['id_client']) ? $_POST['id_client'] : $vehicle['id_client']) == $client['id_client'] ? 
                                                          'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="brand" class="form-label">Marca *</label>
                                <select class="form-select" id="brand" name="brand" required>
                                    <option value="<?php echo htmlspecialchars($vehicle['brand']); ?>" selected>
                                        <?php echo htmlspecialchars($vehicle['brand']); ?>
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="model" class="form-label">Modelo *</label>
                                <select class="form-select" id="model" name="model" required>
                                    <option value="<?php echo htmlspecialchars($vehicle['model']); ?>" selected>
                                        <?php echo htmlspecialchars($vehicle['model']); ?>
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="year" class="form-label">Año *</label>
                                <input type="number" class="form-control" id="year" name="year" 
                                       value="<?php echo isset($_POST['year']) ? (int)$_POST['year'] : $vehicle['year']; ?>" 
                                       min="1900" max="<?php echo date('Y'); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="color" class="form-label">Color</label>
                                <input type="text" class="form-control" id="color" name="color" 
                                       value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : htmlspecialchars($vehicle['color']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="plates" class="form-label">Placas</label>
                                <input type="text" class="form-control" id="plates" name="plates" 
                                       value="<?php echo isset($_POST['plates']) ? htmlspecialchars($_POST['plates']) : htmlspecialchars($vehicle['plates']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="vin" class="form-label">Número de Serie (VIN)</label>
                                <input type="text" class="form-control" id="vin" name="vin" 
                                       value="<?php echo isset($_POST['vin']) ? htmlspecialchars($_POST['vin']) : htmlspecialchars($vehicle['vin']); ?>"
                                       maxlength="17">
                            </div>

                            <div class="col-md-6">
                                <label for="last_mileage" class="form-label">Último Kilometraje</label>
                                <input type="number" class="form-control" id="last_mileage" name="last_mileage" 
                                       value="<?php echo isset($_POST['last_mileage']) ? (int)$_POST['last_mileage'] : $vehicle['last_mileage']; ?>"
                                       min="0">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
                                </button>
                                <a href="view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const brandSelect = document.getElementById('brand');
    const modelSelect = document.getElementById('model');
    const currentBrand = '<?php echo htmlspecialchars($vehicle['brand']); ?>';
    const currentModel = '<?php echo htmlspecialchars($vehicle['model']); ?>';

    // Cargar marcas al iniciar
    fetch('get_brands.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Ordenar las marcas alfabéticamente
            data.sort((a, b) => a.Make_Name.localeCompare(b.Make_Name));
            
            // Agregar todas las marcas al select
            data.forEach(brand => {
                if (brand.Make_Name !== currentBrand) {
                    const option = document.createElement('option');
                    option.value = brand.Make_Name;
                    option.textContent = brand.Make_Name;
                    brandSelect.appendChild(option);
                }
            });
        })
        .catch(error => {
            console.error('Error al cargar las marcas:', error);
            showError('Error al cargar las marcas: ' + error.message);
        });

    brandSelect.addEventListener('change', function() {
        const makeName = this.value;
        modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
        
        if (makeName === currentBrand) {
            // Si se selecciona la marca actual, mostrar el modelo actual
            const option = document.createElement('option');
            option.value = currentModel;
            option.textContent = currentModel;
            option.selected = true;
            modelSelect.appendChild(option);
        } else if (makeName) {
            // Si se selecciona una nueva marca, cargar sus modelos
            loadModels(makeName);
        }
    });

    function loadModels(makeName) {
        fetch(`get_models.php?makeName=${encodeURIComponent(makeName)}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Ordenar los modelos alfabéticamente
                data.sort((a, b) => a.Model_Name.localeCompare(b.Model_Name));
                
                data.forEach(model => {
                    const option = document.createElement('option');
                    option.value = model.Model_Name;
                    option.textContent = model.Model_Name;
                    modelSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error al cargar los modelos:', error);
                showError('Error al cargar los modelos: ' + error.message);
            });
    }

    // Función para mostrar errores
    function showError(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
    }
});

document.getElementById('vehicleForm').addEventListener('submit', function(e) {
    const brand = document.getElementById('brand').value.trim();
    const model = document.getElementById('model').value.trim();
    const year = document.getElementById('year').value;
    const vin = document.getElementById('vin').value.trim();

    if (!brand) {
        e.preventDefault();
        alert('La marca es requerida');
        return;
    }

    if (!model) {
        e.preventDefault();
        alert('El modelo es requerido');
        return;
    }

    if (year < 1900 || year > <?php echo date('Y'); ?>) {
        e.preventDefault();
        alert('El año no es válido');
        return;
    }

    if (vin && vin.length !== 17) {
        e.preventDefault();
        alert('El número de serie (VIN) debe tener 17 caracteres');
        return;
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 