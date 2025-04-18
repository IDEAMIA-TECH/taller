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
    $id_plan = isset($_POST['id_plan']) ? (int)$_POST['id_plan'] : 0;
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = (float)$_POST['price'];
    $duration_months = (int)$_POST['duration_months'];
    $max_users = (int)$_POST['max_users'];
    $max_vehicles = (int)$_POST['max_vehicles'];
    $status = $_POST['status'];
    $features = isset($_POST['features']) ? $_POST['features'] : [];

    // Validaciones básicas
    if (empty($name) || $price <= 0 || $duration_months <= 0 || 
        $max_users <= 0 || $max_vehicles <= 0) {
        throw new Exception('Todos los campos son requeridos y deben ser válidos');
    }

    // Preparar características como JSON
    $features_json = json_encode($features);

    // Iniciar transacción
    $db->beginTransaction();

    if ($id_plan > 0) {
        // Actualizar plan existente
        $stmt = $db->prepare("
            UPDATE subscription_plans 
            SET name = ?, description = ?, price = ?, duration_months = ?,
                max_users = ?, max_vehicles = ?, features = ?, status = ?
            WHERE id_plan = ?
        ");
        $stmt->execute([
            $name, $description, $price, $duration_months,
            $max_users, $max_vehicles, $features_json, $status,
            $id_plan
        ]);
    } else {
        // Insertar nuevo plan
        $stmt = $db->prepare("
            INSERT INTO subscription_plans 
            (name, description, price, duration_months, max_users, max_vehicles, features, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $name, $description, $price, $duration_months,
            $max_users, $max_vehicles, $features_json, $status
        ]);
    }

    // Confirmar transacción
    $db->commit();

    // Redirigir con mensaje de éxito
    $_SESSION['success_message'] = 'Plan guardado exitosamente';
    redirect('list.php');

} catch (Exception $e) {
    // Revertir transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Redirigir con mensaje de error
    $_SESSION['error_message'] = 'Error al guardar el plan: ' . $e->getMessage();
    redirect('list.php');
} 