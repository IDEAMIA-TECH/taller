<?php
require_once '../../includes/config.php';
require_once '../../includes/payment_config.php';

class PaymentController {
    private $db;
    private $config;
    private $paymentGateway;

    public function __construct($db, $gateway = 'stripe') {
        $this->db = $db;
        $this->config = require '../../includes/payment_config.php';
        $this->setPaymentGateway($gateway);
    }

    public function setPaymentGateway($gateway) {
        if (!isset($this->config[$gateway]) || !$this->config[$gateway]['enabled']) {
            throw new Exception("Pasarela de pago no disponible");
        }
        $this->paymentGateway = $gateway;
    }

    public function createPayment($data) {
        // Validar datos del pago
        $this->validatePaymentData($data);

        // Crear registro de pago
        $paymentId = $this->createPaymentRecord($data);

        // Procesar pago según la pasarela seleccionada
        try {
            $result = $this->processPayment($data, $paymentId);
            $this->updatePaymentStatus($paymentId, 'completed', $result);
            return $result;
        } catch (Exception $e) {
            $this->handlePaymentError($paymentId, $e);
            throw $e;
        }
    }

    private function validatePaymentData($data) {
        $required = ['amount', 'currency', 'description', 'customer_email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Campo requerido faltante: $field");
            }
        }
    }

    private function createPaymentRecord($data) {
        $stmt = $this->db->prepare("
            INSERT INTO payments (
                id_subscription,
                amount,
                currency,
                payment_method,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $data['id_subscription'] ?? null,
            $data['amount'],
            $data['currency'] ?? $this->config[$this->paymentGateway]['currency'],
            $this->paymentGateway
        ]);

        return $this->db->lastInsertId();
    }

    private function processPayment($data, $paymentId) {
        switch ($this->paymentGateway) {
            case 'stripe':
                return $this->processStripePayment($data, $paymentId);
            case 'paypal':
                return $this->processPayPalPayment($data, $paymentId);
            case 'oxxo':
                return $this->processOxxoPayment($data, $paymentId);
            case 'bank_transfer':
                return $this->processBankTransfer($data, $paymentId);
            default:
                throw new Exception("Pasarela de pago no soportada");
        }
    }

    private function processStripePayment($data, $paymentId) {
        // Implementar lógica de Stripe
        // ...
    }

    private function processPayPalPayment($data, $paymentId) {
        // Implementar lógica de PayPal
        // ...
    }

    private function processOxxoPayment($data, $paymentId) {
        // Implementar lógica de OXXO
        // ...
    }

    private function processBankTransfer($data, $paymentId) {
        // Implementar lógica de transferencia bancaria
        // ...
    }

    private function updatePaymentStatus($paymentId, $status, $result = null) {
        $stmt = $this->db->prepare("
            UPDATE payments 
            SET status = ?,
                transaction_id = ?,
                receipt_path = ?,
                updated_at = NOW()
            WHERE id_payment = ?
        ");

        $stmt->execute([
            $status,
            $result['transaction_id'] ?? null,
            $result['receipt_path'] ?? null,
            $paymentId
        ]);
    }

    private function handlePaymentError($paymentId, $exception) {
        $this->updatePaymentStatus($paymentId, 'failed');
        
        // Registrar intento fallido
        $stmt = $this->db->prepare("
            INSERT INTO payment_attempts (
                id_payment,
                attempt_number,
                error_message,
                created_at
            ) VALUES (?, 1, ?, NOW())
        ");

        $stmt->execute([
            $paymentId,
            $exception->getMessage()
        ]);

        // Programar reintento si es posible
        $this->scheduleRetry($paymentId);
    }

    private function scheduleRetry($paymentId) {
        $maxRetries = $this->config[$this->paymentGateway]['max_retries'];
        $retryInterval = $this->config[$this->paymentGateway]['retry_interval'];

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM payment_attempts 
            WHERE id_payment = ?
        ");
        $stmt->execute([$paymentId]);
        $attempts = $stmt->fetch()['attempts'];

        if ($attempts < $maxRetries) {
            $nextRetry = date('Y-m-d H:i:s', time() + $retryInterval);
            
            $stmt = $this->db->prepare("
                INSERT INTO payment_retries (
                    id_payment,
                    scheduled_at,
                    status
                ) VALUES (?, ?, 'pending')
            ");
            $stmt->execute([$paymentId, $nextRetry]);
        }
    }

    public function processWebhook($payload, $signature) {
        switch ($this->paymentGateway) {
            case 'stripe':
                return $this->processStripeWebhook($payload, $signature);
            case 'paypal':
                return $this->processPayPalWebhook($payload, $signature);
            default:
                throw new Exception("Webhook no soportado para esta pasarela");
        }
    }

    private function processStripeWebhook($payload, $signature) {
        // Implementar verificación y procesamiento de webhook de Stripe
        // ...
    }

    private function processPayPalWebhook($payload, $signature) {
        // Implementar verificación y procesamiento de webhook de PayPal
        // ...
    }
} 