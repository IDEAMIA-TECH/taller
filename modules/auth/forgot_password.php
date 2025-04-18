<?php
require_once '../../includes/config.php';

// Si el usuario ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    redirect('templates/dashboard.php');
}

$error = '';
$success = '';

// Procesar formulario de recuperación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    try {
        // Validar email
        if (empty($email)) {
            throw new Exception('Por favor ingrese su correo electrónico');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('El correo electrónico no es válido');
        }

        // Buscar usuario
        $stmt = $db->prepare("
            SELECT u.*, w.name as workshop_name
            FROM users u
            JOIN workshops w ON u.id_workshop = w.id_workshop
            WHERE u.email = ? AND u.status = 'active'
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new Exception('No se encontró una cuenta asociada a este correo electrónico');
        }

        // Generar token de recuperación
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Guardar token en la base de datos
        $stmt = $db->prepare("
            INSERT INTO password_resets 
            (id_user, token, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user['id_user'], $token, $expires]);

        // Enviar correo con enlace de recuperación
        $reset_link = SITE_URL . "/modules/auth/reset_password.php?token=" . $token;
        $subject = "Recuperación de Contraseña - " . $user['workshop_name'];
        $message = "Estimado " . $user['full_name'] . ",\n\n";
        $message .= "Hemos recibido una solicitud para restablecer su contraseña.\n\n";
        $message .= "Para continuar con el proceso, haga clic en el siguiente enlace:\n";
        $message .= $reset_link . "\n\n";
        $message .= "Este enlace expirará en 1 hora.\n\n";
        $message .= "Si no solicitó este cambio, puede ignorar este correo.\n\n";
        $message .= "Atentamente,\n";
        $message .= "El equipo de " . $user['workshop_name'];

        // Enviar correo
        if (mail($email, $subject, $message)) {
            $success = 'Se ha enviado un correo con instrucciones para restablecer su contraseña.';
        } else {
            throw new Exception('Error al enviar el correo de recuperación');
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
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="Logo" class="img-fluid mb-3" style="max-height: 100px;">
                        <h4 class="card-title">Recuperar Contraseña</h4>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="email" class="form-label">Correo Electrónico</label>
                            <input type="email" class="form-control" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Enviar Instrucciones</button>
                        </div>
                    </form>

                    <div class="text-center mt-3">
                        <a href="login.php">Volver al inicio de sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 