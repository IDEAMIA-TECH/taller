<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Verificar que se recibió el código postal
if (!isset($_GET['zip_code']) || !preg_match('/^[0-9]{5}$/', $_GET['zip_code'])) {
    echo json_encode(['success' => false, 'message' => 'Código postal inválido']);
    exit;
}

$zip_code = $_GET['zip_code'];

try {
    // Usar la API de códigos postales de México
    $url = "https://api.copomex.com/query/info_cp/{$zip_code}?token=pruebas";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            echo json_encode(['success' => false, 'message' => 'No se encontró información para este código postal']);
            exit;
        }
        
        // Extraer la información
        $state = $data['estado'] ?? '';
        $city = $data['municipio'] ?? '';
        $neighborhoods = [];
        
        if (isset($data['asentamiento']) && is_array($data['asentamiento'])) {
            foreach ($data['asentamiento'] as $asentamiento) {
                $neighborhoods[] = $asentamiento['asentamiento'];
            }
        }
        
        echo json_encode([
            'success' => true,
            'state' => $state,
            'city' => $city,
            'neighborhoods' => $neighborhoods
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al consultar la información del código postal']);
    }
} catch (Exception $e) {
    error_log("Error al consultar código postal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar la información']);
} 