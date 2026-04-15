<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission(ROLE_ADMIN)) {
    die('No autorizado.');
}

$id_mantenimiento = (int)($_GET['id'] ?? 0);
if ($id_mantenimiento <= 0) {
    die('ID de mantenimiento invalido.');
}

$database = new Database();
$conn = $database->getConnection();

$stmt = $conn->prepare("\n    SELECT tm.*,\n           t.marca, t.modelo, t.matricula, t.id_transporte,\n           u.nombre AS usuario_nombre, u.apellido AS usuario_apellido\n    FROM transportes_mantenimientos tm\n    JOIN transportes t ON t.id_transporte = tm.id_transporte\n    LEFT JOIN usuarios u ON u.id_usuario = tm.id_usuario_registro\n    WHERE tm.id_mantenimiento = ?\n");
$stmt->execute([$id_mantenimiento]);
$mantenimiento = $stmt->fetch();

if (!$mantenimiento) {
    die('Mantenimiento no encontrado.');
}

$tipo_labels = [
    'service'    => 'Service',
    'preventivo' => 'Preventivo',
    'correctivo' => 'Correctivo',
    'inspeccion' => 'Inspeccion',
    'otro'       => 'Otro',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Mantenimiento</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print { display: none; }
        }
        body { background: #fff; }
        .logo-print { height: 60px; margin-right: 20px; }
        .bordered { border: 1px solid #dee2e6; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
        .label { color: #6c757d; display: block; font-size: 0.875rem; }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="d-flex align-items-center mb-4">
            <img src="../../assets/img/logo_san_simon.png" alt="Logo" class="logo-print">
            <div>
                <h2 class="mb-0">Detalle de Mantenimiento</h2>
                <small class="text-muted"><?php echo htmlspecialchars($mantenimiento['marca'] . ' ' . $mantenimiento['modelo']); ?> (<?php echo htmlspecialchars($mantenimiento['matricula']); ?>)</small>
            </div>
        </div>
        <hr>

        <div class="bordered">
            <div class="row g-3">
                <div class="col-md-3">
                    <span class="label">Tipo de evento</span>
                    <strong><?php echo $tipo_labels[$mantenimiento['tipo_evento']] ?? ucfirst($mantenimiento['tipo_evento']); ?></strong>
                </div>
                <div class="col-md-3">
                    <span class="label">Fecha del evento</span>
                    <strong><?php echo date('d/m/Y', strtotime($mantenimiento['fecha_evento'])); ?></strong>
                </div>
                <div class="col-md-3">
                    <span class="label">Kilometraje</span>
                    <strong><?php echo $mantenimiento['kilometraje'] !== null ? number_format((int)$mantenimiento['kilometraje']) . ' km' : '-'; ?></strong>
                </div>
                <div class="col-md-3">
                    <span class="label">Proveedor / Taller</span>
                    <strong><?php echo !empty($mantenimiento['proveedor_taller']) ? htmlspecialchars($mantenimiento['proveedor_taller']) : '-'; ?></strong>
                </div>
            </div>
        </div>

        <div class="bordered">
            <h5>Descripcion del problema</h5>
            <div><?php echo !empty($mantenimiento['descripcion_problema']) ? nl2br(htmlspecialchars($mantenimiento['descripcion_problema'])) : '<span class="text-muted">-</span>'; ?></div>
        </div>

        <div class="bordered">
            <h5>Trabajo realizado</h5>
            <div><?php echo !empty($mantenimiento['trabajo_realizado']) ? nl2br(htmlspecialchars($mantenimiento['trabajo_realizado'])) : '<span class="text-muted">-</span>'; ?></div>
        </div>

        <div class="bordered">
            <h5>Costos</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <span class="label">Mano de obra</span>
                    <strong>$<?php echo number_format((float)$mantenimiento['costo_mano_obra'], 2, ',', '.'); ?></strong>
                </div>
                <div class="col-md-4">
                    <span class="label">Repuestos</span>
                    <strong>$<?php echo number_format((float)$mantenimiento['costo_repuestos'], 2, ',', '.'); ?></strong>
                </div>
                <div class="col-md-4">
                    <span class="label">Total</span>
                    <strong>$<?php echo number_format((float)$mantenimiento['costo_total'], 2, ',', '.'); ?></strong>
                </div>
            </div>
        </div>

        <div class="bordered">
            <h5>Observaciones</h5>
            <div><?php echo !empty($mantenimiento['observaciones']) ? nl2br(htmlspecialchars($mantenimiento['observaciones'])) : '<span class="text-muted">-</span>'; ?></div>
        </div>

        <div class="bordered">
            <div class="row g-3">
                <div class="col-md-6">
                    <span class="label">Registrado por</span>
                    <strong>
                        <?php echo !empty($mantenimiento['usuario_nombre'])
                            ? htmlspecialchars($mantenimiento['usuario_nombre'] . ' ' . $mantenimiento['usuario_apellido'])
                            : 'Usuario ID ' . (int)$mantenimiento['id_usuario_registro']; ?>
                    </strong>
                </div>
                <div class="col-md-6">
                    <span class="label">Fecha de registro</span>
                    <strong><?php echo date('d/m/Y H:i', strtotime($mantenimiento['fecha_creacion'])); ?></strong>
                </div>
            </div>
        </div>

        <button class="btn btn-secondary no-print mt-3" onclick="window.close()">Cerrar</button>
    </div>
    <script>window.print();</script>
</body>
</html>
