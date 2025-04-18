<?php
require_once '../../includes/config.php';

// Configuración de CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Obtener la ruta de la API
$request_uri = $_SERVER['REQUEST_URI'];
$api_path = '/api/v1/';
$endpoint = str_replace($api_path, '', $request_uri);
$endpoint = explode('?', $endpoint)[0];
$endpoint = rtrim($endpoint, '/');

// Obtener el método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Obtener el token de autorización
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

try {
    // Verificar autenticación
    if (!authenticateToken($token)) {
        throw new Exception('No autorizado', 401);
    }

    // Enrutamiento
    $response = routeRequest($method, $endpoint);

    // Enviar respuesta
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function authenticateToken($token) {
    if (!$token) {
        return false;
    }

    // Verificar token en la base de datos
    global $db;
    $stmt = $db->prepare("
        SELECT u.*, w.name as workshop_name 
        FROM users u
        JOIN workshops w ON u.id_workshop = w.id_workshop
        WHERE u.api_token = ?
        AND u.status = 'active'
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['user'] = $user;
        return true;
    }

    return false;
}

function routeRequest($method, $endpoint) {
    $routes = [
        'GET' => [
            'vehicles' => 'getVehicles',
            'vehicles/{id}' => 'getVehicle',
            'services' => 'getServices',
            'services/{id}' => 'getService',
            'orders' => 'getOrders',
            'orders/{id}' => 'getOrder',
            'reminders' => 'getReminders',
            'reminders/{id}' => 'getReminder'
        ],
        'POST' => [
            'vehicles' => 'createVehicle',
            'services' => 'createService',
            'orders' => 'createOrder',
            'reminders' => 'createReminder'
        ],
        'PUT' => [
            'vehicles/{id}' => 'updateVehicle',
            'services/{id}' => 'updateService',
            'orders/{id}' => 'updateOrder',
            'reminders/{id}' => 'updateReminder'
        ],
        'DELETE' => [
            'vehicles/{id}' => 'deleteVehicle',
            'services/{id}' => 'deleteService',
            'orders/{id}' => 'deleteOrder',
            'reminders/{id}' => 'deleteReminder'
        ]
    ];

    // Buscar la ruta que coincida
    foreach ($routes[$method] as $route => $handler) {
        $pattern = str_replace('{id}', '(\d+)', $route);
        if (preg_match("#^$pattern$#", $endpoint, $matches)) {
            $params = array_slice($matches, 1);
            return call_user_func_array($handler, $params);
        }
    }

    throw new Exception('Ruta no encontrada', 404);
}

// Handlers de la API
function getVehicles() {
    global $db;
    $stmt = $db->prepare("
        SELECT v.*, c.name as client_name
        FROM vehicles v
        JOIN clients c ON v.id_client = c.id_client
        WHERE v.id_workshop = ?
    ");
    $stmt->execute([$_SESSION['user']['id_workshop']]);
    return $stmt->fetchAll();
}

function getVehicle($id) {
    global $db;
    $stmt = $db->prepare("
        SELECT v.*, c.name as client_name
        FROM vehicles v
        JOIN clients c ON v.id_client = c.id_client
        WHERE v.id_vehicle = ?
        AND v.id_workshop = ?
    ");
    $stmt->execute([$id, $_SESSION['user']['id_workshop']]);
    $vehicle = $stmt->fetch();
    
    if (!$vehicle) {
        throw new Exception('Vehículo no encontrado', 404);
    }
    
    return $vehicle;
}

// ... Implementar el resto de handlers ... 