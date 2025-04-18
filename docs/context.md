# Sistema de Gestión para Taller Mecánico

## Descripción General
Sistema web multi-taller para la administración de talleres mecánicos, con funcionalidades para manejar clientes, vehículos, servicios, órdenes de servicio, recordatorios de mantenimiento, facturación 4.0 y generación de reportes. 

Cada taller operará de forma independiente, con su propio conjunto de usuarios, clientes, vehículos, configuración, logotipo y accesos, sin mezclar información entre talleres.

## Arquitectura del Sistema

### Estructura de Archivos
```
/
├── assets/
│   ├── css/
│   │   ├── main.css
│   │   └── responsive.css
│   ├── js/
│   │   ├── main.js
│   │   └── modules/
│   └── img/
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── sidebar.php
│   ├── config.php
│   └── functions.php
├── modules/
│   ├── auth/
│   ├── clients/
│   ├── vehicles/
│   ├── services/
│   └── reports/
└── templates/
    ├── dashboard.php
    ├── login.php
    └── error.php
```

### Componentes Principales
1. **Archivos Maestros**
   - `header.php`: Encabezado común con menú de navegación
   - `footer.php`: Pie de página con scripts comunes
   - `sidebar.php`: Menú lateral con opciones según rol
   - `config.php`: Configuración global y conexión a BD
   - `functions.php`: Funciones auxiliares comunes

2. **Sistema de Plantillas**
   - Uso de `include()` para componentes reutilizables
   - Plantillas base para diferentes tipos de páginas
   - Sistema de mensajes y alertas estandarizado

3. **Gestión de Sesiones**
   - Sistema de autenticación basado en sesiones PHP
   - Control de acceso por roles y permisos
   - Protección contra CSRF y XSS

## Estructura de la Base de Datos

### Tablas Principales

#### 1. Talleres (workshops)
```sql
CREATE TABLE workshops (
    id_workshop INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    address TEXT NOT NULL,
    phone VARCHAR(20),
    email VARCHAR(100),
    rfc VARCHAR(13),
    logo_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 2. Usuarios (users)
```sql
CREATE TABLE users (
    id_user INT PRIMARY KEY AUTO_INCREMENT,
    id_workshop INT NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role ENUM('admin', 'receptionist', 'mechanic') NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_workshop) REFERENCES workshops(id_workshop)
);
```

#### 3. Clientes (clients)
```sql
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
```

#### 4. Vehículos (vehicles)
```sql
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
```

#### 5. Servicios (services)
```sql
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
```

#### 6. Órdenes de Servicio (service_orders)
```sql
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
```

#### 7. Detalles de Órdenes (order_details)
```sql
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
```

#### 8. Recordatorios (reminders)
```sql
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
```

#### 9. Facturas (invoices)
```sql
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
```

#### 0. Planes de Suscripción (subscription_plans)
```sql
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
```

#### 1. Suscripciones de Talleres (workshop_subscriptions)
```sql
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
```

#### 2. Pagos (payments)
```sql
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
```

#### 3. Notificaciones de Pago (payment_notifications)
```sql
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
```

### Modificaciones a Tablas Existentes

#### Modificación a la tabla workshops
```sql
ALTER TABLE workshops
ADD COLUMN subscription_status ENUM('active', 'suspended', 'cancelled') DEFAULT 'suspended',
ADD COLUMN trial_end_date DATE,
ADD COLUMN max_users_allowed INT DEFAULT 1,
ADD COLUMN max_vehicles_allowed INT DEFAULT 10;
```

### Índices y Optimizaciones
```sql
-- Índices para búsquedas frecuentes
CREATE INDEX idx_workshop_clients ON clients(id_workshop);
CREATE INDEX idx_workshop_vehicles ON vehicles(id_workshop);
CREATE INDEX idx_workshop_orders ON service_orders(id_workshop);
CREATE INDEX idx_vehicle_orders ON service_orders(id_vehicle);
CREATE INDEX idx_client_vehicles ON vehicles(id_client);
CREATE INDEX idx_order_status ON service_orders(status);
CREATE INDEX idx_invoice_number ON invoices(invoice_number);
```

### Índices Adicionales
```sql
CREATE INDEX idx_subscription_status ON workshops(subscription_status);
CREATE INDEX idx_subscription_dates ON workshop_subscriptions(start_date, end_date);
CREATE INDEX idx_payment_status ON payments(status);
CREATE INDEX idx_notification_status ON payment_notifications(status);
```

### Triggers para Auditoría
```sql
-- Trigger para actualizar total_amount en service_orders
DELIMITER //
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
DELIMITER ;
```

### Triggers Adicionales
```sql
-- Trigger para actualizar estado del taller basado en suscripción
DELIMITER //
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
DELIMITER ;

