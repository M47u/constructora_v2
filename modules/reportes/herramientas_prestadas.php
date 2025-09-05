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

$page_title = "Reporte de Herramientas Prestadas";

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();



// Obtener filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01'); // Primer día del mes actual
$fecha_hasta = $_GET['fecha_hasta'] ?? date('Y-m-d'); // Hoy
$id_obra = $_GET['id_obra'] ?? '';
$id_empleado = $_GET['id_empleado'] ?? '';
$estado = $_GET['estado'] ?? '';
$exportar = $_GET['exportar'] ?? '';

try {
    // Obtener obras para el filtro
    $stmt_obras = $pdo->query("SELECT id_obra, nombre_obra FROM obras ORDER BY nombre_obra");
    $obras = $stmt_obras->fetchAll();
    
    // Obtener empleados para el filtro
    $stmt_empleados = $pdo->query("SELECT id_usuario, nombre, apellido FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $empleados = $stmt_empleados->fetchAll();
    
    // Construir consulta con filtros
    $where_conditions = [];
    $params = [];
    
    if ($fecha_desde) {
        $where_conditions[] = "p.fecha_retiro >= ?";
        $params[] = $fecha_desde . ' 00:00:00';
    }
    
    if ($fecha_hasta) {
        $where_conditions[] = "p.fecha_retiro <= ?";
        $params[] = $fecha_hasta . ' 23:59:59';
    }
    
    if ($id_obra) {
        $where_conditions[] = "p.id_obra = ?";
        $params[] = $id_obra;
    }
    
    if ($id_empleado) {
        $where_conditions[] = "p.id_empleado = ?";
        $params[] = $id_empleado;
    }
    
    if ($estado) {
        $where_conditions[] = "p.estado = ?";
        $params[] = $estado;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Consulta principal de préstamos
    $sql = "
        SELECT 
            p.id_prestamo,
            p.numero_prestamo,
            p.fecha_retiro,
            p.fecha_devolucion_programada,
            p.estado,
            p.observaciones_retiro,
            u_empleado.nombre as empleado_nombre,
            u_empleado.apellido as empleado_apellido,
            u_autorizado.nombre as autorizado_nombre,
            u_autorizado.apellido as autorizado_apellido,
            o.nombre_obra,
            COUNT(dp.id_detalle) as total_herramientas,
            COUNT(CASE WHEN dp.devuelto = 0 THEN 1 END) as herramientas_pendientes,
            CASE 
                WHEN p.estado = 'activo' AND p.fecha_devolucion_programada < CURDATE() THEN 'vencido'
                WHEN p.estado = 'activo' AND p.fecha_devolucion_programada = CURDATE() THEN 'vence_hoy'
                WHEN p.estado = 'activo' AND p.fecha_devolucion_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 'vence_pronto'
                ELSE p.estado
            END as estado_calculado,
            DATEDIFF(CURDATE(), p.fecha_devolucion_programada) as dias_vencimiento
        FROM prestamos p
        INNER JOIN usuarios u_empleado ON p.id_empleado = u_empleado.id_usuario
        INNER JOIN usuarios u_autorizado ON p.id_autorizado_por = u_autorizado.id_usuario
        INNER JOIN obras o ON p.id_obra = o.id_obra
        LEFT JOIN detalle_prestamo dp ON p.id_prestamo = dp.id_prestamo
        $where_clause
        GROUP BY p.id_prestamo
        ORDER BY 
            CASE 
                WHEN p.estado = 'activo' AND p.fecha_devolucion_programada < CURDATE() THEN 1
                WHEN p.estado = 'activo' AND p.fecha_devolucion_programada = CURDATE() THEN 2
                WHEN p.estado = 'activo' AND p.fecha_devolucion_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 3
                ELSE 4
            END,
            p.fecha_retiro DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $prestamos = $stmt->fetchAll();
    
    // Obtener estadísticas generales
    $sql_stats = "
        SELECT 
            COUNT(*) as total_prestamos,
            COUNT(CASE WHEN estado = 'activo' THEN 1 END) as prestamos_activos,
            COUNT(CASE WHEN estado = 'devuelto' THEN 1 END) as prestamos_devueltos,
            COUNT(CASE WHEN estado = 'vencido' THEN 1 END) as prestamos_vencidos,
            COUNT(CASE WHEN estado = 'activo' AND fecha_devolucion_programada < CURDATE() THEN 1 END) as vencidos_real,
            COUNT(CASE WHEN estado = 'activo' AND fecha_devolucion_programada = CURDATE() THEN 1 END) as vencen_hoy,
            COUNT(CASE WHEN estado = 'activo' AND fecha_devolucion_programada BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 1 END) as vencen_pronto
        FROM prestamos p
        $where_clause
    ";
    
    $stmt_stats = $pdo->prepare($sql_stats);
    $stmt_stats->execute($params);
    $estadisticas = $stmt_stats->fetch();
    
    // Si se solicita exportar a Excel
    if ($exportar === 'excel') {
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment;filename="herramientas_prestadas_' . date('Y-m-d') . '.xls"');
        header('Cache-Control: max-age=0');
        
        echo "<table border='1'>";
        echo "<tr>";
        echo "<th>Número Préstamo</th>";
        echo "<th>Empleado</th>";
        echo "<th>Obra</th>";
        echo "<th>Fecha Retiro</th>";
        echo "<th>Fecha Devolución</th>";
        echo "<th>Estado</th>";
        echo "<th>Total Herramientas</th>";
        echo "<th>Pendientes</th>";
        echo "<th>Días Vencimiento</th>";
        echo "<th>Autorizado Por</th>";
        echo "</tr>";
        
        foreach ($prestamos as $prestamo) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($prestamo['numero_prestamo']) . "</td>";
            echo "<td>" . htmlspecialchars($prestamo['empleado_nombre'] . ' ' . $prestamo['empleado_apellido']) . "</td>";
            echo "<td>" . htmlspecialchars($prestamo['nombre_obra']) . "</td>";
            echo "<td>" . date('d/m/Y H:i', strtotime($prestamo['fecha_retiro'])) . "</td>";
            echo "<td>" . ($prestamo['fecha_devolucion_programada'] ? date('d/m/Y', strtotime($prestamo['fecha_devolucion_programada'])) : 'No definida') . "</td>";
            echo "<td>" . ucfirst($prestamo['estado']) . "</td>";
            echo "<td>" . $prestamo['total_herramientas'] . "</td>";
            echo "<td>" . $prestamo['herramientas_pendientes'] . "</td>";
            echo "<td>" . ($prestamo['dias_vencimiento'] > 0 ? $prestamo['dias_vencimiento'] : 0) . "</td>";
            echo "<td>" . htmlspecialchars($prestamo['autorizado_nombre'] . ' ' . $prestamo['autorizado_apellido']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        exit();
    }
    
} catch (PDOException $e) {
    $error = "Error al obtener datos: " . $e->getMessage();
}


//header despues de crear la estructura del excel
require_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-tools"></i> Reporte de Herramientas Prestadas</h1>
                <div>
                    <a href="index.php" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Volver al Dashboard
                    </a>
                    <?php if (!empty($prestamos)): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['exportar' => 'excel'])); ?>" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> Exportar Excel
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

     Filtros 
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0"><i class="bi bi-funnel"></i> Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="fecha_desde" class="form-label">Fecha Desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?php echo htmlspecialchars($fecha_desde); ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?php echo htmlspecialchars($fecha_hasta); ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="id_obra" class="form-label">Obra</label>
                    <select class="form-select" id="id_obra" name="id_obra">
                        <option value="">Todas las obras</option>
                        <?php foreach ($obras as $obra): ?>
                            <option value="<?php echo $obra['id_obra']; ?>" <?php echo ($id_obra == $obra['id_obra']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="id_empleado" class="form-label">Empleado</label>
                    <select class="form-select" id="id_empleado" name="id_empleado">
                        <option value="">Todos los empleados</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo $empleado['id_usuario']; ?>" <?php echo ($id_empleado == $empleado['id_usuario']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select class="form-select" id="estado" name="estado">
                        <option value="">Todos los estados</option>
                        <option value="activo" <?php echo ($estado == 'activo') ? 'selected' : ''; ?>>Activo</option>
                        <option value="devuelto" <?php echo ($estado == 'devuelto') ? 'selected' : ''; ?>>Devuelto</option>
                        <option value="vencido" <?php echo ($estado == 'vencido') ? 'selected' : ''; ?>>Vencido</option>
                    </select>
                </div>
                
                <div class="col-md-9 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="bi bi-search"></i> Filtrar
                    </button>
                    <a href="herramientas_prestadas.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

     Estadísticas 
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h4><?php echo number_format($estadisticas['total_prestamos']); ?></h4>
                    <p class="mb-0">Total Préstamos</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h4><?php echo number_format($estadisticas['prestamos_activos']); ?></h4>
                    <p class="mb-0">Activos</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h4><?php echo number_format($estadisticas['prestamos_devueltos']); ?></h4>
                    <p class="mb-0">Devueltos</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h4><?php echo number_format($estadisticas['vencidos_real']); ?></h4>
                    <p class="mb-0">Vencidos</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h4><?php echo number_format($estadisticas['vencen_hoy']); ?></h4>
                    <p class="mb-0">Vencen Hoy</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <h4><?php echo number_format($estadisticas['vencen_pronto']); ?></h4>
                    <p class="mb-0">Vencen Pronto</p>
                </div>
            </div>
        </div>
    </div>

     Tabla de resultados 
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-list-ul"></i> Préstamos de Herramientas
                <span class="badge bg-secondary ms-2"><?php echo count($prestamos); ?> registros</span>
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($prestamos)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-inbox display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">No se encontraron préstamos</h4>
                    <p class="text-muted">No hay préstamos que coincidan con los filtros seleccionados.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Número</th>
                                <th>Empleado</th>
                                <th>Obra</th>
                                <th>Fecha Retiro</th>
                                <th>Fecha Devolución</th>
                                <th>Estado</th>
                                <th>Herramientas</th>
                                <th>Pendientes</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($prestamos as $prestamo): ?>
                                <tr class="<?php 
                                    if ($prestamo['estado_calculado'] === 'vencido') echo 'table-danger';
                                    elseif ($prestamo['estado_calculado'] === 'vence_hoy') echo 'table-warning';
                                    elseif ($prestamo['estado_calculado'] === 'vence_pronto') echo 'table-info';
                                ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($prestamo['numero_prestamo']); ?></strong>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($prestamo['empleado_nombre'] . ' ' . $prestamo['empleado_apellido']); ?></strong>
                                        </div>
                                        <small class="text-muted">
                                            Autorizado por: <?php echo htmlspecialchars($prestamo['autorizado_nombre'] . ' ' . $prestamo['autorizado_apellido']); ?>
                                        </small>
                                    </td>
                                    <td><?php echo htmlspecialchars($prestamo['nombre_obra']); ?></td>
                                    <td>
                                        <div><?php echo date('d/m/Y', strtotime($prestamo['fecha_retiro'])); ?></div>
                                        <small class="text-muted"><?php echo date('H:i', strtotime($prestamo['fecha_retiro'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($prestamo['fecha_devolucion_programada']): ?>
                                            <div><?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion_programada'])); ?></div>
                                            <?php if ($prestamo['estado'] === 'activo' && $prestamo['dias_vencimiento'] > 0): ?>
                                                <small class="text-danger">
                                                    <i class="bi bi-exclamation-triangle"></i> 
                                                    <?php echo $prestamo['dias_vencimiento']; ?> días vencido
                                                </small>
                                            <?php elseif ($prestamo['estado'] === 'activo' && $prestamo['dias_vencimiento'] == 0): ?>
                                                <small class="text-warning">
                                                    <i class="bi bi-clock"></i> Vence hoy
                                                </small>
                                            <?php elseif ($prestamo['estado'] === 'activo' && $prestamo['dias_vencimiento'] >= -3): ?>
                                                <small class="text-info">
                                                    <i class="bi bi-info-circle"></i> 
                                                    Vence en <?php echo abs($prestamo['dias_vencimiento']); ?> días
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No definida</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $estado_badge = '';
                                        switch ($prestamo['estado_calculado']) {
                                            case 'activo':
                                                $estado_badge = '<span class="badge bg-primary">Activo</span>';
                                                break;
                                            case 'devuelto':
                                                $estado_badge = '<span class="badge bg-success">Devuelto</span>';
                                                break;
                                            case 'vencido':
                                                $estado_badge = '<span class="badge bg-danger">Vencido</span>';
                                                break;
                                            case 'vence_hoy':
                                                $estado_badge = '<span class="badge bg-warning text-dark">Vence Hoy</span>';
                                                break;
                                            case 'vence_pronto':
                                                $estado_badge = '<span class="badge bg-info">Vence Pronto</span>';
                                                break;
                                        }
                                        echo $estado_badge;
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $prestamo['total_herramientas']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($prestamo['herramientas_pendientes'] > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo $prestamo['herramientas_pendientes']; ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="../herramientas/view_prestamo.php?id=<?php echo $prestamo['id_prestamo']; ?>" 
                                               class="btn btn-outline-primary" title="Ver Detalle">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($prestamo['estado'] === 'activo' && $prestamo['herramientas_pendientes'] > 0): ?>
                                            <a href="../herramientas/create_devolucion.php?prestamo=<?php echo $prestamo['id_prestamo']; ?>" 
                                               class="btn btn-outline-success" title="Crear Devolución">
                                                <i class="bi bi-arrow-return-left"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

     Información adicional 
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0"><i class="bi bi-info-circle"></i> Leyenda de Estados</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <p><span class="badge bg-primary">Activo</span> - Préstamo en curso</p>
                            <p><span class="badge bg-success">Devuelto</span> - Completamente devuelto</p>
                            <p><span class="badge bg-danger">Vencido</span> - Fecha de devolución pasada</p>
                        </div>
                        <div class="col-6">
                            <p><span class="badge bg-warning text-dark">Vence Hoy</span> - Debe devolverse hoy</p>
                            <p><span class="badge bg-info">Vence Pronto</span> - Vence en 1-3 días</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="card-title mb-0"><i class="bi bi-lightbulb"></i> Consejos</h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li>Los préstamos vencidos aparecen resaltados en rojo</li>
                        <li>Use los filtros para encontrar préstamos específicos</li>
                        <li>Exporte a Excel para análisis detallados</li>
                        <li>Revise regularmente los préstamos próximos a vencer</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.table-responsive {
    border-radius: 0.375rem;
}

.btn-group-sm > .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.badge {
    font-size: 0.75em;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border: 1px solid rgba(0, 0, 0, 0.125);
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.075);
}

.display-1 {
    font-size: 6rem;
}

@media (max-width: 768px) {
    .btn-group {
        flex-direction: column;
    }
    
    .btn-group .btn {
        border-radius: 0.375rem !important;
        margin-bottom: 0.25rem;
    }
    
    .col-md-2 {
        margin-bottom: 1rem;
    }
}
</style>

<?php require_once '../../includes/footer.php'; ?>
