<?php
require_once '../../includes/config.php';

// Array para almacenar los logs
$logs = [];
$logs[] = "=== Inicio del proceso de login ===";
$logs[] = "URL actual: " . $_SERVER['REQUEST_URI'];
$logs[] = "Método de solicitud: " . $_SERVER['REQUEST_METHOD'];

// Si el usuario ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    $logs[] = "Usuario ya autenticado, redirigiendo a dashboard";
    redirect('templates/dashboard.php');
}

$error = '';

// Procesar formulario de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $logs[] = "Método POST detectado";
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $logs[] = "Datos recibidos - Usuario: " . $username;
    $logs[] = "Datos recibidos - Contraseña: " . (!empty($password) ? "***" : "vacía");

    try {
        // Validar campos
        if (empty($username) || empty($password)) {
            $logs[] = "Error: Campos vacíos";
            throw new Exception('Por favor ingrese usuario y contraseña');
        }

        // Buscar usuario
        $logs[] = "Buscando usuario en la base de datos";
        $stmt = $db->prepare("
            SELECT u.*, w.name as workshop_name, w.status as workshop_status
            FROM users u
            JOIN workshops w ON u.id_workshop = w.id_workshop
            WHERE u.username = ? AND u.status = 'active'
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user) {
            $logs[] = "Error: Usuario no encontrado o inactivo";
            throw new Exception('Usuario o contraseña incorrectos');
        }

        $logs[] = "Usuario encontrado: " . $user['username'];

        // Verificar contraseña
        if (!password_verify($password, $user['password'])) {
            $logs[] = "Error: Contraseña incorrecta";
            throw new Exception('Usuario o contraseña incorrectos');
        }

        // Verificar estado del taller
        if ($user['workshop_status'] !== 'active') {
            $logs[] = "Error: Taller inactivo";
            throw new Exception('El taller se encuentra inactivo. Por favor contacte al administrador.');
        }

        $logs[] = "Iniciando sesión para el usuario: " . $user['username'];

        // Iniciar sesión
        $_SESSION['id_user'] = $user['id_user'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['id_workshop'] = $user['id_workshop'];
        $_SESSION['workshop_name'] = $user['workshop_name'];

        // Actualizar último login
        $logs[] = "Actualizando último login";
        $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id_user = ?");
        $stmt->execute([$user['id_user']]);

        $logs[] = "Redirigiendo al dashboard";
        // Redirigir al dashboard
        redirect('templates/dashboard.php');

    } catch (Exception $e) {
        $logs[] = "Error en el proceso de login: " . $e->getMessage();
        $error = $e->getMessage();
    }
}

// Incluir el encabezado sin menú
include '../../includes/header.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: none;
            border: none;
            text-align: center;
            padding: 30px 0 0;
        }
        .card-body {
            padding: 30px;
        }
        .form-control {
            padding: 12px;
            border-radius: 5px;
        }
        .btn-primary {
            padding: 12px;
            border-radius: 5px;
        }
        .logo {
            max-width: 150px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header">
                    <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="<?php echo APP_NAME; ?>" class="logo">
                    <h4 class="mb-4">Iniciar Sesión</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="remember" name="remember">
                            <label class="form-check-label" for="remember">Recordarme</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </button>
                    </form>
                </div>
                <div class="card-footer text-center">
                    <a href="<?php echo APP_URL; ?>/forgot-password" class="text-muted">¿Olvidaste tu contraseña?</a>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="<?php echo APP_URL; ?>" class="text-muted">
                    <i class="fas fa-arrow-left"></i> Volver al inicio
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para manejar el envío del formulario
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            console.log('Formulario enviado');
            
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            if (!username || !password) {
                console.error('Error: Campos vacíos');
                alert('Por favor complete todos los campos');
                e.preventDefault();
                return false;
            }
            
            console.log('Enviando formulario...');
            return true;
        });

        // Mostrar logs iniciales
        const logs = <?php echo json_encode($logs); ?>;
        console.log('Logs iniciales:', logs);
    </script>
</body>
</html> 