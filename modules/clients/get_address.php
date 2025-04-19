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
    // Verificar si las tablas existen
    $checkTables = $db->query("SHOW TABLES LIKE 'zip_codes'");
    if ($checkTables->rowCount() == 0) {
        // Crear tablas si no existen
        $db->query("CREATE TABLE IF NOT EXISTS states (
            id_state INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL
        )");
        
        $db->query("CREATE TABLE IF NOT EXISTS cities (
            id_city INT PRIMARY KEY AUTO_INCREMENT,
            id_state INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            FOREIGN KEY (id_state) REFERENCES states(id_state)
        )");
        
        $db->query("CREATE TABLE IF NOT EXISTS neighborhoods (
            id_neighborhood INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL
        )");
        
        $db->query("CREATE TABLE IF NOT EXISTS zip_codes (
            id_zip_code INT PRIMARY KEY AUTO_INCREMENT,
            zip_code VARCHAR(5) NOT NULL,
            id_state INT NOT NULL,
            id_city INT NOT NULL,
            id_neighborhood INT NOT NULL,
            FOREIGN KEY (id_state) REFERENCES states(id_state),
            FOREIGN KEY (id_city) REFERENCES cities(id_city),
            FOREIGN KEY (id_neighborhood) REFERENCES neighborhoods(id_neighborhood)
        )");
        
        // Insertar datos de ejemplo para Querétaro
        $db->query("INSERT INTO states (name) VALUES ('Querétaro')");
        $stateId = $db->lastInsertId();
        
        $db->query("INSERT INTO cities (id_state, name) VALUES ($stateId, 'Querétaro')");
        $cityId = $db->lastInsertId();
        
        // Insertar algunas colonias de ejemplo
        $neighborhoods = [
            'Centro',
            'Jardines de Querétaro',
            'Lomas de Casa Blanca',
            'Villas del Sol',
            'Lomas del Marqués'
        ];
        
        foreach ($neighborhoods as $neighborhood) {
            $db->query("INSERT INTO neighborhoods (name) VALUES ('$neighborhood')");
            $neighborhoodId = $db->lastInsertId();
            
            $db->query("INSERT INTO zip_codes (zip_code, id_state, id_city, id_neighborhood) 
                        VALUES ('76246', $stateId, $cityId, $neighborhoodId)");
        }
    }
    
    // Consultar la información del código postal
    $stmt = $db->prepare("
        SELECT DISTINCT 
            s.name as state,
            c.name as city,
            GROUP_CONCAT(DISTINCT n.name) as neighborhoods
        FROM zip_codes z
        JOIN states s ON z.id_state = s.id_state
        JOIN cities c ON z.id_city = c.id_city
        JOIN neighborhoods n ON z.id_neighborhood = n.id_neighborhood
        WHERE z.zip_code = ?
        GROUP BY s.name, c.name
    ");
    
    $stmt->execute([$zip_code]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $neighborhoods = explode(',', $result['neighborhoods']);
        
        $logs[] = "Información encontrada - Estado: " . $result['state'] . ", Ciudad: " . $result['city'];
        $logs[] = "Colonias encontradas: " . implode(', ', $neighborhoods);
        
        echo json_encode([
            'success' => true,
            'state' => $result['state'],
            'city' => $result['city'],
            'neighborhoods' => $neighborhoods,
            'logs' => $logs
        ]);
    } else {
        $logs[] = "No se encontraron datos para el código postal";
        echo json_encode(['success' => false, 'message' => 'No se encontró información para este código postal', 'logs' => $logs]);
    }
} catch (Exception $e) {
    $logs[] = "Excepción capturada: " . $e->getMessage();
    error_log("Error al consultar código postal: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al consultar la información', 'logs' => $logs]);
} 