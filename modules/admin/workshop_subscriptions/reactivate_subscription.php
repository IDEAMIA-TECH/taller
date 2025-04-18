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
    // Obtener y validar datos
    $id_subscription = (int)$_POST['id_subscription'];
    $reason = $_POST['reason'] ?? 'Pago recibido';

    // Validaciones básicas
    if ($id_subscription <= 0) {
        throw new Exception('ID de suscripción inválido');
    }

    // Obtener información de la suscripción
    $stmt = $db->prepare("
        SELECT ws.*, w.name as workshop_name, sp.duration_months
        FROM workshop_subscriptions ws
        JOIN workshops w ON ws.id_workshop = w.id_workshop
        JOIN subscription_plans sp ON ws.id_plan = sp.id_plan
        WHERE ws.id_subscription = ?
    ");
    $stmt->execute([$id_subscription]);
    $subscription = $stmt->fetch();

    if (!$subscription) {
        throw new Exception('Suscripción no encontrada');
    }

    // Iniciar transacción
    $db->beginTransaction();

    // Calcular nuevas fechas
    $start_date = date('Y-m-d');
    $end_date = date('Y-m-d', strtotime('+' . $subscription['duration_months'] . ' months'));
    $next_payment_date = $end_date;

    // Actualizar estado de la suscripción
    $stmt = $db->prepare("
        UPDATE workshop_subscriptions 
        SET status = 'active',
            start_date = ?,
            end_date = ?,
            next_payment_date = ?,
            last_payment_date = NOW(),
            suspension_reason = NULL
        WHERE id_subscription = ?
    ");
    $stmt->execute([
        $start_date,
        $end_date,
        $next_payment_date,
        $id_subscription
    ]);

    // Actualizar estado del taller
    $stmt = $db->prepare("
        UPDATE workshops 
        SET subscription_status = 'active'
        WHERE id_workshop = ?
    ");
    $stmt->execute([$subscription['id_workshop']]);

    // Crear notificación de reactivación
    $stmt = $db->prepare("
        INSERT INTO payment_notifications 
        (id_workshop, notification_type, message, status)
        VALUES (?, 'service_reactivation', ?, 'sent')
    ");
    $stmt->execute([
        $subscription['id_workshop'],
        "Suscripción reactivada: $reason"
    ]);

    // Confirmar transacción
    $db->commit();

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Suscripción reactivada exitosamente';
    redirect('view_subscription.php?id=' . $id_subscription);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Redirigir con mensaje de error
    $_SESSION['error_message'] = 'Error al reactivar la suscripción: ' . $e->getMessage();
    redirect('view_subscription.php?id=' . $id_subscription);
} 