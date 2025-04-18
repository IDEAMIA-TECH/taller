<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'tu_usuario');
define('DB_PASS', 'tu_contraseña');
define('DB_NAME', 'taller_db');

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Gestión para Taller Mecánico');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/taller');
define('APP_PATH', dirname(__DIR__));

// Configuración de correo
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', 'tu_correo@gmail.com');
define('MAIL_PASSWORD', 'tu_contraseña_de_app');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'tu_correo@gmail.com');
define('MAIL_FROM_NAME', APP_NAME);

// Configuración de facturación
define('FACTURACION_RFC', 'XAXX010101000');
define('FACTURACION_RAZON_SOCIAL', 'Tu Empresa S.A. de C.V.');
define('FACTURACION_DIRECCION', 'Tu Dirección');
define('FACTURACION_CP', '00000');
define('FACTURACION_REGIMEN', '601');

// Configuración de WhatsApp
define('WHATSAPP_API_KEY', 'tu_api_key');
define('WHATSAPP_API_SECRET', 'tu_api_secret');

// Configuración de pagos
define('STRIPE_PUBLIC_KEY', 'tu_public_key');
define('STRIPE_SECRET_KEY', 'tu_secret_key');

// Configuración de entorno
define('ENVIRONMENT', 'development'); // development, testing, production

// Configuración de errores según el entorno
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Descomentar en producción con HTTPS

// Configuración de subida de archivos
ini_set('upload_max_filesize', '10M');
ini_set('post_max_size', '10M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

// Rutas de archivos
define('UPLOADS_PATH', APP_PATH . '/uploads');
define('LOGS_PATH', APP_PATH . '/logs');

// Crear directorios necesarios si no existen
$directories = [UPLOADS_PATH, LOGS_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
?> 