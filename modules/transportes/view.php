<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$id_transporte = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_transporte <= 0) {
    redirect(SITE_URL . '/modules/transportes/list.php');
}

$page_title = 'Detalle de Transporte';

$database = new Database();
$conn = $database->getConnection();

$transporte = null;
$mantenimientos = [];
$errors = [];

try {
    $query = "SELECT t.*, u.nombre, u.apellido
              FROM transportes t
              LEFT JOIN usuarios u ON t.id_encargado = u.id_usuario
              WHERE t.id_transporte = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id_transporte]);
    $transporte = $stmt->fetch();

    if (!$transporte) {
        redirect(SITE_URL . '/modules/transportes/list.php');
    }

    // Historial reciente de mantenimientos (si la tabla existe)
    try {
        $query_m = "SELECT tm.*, ur.nombre AS usuario_nombre, ur.apellido AS usuario_apellido
                    FROM transportes_mantenimientos tm
                    LEFT JOIN usuarios ur ON tm.id_usuario_registro = ur.id_usuario
                    WHERE tm.id_transporte = ?
                    ORDER BY tm.fecha_evento DESC, tm.fecha_creacion DESC
                    LIMIT 8";
        $stmt_m = $conn->prepare($query_m);
        $stmt_m->execute([$id_transporte]);
        $mantenimientos = $stmt_m->fetchAll();
    } catch (Exception $e) {
        $mantenimientos = [];
    }
} catch (Exception $e) {
    error_log('Error al cargar detalle de transporte: ' . $e->getMessage());
    $errors[] = 'No se pudo cargar la informacion del transporte';
}

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0"><i class="bi bi-truck"></i> Detalle de Transporte</h1>
        <div class="d-flex gap-2">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php if ($transporte): ?>
            <a href="edit.php?id=<?php echo (int)$transporte['id_transporte']; ?>" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Editar
            </a>
            <a href="delete.php?id=<?php echo (int)$transporte['id_transporte']; ?>" class="btn btn-outline-danger">
                <i class="bi bi-slash-circle"></i> Baja
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger" role="alert">
    <i class="bi bi-exclamation-triangle"></i>
    <?php echo htmlspecialchars(implode(' | ', $errors)); ?>
</div>
<?php endif; ?>

<?php if ($transporte): ?>
<?php
$estado_class = 'bg-secondary';
$estado_icon = 'bi-question-circle';
switch ($transporte['estado']) {
    case 'disponible':
        $estado_class = 'bg-success';
        $estado_icon = 'bi-check-circle';
        break;
    case 'en_uso':
        $estado_class = 'bg-warning text-dark';
        $estado_icon = 'bi-gear';
        break;
    case 'mantenimiento':
        $estado_class = 'bg-info';
        $estado_icon = 'bi-tools';
        break;
    case 'fuera_servicio':
        $estado_class = 'bg-danger';
        $estado_icon = 'bi-x-circle';
        break;
}
?>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-truck-front"></i>
            <strong><?php echo htmlspecialchars($transporte['marca'] . ' ' . $transporte['modelo']); ?></strong>
        </div>
        <span class="badge <?php echo $estado_class; ?>">
            <i class="bi <?php echo $estado_icon; ?>"></i>
            <?php echo ucfirst(str_replace('_', ' ', $transporte['estado'])); ?>
        </span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <small class="text-muted d-block">Matricula</small>
                <strong><?php echo htmlspecialchars($transporte['matricula']); ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Tipo</small>
                <strong><?php echo htmlspecialchars($transporte['tipo_vehiculo'] ?: '-'); ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Ano</small>
                <strong><?php echo !empty($transporte['año']) ? (int)$transporte['año'] : '-'; ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Color</small>
                <strong><?php echo htmlspecialchars($transporte['color'] ?: '-'); ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Kilometraje</small>
                <strong><?php echo (int)$transporte['kilometraje']; ?> km</strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Capacidad carga</small>
                <strong><?php echo $transporte['capacidad_carga'] !== null ? (float)$transporte['capacidad_carga'] . ' kg' : '-'; ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Vencimiento VTV</small>
                <strong><?php echo !empty($transporte['fecha_vencimiento_vtv']) ? date('d/m/Y', strtotime($transporte['fecha_vencimiento_vtv'])) : '-'; ?></strong>
            </div>
            <div class="col-md-3">
                <small class="text-muted d-block">Vencimiento seguro</small>
                <strong><?php echo !empty($transporte['fecha_vencimiento_seguro']) ? date('d/m/Y', strtotime($transporte['fecha_vencimiento_seguro'])) : '-'; ?></strong>
            </div>
            <div class="col-md-12">
                <small class="text-muted d-block">Encargado</small>
                <strong>
                    <?php echo !empty($transporte['nombre']) ? htmlspecialchars($transporte['nombre'] . ' ' . $transporte['apellido']) : 'Sin encargado'; ?>
                </strong>
            </div>
            <div class="col-md-12">
                <small class="text-muted d-block">Observaciones</small>
                <div><?php echo !empty($transporte['observaciones']) ? nl2br(htmlspecialchars($transporte['observaciones'])) : '<span class="text-muted">Sin observaciones</span>'; ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-tools"></i> Mantenimientos recientes</span>
        <a href="mantenimiento.php?id=<?php echo (int)$transporte['id_transporte']; ?>" class="btn btn-sm btn-outline-warning">
            <i class="bi bi-plus-circle"></i> Registrar
        </a>
    </div>
    <div class="card-body">
        <?php if (!empty($mantenimientos)): ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Km</th>
                        <th>Costo</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mantenimientos as $item): ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($item['fecha_evento'])); ?></td>
                        <td><?php echo ucfirst($item['tipo_evento']); ?></td>
                        <td><?php echo $item['kilometraje'] !== null ? (int)$item['kilometraje'] : '-'; ?></td>
                        <td>$<?php echo number_format((float)$item['costo_total'], 2, ',', '.'); ?></td>
                        <td><?php echo !empty($item['usuario_nombre']) ? htmlspecialchars($item['usuario_nombre'] . ' ' . $item['usuario_apellido']) : '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted mb-0">No hay mantenimientos registrados.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
