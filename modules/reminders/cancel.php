<?php
require_once '../../includes/config.php';

// Verificar autenticación y permisos
if (!isAuthenticated()) {
    redirect('templates/login.php');
}

// Obtener ID del recordatorio
$id_reminder = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id_reminder <= 0) {
    $_SESSION['error_message'] = 'ID de recordatorio inválido';
    redirect('manage.php');
}

try {
    // Obtener información del recordatorio
    $stmt = $db->prepare("
        SELECT r.*, v.id_workshop, c.email as client_email, c.phone as client_phone,
               s.name as service_name, v.brand, v.model, v.plates
        FROM reminders r
        JOIN vehicles v ON r.id_vehicle = v.id_vehicle
        JOIN clients c ON v.id_client = c.id_client
        JOIN services s ON r.id_service = s.id_service
        WHERE r.id_reminder = ?
    ");
    $stmt->execute([$id_reminder]);
    $reminder = $stmt->fetch();

    if (!$reminder) {
        throw new Exception('Recordatorio no encontrado');
    }

    // Verificar que el recordatorio pertenece al taller del usuario
    if ($reminder['id_workshop'] !== $_SESSION['id_workshop']) {
        throw new Exception('No tiene permiso para modificar este recordatorio');
    }

    // Verificar que el recordatorio no esté ya completado o cancelado
    if ($reminder['status'] !== 'pending') {
        throw new Exception('Solo se pueden cancelar recordatorios pendientes');
    }

    // Actualizar estado del recordatorio
    $stmt = $db->prepare("
        UPDATE reminders 
        SET status = 'cancelled',
            cancelled_at = NOW()
        WHERE id_reminder = ?
    ");
    $stmt->execute([$id_reminder]);

    // Enviar notificación al cliente
    $subject = "Recordatorio cancelado: {$reminder['service_name']}";
    $message = "Estimado cliente,\n\n";
    $message .= "Le informamos que el recordatorio para el servicio {$reminder['service_name']} ";
    $message .= "de su vehículo {$reminder['brand']} {$reminder['model']} ({$reminder['plates']}) ";
    $message .= "ha sido cancelado.\n\n";
    $message .= "Si necesita reprogramar este servicio, por favor contáctenos.\n\n";
    $message .= "Atentamente,\n";
    $message .= "El equipo de {$workshop['name']}";

    // Enviar correo electrónico
    if (!empty($reminder['client_email'])) {
        mail($reminder['client_email'], $subject, $message);
    }

    // Enviar mensaje de WhatsApp si está configurado
    if (!empty($reminder['client_phone'])) {
        // Aquí iría el código para enviar mensaje por WhatsApp
        // usando la API de WhatsApp Business
    }

    $_SESSION['success_message'] = 'Recordatorio cancelado exitosamente';
    redirect('manage.php');

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error al cancelar el recordatorio: ' . $e->getMessage();
    redirect('manage.php');
} 