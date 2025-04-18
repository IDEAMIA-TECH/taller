<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';

// Verificar permisos
if (!hasPermission('admin')) {
    header('Location: /dashboard.php');
    exit;
}

// Obtener datos fiscales actuales
$stmt = $db->prepare("
    SELECT w.*, f.* 
    FROM workshops w
    LEFT JOIN fiscal_data f ON w.id_workshop = f.id_workshop
    WHERE w.id_workshop = ?
");
$stmt->execute([$_SESSION['workshop_id']]);
$workshop = $stmt->fetch();

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos
        $required = [
            'rfc',
            'business_name',
            'fiscal_address',
            'regimen_fiscal',
            'certificate_number'
        ];
        
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("El campo $field es requerido");
            }
        }
        
        // Procesar certificado y llave
        $certificate_path = $workshop['certificate_path'] ?? null;
        $key_path = $workshop['key_path'] ?? null;
        
        if (isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
            $certificate_path = 'certificates/' . uniqid('cert_') . '.cer';
            move_uploaded_file($_FILES['certificate']['tmp_name'], '../../storage/' . $certificate_path);
        }
        
        if (isset($_FILES['key']) && $_FILES['key']['error'] === UPLOAD_ERR_OK) {
            $key_path = 'keys/' . uniqid('key_') . '.key';
            move_uploaded_file($_FILES['key']['tmp_name'], '../../storage/' . $key_path);
        }
        
        // Actualizar datos fiscales
        $stmt = $db->prepare("
            INSERT INTO fiscal_data (
                id_workshop,
                rfc,
                business_name,
                fiscal_address,
                regimen_fiscal,
                certificate_number,
                certificate_path,
                key_path,
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                rfc = VALUES(rfc),
                business_name = VALUES(business_name),
                fiscal_address = VALUES(fiscal_address),
                regimen_fiscal = VALUES(regimen_fiscal),
                certificate_number = VALUES(certificate_number),
                certificate_path = VALUES(certificate_path),
                key_path = VALUES(key_path),
                updated_at = NOW()
        ");
        
        $stmt->execute([
            $_SESSION['workshop_id'],
            $_POST['rfc'],
            $_POST['business_name'],
            $_POST['fiscal_address'],
            $_POST['regimen_fiscal'],
            $_POST['certificate_number'],
            $certificate_path,
            $key_path
        ]);
        
        $success = "Datos fiscales actualizados correctamente";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1 class="h3 mb-4">Configuración de Datos Fiscales</h1>
            
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="rfc" class="form-label">RFC</label>
                                    <input type="text" class="form-control" id="rfc" name="rfc" 
                                           value="<?php echo $workshop['rfc'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="business_name" class="form-label">Razón Social</label>
                                    <input type="text" class="form-control" id="business_name" name="business_name" 
                                           value="<?php echo $workshop['business_name'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fiscal_address" class="form-label">Domicilio Fiscal</label>
                                    <textarea class="form-control" id="fiscal_address" name="fiscal_address" 
                                              rows="3" required><?php echo $workshop['fiscal_address'] ?? ''; ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="regimen_fiscal" class="form-label">Régimen Fiscal</label>
                                    <select class="form-select" id="regimen_fiscal" name="regimen_fiscal" required>
                                        <option value="">Seleccionar...</option>
                                        <option value="601" <?php echo ($workshop['regimen_fiscal'] ?? '') == '601' ? 'selected' : ''; ?>>
                                            General de Ley Personas Morales
                                        </option>
                                        <option value="603" <?php echo ($workshop['regimen_fiscal'] ?? '') == '603' ? 'selected' : ''; ?>>
                                            Personas Morales con Fines no Lucrativos
                                        </option>
                                        <option value="605" <?php echo ($workshop['regimen_fiscal'] ?? '') == '605' ? 'selected' : ''; ?>>
                                            Sueldos y Salarios e Ingresos Asimilados a Salarios
                                        </option>
                                        <option value="606" <?php echo ($workshop['regimen_fiscal'] ?? '') == '606' ? 'selected' : ''; ?>>
                                            Arrendamiento
                                        </option>
                                        <option value="607" <?php echo ($workshop['regimen_fiscal'] ?? '') == '607' ? 'selected' : ''; ?>>
                                            Régimen de Enajenación o Adquisición de Bienes
                                        </option>
                                        <option value="608" <?php echo ($workshop['regimen_fiscal'] ?? '') == '608' ? 'selected' : ''; ?>>
                                            Demás ingresos
                                        </option>
                                        <option value="610" <?php echo ($workshop['regimen_fiscal'] ?? '') == '610' ? 'selected' : ''; ?>>
                                            Residentes en el Extranjero sin Establecimiento Permanente en México
                                        </option>
                                        <option value="611" <?php echo ($workshop['regimen_fiscal'] ?? '') == '611' ? 'selected' : ''; ?>>
                                            Ingresos por Dividendos (socios y accionistas)
                                        </option>
                                        <option value="612" <?php echo ($workshop['regimen_fiscal'] ?? '') == '612' ? 'selected' : ''; ?>>
                                            Personas Físicas con Actividades Empresariales y Profesionales
                                        </option>
                                        <option value="614" <?php echo ($workshop['regimen_fiscal'] ?? '') == '614' ? 'selected' : ''; ?>>
                                            Ingresos por intereses
                                        </option>
                                        <option value="615" <?php echo ($workshop['regimen_fiscal'] ?? '') == '615' ? 'selected' : ''; ?>>
                                            Régimen de los ingresos por obtención de premios
                                        </option>
                                        <option value="616" <?php echo ($workshop['regimen_fiscal'] ?? '') == '616' ? 'selected' : ''; ?>>
                                            Sin obligaciones fiscales
                                        </option>
                                        <option value="620" <?php echo ($workshop['regimen_fiscal'] ?? '') == '620' ? 'selected' : ''; ?>>
                                            Sociedades Cooperativas de Producción que optan por diferir sus ingresos
                                        </option>
                                        <option value="621" <?php echo ($workshop['regimen_fiscal'] ?? '') == '621' ? 'selected' : ''; ?>>
                                            Incorporación Fiscal
                                        </option>
                                        <option value="622" <?php echo ($workshop['regimen_fiscal'] ?? '') == '622' ? 'selected' : ''; ?>>
                                            Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras
                                        </option>
                                        <option value="623" <?php echo ($workshop['regimen_fiscal'] ?? '') == '623' ? 'selected' : ''; ?>>
                                            Opcional para Grupos de Sociedades
                                        </option>
                                        <option value="624" <?php echo ($workshop['regimen_fiscal'] ?? '') == '624' ? 'selected' : ''; ?>>
                                            Coordinados
                                        </option>
                                        <option value="628" <?php echo ($workshop['regimen_fiscal'] ?? '') == '628' ? 'selected' : ''; ?>>
                                            Hidrocarburos
                                        </option>
                                        <option value="629" <?php echo ($workshop['regimen_fiscal'] ?? '') == '629' ? 'selected' : ''; ?>>
                                            De los Regímenes Fiscales Preferentes y de las Empresas Multinacionales
                                        </option>
                                        <option value="630" <?php echo ($workshop['regimen_fiscal'] ?? '') == '630' ? 'selected' : ''; ?>>
                                            Enajenación de acciones en bolsa de valores
                                        </option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="certificate_number" class="form-label">Número de Certificado</label>
                                    <input type="text" class="form-control" id="certificate_number" name="certificate_number" 
                                           value="<?php echo $workshop['certificate_number'] ?? ''; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="certificate" class="form-label">Certificado (.cer)</label>
                                    <input type="file" class="form-control" id="certificate" name="certificate" 
                                           accept=".cer">
                                    <?php if (!empty($workshop['certificate_path'])): ?>
                                        <small class="text-muted">
                                            Certificado actual: <?php echo basename($workshop['certificate_path']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="key" class="form-label">Llave Privada (.key)</label>
                                    <input type="file" class="form-control" id="key" name="key" 
                                           accept=".key">
                                    <?php if (!empty($workshop['key_path'])): ?>
                                        <small class="text-muted">
                                            Llave actual: <?php echo basename($workshop['key_path']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Estado de la Configuración</label>
                                    <div class="alert <?php echo $this->isFiscalDataComplete() ? 'alert-success' : 'alert-warning'; ?>">
                                        <?php if ($this->isFiscalDataComplete()): ?>
                                            <i class="fas fa-check-circle"></i> Configuración fiscal completa
                                        <?php else: ?>
                                            <i class="fas fa-exclamation-triangle"></i> Configuración fiscal incompleta
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar Configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?> 