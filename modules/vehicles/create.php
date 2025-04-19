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
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

// Obtener lista de clientes para el select
try {
    $sql = "SELECT id_client, name FROM clients WHERE id_workshop = :id_workshop ORDER BY name";
    $stmt = $db->query($sql, ['id_workshop' => getCurrentWorkshop()]);
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    showError('Error al cargar los clientes');
    redirect('index.php');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Procesando formulario de vehículo");
    
    // Obtener datos del formulario
    $id_client = isset($_POST['id_client']) ? (int)$_POST['id_client'] : 0;
    $brand = trim($_POST['brand']);
    $model = trim($_POST['model']);
    $year = (int)$_POST['year'];
    $color = trim($_POST['color']);
    $plates = trim($_POST['plates']);
    $vin = trim($_POST['vin']);
    $last_mileage = (int)$_POST['last_mileage'];

    // Validar datos
    if (empty($brand) || empty($model) || empty($year) || empty($color)) {
        error_log("Datos incompletos en el formulario");
        showError('Por favor complete todos los campos requeridos');
    } else {
        try {
            error_log("Preparando inserción de vehículo");
            $sql = "INSERT INTO vehicles (id_client, id_workshop, brand, model, year, color, plates, vin, last_mileage) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            error_log("SQL: " . $sql);
            error_log("Parámetros: " . print_r([$id_client, getCurrentWorkshop(), $brand, $model, $year, $color, $plates, $vin, $last_mileage], true));
            
            $stmt = $db->query($sql, [$id_client, getCurrentWorkshop(), $brand, $model, $year, $color, $plates, $vin, $last_mileage]);
            
            error_log("Vehículo agregado correctamente");
            $_SESSION['success_message'] = 'Vehículo agregado correctamente';
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log("Error PDO al insertar vehículo: " . $e->getMessage());
            showError('Error al guardar el vehículo: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error general al insertar vehículo: " . $e->getMessage());
            showError('Error al procesar la solicitud');
        }
    }
}

// Incluir el encabezado
include '../../includes/header.php';
?>

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
                <h1 class="h3 mb-0">Nuevo Vehículo</h1>
                <a href="<?php echo $client_id ? '../clients/view.php?id=' . $client_id : 'index.php'; ?>" 
                   class="btn btn-outline-secondary">
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
                                <select class="form-select" id="id_client" name="id_client" required 
                                        <?php echo $client_id ? 'disabled' : ''; ?>>
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id_client']; ?>" 
                                                <?php echo ($client_id == $client['id_client'] || 
                                                          (isset($_POST['id_client']) && $_POST['id_client'] == $client['id_client'])) ? 
                                                          'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($client_id): ?>
                                    <input type="hidden" name="id_client" value="<?php echo $client_id; ?>">
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6">
                                <label for="brand" class="form-label">Marca *</label>
                                <select class="form-select" id="brand" name="brand" required>
                                    <option value="">Seleccione una marca</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="model" class="form-label">Modelo *</label>
                                <select class="form-select" id="model" name="model" required disabled>
                                    <option value="">Seleccione un modelo</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="year" class="form-label">Año *</label>
                                <input type="number" class="form-control" id="year" name="year" 
                                       value="<?php echo isset($_POST['year']) ? (int)$_POST['year'] : date('Y'); ?>" 
                                       min="1900" max="<?php echo date('Y'); ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="color" class="form-label">Color</label>
                                <input type="text" class="form-control" id="color" name="color" 
                                       value="<?php echo isset($_POST['color']) ? htmlspecialchars($_POST['color']) : ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="plates" class="form-label">Placas</label>
                                <input type="text" class="form-control" id="plates" name="plates" 
                                       value="<?php echo isset($_POST['plates']) ? htmlspecialchars($_POST['plates']) : ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="vin" class="form-label">Número de Serie (VIN)</label>
                                <input type="text" class="form-control" id="vin" name="vin" 
                                       value="<?php echo isset($_POST['vin']) ? htmlspecialchars($_POST['vin']) : ''; ?>"
                                       maxlength="17">
                            </div>

                            <div class="col-md-6">
                                <label for="last_mileage" class="form-label">Último Kilometraje</label>
                                <input type="number" class="form-control" id="last_mileage" name="last_mileage" 
                                       value="<?php echo isset($_POST['last_mileage']) ? (int)$_POST['last_mileage'] : ''; ?>"
                                       min="0">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar
                                </button>
                                <a href="<?php echo $client_id ? '../clients/view.php?id=' . $client_id : 'index.php'; ?>" 
                                   class="btn btn-outline-secondary">
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

    // Cargar marcas al iniciar
    fetch('get_brands.php')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Ordenar las marcas alfabéticamente
            data.sort((a, b) => a.Make_Name.localeCompare(b.Make_Name));
            
            data.forEach(brand => {
                const option = document.createElement('option');
                option.value = brand.Make_ID;
                option.textContent = brand.Make_Name;
                brandSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error al cargar las marcas:', error);
            showError('Error al cargar las marcas: ' + error.message);
        });

    brandSelect.addEventListener('change', function() {
        const makeId = this.value;
        modelSelect.innerHTML = '<option value="">Seleccione un modelo</option>';
        modelSelect.disabled = true;
        
        if (makeId) {
            fetch(`get_models.php?makeId=${encodeURIComponent(makeId)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // Ordenar los modelos alfabéticamente
                    data.sort((a, b) => a.Model_Name.localeCompare(b.Model_Name));
                    
                    data.forEach(model => {
                        const option = document.createElement('option');
                        option.value = model.Model_Name; // Usamos el nombre del modelo como valor
                        option.textContent = model.Model_Name;
                        modelSelect.appendChild(option);
                    });
                    modelSelect.disabled = false;
                })
                .catch(error => {
                    console.error('Error al cargar los modelos:', error);
                    showError('Error al cargar los modelos: ' + error.message);
                });
        }
    });

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