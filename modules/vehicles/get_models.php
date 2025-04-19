<?php
require_once '../../includes/config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar si se proporcionó el ID de la marca
if (!isset($_GET['makeId'])) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de marca no especificado']);
    exit;
}

$makeId = (int)$_GET['makeId'];

try {
    // URL de la API de NHTSA para obtener modelos por marca
    $url = "https://vpic.nhtsa.dot.gov/api/vehicles/getmodelsformakeid/{$makeId}?format=json";
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    // Ejecutar la petición
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('Error en la petición cURL: ' . curl_error($ch));
    }
    
    curl_close($ch);
    
    // Decodificar la respuesta
    $data = json_decode($response, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Error al decodificar la respuesta JSON');
    }
    
    if (!isset($data['Results']) || !is_array($data['Results'])) {
        throw new Exception('Formato de respuesta inválido');
    }
    
    // Devolver solo los modelos
    header('Content-Type: application/json');
    echo json_encode($data['Results']);
    
} catch (Exception $e) {
    error_log("Error al obtener modelos de la API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener los modelos']);
} 