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

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $rfc = trim($_POST['rfc'] ?? '');

    // Validaciones
    if (empty($name)) {
        $errors[] = 'El nombre es requerido';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no es válido';
    }

    if (!empty($rfc) && !preg_match('/^[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|1[0-9]|2[0-9]|3[0-1])[A-Z0-9]{2}[0-9A]$/', $rfc)) {
        $errors[] = 'El RFC no es válido';
    }

    // Si no hay errores, guardar el cliente
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO clients (id_workshop, name, phone, email, address, rfc) 
                                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                getCurrentWorkshop(),
                $name,
                $phone,
                $email,
                $address,
                $rfc
            ]);

            $success = true;
            showSuccess('Cliente agregado correctamente');
            redirect('index.php');

        } catch (PDOException $e) {
            $errors[] = 'Error al guardar el cliente. Por favor, intente más tarde.';
        }
    }
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Nuevo Cliente</h1>
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
            <form method="POST" id="clientForm">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               required>
                    </div>

                    <div class="col-md-6">
                        <label for="phone" class="form-label">Teléfono</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label">Correo Electrónico</label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    </div>

                    <div class="col-md-6">
                        <label for="rfc" class="form-label">RFC</label>
                        <input type="text" class="form-control" id="rfc" name="rfc" 
                               value="<?php echo isset($_POST['rfc']) ? htmlspecialchars($_POST['rfc']) : ''; ?>"
                               placeholder="XAXX010101000">
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label">Dirección</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php 
                            echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; 
                        ?></textarea>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Guardar
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
document.getElementById('clientForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const rfc = document.getElementById('rfc').value.trim();

    if (!name) {
        e.preventDefault();
        alert('El nombre es requerido');
        return;
    }

    if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        e.preventDefault();
        alert('El correo electrónico no es válido');
        return;
    }

    if (rfc && !rfc.match(/^[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|1[0-9]|2[0-9]|3[0-1])[A-Z0-9]{2}[0-9A]$/)) {
        e.preventDefault();
        alert('El RFC no es válido');
        return;
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 