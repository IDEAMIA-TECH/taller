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
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $transaction_id = $_POST['transaction_id'] ?? null;
    $notes = $_POST['notes'] ?? null;

    // Validaciones básicas
    if ($id_subscription <= 0 || $amount <= 0 || empty($payment_method)) {
        throw new Exception('Todos los campos requeridos son obligatorios');
    }

    // Obtener información de la suscripción
    $stmt = $db->prepare("
        SELECT ws.*, w.name as workshop_name, sp.price, sp.duration_months
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

    // Insertar el pago
    $stmt = $db->prepare("
        INSERT INTO payments 
        (id_subscription, amount, payment_date, payment_method, transaction_id, status, notes)
        VALUES (?, ?, NOW(), ?, ?, 'completed', ?)
    ");
    $stmt->execute([
        $id_subscription,
        $amount,
        $payment_method,
        $transaction_id,
        $notes
    ]);

    // Actualizar fechas de la suscripción
    $next_payment_date = date('Y-m-d', strtotime('+' . $subscription['duration_months'] . ' months'));
    $end_date = date('Y-m-d', strtotime('+' . $subscription['duration_months'] . ' months', strtotime($subscription['end_date'])));

    $stmt = $db->prepare("
        UPDATE workshop_subscriptions 
        SET status = 'active',
            next_payment_date = ?,
            end_date = ?,
            last_payment_date = NOW()
        WHERE id_subscription = ?
    ");
    $stmt->execute([
        $next_payment_date,
        $end_date,
        $id_subscription
    ]);

    // Actualizar estado del taller
    $stmt = $db->prepare("
        UPDATE workshops 
        SET subscription_status = 'active'
        WHERE id_workshop = ?
    ");
    $stmt->execute([$subscription['id_workshop']]);

    // Crear notificación de pago exitoso
    $stmt = $db->prepare("
        INSERT INTO payment_notifications 
        (id_workshop, notification_type, message, status)
        VALUES (?, 'payment_completed', 'Pago registrado exitosamente', 'sent')
    ");
    $stmt->execute([$subscription['id_workshop']]);

    // Confirmar transacción
    $db->commit();

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Pago registrado exitosamente';
    redirect('view_subscription.php?id=' . $id_subscription);

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Redirigir con mensaje de error
    $_SESSION['error_message'] = 'Error al procesar el pago: ' . $e->getMessage();
    redirect('view_subscription.php?id=' . $id_subscription);
} 