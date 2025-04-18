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
    $id_subscription = isset($_POST['id_subscription']) ? (int)$_POST['id_subscription'] : 0;
    $id_workshop = (int)$_POST['id_workshop'];
    $id_plan = (int)$_POST['id_plan'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $payment_method = $_POST['payment_method'];
    $status = $_POST['status'];

    // Validaciones básicas
    if ($id_workshop <= 0 || $id_plan <= 0 || empty($start_date) || empty($end_date)) {
        throw new Exception('Todos los campos son requeridos');
    }

    // Validar fechas
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    if ($end <= $start) {
        throw new Exception('La fecha de fin debe ser posterior a la fecha de inicio');
    }

    // Obtener información del plan
    $stmt = $db->prepare("SELECT duration_months FROM subscription_plans WHERE id_plan = ?");
    $stmt->execute([$id_plan]);
    $plan = $stmt->fetch();

    if (!$plan) {
        throw new Exception('Plan no encontrado');
    }

    // Calcular fecha de próximo pago
    $next_payment_date = clone $start;
    $next_payment_date->modify("+{$plan['duration_months']} months");

    // Iniciar transacción
    $db->beginTransaction();

    if ($id_subscription > 0) {
        // Actualizar suscripción existente
        $stmt = $db->prepare("
            UPDATE workshop_subscriptions 
            SET id_workshop = ?, id_plan = ?, start_date = ?, end_date = ?,
                payment_method = ?, status = ?, next_payment_date = ?
            WHERE id_subscription = ?
        ");
        $stmt->execute([
            $id_workshop, $id_plan, $start_date, $end_date,
            $payment_method, $status, $next_payment_date->format('Y-m-d'),
            $id_subscription
        ]);
    } else {
        // Insertar nueva suscripción
        $stmt = $db->prepare("
            INSERT INTO workshop_subscriptions 
            (id_workshop, id_plan, start_date, end_date, payment_method, status, next_payment_date)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $id_workshop, $id_plan, $start_date, $end_date,
            $payment_method, $status, $next_payment_date->format('Y-m-d')
        ]);

        // Actualizar estado del taller
        $stmt = $db->prepare("
            UPDATE workshops 
            SET subscription_status = 'active',
                max_users_allowed = (SELECT max_users FROM subscription_plans WHERE id_plan = ?),
                max_vehicles_allowed = (SELECT max_vehicles FROM subscription_plans WHERE id_plan = ?)
            WHERE id_workshop = ?
        ");
        $stmt->execute([$id_plan, $id_plan, $id_workshop]);
    }

    // Confirmar transacción
    $db->commit();

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Suscripción guardada exitosamente';
    redirect('list.php');

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Redirigir con mensaje de error
    $_SESSION['error_message'] = 'Error al guardar la suscripción: ' . $e->getMessage();
    redirect('list.php');
} 