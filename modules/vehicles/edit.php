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
            $stmt = $db->prepare("UPDATE vehicles 
                                SET id_client = ?, brand = ?, model = ?, year = ?, 
                                    color = ?, plates = ?, vin = ?, last_mileage = ? 
                                WHERE id_vehicle = ? AND id_workshop = ?");
            $stmt->execute([
                $id_client,
                $brand,
                $model,
                $year,
                $color,
                $plates,
                $vin,
                $last_mileage,
                $id,
                getCurrentWorkshop()
            ]);

            showSuccess('Vehículo actualizado correctamente');
            redirect('view.php?id=' . $id);

        } catch (PDOException $e) {
            $errors[] = 'Error al actualizar el vehículo. Por favor, intente más tarde.';
        }
    }
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
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
                        <input type="text" class="form-control" id="brand" name="brand" 
                               value="<?php echo isset($_POST['brand']) ? htmlspecialchars($_POST['brand']) : htmlspecialchars($vehicle['brand']); ?>" 
                               required>
                    </div>

                    <div class="col-md-6">
                        <label for="model" class="form-label">Modelo *</label>
                        <input type="text" class="form-control" id="model" name="model" 
                               value="<?php echo isset($_POST['model']) ? htmlspecialchars($_POST['model']) : htmlspecialchars($vehicle['model']); ?>" 
                               required>
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