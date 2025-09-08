<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$database = new Database();
$conn = $database->getConnection();

$tarea_id = (int)($_GET['id'] ?? 0);
if ($tarea_id <= 0) {
    die('ID de tarea inválido.');
}

// Obtener datos de la tarea
$query = "SELECT t.*, 
              emp.nombre as empleado_nombre, emp.apellido as empleado_apellido, emp.email as empleado_email,
              asig.nombre as asignador_nombre, asig.apellido as asignador_apellido
          FROM tareas t 
          JOIN usuarios emp ON t.id_empleado = emp.id_usuario
          JOIN usuarios asig ON t.id_asignador = asig.id_usuario
          WHERE t.id_tarea = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$tarea_id]);
$tarea = $stmt->fetch();
if (!$tarea) {
    die('Tarea no encontrada.');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Imprimir Tarea #<?php echo $tarea['id_tarea']; ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print { display: none; }
        }
        body { background: #fff; }
        .logo-print { height: 60px; margin-right: 20px; }
        .bordered { border: 1px solid #dee2e6; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="d-flex align-items-center mb-4">
            <img src="../../assets/img/logo_san_simon.png" alt="Logo" class="logo-print">
            <h2 class="mb-0">Detalle de Tarea #<?php echo $tarea['id_tarea']; ?></h2>
        </div>
        <hr>
        <h4><?php echo htmlspecialchars($tarea['titulo']); ?></h4>
        <div class="row mb-3">
            <div class="col-md-6">
                <p><strong>Empleado:</strong> <?php echo htmlspecialchars($tarea['empleado_nombre'] . ' ' . $tarea['empleado_apellido']); ?><br>
                <small class="text-muted"><?php echo htmlspecialchars($tarea['empleado_email']); ?></small></p>
                <p><strong>Asignado por:</strong> <?php echo htmlspecialchars($tarea['asignador_nombre'] . ' ' . $tarea['asignador_apellido']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Fecha de Asignación:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])); ?></p>
                <p><strong>Fecha de Vencimiento:</strong> <?php echo $tarea['fecha_vencimiento'] ? date('d/m/Y', strtotime($tarea['fecha_vencimiento'])) : 'Sin fecha'; ?></p>
                <?php if ($tarea['fecha_finalizacion']): ?>
                <p><strong>Fecha de Finalización:</strong> <?php echo date('d/m/Y H:i', strtotime($tarea['fecha_finalizacion'])); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="bordered">
            <h5>Descripción</h5>
            <div><?php echo nl2br(htmlspecialchars($tarea['descripcion'])); ?></div>
        </div>
        <?php if (!empty($tarea['observaciones'])): ?>
        <div class="bordered">
            <h5>Observaciones</h5>
            <div><?php echo nl2br(htmlspecialchars($tarea['observaciones'])); ?></div>
        </div>
        <?php endif; ?>
        <button class="btn btn-secondary no-print mt-4" onclick="window.close()">Cerrar</button>
    </div>
    <script>window.print();</script>
</body>
</html>
