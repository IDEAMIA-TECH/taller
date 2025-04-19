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
    $stmt = $db->prepare("SELECT id_client, name FROM clients WHERE id_workshop = ? ORDER BY name");
    $stmt->execute([getCurrentWorkshop()]);
    $clients = $stmt->fetchAll();
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
                        <input type="text" class="form-control" id="brand" name="brand" 
                               value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : ''; ?>" 
                               required>
                    </div>

                    <div class="col-md-6">
                        <label for="model" class="form-label">Modelo *</label>
                        <input type="text" class="form-control" id="model" name="model" 
                               value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : ''; ?>" 
                               required>
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
</div>

<script>
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