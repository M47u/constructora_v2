<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

$page_title = 'Gestión de Pedidos';

$database = new Database();
$conn = $database->getConnection();

$id_usuario_actual = (int) $_SESSION['user_id'];
$rol_actual        = get_user_role();

// Filtros
$filtro_obra        = $_GET['obra']        ?? '';
$filtro_estado      = $_GET['estado']      ?? '';
$filtro_fecha_desde = $_GET['fecha_desde'] ?? '';
$filtro_fecha_hasta = $_GET['fecha_hasta'] ?? '';

// ── Restricción por rol ──────────────────────────────────────────────
// Se construye como condición base (siempre aplicada, antes de los filtros
// del usuario) para que nadie pueda bypassearla con parámetros GET.
$base_conditions = [];
$base_params     = [];

function obtener_estado_efectivo_pedido(array $pedido): string {
    $estado_actual = $pedido['estado'] ?? 'pendiente';

    // Mantener estados terminales/legacy explícitos.
    if (in_array($estado_actual, ['cancelado', 'devuelto', 'entregado', 'en_camino'], true)) {
        return $estado_actual;
    }

    // Derivar por la etapa más avanzada registrada.
    if (!empty($pedido['fecha_recibido']) || !empty($pedido['id_recibido_por'])) {
        return 'recibido';
    }
    if (!empty($pedido['fecha_retiro']) || !empty($pedido['id_retirado_por'])) {
        return 'retirado';
    }
    if (!empty($pedido['fecha_picking']) || !empty($pedido['id_picking_por'])) {
        return 'picking';
    }
    if (!empty($pedido['fecha_aprobacion']) || !empty($pedido['id_aprobado_por'])) {
        return 'aprobado';
    }

    return 'pendiente';
}

if ($rol_actual === ROLE_RESPONSABLE) {
    // Ve los pedidos que él solicitó O los de obras donde es responsable
    $base_conditions[] = "(p.id_solicitante = ? OR o.id_responsable = ?)";
    $base_params[]     = $id_usuario_actual;
    $base_params[]     = $id_usuario_actual;

} elseif ($rol_actual === ROLE_EMPLEADO) {
    // Ve los pedidos en los que participó en alguna etapa
    // o tiene una tarea vinculada al pedido
    $base_conditions[] = "(
        p.id_solicitante  = ? OR
        p.id_aprobado_por = ? OR
        p.id_picking_por  = ? OR
        p.id_retirado_por = ? OR
        p.id_recibido_por = ? OR
        EXISTS (
            SELECT 1 FROM tareas t
            WHERE t.id_pedido  = p.id_pedido
              AND t.id_empleado = ?
        )
    )";
    for ($i = 0; $i < 6; $i++) {
        $base_params[] = $id_usuario_actual;
    }
}
// ROLE_ADMIN: sin restricción base

// ── Filtros del formulario ───────────────────────────────────────────
$filter_conditions = [];
$filter_params     = [];

if (!empty($filtro_obra)) {
    $filter_conditions[] = "p.id_obra = ?";
    $filter_params[]     = $filtro_obra;
}
if (!empty($filtro_estado)) {
    // Se aplica luego sobre estado_efectivo para cubrir datos desfasados.
}
if (!empty($filtro_fecha_desde)) {
    $filter_conditions[] = "DATE(p.fecha_pedido) >= ?";
    $filter_params[]     = $filtro_fecha_desde;
}
if (!empty($filtro_fecha_hasta)) {
    $filter_conditions[] = "DATE(p.fecha_pedido) <= ?";
    $filter_params[]     = $filtro_fecha_hasta;
}

$all_conditions = array_merge($base_conditions, $filter_conditions);
$all_params     = array_merge($base_params, $filter_params);
$where_clause   = $all_conditions ? 'WHERE ' . implode(' AND ', $all_conditions) : '';

