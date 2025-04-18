<?php
require_once '../../includes/config.php';

// Si el usuario ya está autenticado, redirigir al dashboard
if (isAuthenticated()) {
    redirect('templates/dashboard.php');
}

$error = '';
$success = '';

// Obtener token
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $_SESSION['error_message'] = 'Token de recuperación no válido';
    redirect('login.php');
}

try {
    // Verificar token
    $stmt = $db->prepare("
        SELECT pr.*, u.email, u.full_name, w.name as workshop_name
        FROM password_resets pr
        JOIN users u ON pr.id_user = u.id_user
        JOIN workshops w ON u.id_workshop = w.id_workshop
        WHERE pr.token = ? AND pr.expires_at > NOW() AND pr.used = 0
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        throw new Exception('El enlace de recuperación no es válido o ha expirado');
    }

    // Procesar formulario de restablecimiento
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // Validar contraseñas
        if (empty($password) || empty($confirm_password)) {
            throw new Exception('Por favor ingrese y confirme su nueva contraseña');
        }

        if ($password !== $confirm_password) {
            throw new Exception('Las contraseñas no coinciden');
        }

        if (strlen($password) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres');
        }

        // Iniciar transacción
        $db->beginTransaction();

        try {
            // Actualizar contraseña
            $stmt = $db->prepare("
                UPDATE users 
                SET password = ?
                WHERE id_user = ?
            ");
            $stmt->execute([
                password_hash($password, PASSWORD_DEFAULT),
                $reset['id_user']
            ]);

            // Marcar token como usado
            $stmt = $db->prepare("
                UPDATE password_resets 
                SET used = 1, used_at = NOW()
                WHERE id_reset = ?
            ");
            $stmt->execute([$reset['id_reset']]);

            // Confirmar transacción
            $db->commit();

            // Enviar notificación por correo
            $subject = "Contraseña Actualizada - " . $reset['workshop_name'];
            $message = "Estimado " . $reset['full_name'] . ",\n\n";
            $message .= "Su contraseña ha sido actualizada exitosamente.\n\n";
            $message .= "Si no realizó este cambio, por favor contacte al administrador.\n\n";
            $message .= "Atentamente,\n";
            $message .= "El equipo de " . $reset['workshop_name'];

            mail($reset['email'], $subject, $message);

            $_SESSION['success_message'] = 'Su contraseña ha sido actualizada exitosamente';
            redirect('login.php');

        } catch (Exception $e) {
            // Revertir transacción en caso de error
            $db->rollBack();
            throw $e;
        }
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = $e->getMessage();
    redirect('login.php');
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
                        <h4 class="card-title">Restablecer Contraseña</h4>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="password" class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>

                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">Restablecer Contraseña</button>
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