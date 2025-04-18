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

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    showError('Método no permitido');
    redirect('list_invoices.php');
}

// Obtener y validar el ID de la factura
$id_invoice = isset($_POST['id_invoice']) ? (int)$_POST['id_invoice'] : 0;

if (!$id_invoice) {
    showError('ID de factura no válido');
    redirect('list_invoices.php');
}

try {
    // Iniciar transacción
    $db->beginTransaction();

    // Obtener datos de la factura
    $stmt = $db->prepare("
        SELECT 
            i.*,
            w.name as workshop_name,
            w.rfc as workshop_rfc,
            w.pac_username,
            w.pac_password,
            w.pac_url,
            w.certificate_path,
            w.key_path,
            w.key_password
        FROM invoices i
        JOIN workshops w ON i.id_workshop = w.id_workshop
        WHERE i.id_invoice = ? AND i.id_workshop = ?
    ");
    $stmt->execute([$id_invoice, getCurrentWorkshop()]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        throw new Exception('Factura no encontrada');
    }

    // Verificar que la factura no esté ya timbrada
    if ($invoice['status'] === 'paid') {
        throw new Exception('La factura ya ha sido timbrada');
    }

    // Verificar que exista el archivo XML
    if (!file_exists($invoice['xml_path'])) {
        throw new Exception('No se encontró el archivo XML de la factura');
    }

    // Leer el contenido del XML
    $xml_content = file_get_contents($invoice['xml_path']);

    // Preparar datos para el PAC
    $pac_data = [
        'username' => $invoice['pac_username'],
        'password' => $invoice['pac_password'],
        'xml' => base64_encode($xml_content)
    ];

    // Inicializar cURL para comunicación con el PAC
    $ch = curl_init($invoice['pac_url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($pac_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // Ejecutar la petición al PAC
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        throw new Exception('Error en la comunicación con el PAC: ' . $response);
    }

    // Decodificar la respuesta
    $result = json_decode($response, true);

    if (!$result || !isset($result['success']) || !$result['success']) {
        throw new Exception('Error en la respuesta del PAC: ' . ($result['message'] ?? 'Error desconocido'));
    }

    // Obtener el XML timbrado
    $stamped_xml = base64_decode($result['xml']);
    
    // Generar nombre de archivo para el XML timbrado
    $stamped_filename = 'factura_' . $invoice['invoice_number'] . '_timbrado.xml';
    $stamped_path = '../../uploads/invoices/' . $stamped_filename;

    // Guardar el XML timbrado
    if (!file_put_contents($stamped_path, $stamped_xml)) {
        throw new Exception('Error al guardar el XML timbrado');
    }

    // Actualizar la factura en la base de datos
    $stmt = $db->prepare("
        UPDATE invoices 
        SET 
            status = 'paid',
            xml_path = ?,
            stamped_at = NOW(),
            pac_response = ?
        WHERE id_invoice = ?
    ");
    $stmt->execute([
        $stamped_path,
        $response,
        $id_invoice
    ]);

    // Confirmar la transacción
    $db->commit();

    // Redirigir con mensaje de éxito
    $_SESSION['success'] = 'Factura timbrada exitosamente';
    redirect('view_invoice.php?id=' . $id_invoice);

} catch (Exception $e) {
    // Revertir la transacción en caso de error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Registrar el error
    error_log('Error al timbrar factura: ' . $e->getMessage());

    // Redirigir con mensaje de error
    $_SESSION['error'] = 'Error al timbrar la factura: ' . $e->getMessage();
    redirect('view_invoice.php?id=' . $id_invoice);
} 