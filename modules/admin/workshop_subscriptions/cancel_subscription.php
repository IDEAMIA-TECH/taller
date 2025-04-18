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
    // Obtener y validar ID de la suscripción
    $id_subscription = isset($_POST['id_subscription']) ? (int)$_POST['id_subscription'] : 0;
    
    if ($id_subscription <= 0) {
        throw new Exception('ID de suscripción inválido');
    }

    // Obtener datos de la suscripción
    $stmt = $db->prepare("
        SELECT ws.*, w.id_workshop 
        FROM workshop_subscriptions ws
        JOIN workshops w ON ws.id_workshop = w.id_workshop
        WHERE ws.id_subscription = ?
    ");
    $stmt->execute([$id_subscription]);
    $subscription = $stmt->fetch();

    if (!$subscription) {
        throw new Exception('Suscripción no encontrada');
    }

    // Iniciar transacción
    $db->beginTransaction();

    // Actualizar estado de la suscripción
    $stmt = $db->prepare("
        UPDATE workshop_subscriptions 
        SET status = 'cancelled',
            end_date = CURRENT_DATE
        WHERE id_subscription = ?
    ");
    $stmt->execute([$id_subscription]);

    // Actualizar estado del taller
    $stmt = $db->prepare("
        UPDATE workshops 
        SET subscription_status = 'cancelled'
        WHERE id_workshop = ?
    ");
    $stmt->execute([$subscription['id_workshop']]);

    // Crear notificación de cancelación
    $stmt = $db->prepare("
        INSERT INTO payment_notifications 
        (id_workshop, notification_type, message, status)
        VALUES (?, 'service_suspension', 'Su suscripción ha sido cancelada', 'pending')
    ");
    $stmt->execute([$subscription['id_workshop']]);

    // Confirmar transacción
    $db->commit();

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Suscripción cancelada exitosamente';
    redirect('list.php');

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Redirigir con mensaje de error
    $_SESSION['error_message'] = 'Error al cancelar la suscripción: ' . $e->getMessage();
    redirect('list.php');
} 