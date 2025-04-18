<?php
require_once '../../includes/config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar si el taller está activo
if (!isWorkshopActive()) {
    http_response_code(403);
    echo json_encode(['error' => 'Taller no activo']);
    exit;
}

// Obtener y validar el ID del cliente
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if (!$client_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de cliente no válido']);
    exit;
}

try {
    // Obtener vehículos del cliente
    $stmt = $db->prepare("
        SELECT id_vehicle, brand, model, plates, year, color
        FROM vehicles 
        WHERE id_client = ? AND id_workshop = ?
        ORDER BY brand, model
    ");
    $stmt->execute([$client_id, getCurrentWorkshop()]);
    $vehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Devolver los vehículos en formato JSON
    header('Content-Type: application/json');
    echo json_encode($vehicles);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener los vehículos']);
} 