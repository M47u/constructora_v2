<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos (solo administradores y responsables de obra)
if ($_SESSION['user_role'] !== 'administrador' && $_SESSION['user_role'] !== 'responsable_obra') {
    header('Location: ../../dashboard.php?error=sin_permisos');
    exit();
}

$page_title = "Reporte de Extensiones de Préstamos";

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Obtener filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes actual
$fecha_hasta = $_GET['fecha_hasta'] ?? get_current_date(); // Hoy
$id_obra = $_GET['id_obra'] ?? '';
$id_usuario = $_GET['id_usuario'] ?? '';

try {
    // Obtener obras para el filtro
    $stmt_obras = $pdo->query("SELECT id_obra, nombre_obra FROM obras ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();
    
    // Obtener usuarios para el filtro
    $stmt_usuarios = $pdo->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $usuarios = $stmt_usuarios->fetchAll();
    
    // Construir consulta con filtros
    $where_conditions = ["1=1"];
    $params = [];
    
    if ($fecha_desde) {
        $where_conditions[] = "h.fecha_modificacion >= ?";
        $params[] = $fecha_desde . ' 00:00:00';
    }
    
    if ($fecha_hasta) {
        $where_conditions[] = "h.fecha_modificacion <= ?";
        $params[] = $fecha_hasta . ' 23:59:59';
    }
    
    if ($id_obra) {
        $where_conditions[] = "p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    if ($id_usuario) {
        $where_conditions[] = "h.id_usuario_modifico = ?";
        $params[] = $id_usuario;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Consulta principal de extensiones
    $sql = "
        SELECT 
            h.id_extension,
            h.id_prestamo,
            h.fecha_anterior,
            h.fecha_nueva,
            h.motivo,
            h.fecha_modificacion,
            h.id_usuario_modifico,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            p.id_empleado,
            emp.nombre as empleado_nombre,
            emp.apellido as empleado_apellido,
            o.nombre_obra,
            o.localidad as obra_localidad,
            DATEDIFF(h.fecha_nueva, h.fecha_anterior) as dias_extendidos,
            (SELECT GROUP_CONCAT(CONCAT(her.marca, ' ', her.modelo) SEPARATOR ', ')
             FROM detalle_prestamo dp
             JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
             JOIN herramientas her ON hu.id_herramienta = her.id_herramienta
             WHERE dp.id_prestamo = p.id_prestamo
             LIMIT 3) as herramientas
        FROM historial_extensiones_prestamo h
        JOIN usuarios u ON h.id_usuario_modifico = u.id_usuario
        JOIN prestamos p ON h.id_prestamo = p.id_prestamo
        JOIN usuarios emp ON p.id_empleado = emp.id_usuario
        JOIN obras o ON p.id_obra = o.id_obra
        $where_clause
        ORDER BY h.fecha_modificacion DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $extensiones = $stmt->fetchAll();
    
    // Calcular estadísticas
    $total_extensiones = count($extensiones);
    $total_dias_extendidos = 0;
    $extensiones_por_obra = [];
    
    foreach ($extensiones as $ext) {
        if ($ext['dias_extendidos'] > 0) {
            $total_dias_extendidos += $ext['dias_extendidos'];
        }
        
        $obra_nombre = $ext['nombre_obra'];
        if (!isset($extensiones_por_obra[$obra_nombre])) {
            $extensiones_por_obra[$obra_nombre] = 0;
        }
        $extensiones_por_obra[$obra_nombre]++;
    }
    
    $promedio_dias = $total_extensiones > 0 ? round($total_dias_extendidos / $total_extensiones, 1) : 0;
    arsort($extensiones_por_obra);
    
} catch (Exception $e) {
    $error_message = "Error al obtener los datos: " . $e->getMessage();
}

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-clock-history"></i> Reporte de Extensiones de Préstamos
            </h1>
            <div>
                <a href="../../dashboard.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<?php if (!empty($extensiones)): ?>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-calendar-plus text-primary" style="font-size: 2rem;"></i>
                <h3 class="mt-2"><?php echo $total_extensiones; ?></h3>
                <p class="text-muted mb-0">Total Extensiones</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-graph-up text-success" style="font-size: 2rem;"></i>
                <h3 class="mt-2"><?php echo $total_dias_extendidos; ?></h3>
                <p class="text-muted mb-0">Días Extendidos (Total)</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-calculator text-info" style="font-size: 2rem;"></i>
                <h3 class="mt-2"><?php echo $promedio_dias; ?></h3>
                <p class="text-muted mb-0">Promedio Días/Extensión</p>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <i class="bi bi-building text-warning" style="font-size: 2rem;"></i>
                <h3 class="mt-2"><?php echo count($extensiones_por_obra); ?></h3>
                <p class="text-muted mb-0">Obras Involucradas</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-funnel"></i> Filtros de Búsqueda
    </div>
    <div class="card-body">
        <form method="GET" action="" id="form-filtros">
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label for="fecha_desde" class="form-label">Fecha Desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                           value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                           value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="id_obra" class="form-label">Obra</label>
                    <select class="form-select" id="id_obra" name="id_obra">
                        <option value="">Todas las obras</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?php echo $obra['id_obra']; ?>" 
                                    <?php echo ($id_obra == $obra['id_obra']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 mb-3">
                    <label for="id_usuario" class="form-label">Modificado Por</label>
                    <select class="form-select" id="id_usuario" name="id_usuario">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuarios as $usuario): ?>
                            <option value="<?php echo $usuario['id_usuario']; ?>" 
                                    <?php echo ($id_usuario == $usuario['id_usuario']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Filtrar
                </button>
                <a href="?" class="btn btn-outline-secondary">
                    <i class="bi bi-x-circle"></i> Limpiar Filtros
                </a>
                <div class="ms-auto">
                    <button type="button" class="btn btn-success" onclick="exportarExcel()">
                        <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
                    </button>
                    <button type="button" class="btn btn-danger" onclick="exportarPDF()">
                        <i class="bi bi-file-earmark-pdf"></i> Exportar a PDF
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de Resultados -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-table"></i> Extensiones Registradas
        <?php if (!empty($extensiones)): ?>
            <span class="badge bg-primary ms-2"><?php echo $total_extensiones; ?> registros</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i> <?php echo $error_message; ?>
            </div>
        <?php elseif (empty($extensiones)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                <p class="text-muted mt-3">No se encontraron extensiones con los filtros seleccionados.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="tabla-extensiones">
                    <thead class="table-light">
                        <tr>
                            <th>ID Ext.</th>
                            <th>ID Préstamo</th>
                            <th>Obra</th>
                            <th>Empleado</th>
                            <th>Herramientas</th>
                            <th>Fecha Anterior</th>
                            <th>Fecha Nueva</th>
                            <th>Días Ext.</th>
                            <th>Motivo</th>
                            <th>Modificado Por</th>
                            <th>Fecha/Hora Modificación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($extensiones as $ext): ?>
                        <tr>
                            <td><strong><?php echo $ext['id_extension']; ?></strong></td>
                            <td>
                                <a href="../herramientas/view_prestamo.php?id=<?php echo $ext['id_prestamo']; ?>" 
                                   class="text-decoration-none" target="_blank">
                                    #<?php echo $ext['id_prestamo']; ?> <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($ext['nombre_obra']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($ext['obra_localidad']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($ext['empleado_nombre'] . ' ' . $ext['empleado_apellido']); ?></td>
                            <td>
                                <small class="text-muted">
                                    <?php 
                                    $herramientas = $ext['herramientas'];
                                    echo htmlspecialchars(strlen($herramientas) > 50 ? substr($herramientas, 0, 50) . '...' : $herramientas); 
                                    ?>
                                </small>
                            </td>
                            <td>
                                <?php echo $ext['fecha_anterior'] ? date('d/m/Y', strtotime($ext['fecha_anterior'])) : '<em class="text-muted">No definida</em>'; ?>
                            </td>
                            <td>
                                <strong class="text-primary">
                                    <?php echo date('d/m/Y', strtotime($ext['fecha_nueva'])); ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($ext['dias_extendidos'] > 0): ?>
                                    <span class="badge bg-info">+<?php echo $ext['dias_extendidos']; ?> días</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small>
                                    <?php 
                                    $motivo = $ext['motivo'];
                                    if (!empty($motivo)) {
                                        echo htmlspecialchars(strlen($motivo) > 50 ? substr($motivo, 0, 50) . '...' : $motivo);
                                    } else {
                                        echo '<em class="text-muted">Sin motivo</em>';
                                    }
                                    ?>
                                </small>
                            </td>
                            <td><?php echo htmlspecialchars($ext['usuario_nombre'] . ' ' . $ext['usuario_apellido']); ?></td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('d/m/Y H:i', strtotime($ext['fecha_modificacion'])); ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function exportarExcel() {
    const params = new URLSearchParams(window.location.search);
    params.set('exportar', 'excel');
    window.location.href = 'exportar_extensiones_excel.php?' + params.toString();
}

function exportarPDF() {
    const params = new URLSearchParams(window.location.search);
    params.set('exportar', 'pdf');
    window.location.href = 'exportar_extensiones_pdf.php?' + params.toString();
}
</script>
