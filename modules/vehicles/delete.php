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

// Obtener el ID del vehículo
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    showError('Vehículo no válido');
    redirect('index.php');
}

try {
    // Verificar si el vehículo existe y pertenece al taller
    $stmt = $db->prepare("SELECT * FROM vehicles WHERE id_vehicle = ? AND id_workshop = ?");
    $stmt->execute([$id, getCurrentWorkshop()]);
    $vehicle = $stmt->fetch();

    if (!$vehicle) {
        showError('Vehículo no encontrado');
        redirect('index.php');
    }

    // Verificar si el vehículo tiene órdenes de servicio activas
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM service_orders 
        WHERE id_vehicle = ? AND status IN ('open', 'in_progress')
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        showError('No se puede eliminar el vehículo porque tiene órdenes de servicio activas.');
        redirect('view.php?id=' . $id);
    }

    // Verificar si el vehículo tiene recordatorios pendientes
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM reminders 
        WHERE id_vehicle = ? AND status = 'pending'
    ");
    $stmt->execute([$id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
        showError('No se puede eliminar el vehículo porque tiene recordatorios pendientes.');
        redirect('view.php?id=' . $id);
    }

    // Iniciar transacción
    $db->beginTransaction();

    try {
        // Eliminar recordatorios completados
        $stmt = $db->prepare("DELETE FROM reminders WHERE id_vehicle = ?");
        $stmt->execute([$id]);

        // Eliminar órdenes de servicio completadas
        $stmt = $db->prepare("DELETE FROM service_orders WHERE id_vehicle = ?");
        $stmt->execute([$id]);

        // Eliminar el vehículo
        $stmt = $db->prepare("DELETE FROM vehicles WHERE id_vehicle = ? AND id_workshop = ?");
        $stmt->execute([$id, getCurrentWorkshop()]);

        // Confirmar transacción
        $db->commit();

        showSuccess('Vehículo eliminado correctamente');
        redirect('index.php');

    } catch (PDOException $e) {
        // Revertir transacción en caso de error
        $db->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    showError('Error al eliminar el vehículo. Por favor, intente más tarde.');
    redirect('view.php?id=' . $id);
} 