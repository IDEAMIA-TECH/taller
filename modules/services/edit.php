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

// Obtener ID del servicio
$id_service = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_service) {
    showError('ID de servicio no válido');
    redirect('index.php');
}

// Obtener datos del servicio
try {
    $stmt = $db->prepare("SELECT * FROM services WHERE id_service = ? AND id_workshop = ?");
    $stmt->execute([$id_service, getCurrentWorkshop()]);
    $service = $stmt->fetch();

    if (!$service) {
        showError('Servicio no encontrado');
        redirect('index.php');
    }
} catch (PDOException $e) {
    showError('Error al cargar el servicio');
    redirect('index.php');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = isset($_POST['price']) ? (float)$_POST['price'] : 0;
    $duration = isset($_POST['duration']) ? (int)$_POST['duration'] : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : 'active';

    // Validaciones
    if (empty($name)) {
        $errors[] = 'El nombre del servicio es requerido';
    }

    if (strlen($name) > 100) {
        $errors[] = 'El nombre no puede exceder los 100 caracteres';
    }

    if (strlen($description) > 500) {
        $errors[] = 'La descripción no puede exceder los 500 caracteres';
    }

    if ($price <= 0) {
        $errors[] = 'El precio debe ser mayor a 0';
    }

    if ($duration <= 0) {
        $errors[] = 'La duración debe ser mayor a 0';
    }

    if (!in_array($status, ['active', 'inactive'])) {
        $errors[] = 'Estado no válido';
    }

    // Si no hay errores, actualizar el servicio
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("UPDATE services SET 
                                name = ?, 
                                description = ?, 
                                price = ?, 
                                duration = ?, 
                                status = ? 
                                WHERE id_service = ? AND id_workshop = ?");
            $stmt->execute([
                $name,
                $description,
                $price,
                $duration,
                $status,
                $id_service,
                getCurrentWorkshop()
            ]);

            $success = true;
            showSuccess('Servicio actualizado correctamente');
            redirect('index.php');

        } catch (PDOException $e) {
            $errors[] = 'Error al actualizar el servicio. Por favor, intente más tarde.';
        }
    }
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Editar Servicio</h1>
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
            <form method="POST" id="serviceForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($service['name']); ?>" 
                               required maxlength="100">
                        <div class="form-text">Máximo 100 caracteres</div>
                    </div>

                    <div class="col-md-6">
                        <label for="price" class="form-label">Precio *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="price" name="price" 
                                   value="<?php echo $service['price']; ?>" 
                                   step="0.01" min="0.01" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label for="duration" class="form-label">Duración (minutos) *</label>
                        <input type="number" class="form-control" id="duration" name="duration" 
                               value="<?php echo $service['duration']; ?>" 
                               min="1" required>
                    </div>

                    <div class="col-md-6">
                        <label for="status" class="form-label">Estado *</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="active" <?php echo $service['status'] === 'active' ? 'selected' : ''; ?>>
                                Activo
                            </option>
                            <option value="inactive" <?php echo $service['status'] === 'inactive' ? 'selected' : ''; ?>>
                                Inactivo
                            </option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">Descripción</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3" maxlength="500"><?php echo htmlspecialchars($service['description']); ?></textarea>
                        <div class="form-text">Máximo 500 caracteres</div>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar Cambios
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

<script>
document.getElementById('serviceForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const price = document.getElementById('price').value;
    const duration = document.getElementById('duration').value;
    const description = document.getElementById('description').value.trim();

    if (!name) {
        e.preventDefault();
        alert('El nombre del servicio es requerido');
        return;
    }

    if (name.length > 100) {
        e.preventDefault();
        alert('El nombre no puede exceder los 100 caracteres');
        return;
    }

    if (description.length > 500) {
        e.preventDefault();
        alert('La descripción no puede exceder los 500 caracteres');
        return;
    }

    if (price <= 0) {
        e.preventDefault();
        alert('El precio debe ser mayor a 0');
        return;
    }

    if (duration <= 0) {
        e.preventDefault();
        alert('La duración debe ser mayor a 0');
        return;
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 