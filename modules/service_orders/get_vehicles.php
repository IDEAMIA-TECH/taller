<?php
require_once '../../includes/config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar si el taller está activo
if (!isWorkshopActive()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'El taller no está activo']);
    exit;
}

// Obtener el ID del cliente desde la URL
$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

if (!$client_id) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de cliente no válido']);
    exit;
}

try {
    // Obtener vehículos del cliente
    $sql = "SELECT v.id_vehicle, v.brand, v.model, v.plates, v.color, v.year 
            FROM vehicles v 
            WHERE v.id_client = '" . addslashes($client_id) . "' 
            AND v.id_workshop = '" . addslashes(getCurrentWorkshop()) . "' 
            ORDER BY v.brand ASC, v.model ASC";
    
    $result = $db->query($sql);
    $vehicles = $result->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($vehicles);

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Error al obtener los vehículos']);
} 