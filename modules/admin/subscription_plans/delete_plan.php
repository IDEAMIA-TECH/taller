<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    redirect('templates/login.php');
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('list.php');
}

try {
    // Obtener y validar ID del plan
    $id_plan = isset($_POST['id_plan']) ? (int)$_POST['id_plan'] : 0;
    
    if ($id_plan <= 0) {
        throw new Exception('ID de plan inválido');
    }

    // Verificar si el plan está en uso
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM workshop_subscriptions 
        WHERE id_plan = ? AND status = 'active'
    ");
    $stmt->execute([$id_plan]);
    $active_subscriptions = $stmt->fetchColumn();

    if ($active_subscriptions > 0) {
        throw new Exception('No se puede eliminar el plan porque tiene suscripciones activas');
    }

    // Iniciar transacción
    $db->beginTransaction();

    // Eliminar el plan
    $stmt = $db->prepare("DELETE FROM subscription_plans WHERE id_plan = ?");
    $stmt->execute([$id_plan]);

    // Confirmar transacción
    $db->commit();

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Plan eliminado exitosamente';
    redirect('list.php');

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Redirigir con mensaje de error
    $_SESSION['error_message'] = 'Error al eliminar el plan: ' . $e->getMessage();
    redirect('list.php');
} 