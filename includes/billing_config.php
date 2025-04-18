<?php
// Configuración de Facturación
return [
    // Configuración general
    'version' => '4.0',
    'currency' => 'MXN',
    'decimal_precision' => 2,
    
    // Configuración del PAC
    'pac' => [
        'enabled' => true,
        'test_mode' => true,
        'provider' => 'finkok', // o el PAC seleccionado
        'username' => '',
        'password' => '',
        'endpoint' => [
            'test' => 'https://demo-finkok.com/services/soap',
            'production' => 'https://facturacion.finkok.com/services/soap'
        ]
    ],
    
    // Configuración de timbrado
    'stamping' => [
        'max_retries' => 3,
        'retry_interval' => 300, // segundos
        'timeout' => 30 // segundos
    ],
    
    // Configuración de archivos
    'paths' => [
        'xml' => __DIR__ . '/../storage/invoices/xml/',
        'pdf' => __DIR__ . '/../storage/invoices/pdf/',
        'templates' => __DIR__ . '/../templates/invoices/'
    ],
    
    // Configuración de correo
    'email' => [
        'enabled' => true,
        'subject' => 'Factura Electrónica',
        'template' => 'invoice_email.php'
    ],
    
    // Configuración de complementos
    'complements' => [
        'pago' => true,
        'nomina' => false,
        'comercio_exterior' => false
    ]
]; 