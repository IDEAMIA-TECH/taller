-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS taller_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE taller_db;

-- Tabla de planes de suscripción
CREATE TABLE subscription_plans (
    id_plan INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_months INT NOT NULL,
    max_users INT NOT NULL,
    max_vehicles INT NOT NULL,
    features JSON,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de talleres
CREATE TABLE workshops (
    id_workshop INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    rfc VARCHAR(13),
    logo_path VARCHAR(255),
    subscription_status ENUM('active', 'suspended', 'cancelled') DEFAULT 'suspended',
    trial_end_date DATE,
    max_users_allowed INT DEFAULT 1,
    max_vehicles_allowed INT DEFAULT 10,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabla de usuarios
CREATE TABLE users (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    id_workshop INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('super_admin', 'admin', 'receptionist', 'mechanic') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop)
);

-- Tabla de suscripciones
CREATE TABLE workshop_subscriptions (
    id_subscription INT PRIMARY KEY AUTO_INCREMENT,
    id_workshop INT NOT NULL,
    id_plan INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'suspended', 'cancelled', 'pending_payment') DEFAULT 'pending_payment',
    payment_method VARCHAR(50),
    last_payment_date DATE,
    next_payment_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop),
    FOREIGN KEY (id_plan) REFERENCES subscription_plans(id_plan)
);

-- Tabla de clientes
CREATE TABLE clients (
    id_client INT PRIMARY KEY AUTO_INCREMENT,
    id_workshop INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    rfc VARCHAR(13),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop)
);

-- Tabla de vehículos
CREATE TABLE vehicles (
    id_vehicle INT PRIMARY KEY AUTO_INCREMENT,
    id_client INT NOT NULL,
    id_workshop INT NOT NULL,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    color VARCHAR(30),
    plates VARCHAR(20),
    vin VARCHAR(17),
    last_mileage INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_client) REFERENCES clients(id_client),
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop)
);

-- Tabla de servicios
CREATE TABLE services (
    id_service INT PRIMARY KEY AUTO_INCREMENT,
    id_workshop INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration INT, -- en minutos
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop)
);

-- Tabla de órdenes de servicio
CREATE TABLE service_orders (
    id_order INT PRIMARY KEY AUTO_INCREMENT,
    id_workshop INT NOT NULL,
    id_vehicle INT NOT NULL,
    id_client INT NOT NULL,
    id_user_created INT NOT NULL,
    id_user_assigned INT, -- mecánico asignado
    order_number VARCHAR(20) NOT NULL UNIQUE,
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    total_amount DECIMAL(10,2) DEFAULT 0,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop),
    FOREIGN KEY (id_vehicle) REFERENCES vehicles(id_vehicle),
    FOREIGN KEY (id_client) REFERENCES clients(id_client),
    FOREIGN KEY (id_user_created) REFERENCES users(id_user),
    FOREIGN KEY (id_user_assigned) REFERENCES users(id_user)
);

-- Tabla de detalles de órdenes
CREATE TABLE order_details (
    id_detail INT PRIMARY KEY AUTO_INCREMENT,
    id_order INT NOT NULL,
    id_service INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_order) REFERENCES service_orders(id_order),
    FOREIGN KEY (id_service) REFERENCES services(id_service)
);

-- Tabla de recordatorios
CREATE TABLE reminders (
    id_reminder INT PRIMARY KEY AUTO_INCREMENT,
    id_vehicle INT NOT NULL,
    id_service INT NOT NULL,
    reminder_type ENUM('date', 'mileage') NOT NULL,
    due_date DATE,
    due_mileage INT,
    status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_vehicle) REFERENCES vehicles(id_vehicle),
    FOREIGN KEY (id_service) REFERENCES services(id_service)
);

-- Tabla de facturas
CREATE TABLE invoices (
    id_invoice INT PRIMARY KEY AUTO_INCREMENT,
    id_order INT NOT NULL,
    id_workshop INT NOT NULL,
    invoice_number VARCHAR(20) NOT NULL UNIQUE,
    rfc_issuer VARCHAR(13) NOT NULL,
    rfc_receiver VARCHAR(13) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_form VARCHAR(50) NOT NULL,
    cfdi_use VARCHAR(50) NOT NULL,
    xml_path VARCHAR(255),
    pdf_path VARCHAR(255),
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_order) REFERENCES service_orders(id_order),
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop)
);

