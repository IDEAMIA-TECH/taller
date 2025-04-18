<?php
require_once '../../includes/config.php';

// Si el usuario ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    redirect('templates/dashboard.php');
}

$error = '';
$success = '';

// Procesar formulario de registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $workshop_name = trim($_POST['workshop_name'] ?? '');
    $workshop_address = trim($_POST['workshop_address'] ?? '');
    $workshop_phone = trim($_POST['workshop_phone'] ?? '');
    $workshop_rfc = trim($_POST['workshop_rfc'] ?? '');

    try {
        // Validar campos
        if (empty($username) || empty($password) || empty($confirm_password) || 
            empty($full_name) || empty($email) || empty($workshop_name) || 
            empty($workshop_address) || empty($workshop_phone) || empty($workshop_rfc)) {
            throw new Exception('Todos los campos son requeridos');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Las contraseñas no coinciden');
        }

        if (strlen($password) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no es válido');
        }

        // Verificar si el usuario ya existe
        $stmt = $db->prepare("SELECT id_user FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            throw new Exception('El usuario o correo electrónico ya está registrado');
        }

        // Verificar si el taller ya existe
        $stmt = $db->prepare("SELECT id_workshop FROM workshops WHERE rfc = ?");
        $stmt->execute([$workshop_rfc]);
        if ($stmt->fetch()) {
            throw new Exception('El RFC del taller ya está registrado');
        }

        // Iniciar transacción
        $db->beginTransaction();

        try {
            // Crear taller
            $stmt = $db->prepare("
                INSERT INTO workshops 
                (name, address, phone, email, rfc, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $workshop_name,
                $workshop_address,
                $workshop_phone,
                $email,
                $workshop_rfc
            ]);
            $id_workshop = $db->lastInsertId();

            // Crear usuario administrador
            $stmt = $db->prepare("
                INSERT INTO users 
                (id_workshop, username, password, full_name, email, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'admin', 'active', NOW())
            ");
            $stmt->execute([
                $id_workshop,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $full_name,
                $email
            ]);

            // Confirmar transacción
            $db->commit();
            $success = 'Registro exitoso. Ahora puede iniciar sesión.';

        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Incluir el encabezado sin menú
include '../../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-8">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="Logo" class="img-fluid mb-3" style="max-height: 100px;">
                        <h4 class="card-title">Registro de Taller</h4>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <h5 class="mb-3">Datos del Administrador</h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Usuario</label>
                                <input type="text" class="form-control" id="username" name="username" required 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Contraseña</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirmar Contraseña</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                        </div>

                        <h5 class="mb-3 mt-4">Datos del Taller</h5>
                        <div class="mb-3">
                            <label for="workshop_name" class="form-label">Nombre del Taller</label>
                            <input type="text" class="form-control" id="workshop_name" name="workshop_name" required 
                                   value="<?php echo htmlspecialchars($_POST['workshop_name'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="workshop_address" class="form-label">Dirección</label>
                            <textarea class="form-control" id="workshop_address" name="workshop_address" required><?php 
                                echo htmlspecialchars($_POST['workshop_address'] ?? ''); 
                            ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="workshop_phone" class="form-label">Teléfono</label>
                                <input type="text" class="form-control" id="workshop_phone" name="workshop_phone" required 
                                       value="<?php echo htmlspecialchars($_POST['workshop_phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="workshop_rfc" class="form-label">RFC</label>
                                <input type="text" class="form-control" id="workshop_rfc" name="workshop_rfc" required 
                                       value="<?php echo htmlspecialchars($_POST['workshop_rfc'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Registrar</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="login.php">¿Ya tiene una cuenta? Inicie sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 