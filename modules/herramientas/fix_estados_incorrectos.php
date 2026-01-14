<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden ejecutar este script
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Corregir Estados Incorrectos de Herramientas';

$database = new Database();
$conn = $database->getConnection();

$errores = [];
$corregidos = [];
$sin_cambios = 0;

// Estados válidos en herramientas_unidades
$estados_validos = ['disponible', 'prestada', 'mantenimiento', 'dañada', 'perdida'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ejecutar_correccion'])) {
    try {
        // Obtener todas las herramientas con estados inválidos
        $query = "SELECT id_unidad, qr_code, estado_actual 
                  FROM herramientas_unidades 
                  WHERE estado_actual NOT IN ('disponible', 'prestada', 'mantenimiento', 'dañada', 'perdida')";
        $stmt = $conn->query($query);
        $herramientas_invalidas = $stmt->fetchAll();
        
        if (empty($herramientas_invalidas)) {
            $sin_cambios = "No se encontraron herramientas con estados incorrectos.";
        } else {
            $conn->beginTransaction();
            
            foreach ($herramientas_invalidas as $herramienta) {
                $estado_actual = $herramienta['estado_actual'];
                $nuevo_estado = '';
                
                // Determinar el nuevo estado correcto
                switch ($estado_actual) {
                    case 'excelente':
                    case 'buena':
                    case 'regular':
                        $nuevo_estado = 'disponible';
                        break;
                    case 'mala':
                        $nuevo_estado = 'mantenimiento';
                        break;
                    default:
                        // Si es algo completamente inesperado, ponerlo en mantenimiento
                        $nuevo_estado = 'mantenimiento';
                        break;
                }
                
                // Actualizar el estado
                $query_update = "UPDATE herramientas_unidades 
                                 SET estado_actual = ? 
                                 WHERE id_unidad = ?";
                $stmt_update = $conn->prepare($query_update);
                $result = $stmt_update->execute([$nuevo_estado, $herramienta['id_unidad']]);
                
                if ($result) {
                    $corregidos[] = [
                        'qr_code' => $herramienta['qr_code'],
                        'estado_anterior' => $estado_actual,
                        'estado_nuevo' => $nuevo_estado
                    ];
                } else {
                    $errores[] = "Error al actualizar herramienta QR: " . $herramienta['qr_code'];
                }
            }
            
            $conn->commit();
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $errores[] = "Error general: " . $e->getMessage();
        error_log("Error en corrección de estados: " . $e->getMessage());
    }
}

// Obtener vista previa de herramientas con estados incorrectos
try {
    $query_preview = "SELECT hu.id_unidad, hu.qr_code, hu.estado_actual, h.marca, h.modelo
                      FROM herramientas_unidades hu
                      JOIN herramientas h ON hu.id_herramienta = h.id_herramienta
                      WHERE hu.estado_actual NOT IN ('disponible', 'prestada', 'mantenimiento', 'dañada', 'perdida')";
    $stmt_preview = $conn->query($query_preview);
    $preview = $stmt_preview->fetchAll();
} catch (Exception $e) {
    $preview = [];
    error_log("Error en preview: " . $e->getMessage());
}

include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3">
                    <i class="bi bi-tools"></i> Corregir Estados Incorrectos de Herramientas
                </h1>
                <a href="list.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Listado
                </a>
            </div>
        </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
        <h5><i class="bi bi-exclamation-triangle"></i> Errores:</h5>
        <ul class="mb-0">
            <?php foreach ($errores as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if (!empty($corregidos)): ?>
    <div class="alert alert-success">
        <h5><i class="bi bi-check-circle"></i> Se corrigieron <?php echo count($corregidos); ?> herramientas:</h5>
        <div class="table-responsive mt-3">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>QR Code</th>
                        <th>Estado Anterior</th>
                        <th>Estado Nuevo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($corregidos as $item): ?>
                    <tr>
                        <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['qr_code']); ?></span></td>
                        <td><span class="badge bg-danger"><?php echo htmlspecialchars($item['estado_anterior']); ?></span></td>
                        <td><span class="badge bg-success"><?php echo htmlspecialchars($item['estado_nuevo']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($sin_cambios): ?>
    <div class="alert alert-info">
        <i class="bi bi-info-circle"></i> <?php echo $sin_cambios; ?>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header bg-warning">
            <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Vista Previa</h5>
        </div>
        <div class="card-body">
            <p><strong>Estados válidos:</strong> disponible, prestada, mantenimiento, dañada, perdida</p>
            
            <?php if (!empty($preview)): ?>
            <div class="alert alert-warning">
                <p><strong>Se encontraron <?php echo count($preview); ?> herramientas con estados incorrectos:</strong></p>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>QR Code</th>
                            <th>Herramienta</th>
                            <th>Estado Actual (INCORRECTO)</th>
                            <th>Nuevo Estado (Después de corregir)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preview as $item): 
                            $estado_actual = $item['estado_actual'];
                            $nuevo_estado = '';
                            
                            switch ($estado_actual) {
                                case 'excelente':
                                case 'buena':
                                case 'regular':
                                    $nuevo_estado = 'disponible';
                                    break;
                                case 'mala':
                                    $nuevo_estado = 'mantenimiento';
                                    break;
                                default:
                                    $nuevo_estado = 'mantenimiento';
                                    break;
                            }
                        ?>
                        <tr>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($item['qr_code']); ?></span></td>
                            <td><?php echo htmlspecialchars($item['marca'] . ' ' . $item['modelo']); ?></td>
                            <td><span class="badge bg-danger"><?php echo htmlspecialchars($estado_actual); ?></span></td>
                            <td><span class="badge bg-success"><?php echo htmlspecialchars($nuevo_estado); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <form method="POST" class="mt-4" onsubmit="return confirm('¿Está seguro de que desea corregir los estados de estas herramientas?');">
                <button type="submit" name="ejecutar_correccion" class="btn btn-warning btn-lg">
                    <i class="bi bi-wrench"></i> Ejecutar Corrección
                </button>
            </form>
            
            <?php else: ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> ¡Excelente! No hay herramientas con estados incorrectos.
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
