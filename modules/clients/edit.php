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
$client = null;

// Obtener el ID del cliente
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    showError('Cliente no válido');
    redirect('index.php');
}

// Función para mostrar errores
function showError($message) {
    $_SESSION['error_message'] = $message;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

try {
    // Obtener información del cliente
    error_log("Preparando consulta para obtener datos del cliente");
    $sql = "SELECT * FROM clients WHERE id_client = ? AND id_workshop = ?";
    error_log("SQL: " . $sql);
    error_log("Parámetros: " . print_r([$id, getCurrentWorkshop()], true));
    
    $stmt = $db->query($sql, [$id, getCurrentWorkshop()]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        error_log("Cliente no encontrado");
        showError('Cliente no encontrado');
    }

    error_log("Cliente encontrado: " . print_r($client, true));

    // Obtener códigos postales
    error_log("Obteniendo códigos postales");
    $zip_sql = "SELECT DISTINCT z.zip_code, s.name as state, c.name as city 
                FROM zip_codes z
                JOIN states s ON z.id_state = s.id_state
                JOIN cities c ON z.id_city = c.id_city
                ORDER BY z.zip_code";
    error_log("SQL códigos postales: " . $zip_sql);
    $zip_stmt = $db->query($zip_sql);
    $zip_codes = $zip_stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Códigos postales encontrados: " . print_r($zip_codes, true));

    // Obtener colonias para el código postal del cliente
    error_log("Obteniendo colonias para el código postal del cliente");
    $neighborhood_sql = "SELECT n.* FROM neighborhoods n 
                        JOIN zip_codes z ON n.id_neighborhood = z.id_neighborhood 
                        WHERE z.zip_code = ?";
    error_log("SQL colonias: " . $neighborhood_sql);
    error_log("Parámetros: " . print_r([$client['zip_code']], true));
    
    $neighborhood_stmt = $db->query($neighborhood_sql, [$client['zip_code']]);
    $neighborhoods = $neighborhood_stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Colonias encontradas: " . print_r($neighborhoods, true));

} catch (PDOException $e) {
    error_log("Error PDO: " . $e->getMessage());
    showError('Error al obtener la información del cliente');
} catch (Exception $e) {
    error_log("Error general: " . $e->getMessage());
    showError('Error al procesar la solicitud');
}

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Procesando formulario de edición de cliente");
    
    // Obtener datos del formulario
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $rfc = trim($_POST['rfc']);
    $street = trim($_POST['street']);
    $number = trim($_POST['number']);
    $number_int = trim($_POST['number_int']);
    $neighborhood = trim($_POST['neighborhood']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $zip_code = trim($_POST['zip_code']);
    $reference = trim($_POST['reference']);

    // Validar datos
    if (empty($name) || empty($street) || empty($number) || empty($neighborhood) || 
        empty($city) || empty($state) || empty($zip_code)) {
        error_log("Datos incompletos en el formulario");
        showError('Por favor complete todos los campos requeridos');
    } else {
        try {
            error_log("Preparando actualización de cliente");
            $sql = "UPDATE clients SET 
                    name = ?, 
                    phone = ?, 
                    email = ?, 
                    rfc = ?, 
                    street = ?, 
                    number = ?, 
                    number_int = ?, 
                    neighborhood = ?, 
                    city = ?, 
                    state = ?, 
                    zip_code = ?, 
                    reference = ? 
                    WHERE id_client = ? AND id_workshop = ?";
            
            error_log("SQL: " . $sql);
            error_log("Parámetros: " . print_r([$name, $phone, $email, $rfc, $street, $number, $number_int, 
                $neighborhood, $city, $state, $zip_code, $reference, $id, getCurrentWorkshop()], true));
            
            $stmt = $db->query($sql, [$name, $phone, $email, $rfc, $street, $number, $number_int, 
                $neighborhood, $city, $state, $zip_code, $reference, $id, getCurrentWorkshop()]);
            
            error_log("Cliente actualizado correctamente");
            $_SESSION['success_message'] = 'Cliente actualizado correctamente';
            header('Location: view.php?id=' . $id);
            exit;
        } catch (PDOException $e) {
            error_log("Error PDO al actualizar cliente: " . $e->getMessage());
            showError('Error al actualizar el cliente: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log("Error general al actualizar cliente: " . $e->getMessage());
            showError('Error al procesar la solicitud');
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
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Editar Cliente</h1>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
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
                                       value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($client['name']); ?>" 
                                       required>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : htmlspecialchars($client['phone']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label">Correo Electrónico</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : htmlspecialchars($client['email']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="rfc" class="form-label">RFC</label>
                                <input type="text" class="form-control" id="rfc" name="rfc" 
                                       value="<?php echo isset($_POST['rfc']) ? htmlspecialchars($_POST['rfc']) : htmlspecialchars($client['rfc']); ?>"
                                       placeholder="XAXX010101000">
                            </div>

                            <div class="col-md-6">
                                <label for="zip_code" class="form-label">Código Postal *</label>
                                <select class="form-select" id="zip_code" name="zip_code" required>
                                    <option value="">Seleccione un código postal</option>
                                    <?php foreach ($zip_codes as $zip): ?>
                                        <option value="<?php echo htmlspecialchars($zip['zip_code']); ?>"
                                            <?php echo (isset($_POST['zip_code']) && $_POST['zip_code'] == $zip['zip_code']) || 
                                                    (!isset($_POST['zip_code']) && $client['zip_code'] == $zip['zip_code']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($zip['zip_code'] . ' - ' . $zip['city'] . ', ' . $zip['state']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="neighborhood" class="form-label">Colonia *</label>
                                <select class="form-select" id="neighborhood" name="neighborhood" required>
                                    <option value="">Seleccione una colonia</option>
                                    <?php foreach ($neighborhoods as $neighborhood): ?>
                                        <option value="<?php echo htmlspecialchars($neighborhood['id_neighborhood']); ?>"
                                            <?php echo (isset($_POST['neighborhood']) && $_POST['neighborhood'] == $neighborhood['id_neighborhood']) || 
                                                    (!isset($_POST['neighborhood']) && $client['neighborhood'] == $neighborhood['id_neighborhood']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($neighborhood['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="street" class="form-label">Calle *</label>
                                <input type="text" class="form-control" id="street" name="street" 
                                       value="<?php echo isset($_POST['street']) ? htmlspecialchars($_POST['street']) : htmlspecialchars($client['street']); ?>" 
                                       required>
                            </div>

                            <div class="col-md-4">
                                <label for="number" class="form-label">Número Exterior *</label>
                                <input type="text" class="form-control" id="number" name="number" 
                                       value="<?php echo isset($_POST['number']) ? htmlspecialchars($_POST['number']) : htmlspecialchars($client['number']); ?>" 
                                       required>
                            </div>

                            <div class="col-md-4">
                                <label for="number_int" class="form-label">Número Interior</label>
                                <input type="text" class="form-control" id="number_int" name="number_int" 
                                       value="<?php echo isset($_POST['number_int']) ? htmlspecialchars($_POST['number_int']) : htmlspecialchars($client['number_int']); ?>">
                            </div>

                            <div class="col-md-4">
                                <label for="reference" class="form-label">Referencia</label>
                                <input type="text" class="form-control" id="reference" name="reference" 
                                       value="<?php echo isset($_POST['reference']) ? htmlspecialchars($_POST['reference']) : htmlspecialchars($client['reference']); ?>">
                            </div>

                            <div class="col-md-6">
                                <label for="city" class="form-label">Ciudad *</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : htmlspecialchars($client['city']); ?>" 
                                       required readonly>
                            </div>

                            <div class="col-md-6">
                                <label for="state" class="form-label">Estado *</label>
                                <input type="text" class="form-control" id="state" name="state" 
                                       value="<?php echo isset($_POST['state']) ? htmlspecialchars($_POST['state']) : htmlspecialchars($client['state']); ?>" 
                                       required readonly>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Guardar Cambios
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
</script>

<?php include '../../includes/footer.php'; ?> 