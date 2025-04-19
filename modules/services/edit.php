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
    $sql = "SELECT * FROM services WHERE id_service = '" . addslashes($id_service) . "' AND id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";
    $result = $db->query($sql);
    $service = $result->fetch(PDO::FETCH_ASSOC);

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
            $sql = "UPDATE services SET 
                    name = '" . addslashes($name) . "', 
                    description = '" . addslashes($description) . "', 
                    price = '" . addslashes($price) . "', 
                    duration = '" . addslashes($duration) . "', 
                    status = '" . addslashes($status) . "' 
                    WHERE id_service = '" . addslashes($id_service) . "' 
                    AND id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";
            
            $db->query($sql);

            $success = true;
            $_SESSION['success_message'] = 'Servicio actualizado correctamente';
            redirect(APP_URL . '/modules/services/index.php');

        } catch (PDOException $e) {
            $errors[] = 'Error al actualizar el servicio. Por favor, intente más tarde.';
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
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/vehicles/">
                            <i class="fas fa-car"></i> Vehículos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo APP_URL; ?>/modules/services/">
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
                <h1 class="h3 mb-0">Editar Servicio</h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

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
        </main>
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