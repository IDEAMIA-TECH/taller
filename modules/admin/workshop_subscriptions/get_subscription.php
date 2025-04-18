<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener y validar ID de la suscripción
    $id_subscription = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id_subscription <= 0) {
        throw new Exception('ID de suscripción inválido');
    }

    // Obtener datos de la suscripción
    $stmt = $db->prepare("
        SELECT * FROM workshop_subscriptions 
        WHERE id_subscription = ?
    ");
    $stmt->execute([$id_subscription]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subscription) {
        throw new Exception('Suscripción no encontrada');
    }

    // Devolver datos en formato JSON
    header('Content-Type: application/json');
    echo json_encode($subscription);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 