<?php
require_once '../../includes/config.php';

// Verificar autenticación
if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

try {
    // URL de la API de NHTSA para obtener todas las marcas
    $url = 'https://vpic.nhtsa.dot.gov/api/vehicles/getallmakes?format=json';
    
    // Inicializar cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 segundos de timeout
    
    // Ejecutar la petición
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        throw new Exception('Error en la petición cURL: ' . curl_error($ch));
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode !== 200) {
        throw new Exception('Error HTTP: ' . $httpCode);
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
    
    // Filtrar y formatear los resultados
    $brands = array_map(function($item) {
        return [
            'Make_ID' => $item['Make_ID'],
            'Make_Name' => $item['Make_Name']
        ];
    }, $data['Results']);
    
    // Devolver las marcas
    header('Content-Type: application/json');
    echo json_encode($brands);
    
} catch (Exception $e) {
    error_log("Error al obtener marcas de la API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener las marcas: ' . $e->getMessage()]);
} 