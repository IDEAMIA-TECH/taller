<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'ideamiadev_taller');
define('DB_PASS', 'j5?rAqQ5D#zy3Y76');
define('DB_NAME', 'ideamiadev_taller');

// Configuración de la aplicación
define('APP_NAME', 'Sistema de Gestión para Taller Mecánico');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://ideamia-dev.com/taller');
define('APP_PATH', dirname(__DIR__));

// Configuración de rutas
define('ASSETS_URL', APP_URL . '/assets');
define('UPLOADS_URL', APP_URL . '/uploads');
define('INVOICES_URL', APP_URL . '/storage/invoices');

// Configuración de directorios
define('ASSETS_PATH', APP_PATH . '/assets');
define('UPLOADS_PATH', APP_PATH . '/uploads');
define('INVOICES_PATH', APP_PATH . '/storage/invoices');

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1); // Solo para HTTPS
session_start();

// Conexión a la base de datos usando la clase Database
require_once __DIR__ . '/database.php';
$db = Database::getInstance();

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Función para verificar el rol del usuario
function checkRole($requiredRole) {
    if (!isAuthenticated()) {
        return false;
    }
    return $_SESSION['user_role'] === $requiredRole;
}

// Función para redireccionar
function redirect($path) {
    header("Location: " . APP_URL . "/" . $path);
    exit();
}

// Función para sanitizar entrada
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Función para mostrar mensajes de error
function showError($message) {
    $_SESSION['error'] = $message;
}

// Función para mostrar mensajes de éxito
function showSuccess($message) {
    $_SESSION['success'] = $message;
}

// Función para obtener el taller actual
function getCurrentWorkshop() {
    if (isset($_SESSION['workshop_id'])) {
        return $_SESSION['workshop_id'];
    }
    return null;
}

// Función para verificar si el taller está activo
function isWorkshopActive() {
    global $db;
    $workshopId = getCurrentWorkshop();
    
    if (!$workshopId) {
        return false;
    }
    
    $result = $db->fetch(
        "SELECT subscription_status FROM workshops WHERE id_workshop = ?",
        [$workshopId]
    );
    
    return $result && $result['subscription_status'] === 'active';
}

// Función para verificar permisos
function hasPermission($permission) {
    if (!isAuthenticated()) {
        return false;
    }
    
    global $db;
    $userId = $_SESSION['user_id'];
    
    $result = $db->fetch(
        "SELECT role FROM users WHERE id_user = ?",
        [$userId]
    );
    
    if (!$result) {
        return false;
    }
    
    // Mapeo de roles a permisos
    $rolePermissions = [
        'admin' => ['admin', 'receptionist', 'mechanic'],
        'receptionist' => ['receptionist'],
        'mechanic' => ['mechanic']
    ];
    
    return in_array($permission, $rolePermissions[$result['role']] ?? []);
}

// Función para obtener el nombre del usuario actual
function getCurrentUserName() {
    if (!isAuthenticated()) {
        return null;
    }
    
    global $db;
    $userId = $_SESSION['user_id'];
    
    $result = $db->fetch(
        "SELECT full_name FROM users WHERE id_user = ?",
        [$userId]
    );
    
    return $result ? $result['full_name'] : null;
}

// Función para obtener el nombre del taller actual
function getCurrentWorkshopName() {
    $workshopId = getCurrentWorkshop();
    if (!$workshopId) {
        return null;
    }
    
    global $db;
    $result = $db->fetch(
        "SELECT name FROM workshops WHERE id_workshop = ?",
        [$workshopId]
    );
    
    return $result ? $result['name'] : null;
}

// Función para obtener la URL completa de un recurso
function asset($path) {
    return ASSETS_URL . '/' . ltrim($path, '/');
}

// Función para obtener la URL completa de un archivo subido
function upload($path) {
    return UPLOADS_URL . '/' . ltrim($path, '/');
}

// Función para obtener la URL completa de una factura
function invoice($path) {
    return INVOICES_URL . '/' . ltrim($path, '/');
}

// Función para obtener la ruta física de un archivo
function storage_path($path) {
    return APP_PATH . '/storage/' . ltrim($path, '/');
}

// Función para verificar si el sitio está en HTTPS
function isSecure() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
        || $_SERVER['SERVER_PORT'] == 443;
}
?>
