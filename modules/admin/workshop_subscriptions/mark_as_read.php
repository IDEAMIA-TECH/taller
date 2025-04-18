<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar método de petición
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y validar el ID de la notificación
$id_notification = isset($_POST['id_notification']) ? (int)$_POST['id_notification'] : 0;

if ($id_notification <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'ID de notificación inválido']);
    exit;
}

try {
    // Actualizar el estado de la notificación
    $stmt = $db->prepare("
        UPDATE payment_notifications 
        SET status = 'read', 
            read_at = NOW() 
        WHERE id_notification = ?
    ");
    
    $stmt->execute([$id_notification]);
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error al actualizar la notificación']);
} 