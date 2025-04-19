<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Inicializar logs
$logs = [];
$logs[] = "Iniciando proceso de consulta de código postal";

// Verificar que se recibió el código postal
if (!isset($_GET['zip_code']) || !preg_match('/^[0-9]{5}$/', $_GET['zip_code'])) {
    $logs[] = "Código postal inválido o no proporcionado";
    echo json_encode(['success' => false, 'message' => 'Código postal inválido', 'logs' => $logs]);
    exit;
}

$zip_code = $_GET['zip_code'];
$logs[] = "Código postal recibido: " . $zip_code;

try {
    // Usar la API de México
    $url = "https://api.correosdemexico.gob.mx/v1/codigo-postal/{$zip_code}";
    $logs[] = "URL de la API: " . $url;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $logs[] = "Código HTTP de respuesta: " . $httpCode;
    $logs[] = "Respuesta de la API: " . $response;
    
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        $logs[] = "Datos decodificados: " . print_r($data, true);
        
        if (empty($data) || !isset($data['estado'])) {
            $logs[] = "No se encontraron datos para el código postal";
            echo json_encode(['success' => false, 'message' => 'No se encontró información para este código postal', 'logs' => $logs]);
            exit;
        }
        
        // Extraer la información
        $state = $data['estado'] ?? '';
        $city = $data['municipio'] ?? '';
        $neighborhoods = [];
        
        if (isset($data['asentamientos']) && is_array($data['asentamientos'])) {
            foreach ($data['asentamientos'] as $asentamiento) {
                $neighborhoods[] = $asentamiento['nombre'];
            }
        }
        
        $logs[] = "Información extraída - Estado: " . $state . ", Ciudad: " . $city;
        $logs[] = "Colonias encontradas: " . implode(', ', $neighborhoods);
        
        echo json_encode([
            'success' => true,
            'state' => $state,
            'city' => $city,
            'neighborhoods' => $neighborhoods,
            'logs' => $logs
        ]);
    } else {
        $logs[] = "Error en la petición HTTP";
        echo json_encode(['success' => false, 'message' => 'Error al consultar la información del código postal', 'logs' => $logs]);
    }
} catch (Exception $e) {
    $logs[] = "Excepción capturada: " . $e->getMessage();
    error_log("Error al consultar código postal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar la información', 'logs' => $logs]);
} 