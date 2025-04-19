<?php
require_once '../../includes/config.php';

header('Content-Type: application/json');

// Inicializar logs
$logs = [];
$logs[] = "Iniciando proceso de guardado de nueva colonia";

try {
    // Verificar que se recibieron los datos necesarios
    if (!isset($_POST['zip_code']) || !isset($_POST['neighborhood'])) {
        $logs[] = "Datos incompletos";
        echo json_encode(['success' => false, 'message' => 'Datos incompletos', 'logs' => $logs]);
        exit;
    }

    $zip_code = $_POST['zip_code'];
    $neighborhood = trim($_POST['neighborhood']);
    $logs[] = "Datos recibidos - Código postal: " . $zip_code . ", Colonia: " . $neighborhood;

    // Verificar que el código postal sea válido
    if (!preg_match('/^[0-9]{5}$/', $zip_code)) {
        $logs[] = "Código postal inválido";
        echo json_encode(['success' => false, 'message' => 'Código postal inválido', 'logs' => $logs]);
        exit;
    }

    // Verificar que la colonia no esté vacía
    if (empty($neighborhood)) {
        $logs[] = "Nombre de colonia vacío";
        echo json_encode(['success' => false, 'message' => 'El nombre de la colonia no puede estar vacío', 'logs' => $logs]);
        exit;
    }

    // Verificar si la colonia ya existe para este código postal
    $checkQuery = $db->query("
        SELECT COUNT(*) as count 
        FROM zip_codes z 
        JOIN neighborhoods n ON z.id_neighborhood = n.id_neighborhood 
        WHERE z.zip_code = '$zip_code' AND n.name = '$neighborhood'
    ");
    $exists = $checkQuery->fetch(PDO::FETCH_ASSOC)['count'] > 0;

    if ($exists) {
        $logs[] = "La colonia ya existe para este código postal";
        echo json_encode(['success' => false, 'message' => 'Esta colonia ya existe para este código postal', 'logs' => $logs]);
        exit;
    }

    // Obtener el estado y ciudad del código postal
    $locationQuery = $db->query("
        SELECT s.name as state, c.name as city 
        FROM zip_codes z 
        JOIN states s ON z.id_state = s.id_state 
        JOIN cities c ON z.id_city = c.id_city 
        WHERE z.zip_code = '$zip_code' 
        LIMIT 1
    ");
    $location = $locationQuery->fetch(PDO::FETCH_ASSOC);

    if (!$location) {
        $logs[] = "No se encontró información para el código postal";
        echo json_encode(['success' => false, 'message' => 'No se encontró información para este código postal', 'logs' => $logs]);
        exit;
    }

    // Insertar la nueva colonia
    $db->query("INSERT INTO neighborhoods (name) VALUES ('$neighborhood')");
    $neighborhoodId = $db->lastInsertId();
    $logs[] = "Nueva colonia insertada con ID: " . $neighborhoodId;

    // Obtener el id_state y id_city
    $idsQuery = $db->query("
        SELECT id_state, id_city 
        FROM zip_codes 
        WHERE zip_code = '$zip_code' 
        LIMIT 1
    ");
    $ids = $idsQuery->fetch(PDO::FETCH_ASSOC);

    // Insertar la relación con el código postal
    $db->query("
        INSERT INTO zip_codes (zip_code, id_state, id_city, id_neighborhood) 
        VALUES ('$zip_code', {$ids['id_state']}, {$ids['id_city']}, $neighborhoodId)
    ");
    $logs[] = "Relación con código postal creada";

    echo json_encode([
        'success' => true,
        'state' => $location['state'],
        'city' => $location['city'],
        'logs' => $logs
    ]);

} catch (PDOException $e) {
    $logs[] = "Error de PDO: " . $e->getMessage();
    error_log("Error de PDO: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos', 'logs' => $logs]);
} catch (Exception $e) {
    $logs[] = "Error general: " . $e->getMessage();
    error_log("Error general: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error al procesar la solicitud', 'logs' => $logs]);
} 