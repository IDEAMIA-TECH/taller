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
    // Consultar la información del código postal
    $stmt = $db->prepare("
        SELECT DISTINCT 
            c.name as city,
            s.name as state,
            GROUP_CONCAT(DISTINCT n.name) as neighborhoods
        FROM zip_codes z
        JOIN cities c ON z.id_city = c.id_city
        JOIN states s ON c.id_state = s.id_state
        JOIN neighborhoods n ON z.id_neighborhood = n.id_neighborhood
        WHERE z.zip_code = ?
        GROUP BY c.name, s.name
    ");
    
    $stmt->execute([$zip_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        // Separar las colonias en un array
        $neighborhoods = explode(',', $result['neighborhoods']);
        
        echo json_encode([
            'success' => true,
            'state' => $result['state'],
            'city' => $result['city'],
            'neighborhoods' => $neighborhoods
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se encontró información para este código postal']);
    }
} catch (PDOException $e) {
    error_log("Error al consultar código postal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar la información']);
} 