try {
    // Obtener pedidos
    $query = "SELECT p.*, o.nombre_obra, u.nombre, u.apellido,
              TIMESTAMPDIFF(HOUR, p.fecha_pedido, p.fecha_recibido) as total_demora_horas
              FROM pedidos_materiales p
              LEFT JOIN obras o ON p.id_obra = o.id_obra
              LEFT JOIN usuarios u ON p.id_solicitante = u.id_usuario
              $where_clause
              ORDER BY p.fecha_pedido DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($all_params);
    $pedidos = $stmt->fetchAll();

    // Estado efectivo para evitar desfasajes entre estado y etapas guardadas.
    foreach ($pedidos as &$pedido) {
        $pedido['estado_efectivo'] = obtener_estado_efectivo_pedido($pedido);
    }
    unset($pedido);

    // Aplicar filtro de estado sobre el estado efectivo (blindaje ante datos desfasados).
    if (!empty($filtro_estado)) {
        $pedidos = array_values(array_filter($pedidos, function ($pedido) use ($filtro_estado) {
            return ($pedido['estado_efectivo'] ?? $pedido['estado']) === $filtro_estado;
        }));
    }

    // Obras visibles para el filtro (coherente con los pedidos que el usuario puede ver)
    if ($rol_actual === ROLE_ADMIN) {
        $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras ORDER BY CASE prioridad WHEN 'alta' THEN 1 WHEN 'media' THEN 2 WHEN 'baja' THEN 3 ELSE 4 END, CASE WHEN fecha_fin IS NULL THEN 1 ELSE 0 END, fecha_fin ASC, fecha_creacion DESC");
    } elseif ($rol_actual === ROLE_RESPONSABLE) {
        $stmt_obras = $conn->prepare("SELECT id_obra, nombre_obra FROM obras WHERE id_responsable = ? ORDER BY CASE prioridad WHEN 'alta' THEN 1 WHEN 'media' THEN 2 WHEN 'baja' THEN 3 ELSE 4 END, CASE WHEN fecha_fin IS NULL THEN 1 ELSE 0 END, fecha_fin ASC, fecha_creacion DESC");
        $stmt_obras->execute([$id_usuario_actual]);
    } else {
        // Empleado: solo las obras de los pedidos que puede ver
        $stmt_obras = $conn->prepare("
            SELECT DISTINCT o.id_obra, o.nombre_obra
            FROM obras o
            JOIN pedidos_materiales p ON p.id_obra = o.id_obra
            LEFT JOIN tareas t ON t.id_pedido = p.id_pedido AND t.id_empleado = ?
            WHERE (
                p.id_solicitante = ? OR p.id_aprobado_por = ? OR
                p.id_picking_por = ? OR p.id_retirado_por = ? OR
                p.id_recibido_por = ? OR t.id_tarea IS NOT NULL
            )
            ORDER BY o.nombre_obra
        ");
        $stmt_obras->execute(array_fill(0, 7, $id_usuario_actual));
    }
    $obras = $stmt_obras->fetchAll();

    // Estadísticas (sobre los pedidos que el usuario puede ver, sin filtros de formulario)
    $stats_where = $base_conditions ? 'WHERE ' . implode(' AND ', $base_conditions) : '';
    $stats_query = "SELECT
        COUNT(*) as total_pedidos,
        COUNT(CASE WHEN p.estado = 'pendiente' THEN 1 END) as pendientes,
        COUNT(CASE WHEN p.estado = 'aprobado'  THEN 1 END) as aprobados,
        COUNT(CASE WHEN p.estado = 'entregado' THEN 1 END) as entregados,
        COALESCE(SUM(p.valor_total), 0) as valor_total_pedidos
        FROM pedidos_materiales p
        LEFT JOIN obras o ON p.id_obra = o.id_obra
        $stats_where";
    $stmt_stats = $conn->prepare($stats_query);
    $stmt_stats->execute($base_params);
    $stats = $stmt_stats->fetch();

} catch (Exception $e) {
    error_log("Error al obtener pedidos: " . $e->getMessage());
    $pedidos = [];
    $obras = [];
    $stats = ['total_pedidos' => 0, 'pendientes' => 0, 'aprobados' => 0, 'entregados' => 0, 'valor_total_pedidos' => 0];
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">
                    <i class="bi bi-clipboard-check"></i> Gestión de Pedidos
                </h1>
                <?php if ($rol_actual === ROLE_RESPONSABLE): ?>
                <small class="text-muted">Mostrando pedidos de tus obras y los que solicitaste</small>
                <?php elseif ($rol_actual === ROLE_EMPLEADO): ?>
                <small class="text-muted">Mostrando pedidos en los que participaste</small>
                <?php endif; ?>
            </div>
            <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nuevo Pedido
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card dashboard-card">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Total Pedidos</h6>
                        <h3 class="mb-0"><?php echo $stats['total_pedidos']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clipboard dashboard-icon text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card warning">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Pendientes</h6>
                        <h3 class="mb-0 text-warning"><?php echo $stats['pendientes']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock dashboard-icon text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card info">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Aprobados</h6>
                        <h3 class="mb-0 text-info"><?php echo $stats['aprobados']; ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle dashboard-icon text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card dashboard-card success">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6 class="text-muted">Valor Total</h6>
                        <h3 class="mb-0 text-success">$<?php echo number_format($stats['valor_total_pedidos'], 0); ?></h3>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-currency-dollar dashboard-icon text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="filter-section">
    <form method="GET" class="row g-3">
        <div class="col-md-3">
            <label for="obra" class="form-label">Obra</label>
            <select class="form-select" id="obra" name="obra">
                <option value="">Todas las obras</option>
                <?php foreach ($obras as $obra): ?>
                    <option value="<?php echo $obra['id_obra']; ?>" 
                            <?php echo $filtro_obra == $obra['id_obra'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($obra['nombre_obra']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="estado" class="form-label">Estado</label>
            <select class="form-select" id="estado" name="estado">
                <option value="">Todos los estados</option>
                <option value="pendiente" <?php echo $filtro_estado == 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                <option value="aprobado" <?php echo $filtro_estado == 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                <option value="en_camino" <?php echo $filtro_estado == 'en_camino' ? 'selected' : ''; ?>>En Camino</option>
                <option value="retirado" <?php echo $filtro_estado == 'retirado' ? 'selected' : ''; ?>>Retirado</option>
                <option value="recibido" <?php echo $filtro_estado == 'recibido' ? 'selected' : ''; ?>>Recibido</option>
                <option value="entregado" <?php echo $filtro_estado == 'entregado' ? 'selected' : ''; ?>>Entregado</option>
                <option value="devuelto" <?php echo $filtro_estado == 'devuelto' ? 'selected' : ''; ?>>Devuelto</option>
                <option value="cancelado" <?php echo $filtro_estado == 'cancelado' ? 'selected' : ''; ?>>Cancelado</option>
            </select>
        </div>
        
        <div class="col-md-2">
            <label for="fecha_desde" class="form-label">Desde</label>
            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" 
                   value="<?php echo htmlspecialchars($filtro_fecha_desde); ?>">
        </div>
        
        <div class="col-md-2">
            <label for="fecha_hasta" class="form-label">Hasta</label>
            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" 
                   value="<?php echo htmlspecialchars($filtro_fecha_hasta); ?>">
        </div>
        
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-outline-primary me-2">
                <i class="bi bi-search"></i> Filtrar
            </button>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
        </div>
    </form>
</div>

<!-- Tabla de pedidos -->
<div class="card">
    <div class="card-body">
        <?php if (!empty($pedidos)): ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Obra</th>
                        <th>Solicitante</th>
                        <th>Fecha</th>
                        <th>Items</th>
                        <th>Valor Total</th>
                        <th>Total Demora</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <?php $estado_pedido = $pedido['estado_efectivo'] ?? $pedido['estado']; ?>
                        <td>
                            <strong>#<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($pedido['nombre_obra']); ?></td>
                        <td><?php echo htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']); ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></td>
                        <td>
                            <span class="badge bg-secondary"><?php echo $pedido['total_items']; ?> items</span>
                        </td>
                        <td>
                            <strong>$<?php echo number_format($pedido['valor_total'], 2); ?></strong>
                            <?php if ($pedido['valor_a_comprar'] > 0): ?>
                                <br><small class="text-danger">Requiere: $<?php echo number_format($pedido['valor_a_comprar'], 2); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($pedido['total_demora_horas'] !== null): 
                                $dias = floor($pedido['total_demora_horas'] / 24);
                                $horas = fmod($pedido['total_demora_horas'], 24);
                            ?>
                                <strong>
                                    <?php if ($dias > 0): ?>
                                        <?php echo $dias; ?>d 
                                    <?php endif; ?>
                                    <?php echo number_format($horas, 1); ?>h
                                </strong>
                                <?php if ($pedido['total_demora_horas'] > 48): ?>
                                    <br><small class="text-danger"><i class="bi bi-exclamation-triangle"></i> Demorado</small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $estado_class = [
                                'pendiente' => 'bg-warning text-dark',
                                'aprobado' => 'bg-info',
                                'picking' => 'bg-warning',
                                'retirado' => 'bg-primary',
                                'recibido' => 'bg-success',
                                'en_camino' => 'bg-primary',
                                'entregado' => 'bg-success',
                                'devuelto' => 'bg-secondary',
                                'cancelado' => 'bg-danger'
                            ];
                            $estado_icons = [
                                'pendiente' => 'clock',
                                'aprobado' => 'check-circle',
                                'picking' => 'box-seam',
                                'retirado' => 'box-arrow-right',
                                'recibido' => 'check-circle-fill',
                                'en_camino' => 'truck',
                                'entregado' => 'check-square',
                                'devuelto' => 'arrow-return-left',
                                'cancelado' => 'x-circle'
                            ];
                            $estado_texto = [
                                'pendiente' => 'Pendiente',
                                'aprobado' => 'Aprobado',
                                'picking' => 'En Picking',
                                'retirado' => 'Retirado',
                                'recibido' => 'Entregado',
                                'en_camino' => 'En camino',
                                'entregado' => 'Entregado',
                                'devuelto' => 'Devuelto',
                                'cancelado' => 'Cancelado'
                            ];
                            ?>
                            <span class="badge <?php echo $estado_class[$estado_pedido] ?? 'bg-secondary'; ?>">
                                <i class="bi bi-<?php echo $estado_icons[$estado_pedido] ?? 'question'; ?>"></i>
                                <?php echo $estado_texto[$estado_pedido] ?? ucfirst($estado_pedido); ?>
                            </span>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-info" title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($estado_pedido == 'pendiente'): ?>
                                <a href="edit.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-primary" title="Editar">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) && in_array($estado_pedido, ['pendiente', 'aprobado', 'retirado'])): ?>
                                <a href="process.php?id=<?php echo $pedido['id_pedido']; ?>"
                                   class="btn btn-outline-success" title="Procesar">
                                    <i class="bi bi-gear"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE]) && in_array($estado_pedido, ['retirado', 'recibido'])): ?>
                                <a href="devolver.php?id=<?php echo $pedido['id_pedido']; ?>"
                                   class="btn btn-outline-warning" title="Registrar devolución">
                                    <i class="bi bi-arrow-return-left"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($estado_pedido == 'pendiente'): ?>
                                <a href="delete.php?id=<?php echo $pedido['id_pedido']; ?>" 
                                   class="btn btn-outline-danger btn-delete" 
                                   data-item-name="el pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?>"
                                   title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-clipboard-x text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 text-muted">No se encontraron pedidos</h5>
            <p class="text-muted">
                <?php if (!empty($filtro_obra) || !empty($filtro_estado)): ?>
                    Intente modificar los filtros de búsqueda.
                <?php else: ?>
                    Comience creando el primer pedido de materiales.
                <?php endif; ?>
            </p>
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Crear Primer Pedido
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
