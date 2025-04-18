<?php
// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'ideamiadev_taller');
define('DB_PASS', 'j5?rAqQ5D#zy3Y76');
define('DB_NAME', 'ideamiadev_taller');

// Configuración de la aplicación
define('APP_NAME', 'TallerPro');
define('APP_URL', 'https://ideamia-dev.com/taller');
define('APP_VERSION', '1.0.0');

// Configuración de rutas
define('ASSETS_URL', APP_URL . '/assets');
define('UPLOADS_URL', APP_URL . '/uploads');
define('INVOICES_URL', APP_URL . '/storage/invoices');

// Configuración de directorios
define('ASSETS_PATH', APP_URL . '/assets');
define('UPLOADS_PATH', APP_URL . '/uploads');
define('INVOICES_PATH', APP_URL . '/storage/invoices');

// Configuración de sesión
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    session_start();
}

// Inicializar la conexión a la base de datos
require_once __DIR__ . '/database.php';
$db = Database::getInstance();

// Funciones de utilidad
function isLoggedIn() {
    return isset($_SESSION['id_user']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/login');
        exit;
    }
}

function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

function requireRole($role) {
    if (!hasRole($role)) {
        header('Location: ' . APP_URL . '/error?code=403');
        exit;
    }
}

function redirect($url) {
    header('Location: ' . APP_URL . '/' . $url);
    exit;
}

function sanitize($input) {
    return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
}

function setMessage($type, $message) {
    $_SESSION['message'] = [
        'type' => $type,
        'text' => $message
    ];
}

function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}

function getCurrentWorkshop() {
    if (isset($_SESSION['id_workshop'])) {
        return $_SESSION['id_workshop'];
    }
    return null;
}

function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        $db = Database::getInstance();
        return $db->fetch(
            "SELECT * FROM users WHERE id_user = ?",
            [$_SESSION['user_id']]
        );
    }
    return null;
}

function isWorkshopActive() {
    $workshopId = getCurrentWorkshop();
    if (!$workshopId) {
        return false;
    }
    
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $pdo->prepare("SELECT subscription_status FROM workshops WHERE id_workshop = ?");
        $stmt->execute([$workshopId]);
        $result = $stmt->fetch();
        
        return $result && $result['subscription_status'] === 'active';
    } catch (PDOException $e) {
        error_log("Error al verificar estado del taller: " . $e->getMessage());
        return false;
    }
}

function getResourcePath($type) {
    switch ($type) {
        case 'assets':
            return APP_URL . '/assets';
        case 'uploads':
            return APP_URL . '/uploads';
        case 'invoices':
            return APP_URL . '/invoices';
        default:
            return APP_URL;
    }
}

function getPhysicalPath($type) {
    switch ($type) {
        case 'assets':
            return __DIR__ . '/../assets';
        case 'uploads':
            return __DIR__ . '/../uploads';
        case 'invoices':
            return __DIR__ . '/../invoices';
        default:
            return __DIR__ . '/..';
    }
}

// Verificar si la solicitud es para una página pública
$public_pages = ['index.php', 'login.php', 'register.php', 'contact.php'];
$current_page = basename($_SERVER['PHP_SELF']);

// Solo redirigir a login si no está en una página pública y no está autenticado
if (!in_array($current_page, $public_pages) && !isLoggedIn()) {
    requireLogin();
}

// Función para verificar si el usuario está autenticado
function isAuthenticated() {
    return isset($_SESSION['id_user']) && !empty($_SESSION['id_user']);
}

// Función para verificar el rol del usuario
function checkRole($requiredRole) {
    if (!isAuthenticated()) {
        return false;
    }
    return isset($_SESSION['role']) && $_SESSION['role'] === $requiredRole;
}

// Función para obtener el nombre del usuario actual
function getCurrentUserName() {
    if (!isAuthenticated()) {
        return null;
    }
    
    global $db;
    $userId = $_SESSION['id_user'];
    
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
