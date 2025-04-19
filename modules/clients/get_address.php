<?php
require_once '../../includes/config.php';

// Habilitar reporte de errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

// Inicializar logs
$logs = [];
$logs[] = "Iniciando proceso de consulta de código postal";

try {
    // Verificar que se recibió el código postal
    if (!isset($_GET['zip_code']) || !preg_match('/^[0-9]{5}$/', $_GET['zip_code'])) {
        $logs[] = "Código postal inválido o no proporcionado";
        echo json_encode(['success' => false, 'message' => 'Código postal inválido', 'logs' => $logs]);
        exit;
    }

    $zip_code = $_GET['zip_code'];
    $logs[] = "Código postal recibido: " . $zip_code;

    // Verificar conexión a la base de datos
    $logs[] = "Verificando conexión a la base de datos";
    $db->query("SELECT 1");
    $logs[] = "Conexión a la base de datos exitosa";

    // Verificar si las tablas existen
    $logs[] = "Verificando existencia de tablas";
    $checkTables = $db->query("SHOW TABLES LIKE 'zip_codes'");
    if ($checkTables->rowCount() == 0) {
        $logs[] = "Tablas no encontradas, creando estructura";
        
        // Crear tablas si no existen
        $db->query("CREATE TABLE IF NOT EXISTS states (
            id_state INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL
        )");
        $logs[] = "Tabla states creada";
        
        $db->query("CREATE TABLE IF NOT EXISTS cities (
            id_city INT PRIMARY KEY AUTO_INCREMENT,
            id_state INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            FOREIGN KEY (id_state) REFERENCES states(id_state)
        )");
        $logs[] = "Tabla cities creada";
        
        $db->query("CREATE TABLE IF NOT EXISTS neighborhoods (
            id_neighborhood INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL
        )");
        $logs[] = "Tabla neighborhoods creada";
        
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
        $logs[] = "Tabla zip_codes creada";
        
        // Insertar datos de ejemplo para Querétaro
        $logs[] = "Insertando datos de ejemplo";
        $db->query("INSERT INTO states (name) VALUES ('Querétaro')");
        $stateId = $db->lastInsertId();
        $logs[] = "Estado insertado con ID: " . $stateId;
        
        $db->query("INSERT INTO cities (id_state, name) VALUES ($stateId, 'Querétaro')");
        $cityId = $db->lastInsertId();
        $logs[] = "Ciudad insertada con ID: " . $cityId;
        
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
            $logs[] = "Colonia insertada: " . $neighborhood . " con ID: " . $neighborhoodId;
            
            $db->query("INSERT INTO zip_codes (zip_code, id_state, id_city, id_neighborhood) 
                        VALUES ('76246', $stateId, $cityId, $neighborhoodId)");
            $logs[] = "Código postal insertado para colonia: " . $neighborhood;
        }
    }
    
    // Consultar la información del código postal
    $logs[] = "Consultando información del código postal";
    $result = $db->query("
        SELECT DISTINCT 
            s.name as state,
            c.name as city,
            GROUP_CONCAT(DISTINCT n.name) as neighborhoods
        FROM zip_codes z
        JOIN states s ON z.id_state = s.id_state
        JOIN cities c ON z.id_city = c.id_city
        JOIN neighborhoods n ON z.id_neighborhood = n.id_neighborhood
        WHERE z.zip_code = '$zip_code'
        GROUP BY s.name, c.name
    ")->fetch(PDO::FETCH_ASSOC);
    
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
} catch (PDOException $e) {
    $logs[] = "Error de PDO: " . $e->getMessage();
    error_log("Error de PDO: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos', 'logs' => $logs]);
} catch (Exception $e) {
    $logs[] = "Error general: " . $e->getMessage();
    error_log("Error general: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud', 'logs' => $logs]);
} 