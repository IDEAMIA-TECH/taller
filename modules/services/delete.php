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

// Verificar que el usuario tenga permisos de administrador
if (!hasRole('admin') && !hasRole('super_admin')) {
    showError('No tiene permisos para realizar esta acción.');
    redirect('index.php');
}

// Obtener ID del servicio
$id_service = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id_service) {
    showError('ID de servicio no válido');
    redirect('index.php');
}

try {
    // Verificar si el servicio existe y pertenece al taller actual
    $sql = "SELECT * FROM services WHERE id_service = '" . addslashes($id_service) . "' AND id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";
    $result = $db->query($sql);
    $service = $result->fetch(PDO::FETCH_ASSOC);

    if (!$service) {
        showError('Servicio no encontrado');
        redirect('index.php');
    }

    // Verificar si el servicio está siendo usado en órdenes de servicio
    $sql = "SELECT COUNT(*) as count FROM order_details WHERE id_service = '" . addslashes($id_service) . "'";
    $result = $db->query($sql);
    $count = $result->fetch(PDO::FETCH_ASSOC)['count'];

    if ($count > 0) {
        showError('No se puede eliminar el servicio porque está siendo utilizado en órdenes de servicio.');
        redirect('index.php');
    }

    // Eliminar el servicio
    $sql = "DELETE FROM services WHERE id_service = '" . addslashes($id_service) . "' AND id_workshop = '" . addslashes(getCurrentWorkshop()) . "'";
    $db->query($sql);

    $_SESSION['success_message'] = 'Servicio eliminado correctamente';
    redirect(APP_URL . '/modules/services/index.php');

} catch (PDOException $e) {
    showError('Error al eliminar el servicio. Por favor, intente más tarde.');
    redirect('index.php');
} 