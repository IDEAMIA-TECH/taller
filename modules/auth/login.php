<?php
require_once '../../includes/config.php';

// Iniciar el log
error_log("=== Inicio del proceso de login ===");

// Si el usuario ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    error_log("Usuario ya autenticado, redirigiendo a dashboard");
    redirect('templates/dashboard.php');
}

$error = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Método POST detectado");
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    error_log("Datos recibidos - Usuario: " . $username);

    try {
        // Validar campos
        if (empty($username) || empty($password)) {
            error_log("Error: Campos vacíos");
            throw new Exception('Por favor ingrese usuario y contraseña');
        }

        // Buscar usuario
        error_log("Buscando usuario en la base de datos");
        $stmt = $db->prepare("
            SELECT u.*, w.name as workshop_name, w.status as workshop_status
            FROM users u
            JOIN workshops w ON u.id_workshop = w.id_workshop
            WHERE u.username = ? AND u.status = 'active'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            error_log("Error: Usuario no encontrado o inactivo");
            throw new Exception('Usuario o contraseña incorrectos');
        }

        error_log("Usuario encontrado: " . $user['username']);

        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            error_log("Error: Contraseña incorrecta");
            throw new Exception('Usuario o contraseña incorrectos');
        }

        // Verificar estado del taller
        if ($user['workshop_status'] !== 'active') {
            error_log("Error: Taller inactivo");
            throw new Exception('El taller se encuentra inactivo. Por favor contacte al administrador.');
        }

        error_log("Iniciando sesión para el usuario: " . $user['username']);

        // Iniciar sesión
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['id_workshop'] = $user['id_workshop'];
        $_SESSION['workshop_name'] = $user['workshop_name'];

        // Actualizar último login
        error_log("Actualizando último login");
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id_user = ?");
        $stmt->execute([$user['id_user']]);

        error_log("Redirigiendo al dashboard");
        // Redirigir al dashboard
        redirect('templates/dashboard.php');

    } catch (Exception $e) {
        error_log("Error en el proceso de login: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

// Incluir el encabezado sin menú
include '../../includes/header.php';
?>

<div class="container">
    <div class="row justify-content-center mt-5">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="Logo" class="img-fluid mb-3" style="max-height: 100px;">
                        <h4 class="card-title">Iniciar Sesión</h4>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="forgot_password.php">¿Olvidó su contraseña?</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 