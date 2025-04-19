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
    $zip_sql = "SELECT DISTINCT zip_code, estado as state, municipio as city FROM zip_codes ORDER BY zip_code";
    error_log("SQL códigos postales: " . $zip_sql);
    $zip_stmt = $db->query($zip_sql);
    $zip_codes = $zip_stmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("Códigos postales encontrados: " . print_r($zip_codes, true));

    // Obtener colonias para el código postal del cliente
    error_log("Obteniendo colonias para el código postal del cliente");
    $neighborhood_sql = "SELECT n.* FROM neighborhoods n 
                        JOIN zip_codes z ON n.id_zip_code = z.id_zip_code 
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

<div class="container-fluid">
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

                    <div class="col-12">
                        <label for="address" class="form-label">Dirección</label>
                        <textarea class="form-control" id="address" name="address" rows="3"><?php 
                            echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : htmlspecialchars($client['address']); 
                        ?></textarea>
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