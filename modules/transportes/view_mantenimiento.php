<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$id_mantenimiento = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_mantenimiento <= 0) {
    redirect(SITE_URL . '/modules/transportes/list.php');
}

$page_title = 'Detalle de Mantenimiento';

$database = new Database();
$conn = $database->getConnection();

$mantenimiento = null;
$errors = [];

try {
    $stmt = $conn->prepare("
        SELECT tm.*,
               t.marca, t.modelo, t.matricula, t.id_transporte,
               u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
        FROM transportes_mantenimientos tm
        JOIN transportes t ON t.id_transporte = tm.id_transporte
        LEFT JOIN usuarios u ON u.id_usuario = tm.id_usuario_registro
        WHERE tm.id_mantenimiento = ?
    ");
    $stmt->execute([$id_mantenimiento]);
    $mantenimiento = $stmt->fetch();

    if (!$mantenimiento) {
        redirect(SITE_URL . '/modules/transportes/list.php');
    }
} catch (Exception $e) {
    error_log('Error al cargar detalle de mantenimiento: ' . $e->getMessage());
    $errors[] = 'No se pudo cargar el detalle del mantenimiento.';
}

$tipo_labels = [
    'service'    => 'Service',
    'preventivo' => 'Preventivo',
    'correctivo' => 'Correctivo',
    'inspeccion' => 'Inspección',
    'otro'       => 'Otro',
];

include '../../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12 d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0"><i class="bi bi-tools"></i> Detalle de Mantenimiento</h1>
        <div class="d-flex gap-2">
            <?php if ($mantenimiento): ?>
            <a href="print_mantenimiento.php?id=<?php echo (int)$mantenimiento['id_mantenimiento']; ?>" target="_blank" class="btn btn-outline-primary">
                <i class="bi bi-printer"></i> Imprimir
            </a>
            <a href="mantenimiento.php?id=<?php echo (int)$mantenimiento['id_transporte']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php else: ?>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle"></i>
    <?php echo htmlspecialchars(implode(' | ', $errors)); ?>
</div>
<?php endif; ?>

<?php if ($mantenimiento): ?>

<!-- Vehículo -->
<div class="card mb-3">
    <div class="card-body py-2">
        <div class="row align-items-center">
            <div class="col-auto">
                <i class="bi bi-truck-front fs-4 text-muted"></i>
            </div>
            <div class="col">
                <strong><?php echo htmlspecialchars($mantenimiento['marca'] . ' ' . $mantenimiento['modelo']); ?></strong>
                <span class="text-muted ms-2"><?php echo htmlspecialchars($mantenimiento['matricula']); ?></span>
            </div>
            <div class="col-auto">
                <a href="view.php?id=<?php echo (int)$mantenimiento['id_transporte']; ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-eye"></i> Ver transporte
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Detalle del mantenimiento -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-clipboard-check"></i>
        <?php echo $tipo_labels[$mantenimiento['tipo_evento']] ?? ucfirst($mantenimiento['tipo_evento']); ?>
        &mdash;
        <?php echo date('d/m/Y', strtotime($mantenimiento['fecha_evento'])); ?>
    </div>
    <div class="card-body">
        <div class="row g-3">

            <div class="col-md-3">
                <small class="text-muted d-block">Tipo de evento</small>
                <strong><?php echo $tipo_labels[$mantenimiento['tipo_evento']] ?? ucfirst($mantenimiento['tipo_evento']); ?></strong>
            </div>

            <div class="col-md-3">
                <small class="text-muted d-block">Fecha</small>
                <strong><?php echo date('d/m/Y', strtotime($mantenimiento['fecha_evento'])); ?></strong>
            </div>

            <div class="col-md-3">
                <small class="text-muted d-block">Kilometraje</small>
                <strong><?php echo $mantenimiento['kilometraje'] !== null ? number_format((int)$mantenimiento['kilometraje']) . ' km' : '-'; ?></strong>
            </div>

            <div class="col-md-3">
                <small class="text-muted d-block">Proveedor / Taller</small>
                <strong><?php echo !empty($mantenimiento['proveedor_taller']) ? htmlspecialchars($mantenimiento['proveedor_taller']) : '-'; ?></strong>
            </div>

            <div class="col-md-12">
                <small class="text-muted d-block">Descripción del problema</small>
                <div><?php echo !empty($mantenimiento['descripcion_problema']) ? nl2br(htmlspecialchars($mantenimiento['descripcion_problema'])) : '<span class="text-muted">-</span>'; ?></div>
            </div>

            <div class="col-md-12">
                <small class="text-muted d-block">Trabajo realizado</small>
                <div><?php echo !empty($mantenimiento['trabajo_realizado']) ? nl2br(htmlspecialchars($mantenimiento['trabajo_realizado'])) : '<span class="text-muted">-</span>'; ?></div>
            </div>

            <div class="col-md-12"><hr class="my-1"></div>

            <div class="col-md-4">
                <small class="text-muted d-block">Costo mano de obra</small>
                <strong>$<?php echo number_format((float)$mantenimiento['costo_mano_obra'], 2, ',', '.'); ?></strong>
            </div>

            <div class="col-md-4">
                <small class="text-muted d-block">Costo repuestos</small>
                <strong>$<?php echo number_format((float)$mantenimiento['costo_repuestos'], 2, ',', '.'); ?></strong>
            </div>

            <div class="col-md-4">
                <small class="text-muted d-block">Costo total</small>
                <strong class="fs-5">$<?php echo number_format((float)$mantenimiento['costo_total'], 2, ',', '.'); ?></strong>
            </div>

            <div class="col-md-12"><hr class="my-1"></div>
            <div class="col-md-12">
                <small class="text-muted d-block">Observaciones</small>
                <div><?php echo !empty($mantenimiento['observaciones']) ? nl2br(htmlspecialchars($mantenimiento['observaciones'])) : '<span class="text-muted">-</span>'; ?></div>
            </div>

            <div class="col-md-12"><hr class="my-1"></div>

            <div class="col-md-6">
                <small class="text-muted d-block">Registrado por</small>
                <strong>
                    <?php echo !empty($mantenimiento['usuario_nombre'])
                        ? htmlspecialchars($mantenimiento['usuario_nombre'] . ' ' . $mantenimiento['usuario_apellido'])
                        : '-'; ?>
                </strong>
                <div class="small text-muted">Usuario ID: <?php echo (int)$mantenimiento['id_usuario_registro']; ?></div>
            </div>

            <div class="col-md-6">
                <small class="text-muted d-block">Fecha de registro</small>
                <strong><?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_creacion'])); ?></strong>
            </div>

        </div>
    </div>
</div>

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
