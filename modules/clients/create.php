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

$errors = [];
$success = false;

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener y validar datos
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $rfc = trim($_POST['rfc'] ?? '');

    // Campos de dirección
    $street = trim($_POST['street'] ?? '');
    $number = trim($_POST['number'] ?? '');
    $number_int = trim($_POST['number_int'] ?? '');
    $neighborhood = trim($_POST['neighborhood'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $zip_code = trim($_POST['zip_code'] ?? '');
    $reference = trim($_POST['reference'] ?? '');

    // Validaciones
    if (empty($name)) {
        $errors[] = 'El nombre es requerido';
    }

    if (empty($street)) {
        $errors[] = 'La calle es requerida';
    }

    if (empty($neighborhood)) {
        $errors[] = 'La colonia es requerida';
    }

    if (empty($city)) {
        $errors[] = 'La ciudad es requerida';
    }

    if (empty($state)) {
        $errors[] = 'El estado es requerido';
    }

    if (empty($zip_code)) {
        $errors[] = 'El código postal es requerido';
    } elseif (!preg_match('/^[0-9]{5}$/', $zip_code)) {
        $errors[] = 'El código postal debe tener 5 dígitos';
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no es válido';
    }

    if (!empty($rfc) && !preg_match('/^[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|1[0-9]|2[0-9]|3[0-1])[A-Z0-9]{2}[0-9A]$/', $rfc)) {
        $errors[] = 'El RFC no es válido';
    }

    // Si no hay errores, guardar el cliente
    if (empty($errors)) {
        try {
            // Verificar si la tabla necesita ser actualizada
            $checkTable = $db->query("SHOW COLUMNS FROM clients LIKE 'street'");
            if ($checkTable->rowCount() == 0) {
                // Actualizar la tabla para incluir los nuevos campos
                $db->query("ALTER TABLE clients 
                    ADD COLUMN street VARCHAR(255) NOT NULL AFTER rfc,
                    ADD COLUMN number VARCHAR(20) AFTER street,
                    ADD COLUMN number_int VARCHAR(20) AFTER number,
                    ADD COLUMN neighborhood VARCHAR(255) NOT NULL AFTER number_int,
                    ADD COLUMN city VARCHAR(255) NOT NULL AFTER neighborhood,
                    ADD COLUMN state VARCHAR(255) NOT NULL AFTER city,
                    ADD COLUMN zip_code VARCHAR(5) NOT NULL AFTER state,
                    ADD COLUMN reference TEXT AFTER zip_code,
                    DROP COLUMN address");
            }

            // Usar query directamente con valores escapados usando PDO::quote()
            $query = "INSERT INTO clients (id_workshop, name, phone, email, rfc, 
                    street, number, number_int, neighborhood, city, state, zip_code, reference) 
                    VALUES (
                        " . getCurrentWorkshop() . ",
                        " . $db->quote($name) . ",
                        " . $db->quote($phone) . ",
                        " . $db->quote($email) . ",
                        " . $db->quote($rfc) . ",
                        " . $db->quote($street) . ",
                        " . $db->quote($number) . ",
                        " . $db->quote($number_int) . ",
                        " . $db->quote($neighborhood) . ",
                        " . $db->quote($city) . ",
                        " . $db->quote($state) . ",
                        " . $db->quote($zip_code) . ",
                        " . $db->quote($reference) . "
                    )";
            
            $db->query($query);

            $success = true;
            showSuccess('Cliente agregado correctamente');
            redirect('index.php');

        } catch (PDOException $e) {
            $errors[] = 'Error al guardar el cliente. Por favor, intente más tarde.';
        }
    }
}

// Incluir el encabezado
include '../../includes/header.php';
?>

<style>
/* Estilos para el sidebar */
.sidebar {
    position: fixed;
    top: 56px; /* Altura del navbar */
    bottom: 0;
    left: 0;
    z-index: 100;
    padding: 20px 0;
    box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
    background-color: #343a40;
    width: 250px;
}

.sidebar-sticky {
    position: relative;
    top: 0;
    height: calc(100vh - 56px);
    padding-top: .5rem;
    overflow-x: hidden;
    overflow-y: auto;
}

.sidebar .nav-link {
    font-weight: 500;
    color: #adb5bd;
    padding: .75rem 1rem;
    display: flex;
    align-items: center;
}

.sidebar .nav-link:hover {
    color: #fff;
    background-color: rgba(255, 255, 255, .1);
}

.sidebar .nav-link.active {
    color: #fff;
    background-color: rgba(255, 255, 255, .1);
}

.sidebar .nav-link i {
    margin-right: .5rem;
    width: 20px;
    text-align: center;
}

.main-content {
    margin-left: 250px;
    padding: 20px;
    min-height: calc(100vh - 56px);
}

@media (max-width: 767.98px) {
    .sidebar {
        position: static;
        height: auto;
        padding-top: 0;
        width: 100%;
    }
    .main-content {
        margin-left: 0;
    }
}
</style>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar">
            <div class="sidebar-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/templates/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <?php if (hasRole('admin') || hasRole('receptionist') || hasRole('super_admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?php echo APP_URL; ?>/modules/clients/">
                            <i class="fas fa-users"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/vehicles/">
                            <i class="fas fa-car"></i> Vehículos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/services/">
                            <i class="fas fa-tools"></i> Servicios
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/orders/">
                            <i class="fas fa-clipboard-list"></i> Órdenes
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (hasRole('admin') || hasRole('super_admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/reports/">
                            <i class="fas fa-chart-bar"></i> Reportes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/modules/settings/">
                            <i class="fas fa-cog"></i> Configuración
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </nav>

        <!-- Contenido principal -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Nuevo Cliente</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="index.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" id="clientForm">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="rfc" class="form-label">RFC</label>
                                <input type="text" class="form-control" id="rfc" name="rfc" 
                                       value="<?php echo isset($_POST['rfc']) ? htmlspecialchars($_POST['rfc']) : ''; ?>"
                                       placeholder="XAXX010101000">
                            </div>

                            <div class="col-12">
                                <h5 class="mb-3">Dirección</h5>
                            </div>

                            <div class="col-md-6">
                                <label for="street" class="form-label">Calle *</label>
                                <input type="text" class="form-control" id="street" name="street" 
                                       value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="col-md-3">
                                <label for="number" class="form-label">Número Exterior</label>
                                <input type="text" class="form-control" id="number" name="number" 
                                       value="<?php echo isset($_POST['number']) ? htmlspecialchars($_POST['number']) : ''; ?>">
                            </div>

                            <div class="col-md-3">
                                <label for="number_int" class="form-label">Número Interior</label>
                                <input type="text" class="form-control" id="number_int" name="number_int" 
                                       value="<?php echo isset($_POST['number_int']) ? htmlspecialchars($_POST['number_int']) : ''; ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="neighborhood" class="form-label">Colonia *</label>
                                <div class="input-group">
                                    <select class="form-select" id="neighborhood" name="neighborhood" required>
                                        <option value="">Seleccione una colonia</option>
                                    </select>
                                    <button type="button" class="btn btn-outline-secondary" id="addNeighborhoodBtn">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <label for="city" class="form-label">Ciudad *</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="col-md-4">
                                <label for="state" class="form-label">Estado *</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : ''; ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="zip_code" class="form-label">Código Postal *</label>
                                <input type="text" class="form-control" id="zip_code" name="zip_code" 
                                       value="<?php echo isset($_POST['zip_code']) ? htmlspecialchars($_POST['zip_code']) : ''; ?>" 
                                       pattern="[0-9]{5}" 
                                       title="El código postal debe tener 5 dígitos"
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="reference" class="form-label">Referencias</label>
                                <input type="text" class="form-control" id="reference" name="reference" 
                                       value="<?php echo isset($_POST['reference']) ? htmlspecialchars($_POST['reference']) : ''; ?>"
                                       placeholder="Entre calles, puntos de referencia, etc.">
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
document.getElementById('clientForm').addEventListener('submit', function(e) {
    const name = document.getElementById('name').value.trim();
    const email = document.getElementById('email').value.trim();
    const rfc = document.getElementById('rfc').value.trim();

    if (!name) {
        e.preventDefault();
        alert('El nombre es requerido');
        return;
    }

    if (email && !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        e.preventDefault();
        alert('El correo electrónico no es válido');
        return;
    }

    if (rfc && !rfc.match(/^[A-Z&Ñ]{3,4}[0-9]{2}(0[1-9]|1[0-2])(0[1-9]|1[0-9]|2[0-9]|3[0-1])[A-Z0-9]{2}[0-9A]$/)) {
        e.preventDefault();
        alert('El RFC no es válido');
        return;
    }
});

// Función para autocompletar dirección basada en código postal
document.getElementById('zip_code').addEventListener('change', function() {
    const zipCode = this.value.trim();
    
    if (zipCode.length === 5) {
        // Mostrar indicador de carga
        const loadingIndicator = document.createElement('div');
        loadingIndicator.className = 'spinner-border spinner-border-sm text-primary';
        loadingIndicator.setAttribute('role', 'status');
        this.parentNode.appendChild(loadingIndicator);
        
        // Hacer la llamada AJAX
        console.log('Iniciando petición AJAX para código postal:', zipCode);
        fetch(`../../modules/clients/get_address.php?zip_code=${zipCode}`)
            .then(response => {
                console.log('Respuesta recibida:', response);
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                console.log('Datos recibidos:', data);
                // Remover indicador de carga
                this.parentNode.removeChild(loadingIndicator);
                
                if (data.success) {
                    console.log('Actualizando campos con:', {
                        state: data.state,
                        city: data.city,
                        neighborhoods: data.neighborhoods
                    });
                    // Actualizar campos
                    document.getElementById('state').value = data.state;
                    document.getElementById('city').value = data.city;
                    
                    // Actualizar select de colonias
                    const neighborhoodSelect = document.getElementById('neighborhood');
                    neighborhoodSelect.innerHTML = '<option value="">Seleccione una colonia</option>';
                    
                    data.neighborhoods.forEach(neighborhood => {
                        const option = document.createElement('option');
                        option.value = neighborhood;
                        option.textContent = neighborhood;
                        neighborhoodSelect.appendChild(option);
                    });
                    console.log('Campos actualizados exitosamente');
                } else {
                    console.error('Error en la respuesta:', data.message);
                    alert('No se encontró información para este código postal');
                }
            })
            .catch(error => {
                console.error('Error en la petición:', error);
                alert('Error al obtener la información del código postal');
            });
    }
});

// Función para agregar nueva colonia
document.getElementById('addNeighborhoodBtn').addEventListener('click', function() {
    const zipCode = document.getElementById('zip_code').value.trim();
    if (!zipCode) {
        alert('Por favor, ingrese primero el código postal');
        return;
    }

    const newNeighborhood = prompt('Ingrese el nombre de la nueva colonia:');
    if (newNeighborhood) {
        // Hacer la llamada AJAX para guardar la nueva colonia
        fetch('../../modules/clients/save_neighborhood.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `zip_code=${zipCode}&neighborhood=${encodeURIComponent(newNeighborhood)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Agregar la nueva colonia al select
                const neighborhoodSelect = document.getElementById('neighborhood');
                const option = document.createElement('option');
                option.value = newNeighborhood;
                option.textContent = newNeighborhood;
                option.selected = true;
                neighborhoodSelect.appendChild(option);
                
                // Actualizar el campo de ciudad y estado si no están llenos
                if (!document.getElementById('city').value) {
                    document.getElementById('city').value = data.city;
                }
                if (!document.getElementById('state').value) {
                    document.getElementById('state').value = data.state;
                }
                
                alert('Colonia agregada exitosamente');
            } else {
                alert('Error al agregar la colonia: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al agregar la colonia');
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?> 