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

// Obtener y validar el ID de la factura
$id_invoice = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id_invoice) {
    showError('ID de factura no válido');
    redirect('list_invoices.php');
}

try {
    // Obtener datos de la factura
    $stmt = $db->prepare("
        SELECT 
            i.*,
            w.name as workshop_name,
            w.address as workshop_address,
            w.phone as workshop_phone,
            w.email as workshop_email,
            w.rfc as workshop_rfc,
            w.regimen_fiscal as workshop_regimen_fiscal,
            c.name as client_name,
            c.phone as client_phone,
            c.email as client_email,
            c.rfc as client_rfc,
            c.regimen_fiscal as client_regimen_fiscal,
            v.brand,
            v.model,
            v.plates,
            so.order_number,
            so.created_at as order_date
        FROM invoices i
        JOIN workshops w ON i.id_workshop = w.id_workshop
        JOIN service_orders so ON i.id_order = so.id_order
        JOIN clients c ON so.id_client = c.id_client
        JOIN vehicles v ON so.id_vehicle = v.id_vehicle
        WHERE i.id_invoice = ? AND i.id_workshop = ?
    ");
    $stmt->execute([$id_invoice, getCurrentWorkshop()]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        showError('Factura no encontrada');
        redirect('list_invoices.php');
    }

    // Obtener detalles de la orden
    $stmt = $db->prepare("
        SELECT od.*, s.name as service_name
        FROM order_details od
        JOIN services s ON od.id_service = s.id_service
        WHERE od.id_order = ?
    ");
    $stmt->execute([$invoice['id_order']]);
    $order_details = $stmt->fetchAll();

} catch (PDOException $e) {
    showError('Error al cargar los datos de la factura');
    redirect('list_invoices.php');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?php echo htmlspecialchars($invoice['invoice_number']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 1cm;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }
        .container {
            max-width: 21cm;
            margin: 0 auto;
            padding: 1cm;
        }
        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2cm;
        }
        .workshop-info {
            flex: 1;
        }
        .invoice-info {
            text-align: right;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 0.5cm;
            text-align: center;
        }
        .section {
            margin-bottom: 1cm;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 0.5cm;
            border-bottom: 1px solid #333;
            padding-bottom: 0.2cm;
        }
        .row {
            display: flex;
            margin-bottom: 0.3cm;
        }
        .col {
            flex: 1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1cm;
        }
        th, td {
            border: 1px solid #333;
            padding: 0.3cm;
            text-align: left;
        }
        th {
            background-color: #f0f0f0;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .signature-section {
            margin-top: 2cm;
            display: flex;
            justify-content: space-between;
        }
        .signature-box {
            width: 6cm;
            border-top: 1px solid #333;
            padding-top: 0.5cm;
            text-align: center;
        }
        .footer {
            margin-top: 2cm;
            font-size: 10px;
            text-align: center;
        }
        @media print {
            .no-print {
                display: none;
            }
            body {
                font-size: 10pt;
            }
            .container {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Botón de impresión (solo visible en pantalla) -->
        <div class="no-print" style="text-align: right; margin-bottom: 1cm;">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Imprimir
            </button>
        </div>

        <!-- Encabezado -->
        <div class="header">
            <div class="workshop-info">
                <h2><?php echo htmlspecialchars($invoice['workshop_name']); ?></h2>
                <p><?php echo nl2br(htmlspecialchars($invoice['workshop_address'])); ?></p>
                <p>Tel: <?php echo htmlspecialchars($invoice['workshop_phone']); ?></p>
                <p>Email: <?php echo htmlspecialchars($invoice['workshop_email']); ?></p>
                <p>RFC: <?php echo htmlspecialchars($invoice['workshop_rfc']); ?></p>
                <p>Régimen Fiscal: <?php echo htmlspecialchars($invoice['workshop_regimen_fiscal']); ?></p>
            </div>
            <div class="invoice-info">
                <h1 class="invoice-title">FACTURA</h1>
                <p><strong>Número:</strong> <?php echo htmlspecialchars($invoice['invoice_number']); ?></p>
                <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($invoice['created_at'])); ?></p>
                <p><strong>Orden de Servicio:</strong> <?php echo htmlspecialchars($invoice['order_number']); ?></p>
            </div>
        </div>

        <!-- Datos del Cliente -->
        <div class="section">
            <h3 class="section-title">Datos del Cliente</h3>
            <div class="row">
                <div class="col">
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($invoice['client_name']); ?></p>
                    <p><strong>RFC:</strong> <?php echo htmlspecialchars($invoice['client_rfc']); ?></p>
                    <p><strong>Régimen Fiscal:</strong> <?php echo htmlspecialchars($invoice['client_regimen_fiscal']); ?></p>
                </div>
                <div class="col">
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($invoice['client_phone']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($invoice['client_email']); ?></p>
                </div>
            </div>
        </div>

        <!-- Datos del Vehículo -->
        <div class="section">
            <h3 class="section-title">Datos del Vehículo</h3>
            <div class="row">
                <div class="col">
                    <p><strong>Marca/Modelo:</strong> <?php echo htmlspecialchars($invoice['brand'] . ' ' . $invoice['model']); ?></p>
                    <p><strong>Placas:</strong> <?php echo htmlspecialchars($invoice['plates']); ?></p>
                </div>
            </div>
        </div>

        <!-- Detalles de la Factura -->
        <div class="section">
            <h3 class="section-title">Detalles de la Factura</h3>
            <table>
                <thead>
                    <tr>
                        <th>Cantidad</th>
                        <th>Descripción</th>
                        <th class="text-right">Precio Unitario</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order_details as $detail): ?>
                        <tr>
                            <td class="text-center"><?php echo $detail['quantity']; ?></td>
                            <td><?php echo htmlspecialchars($detail['service_name']); ?></td>
                            <td class="text-right">$<?php echo number_format($detail['unit_price'], 2); ?></td>
                            <td class="text-right">$<?php echo number_format($detail['subtotal'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right"><strong>Total:</strong></td>
                        <td class="text-right"><strong>$<?php echo number_format($invoice['total_amount'], 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Información Adicional -->
        <div class="section">
            <h3 class="section-title">Información Adicional</h3>
            <div class="row">
                <div class="col">
                    <p><strong>Método de Pago:</strong> <?php echo htmlspecialchars($invoice['payment_method']); ?></p>
                    <p><strong>Forma de Pago:</strong> <?php echo htmlspecialchars($invoice['payment_form']); ?></p>
                    <p><strong>Uso del CFDI:</strong> <?php echo htmlspecialchars($invoice['cfdi_use']); ?></p>
                </div>
            </div>
        </div>

        <!-- Firmas -->
        <div class="signature-section">
            <div class="signature-box">
                <p>Firma del Cliente</p>
            </div>
            <div class="signature-box">
                <p>Firma del Representante</p>
            </div>
        </div>

        <!-- Pie de Página -->
        <div class="footer">
            <p>Este documento es una representación impresa de un CFDI. Para consultar el documento electrónico, 
               escanee el código QR o visite el portal del SAT.</p>
            <p>Fecha y hora de impresión: <?php echo date('d/m/Y H:i'); ?></p>
        </div>
    </div>

    <script>
        // Imprimir automáticamente al cargar la página
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html> 