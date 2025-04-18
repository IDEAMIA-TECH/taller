<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener y validar ID del plan
    $id_plan = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($id_plan <= 0) {
        throw new Exception('ID de plan inválido');
    }

    // Obtener datos del plan
    $stmt = $db->prepare("
        SELECT * FROM subscription_plans 
        WHERE id_plan = ?
    ");
    $stmt->execute([$id_plan]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plan) {
        throw new Exception('Plan no encontrado');
    }

    // Devolver datos en formato JSON
    header('Content-Type: application/json');
    echo json_encode($plan);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
} 