-- Trigger para crear notificación de pago vencido
DELIMITER //
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
```

## Objetivos
- Agilizar la gestión de servicios mecánicos.
- Controlar clientes y sus vehículos por taller.
- Registrar y dar seguimiento a órdenes de servicio.
- Generar recordatorios automáticos de mantenimiento.
- Emitir facturas electrónicas 4.0 válidas en México.
- Facilitar la cobranza mediante reportes detallados.
- Soportar múltiples talleres independientes dentro de una sola plataforma.

## Roles de Usuario

### 0. Super Administrador (Nuevo)
- Acceso total al sistema para todos los talleres
- Gestión de suscripciones y pagos de talleres
- Activación/desactivación de talleres
- Visualización de reportes globales
- Configuración de planes y precios
- Gestión de usuarios super administradores

### 1. Administrador
- Acceso total al sistema dentro de su taller.
- Gestión de usuarios y roles de su taller.
- Configuración del sistema, datos fiscales y logotipo del taller.
- Visualización de reportes generales de su propio taller.

### 2. Recepcionista
- Registro de clientes y vehículos del taller.
- Creación y cierre de órdenes de servicio.
- Emisión de reportes de servicio y facturas.
- Programación de citas y recordatorios.

### 3. Mecánico
- Visualización de órdenes asignadas del taller.
- Registro de avance y servicios realizados.
- Cierre parcial de actividades en la orden.

## Módulos Principales

### 0. Panel de Super Administrador (Nuevo)
- Gestión de planes de suscripción
- Monitoreo de pagos y suscripciones
- Activación/desactivación de talleres
- Reportes financieros globales
- Configuración de notificaciones automáticas
- Gestión de usuarios super administradores

### 1. Gestión de Talleres
- Alta de nuevos talleres.
- Configuración independiente por taller: nombre, dirección, RFC, logotipo, datos fiscales.

### 2. Gestión de Clientes
- Alta, baja y edición de clientes por taller.
- Consulta por nombre, teléfono o correo.

### 3. Gestión de Vehículos
- Alta de vehículos vinculados a clientes por taller.
- Datos: marca, modelo, año, color, placas, número de serie.

### 4. Órdenes de Servicio
- Creación de orden con selección de vehículo y cliente.
- Agregado de servicios, repuestos, mano de obra.
- Asignación de mecánico.
- Estado de la orden: Abierta, En proceso, Finalizada.
- Impresión de orden.

### 5. Servicios
- Catálogo de servicios con descripción y precio por taller.
- Posibilidad de crear combos o paquetes.

### 6. Recordatorios de Mantenimiento
- Configuración de recordatorios por fecha o kilometraje.
- Notificaciones por correo o WhatsApp.

### 7. Facturación 4.0
- Generación de CFDI con datos requeridos por SAT:
  - RFC, uso CFDI, método de pago, forma de pago, régimen fiscal.
- Integración con PAC.
- Descarga de XML y PDF.

### 8. Reportes
- Reportes por cliente, vehículo, fecha, estatus de orden, por taller.
- Servicios más solicitados por taller.
- Ingresos por periodo por taller.

## Requisitos Técnicos
- **Frontend:** 
  - HTML5 + CSS3 (Bootstrap 5)
  - JavaScript vanilla + jQuery
  - AJAX para operaciones asíncronas
- **Backend:** 
  - PHP 8.0+
  - Programación orientada a objetos
  - Patrón MVC simplificado
- **Base de Datos:** 
  - MySQL 8.0+
  - Estructura multi-tenant por taller
  - Índices optimizados
- **Seguridad:**
  - Validación de datos en frontend y backend
  - Sanitización de inputs
  - Protección contra inyección SQL
  - Encriptación de contraseñas
- **Facturación:** 
  - Integración con PAC autorizado por el SAT
  - Generación de CFDI 4.0
- **Notificaciones:** 
  - Email (PHPMailer)
  - API de WhatsApp

### Integración de Pagos
- Sistema de pagos en línea (Stripe/PayPal)
- Generación automática de facturas
- Notificaciones automáticas de vencimiento
- Sistema de reintentos de pago
- Registro de transacciones
- Reportes de ingresos

## Estándares de Desarrollo
1. **Código**
   - PSR-4 para autoloading
   - PSR-12 para estilo de código
   - Documentación PHPDoc
   - Nombres descriptivos en inglés

2. **Base de Datos**
   - Nombres de tablas en plural
   - Claves foráneas con prefijo `id_`
   - Índices en campos de búsqueda frecuente
   - Triggers para auditoría

3. **Seguridad**
   - Validación en frontend y backend
   - Sanitización de datos
   - Protección contra XSS y CSRF
   - Logs de actividad

4. **Rendimiento**
   - Caché de consultas frecuentes
   - Optimización de imágenes
   - Minificación de CSS/JS
   - Lazy loading de recursos

## Plan de Implementación
1. **Fase 1: Estructura Base**
   - Configuración inicial
   - Sistema de autenticación
   - Plantillas maestras
   - Base de datos

2. **Fase 2: Módulos Core**
   - Gestión de talleres
   - Gestión de clientes
   - Gestión de vehículos
   - Órdenes de servicio

3. **Fase 3: Funcionalidades Avanzadas**
   - Facturación 4.0
   - Reportes
   - Notificaciones
   - Integraciones

## Futuras Extensiones
- Firma digital de ordenes de servicio.
- Portal de clientes para consultar historial.
- Integración con punto de venta (POS).
- App móvil para mecánicos y clientes.

---

**Fecha de inicio del proyecto:** [Por definir]

**Responsables:** [Por definir]

**Repositorio:** [URL del repositorio en GitHub o GitLab]
