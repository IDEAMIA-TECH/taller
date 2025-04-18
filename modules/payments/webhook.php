<?php
require_once '../../includes/config.php';
require_once 'PaymentController.php';

// Obtener el payload y headers
$payload = file_get_contents('php://input');
$headers = getallheaders();

// Determinar la pasarela de pago basada en el origen de la petición
$gateway = determinePaymentGateway($headers);

try {
    $controller = new PaymentController($db, $gateway);
    
    // Procesar el webhook
    $result = $controller->processWebhook($payload, $headers);
    
    // Registrar el resultado
    logWebhookResult($gateway, $result);
    
    // Responder con éxito
    http_response_code(200);
    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    // Registrar el error
    logWebhookError($gateway, $e);
    
    // Responder con error
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function determinePaymentGateway($headers) {
    if (isset($headers['Stripe-Signature'])) {
        return 'stripe';
    } elseif (isset($headers['Paypal-Transmission-Id'])) {
        return 'paypal';
    }
    throw new Exception("No se pudo determinar la pasarela de pago");
}

function logWebhookResult($gateway, $result) {
    $logFile = __DIR__ . "/logs/webhook_{$gateway}_" . date('Y-m-d') . ".log";
    $logMessage = date('Y-m-d H:i:s') . " - Webhook procesado exitosamente\n";
    $logMessage .= "Resultado: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

function logWebhookError($gateway, $exception) {
    $logFile = __DIR__ . "/logs/webhook_{$gateway}_error_" . date('Y-m-d') . ".log";
    $logMessage = date('Y-m-d H:i:s') . " - Error en webhook\n";
    $logMessage .= "Mensaje: " . $exception->getMessage() . "\n";
    $logMessage .= "Stack trace: " . $exception->getTraceAsString() . "\n\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND);
} 