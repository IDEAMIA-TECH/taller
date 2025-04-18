<?php
require_once '../../includes/config.php';

// Verificar autenticación y permisos
if (!isAuthenticated()) {
    redirect('templates/login.php');
}

// Verificar si el taller está activo
if (!isWorkshopActive()) {
    showError('El taller no está activo. Por favor, contacte al administrador.');
    redirect('templates/dashboard.php');
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Método no permitido');
    redirect('index.php');
}

// Obtener y validar datos
$id_order = isset($_POST['id_order']) ? (int)$_POST['id_order'] : 0;
$status = isset($_POST['status']) ? $_POST['status'] : '';
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validar datos
if (!$id_order || !$status) {
    showError('Datos incompletos');
    redirect('index.php');
}

// Validar estado
$valid_statuses = ['open', 'in_progress', 'completed', 'cancelled'];
if (!in_array($status, $valid_statuses)) {
    showError('Estado no válido');
    redirect('index.php');
}

try {
    // Iniciar transacción
    $db->beginTransaction();

    // Verificar que la orden existe y pertenece al taller
    $stmt = $db->prepare("
        SELECT status, id_workshop 
        FROM service_orders 
        WHERE id_order = ?
    ");
    $stmt->execute([$id_order]);
    $order = $stmt->fetch();

    if (!$order) {
        throw new Exception('Orden no encontrada');
    }

    if ($order['id_workshop'] !== getCurrentWorkshop()) {
        throw new Exception('No tiene permiso para modificar esta orden');
    }

    // Validar transiciones de estado
    $current_status = $order['status'];
    $valid_transitions = [
        'open' => ['in_progress', 'cancelled'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => []
    ];

    if (!in_array($status, $valid_transitions[$current_status])) {
        throw new Exception('Transición de estado no válida');
    }

    // Actualizar estado de la orden
    $stmt = $db->prepare("
        UPDATE service_orders 
        SET status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id_order = ?
    ");
    $stmt->execute([$status, $id_order]);

    // Registrar en el historial
    $stmt = $db->prepare("
        INSERT INTO service_order_history (
            id_order, id_user, status, notes
        ) VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $id_order,
        getCurrentUserId(),
        $status,
        $notes
    ]);

    // Si la orden se completa, actualizar el kilometraje del vehículo si se proporciona
    if ($status === 'completed' && isset($_POST['final_mileage'])) {
        $final_mileage = (int)$_POST['final_mileage'];
        if ($final_mileage > 0) {
            $stmt = $db->prepare("
                UPDATE vehicles v
                JOIN service_orders so ON v.id_vehicle = so.id_vehicle
                SET v.last_mileage = ?
                WHERE so.id_order = ?
            ");
            $stmt->execute([$final_mileage, $id_order]);
        }
    }

    // Confirmar transacción
    $db->commit();

    // Mostrar mensaje de éxito
    showSuccess('Estado de la orden actualizado correctamente');

} catch (Exception $e) {
    // Revertir transacción en caso de error
    $db->rollBack();
    showError('Error al actualizar el estado: ' . $e->getMessage());
}

// Redirigir a la vista de la orden
redirect('view.php?id=' . $id_order); 