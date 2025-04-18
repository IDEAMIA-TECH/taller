<?php
// Configuración de la base de datos
return [
    'host' => 'localhost',
    'database' => 'ideamiadev_taller',
    'username' => 'ideamiadev_taller',
    'password' => 'j5?rAqQ5D#zy3Y76',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
]; 