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
    $id_workshop = (int)$_POST['id_workshop'];
    $notification_type = $_POST['notification_type'];
    $message = $_POST['message'];
    $due_date = $_POST['due_date'] ?? null;

    // Validaciones básicas
    if ($id_workshop <= 0 || empty($notification_type) || empty($message)) {
        throw new Exception('Todos los campos requeridos son obligatorios');
    }

    // Verificar que el taller existe
    $stmt = $db->prepare("SELECT id_workshop FROM workshops WHERE id_workshop = ?");
    $stmt->execute([$id_workshop]);
    if (!$stmt->fetch()) {
        throw new Exception('Taller no encontrado');
    }

    // Insertar la notificación
    $stmt = $db->prepare("
        INSERT INTO payment_notifications 
        (id_workshop, notification_type, message, due_date, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $id_workshop,
        $notification_type,
        $message,
        $due_date
    ]);

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Notificación generada exitosamente';
    redirect('view_subscription.php?id=' . $_POST['id_subscription']);

} catch (Exception $e) {
    // Redirigir con mensaje de error
    $_SESSION['error_message'] = 'Error al generar la notificación: ' . $e->getMessage();
    redirect('view_subscription.php?id=' . $_POST['id_subscription']);
} 