<?php
require_once 'includes/config.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Sistema de Gestión para Talleres</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('assets/img/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
        }
        .feature-card {
            transition: transform 0.3s;
            margin-bottom: 20px;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: #0d6efd;
        }
        .pricing-card {
            border: none;
            transition: all 0.3s;
        }
        .pricing-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .pricing-header {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .pricing-price {
            font-size: 2.5rem;
            font-weight: bold;
            color: #0d6efd;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="<?php echo APP_URL; ?>">
                <i class="fas fa-wrench"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="#caracteristicas">Características</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#planes">Planes</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#contacto">Contacto</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link btn btn-primary text-white ms-2" href="<?php echo APP_URL; ?>/login">
                            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section text-center">
        <div class="container">
            <h1 class="display-4 mb-4">Sistema de Gestión para Talleres Mecánicos</h1>
            <p class="lead mb-4">Optimiza la administración de tu taller con nuestra plataforma integral</p>
            <a href="#planes" class="btn btn-primary btn-lg">Ver Planes</a>
        </div>
    </section>

    <!-- Características -->
    <section id="caracteristicas" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Características Principales</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-users feature-icon"></i>
                            <h3>Gestión de Clientes</h3>
                            <p>Administra clientes, vehículos y su historial de servicios de manera eficiente</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-clipboard-list feature-icon"></i>
                            <h3>Órdenes de Servicio</h3>
                            <p>Crea y gestiona órdenes de servicio con seguimiento en tiempo real</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-file-invoice-dollar feature-icon"></i>
                            <h3>Facturación 4.0</h3>
                            <p>Genera facturas electrónicas CFDI 4.0 integradas con PAC</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-chart-bar feature-icon"></i>
                            <h3>Reportes</h3>
                            <p>Genera reportes detallados de servicios, ingresos y clientes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-bell feature-icon"></i>
                            <h3>Recordatorios</h3>
                            <p>Programa recordatorios de mantenimiento para tus clientes</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card feature-card">
                        <div class="card-body text-center">
                            <i class="fas fa-mobile-alt feature-icon"></i>
                            <h3>Acceso Móvil</h3>
                            <p>Accede a tu taller desde cualquier dispositivo con internet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Planes -->
    <section id="planes" class="bg-light py-5">
        <div class="container">
            <h2 class="text-center mb-5">Planes Disponibles</h2>
            <div class="row">
                <?php
                $db = Database::getInstance();
                $plans = $db->fetchAll(
                    "SELECT * FROM subscription_plans WHERE status = 'active' ORDER BY price"
                );

                foreach ($plans as $plan):
                ?>
                <div class="col-md-4">
                    <div class="card pricing-card">
                        <div class="pricing-header text-center">
                            <h3><?php echo sanitize($plan['name']); ?></h3>
                            <div class="pricing-price">
                                $<?php echo number_format($plan['price'], 2); ?>
                                <small class="text-muted">/mes</small>
                            </div>
                        </div>
                        <div class="card-body">
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success"></i> <?php echo $plan['max_users']; ?> usuarios</li>
                                <li><i class="fas fa-check text-success"></i> <?php echo $plan['max_vehicles']; ?> vehículos</li>
                                <li><i class="fas fa-check text-success"></i> Facturación electrónica</li>
                                <li><i class="fas fa-check text-success"></i> Soporte técnico</li>
                                <li><i class="fas fa-check text-success"></i> Actualizaciones incluidas</li>
                            </ul>
                            <a href="<?php echo APP_URL; ?>/register" class="btn btn-primary w-100">Comenzar Prueba</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contacto -->
    <section id="contacto" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">Contáctanos</h2>
            <div class="row">
                <div class="col-md-6">
                    <form action="<?php echo APP_URL; ?>/contact.php" method="POST">
                        <div class="mb-3">
                            <input type="text" class="form-control" name="name" placeholder="Nombre del Taller" required>
                        </div>
                        <div class="mb-3">
                            <input type="email" class="form-control" name="email" placeholder="Correo Electrónico" required>
                        </div>
                        <div class="mb-3">
                            <input type="tel" class="form-control" name="phone" placeholder="Teléfono" required>
                        </div>
                        <div class="mb-3">
                            <textarea class="form-control" name="message" rows="4" placeholder="Mensaje" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Enviar Mensaje</button>
                    </form>
                </div>
                <div class="col-md-6">
                    <h4>Información de Contacto</h4>
                    <p><i class="fas fa-envelope"></i> Email: soporte@<?php echo strtolower(APP_NAME); ?>.com</p>
                    <p><i class="fas fa-phone"></i> Teléfono: [Teléfono de Soporte]</p>
                    <p><i class="fas fa-clock"></i> Horario de Soporte: Lunes a Viernes 9:00 - 18:00</p>
                    <div class="mt-4">
                        <h5>Síguenos en Redes Sociales</h5>
                        <a href="#" class="btn btn-outline-primary me-2"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="btn btn-outline-primary me-2"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="btn btn-outline-primary"><i class="fab fa-linkedin"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo APP_NAME; ?></h5>
                    <p>Sistema de gestión integral para talleres mecánicos</p>
                </div>
                <div class="col-md-6 text-end">
                    <a href="#" class="text-white me-3">Términos y Condiciones</a>
                    <a href="#" class="text-white">Política de Privacidad</a>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <small>&copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Todos los derechos reservados.</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 