-- Tabla de pagos
CREATE TABLE payments (
    id_payment INT PRIMARY KEY AUTO_INCREMENT,
    id_subscription INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    receipt_path VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_subscription) REFERENCES workshop_subscriptions(id_subscription)
);

-- Tabla de notificaciones
CREATE TABLE payment_notifications (
    id_notification INT PRIMARY KEY AUTO_INCREMENT,
    id_workshop INT NOT NULL,
    notification_type ENUM('payment_due', 'payment_overdue', 'service_suspension', 'service_reactivation') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'pending', 'failed') DEFAULT 'pending',
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop)
);

-- Índices para optimización
CREATE INDEX idx_workshop_clients ON clients(id_workshop);
CREATE INDEX idx_workshop_vehicles ON vehicles(id_workshop);
CREATE INDEX idx_workshop_orders ON service_orders(id_workshop);
CREATE INDEX idx_vehicle_orders ON service_orders(id_vehicle);
CREATE INDEX idx_client_vehicles ON vehicles(id_client);
CREATE INDEX idx_order_status ON service_orders(status);
CREATE INDEX idx_invoice_number ON invoices(invoice_number);
CREATE INDEX idx_subscription_status ON workshops(subscription_status);
CREATE INDEX idx_subscription_dates ON workshop_subscriptions(start_date, end_date);
CREATE INDEX idx_payment_status ON payments(status);
CREATE INDEX idx_notification_status ON payment_notifications(status);

-- Triggers
DELIMITER //

-- Trigger para actualizar total_amount en service_orders
CREATE TRIGGER update_order_total
AFTER INSERT ON order_details
FOR EACH ROW
BEGIN
    UPDATE service_orders 
    SET total_amount = (
        SELECT SUM(subtotal) 
        FROM order_details 
        WHERE id_order = NEW.id_order
    )
    WHERE id_order = NEW.id_order;
END //

-- Trigger para actualizar estado del taller basado en suscripción
CREATE TRIGGER update_workshop_status
AFTER UPDATE ON workshop_subscriptions
FOR EACH ROW
BEGIN
    IF NEW.status = 'suspended' THEN
        UPDATE workshops 
        SET subscription_status = 'suspended'
        WHERE id_workshop = NEW.id_workshop;
    ELSEIF NEW.status = 'active' THEN
        UPDATE workshops 
        SET subscription_status = 'active'
        WHERE id_workshop = NEW.id_workshop;
    END IF;
END //

-- Trigger para crear notificación de pago vencido
CREATE TRIGGER create_payment_due_notification
BEFORE INSERT ON payment_notifications
FOR EACH ROW
BEGIN
    IF NEW.notification_type = 'payment_due' THEN
        SET NEW.message = CONCAT('Su pago mensual vence el ', 
            DATE_FORMAT((SELECT next_payment_date FROM workshop_subscriptions 
                        WHERE id_workshop = NEW.id_workshop 
                        ORDER BY id_subscription DESC LIMIT 1), '%d/%m/%Y'));
    END IF;
END //

DELIMITER ;

-- Insertar datos iniciales
INSERT INTO subscription_plans (name, description, price, duration_months, max_users, max_vehicles, features) VALUES
('Básico', 'Plan básico para talleres pequeños', 499.00, 1, 2, 50, '{"features": ["Gestión de clientes", "Gestión de vehículos", "Órdenes de servicio básicas"]}'),
('Profesional', 'Plan profesional para talleres medianos', 999.00, 1, 5, 200, '{"features": ["Todas las características del plan básico", "Facturación electrónica", "Reportes avanzados"]}'),
('Empresarial', 'Plan empresarial para talleres grandes', 1999.00, 1, 10, 500, '{"features": ["Todas las características del plan profesional", "API de integración", "Soporte prioritario"]}');

-- Insertar usuario super administrador
INSERT INTO workshops (name, address, phone, email, subscription_status) VALUES
('Taller Principal', 'Dirección del taller principal', '5551234567', 'admin@taller.com', 'active');

INSERT INTO users (id_workshop, username, password, full_name, email, role) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Principal', 'admin@taller.com', 'super_admin');
-- Nota: La contraseña es 'password' 