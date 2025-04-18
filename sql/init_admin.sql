-- Insertar taller por defecto
INSERT INTO workshops (name, address, phone, email, rfc, subscription_status, max_users_allowed, max_vehicles_allowed)
VALUES (
    'Taller Demo',
    'Dirección de ejemplo',
    '555-123-4567',
    'demo@taller.com',
    'XAXX010101000',
    'active',
    5,
    100
);

-- Insertar usuario administrador
INSERT INTO users (id_workshop, username, password, full_name, email, role, status)
VALUES (
    LAST_INSERT_ID(),
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: password
    'Administrador',
    'admin@taller.com',
    'admin',
    'active'
); 