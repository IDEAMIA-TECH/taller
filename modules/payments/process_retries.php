<?php
require_once '../../includes/config.php';
require_once 'PaymentController.php';

// Obtener pagos pendientes de reintento
$stmt = $db->prepare("
    SELECT pr.*, p.* 
    FROM payment_retries pr
    JOIN payments p ON pr.id_payment = p.id_payment
    WHERE pr.status = 'pending'
    AND pr.scheduled_at <= NOW()
    ORDER BY pr.scheduled_at ASC
");

$stmt->execute();
$retries = $stmt->fetchAll();

foreach ($retries as $retry) {
    try {
        // Actualizar estado del reintento
        $updateStmt = $db->prepare("
            UPDATE payment_retries 
            SET status = 'processing',
                started_at = NOW()
            WHERE id_retry = ?
        ");
        $updateStmt->execute([$retry['id_retry']]);

        // Procesar el pago
        $controller = new PaymentController($db, $retry['payment_method']);
        $result = $controller->createPayment([
            'amount' => $retry['amount'],
            'currency' => $retry['currency'],
            'description' => "Reintento de pago #{$retry['id_payment']}",
            'customer_email' => $retry['customer_email'],
            'id_subscription' => $retry['id_subscription']
        ]);

        // Actualizar estado a completado
        $updateStmt = $db->prepare("
            UPDATE payment_retries 
            SET status = 'completed',
                completed_at = NOW(),
                result = ?
            WHERE id_retry = ?
        ");
        $updateStmt->execute([
            json_encode($result),
            $retry['id_retry']
        ]);

        // Registrar éxito
        logRetryResult($retry, $result);
    } catch (Exception $e) {
        // Actualizar estado a fallido
        $updateStmt = $db->prepare("
            UPDATE payment_retries 
            SET status = 'failed',
                completed_at = NOW(),
                error_message = ?
            WHERE id_retry = ?
        ");
        $updateStmt->execute([
            $e->getMessage(),
            $retry['id_retry']
        ]);

        // Registrar error
        logRetryError($retry, $e);
    }
}

function logRetryResult($retry, $result) {
    $logFile = __DIR__ . "/logs/retry_success_" . date('Y-m-d') . ".log";
    $logMessage = date('Y-m-d H:i:s') . " - Reintento exitoso\n";
    $logMessage .= "ID Pago: {$retry['id_payment']}\n";
    $logMessage .= "ID Reintento: {$retry['id_retry']}\n";
    $logMessage .= "Resultado: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function logRetryError($retry, $exception) {
    $logFile = __DIR__ . "/logs/retry_error_" . date('Y-m-d') . ".log";
    $logMessage = date('Y-m-d H:i:s') . " - Error en reintento\n";
    $logMessage .= "ID Pago: {$retry['id_payment']}\n";
    $logMessage .= "ID Reintento: {$retry['id_retry']}\n";
    $logMessage .= "Error: " . $exception->getMessage() . "\n";
    $logMessage .= "Stack trace: " . $exception->getTraceAsString() . "\n\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
} 