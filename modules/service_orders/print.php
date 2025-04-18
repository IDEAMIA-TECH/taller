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

// Obtener y validar el ID de la orden
$id_order = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_order) {
    showError('ID de orden no válido');
    redirect('index.php');
}

try {
    // Obtener datos del taller
    $stmt = $db->prepare("
        SELECT 
            w.name as workshop_name,
            w.address,
            w.phone,
            w.email,
            w.rfc,
            w.logo_path
        FROM workshops w
        WHERE w.id_workshop = ?
    ");
    $stmt->execute([getCurrentWorkshop()]);
    $workshop = $stmt->fetch();

    // Obtener datos de la orden
    $stmt = $db->prepare("
        SELECT 
            so.*,
            c.name as client_name,
            c.phone as client_phone,
            c.email as client_email,
            c.rfc as client_rfc,
            v.brand,
            v.model,
            v.plates,
            v.year,
            v.color,
            v.vin,
            v.last_mileage,
            u_created.full_name as created_by,
            u_assigned.full_name as assigned_to
        FROM service_orders so
        JOIN clients c ON so.id_client = c.id_client
        JOIN vehicles v ON so.id_vehicle = v.id_vehicle
        JOIN users u_created ON so.id_user_created = u_created.id_user
        LEFT JOIN users u_assigned ON so.id_user_assigned = u_assigned.id_user
        WHERE so.id_order = ? AND so.id_workshop = ?
    ");
    $stmt->execute([$id_order, getCurrentWorkshop()]);
    $order = $stmt->fetch();

    if (!$order) {
        showError('Orden no encontrada');
        redirect('index.php');
    }

    // Obtener detalles de la orden
    $stmt = $db->prepare("
        SELECT od.*, s.name as service_name, s.duration
        FROM order_details od
        JOIN services s ON od.id_service = s.id_service
        WHERE od.id_order = ?
    ");
    $stmt->execute([$id_order]);
    $order_details = $stmt->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar los datos de la orden');
    redirect('index.php');
}

// Configurar encabezados para impresión
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Servicio #<?php echo $order['order_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            body {
                padding: 20px;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
        }
        .header {
            border-bottom: 2px solid #000;
            margin-bottom: 20px;
            padding-bottom: 20px;
        }
        .workshop-logo {
            max-height: 100px;
            max-width: 200px;
        }
        .order-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .table {
            margin-bottom: 0;
        }
        .table th {
            background-color: #f8f9fa;
        }
        .signature-section {
            margin-top: 50px;
            border-top: 1px solid #000;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Botón de impresión -->
        <div class="text-end mb-3 no-print">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>

        <!-- Encabezado -->
        <div class="header">
            <div class="row">
                <div class="col-md-6">
                    <?php if ($workshop['logo_path']): ?>
                        <img src="<?php echo htmlspecialchars($workshop['logo_path']); ?>" alt="Logo" class="workshop-logo mb-3">
                    <?php endif; ?>
                    <h2><?php echo htmlspecialchars($workshop['workshop_name']); ?></h2>
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($workshop['address'])); ?></p>
                    <p class="mb-1">Tel: <?php echo htmlspecialchars($workshop['phone']); ?></p>
                    <p class="mb-1">Email: <?php echo htmlspecialchars($workshop['email']); ?></p>
                    <p class="mb-0">RFC: <?php echo htmlspecialchars($workshop['rfc']); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <h1 class="mb-3">Orden de Servicio</h1>
                    <p class="mb-1"><strong>Número:</strong> <?php echo $order['order_number']; ?></p>
                    <p class="mb-1"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                    <p class="mb-0"><strong>Estado:</strong> <?php echo ucfirst($order['status']); ?></p>
                </div>
            </div>
        </div>

        <!-- Información del Cliente y Vehículo -->
        <div class="order-info">
            <div class="row">
                <div class="col-md-6">
                    <h5>Datos del Cliente</h5>
                    <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($order['client_name']); ?></p>
                    <p class="mb-1"><strong>Teléfono:</strong> <?php echo htmlspecialchars($order['client_phone']); ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($order['client_email']); ?></p>
                    <?php if ($order['client_rfc']): ?>
                        <p class="mb-0"><strong>RFC:</strong> <?php echo htmlspecialchars($order['client_rfc']); ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6">
                    <h5>Datos del Vehículo</h5>
                    <p class="mb-1"><strong>Marca/Modelo:</strong> <?php echo htmlspecialchars($order['brand'] . ' ' . $order['model']); ?></p>
                    <p class="mb-1"><strong>Placas:</strong> <?php echo htmlspecialchars($order['plates']); ?></p>
                    <p class="mb-1"><strong>Año:</strong> <?php echo $order['year']; ?></p>
                    <p class="mb-1"><strong>Color:</strong> <?php echo htmlspecialchars($order['color']); ?></p>
                    <p class="mb-1"><strong>VIN:</strong> <?php echo htmlspecialchars($order['vin']); ?></p>
                    <p class="mb-0"><strong>Kilometraje:</strong> <?php echo number_format($order['last_mileage']); ?> km</p>
                </div>
            </div>
        </div>

        <!-- Servicios -->
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Servicio</th>
                        <th class="text-end">Precio Unitario</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_details as $detail): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detail['service_name']); ?></td>
                            <td class="text-end">$<?php echo number_format($detail['unit_price'], 2); ?></td>
                            <td class="text-center"><?php echo $detail['quantity']; ?></td>
                            <td class="text-end">$<?php echo number_format($detail['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3" class="text-end">Total:</th>
                        <th class="text-end">$<?php echo number_format($order['total_amount'], 2); ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Notas -->
        <?php if ($order['notes']): ?>
            <div class="mt-4">
                <h5>Notas</h5>
                <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
            </div>
        <?php endif; ?>

        <!-- Sección de Firmas -->
        <div class="signature-section">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-4">___________________________</p>
                    <p>Firma del Cliente</p>
                </div>
                <div class="col-md-6 text-end">
                    <p class="mb-4">___________________________</p>
                    <p>Firma del Mecánico</p>
                </div>
            </div>
        </div>

        <!-- Información Adicional -->
        <div class="mt-4 small text-muted">
            <p class="mb-1">Orden creada por: <?php echo htmlspecialchars($order['created_by']); ?></p>
            <?php if ($order['assigned_to']): ?>
                <p class="mb-1">Mecánico asignado: <?php echo htmlspecialchars($order['assigned_to']); ?></p>
            <?php endif; ?>
            <p class="mb-0">Última actualización: <?php echo date('d/m/Y H:i', strtotime($order['updated_at'])); ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://kit.fontawesome.com/a076d05399.js"></script>
</body>
</html> 