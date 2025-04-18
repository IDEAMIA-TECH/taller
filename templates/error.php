<?php
require_once '../includes/config.php';

// Obtener el código de error
$errorCode = isset($_GET['code']) ? (int)$_GET['code'] : 404;
$errorMessage = '';

// Definir mensajes de error
switch ($errorCode) {
    case 403:
        $errorMessage = 'Acceso denegado. No tienes permiso para acceder a esta página.';
        break;
    case 404:
        $errorMessage = 'Página no encontrada. La página que buscas no existe.';
        break;
    case 500:
        $errorMessage = 'Error interno del servidor. Por favor, intente más tarde.';
        break;
    default:
        $errorMessage = 'Ha ocurrido un error inesperado.';
        break;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo $errorCode; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo APP_URL; ?>/assets/css/main.css" rel="stylesheet">
    
    <style>
        .error-container {
            max-width: 600px;
            margin: 100px auto;
            text-align: center;
        }
        .error-icon {
            font-size: 72px;
            margin-bottom: 20px;
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #dc3545;
            line-height: 1;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-icon">
                <?php if ($errorCode === 403): ?>
                    <i class="fas fa-lock text-danger"></i>
                <?php elseif ($errorCode === 404): ?>
                    <i class="fas fa-exclamation-triangle text-warning"></i>
                <?php else: ?>
                    <i class="fas fa-bug text-danger"></i>
                <?php endif; ?>
            </div>
            
            <div class="error-code"><?php echo $errorCode; ?></div>
            
            <h2 class="mb-4"><?php echo $errorMessage; ?></h2>
            
            <p class="text-muted mb-4">
                Lo sentimos, ha ocurrido un error. Por favor, verifica la URL o intenta nuevamente más tarde.
            </p>
            
            <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
                <a href="<?php echo APP_URL; ?>/templates/dashboard.php" class="btn btn-primary">
                    <i class="fas fa-home"></i> Volver al Inicio
                </a>
                <button onclick="history.back()" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver Atrás
                </button>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo APP_URL; ?>/assets/js/main.js"></script>
</body>
</html>
