<?php
require_once '../../../includes/config.php';

// Verificar autenticación y permisos de super administrador
if (!isAuthenticated() || !isSuperAdmin()) {
    redirect('templates/login.php');
}

// Obtener el ID del taller si se proporciona
$id_workshop = isset($_GET['id_workshop']) ? (int)$_GET['id_workshop'] : 0;

// Construir la consulta base
$query = "
    SELECT pn.*, w.name AS workshop_name
    FROM payment_notifications pn
    JOIN workshops w ON pn.id_workshop = w.id_workshop
";

// Agregar filtro por taller si se especifica
if ($id_workshop > 0) {
    $query .= " WHERE pn.id_workshop = ?";
    $params = [$id_workshop];
} else {
    $params = [];
}

$query .= " ORDER BY pn.created_at DESC";

// Ejecutar la consulta
$stmt = $db->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Obtener lista de talleres para el filtro
$stmt = $db->query("SELECT id_workshop, name FROM workshops ORDER BY name");
$workshops = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificaciones de Pago - Sistema de Talleres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Notificaciones de Pago</h1>
            </div>
            <div class="col-auto">
                <a href="list.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Volver
                </a>
            </div>
        </div>

        <!-- Filtro por taller -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="id_workshop" class="form-label">Filtrar por Taller</label>
                        <select class="form-select" id="id_workshop" name="id_workshop">
                            <option value="">Todos los talleres</option>
                            <?php foreach ($workshops as $workshop): ?>
                                <option value="<?= $workshop['id_workshop'] ?>" 
                                    <?= $id_workshop == $workshop['id_workshop'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($workshop['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Lista de notificaciones -->
        <div class="card">
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="alert alert-info">
                        No hay notificaciones registradas.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Taller</th>
                                    <th>Tipo</th>
                                    <th>Mensaje</th>
                                    <th>Fecha de Vencimiento</th>
                                    <th>Estado</th>
                                    <th>Fecha de Creación</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($notification['workshop_name']) ?></td>
                                        <td><?= htmlspecialchars($notification['notification_type']) ?></td>
                                        <td><?= htmlspecialchars($notification['message']) ?></td>
                                        <td>
                                            <?= $notification['due_date'] 
                                                ? date('d/m/Y', strtotime($notification['due_date']))
                                                : 'No especificada' ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $notification['status'] === 'pending' ? 'warning' : 'success' ?>">
                                                <?= ucfirst($notification['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($notification['created_at'])) ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-primary" 
                                                onclick="markAsRead(<?= $notification['id_notification'] ?>)">
                                                <i class="bi bi-check-circle"></i> Marcar como Leída
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function markAsRead(id_notification) {
            if (confirm('¿Está seguro de marcar esta notificación como leída?')) {
                fetch('mark_as_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'id_notification=' + id_notification
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Error al marcar la notificación como leída');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al marcar la notificación como leída');
                });
            }
        }
    </script>
</body>
</html> 