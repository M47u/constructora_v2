<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Registrar Devolución';

$database = new Database();
$conn     = $database->getConnection();

$id_pedido = intval($_GET['id'] ?? 0);
$errors    = [];
$modo_forzado = intval($_GET['forzar'] ?? 0) === 1;

if (!$id_pedido) {
    redirect(SITE_URL . '/modules/pedidos/list.php');
}

// ------------------------------------------------------------------
// Cargar pedido
// ------------------------------------------------------------------
try {
    $stmt = $conn->prepare("
        SELECT p.*, o.nombre_obra, u.nombre, u.apellido
        FROM   pedidos_materiales p
        LEFT JOIN obras    o ON p.id_obra       = o.id_obra
        LEFT JOIN usuarios u ON p.id_solicitante = u.id_usuario
        WHERE  p.id_pedido = ?
    ");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();

    if (!$pedido || !in_array($pedido['estado'], ['retirado', 'recibido'])) {
        $_SESSION['error_message'] = 'Solo se pueden registrar devoluciones de pedidos en estado Retirado o Recibido.';
        redirect(SITE_URL . '/modules/pedidos/view.php?id=' . $id_pedido);
    }

    // Cargar ítems del pedido para calcular máximos devolvibles.
    $stmt_det = $conn->prepare("
        SELECT d.id_detalle,
               d.id_material,
               d.cantidad_solicitada,
               d.cantidad_retirada,
               d.cantidad_devuelta,
               (d.cantidad_retirada - d.cantidad_devuelta) AS saldo,
               m.nombre_material,
               m.unidad_medida
        FROM   detalle_pedidos_materiales d
        LEFT JOIN materiales m ON d.id_material = m.id_material
                WHERE  d.id_pedido = ?
        ORDER BY m.nombre_material
    ");
    $stmt_det->execute([$id_pedido]);
    $detalles = $stmt_det->fetchAll();

    foreach ($detalles as &$det) {
        $saldo_retirado = intval($det['saldo']);
        $saldo_solicitado = intval($det['cantidad_solicitada']) - intval($det['cantidad_devuelta']);
        $det['max_devolver'] = $modo_forzado
            ? max(0, $saldo_solicitado)
            : max(0, $saldo_retirado);
    }
    unset($det);

    // Ítems realmente devolvibles según el modo.
    $detalles_con_saldo = array_filter($detalles, fn($d) => intval($d['max_devolver']) > 0);

    if (empty($detalles_con_saldo)) {
        $_SESSION['error_message'] = $modo_forzado
            ? 'Este pedido no tiene ítems disponibles para devolución manual.'
            : 'Este pedido no tiene ítems con saldo pendiente de devolución.';
        redirect(SITE_URL . '/modules/pedidos/view.php?id=' . $id_pedido);
    }

} catch (Exception $e) {
    error_log("Error cargando pedido para devolución: " . $e->getMessage());
    redirect(SITE_URL . '/modules/pedidos/list.php');
}

// ------------------------------------------------------------------
// Procesar formulario POST
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $modo_forzado = intval($_POST['modo_forzado'] ?? 0) === 1;

    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = 'Token de seguridad inválido. Recargue la página.';
    } else {
        $cantidades_post = $_POST['cantidades'] ?? [];
        $observaciones   = sanitize_input($_POST['observaciones'] ?? '');

        // Construir mapa id_detalle → datos para validación
        $saldo_map = [];
        foreach ($detalles as $det) {
            $saldo_map[intval($det['id_detalle'])] = $det;
        }

        $items_a_devolver = [];

        foreach ($cantidades_post as $id_detalle_raw => $cantidad_raw) {
            $id_det  = intval($id_detalle_raw);
            $cantidad = intval($cantidad_raw);

            if ($cantidad === 0) continue;

            if ($cantidad < 1) {
                $errors[] = 'Las cantidades a devolver deben ser mayores a cero.';
                break;
            }

            if (!isset($saldo_map[$id_det])) {
                $errors[] = 'Ítem de pedido no válido (ID: ' . $id_det . ').';
                break;
            }

            $max_devolver = intval($saldo_map[$id_det]['max_devolver']);
            if ($cantidad > $max_devolver) {
                $nombre = htmlspecialchars($saldo_map[$id_det]['nombre_material']);
                $errors[] = "La cantidad a devolver de «$nombre» ($cantidad) supera el máximo permitido ($max_devolver).";
            }

            $items_a_devolver[$id_det] = $cantidad;
        }

        if (empty($items_a_devolver) && empty($errors)) {
            $errors[] = 'Debe ingresar al menos una cantidad mayor a cero.';
        }

        if (empty($errors)) {
            try {
                $conn->beginTransaction();

                $fecha_actual = date('Y-m-d H:i:s');
                $id_usuario   = intval($_SESSION['user_id']);

                // Generar número de devolución (año + secuencial 4 dígitos)
                $year   = date('Y');
                $prefix = 'DEV' . $year;
                $stmt_num = $conn->prepare("
                    SELECT COALESCE(MAX(CAST(SUBSTRING(numero_devolucion, ?) AS UNSIGNED)), 0) + 1
                    FROM   devoluciones_pedidos
                    WHERE  numero_devolucion LIKE ?
                ");
                $stmt_num->execute([strlen($prefix) + 1, $prefix . '%']);
                $next_num         = intval($stmt_num->fetchColumn());
                $numero_devolucion = $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);

                // Determinar si es devolución parcial o total
                $max_total_actual  = 0;
                $devuelto_este_evento = 0;
                foreach ($detalles_con_saldo as $det) {
                    $max_total_actual   += intval($det['max_devolver']);
                    $devuelto_este_evento += ($items_a_devolver[intval($det['id_detalle'])] ?? 0);
                }
                $tipo = ($devuelto_este_evento >= $max_total_actual) ? 'total' : 'parcial';

                // Insertar cabecera de devolución
                $stmt_dev = $conn->prepare("
                    INSERT INTO devoluciones_pedidos
                        (id_pedido, numero_devolucion, tipo, fecha_devolucion, id_usuario, observaciones)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_dev->execute([$id_pedido, $numero_devolucion, $tipo, $fecha_actual, $id_usuario, $observaciones]);
                $id_devolucion = intval($conn->lastInsertId());

                // Preparar statements reutilizables
                $stmt_detdev = $conn->prepare("
                    INSERT INTO detalle_devoluciones_pedidos
                        (id_devolucion, id_detalle_pedido, id_material, cantidad_devuelta)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt_upd_det = $conn->prepare("
                    UPDATE detalle_pedidos_materiales
                    SET    cantidad_devuelta = cantidad_devuelta + ?
                    WHERE  id_detalle = ?
                ");
                $stmt_stock = $conn->prepare("
                    UPDATE materiales
                    SET    stock_actual = stock_actual + ?
                    WHERE  id_material  = ?
                ");
                $stmt_log = $conn->prepare("
                    INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?)
                ");

                foreach ($items_a_devolver as $id_det => $cantidad) {
                    $det = $saldo_map[$id_det];

                    // Detalle devolución
                    $stmt_detdev->execute([
                        $id_devolucion,
                        $id_det,
                        intval($det['id_material']),
                        $cantidad
                    ]);

                    // Actualizar cantidad_devuelta acumulada en el ítem
                    $stmt_upd_det->execute([$cantidad, $id_det]);

                    // Devolver al stock
                    $stmt_stock->execute([$cantidad, intval($det['id_material'])]);

                    // Log de movimiento de stock
                    $stmt_log->execute([
                        $id_usuario,
                        'stock_entrada',
                        'materiales',
                        "Devolución $numero_devolucion - Pedido #" . str_pad($id_pedido, 4, '0', STR_PAD_LEFT)
                            . " - Material ID: " . $det['id_material']
                            . " - Cantidad devuelta: $cantidad",
                        $fecha_actual
                    ]);
                }

                // Cambiar estado del pedido si es devolución total
                $estado_anterior = $pedido['estado'];
                $estado_nuevo    = $estado_anterior; // por defecto no cambia

                if ($tipo === 'total') {
                    $estado_nuevo = 'devuelto';
                    $stmt_estado  = $conn->prepare("
                        UPDATE pedidos_materiales SET estado = 'devuelto' WHERE id_pedido = ?
                    ");
                    $stmt_estado->execute([$id_pedido]);
                }

                // Registrar en seguimiento_pedidos
                $obs_seg = ($tipo === 'total')
                    ? "Devolución total registrada — $numero_devolucion"
                    : "Devolución parcial registrada — $numero_devolucion";
                if (!empty($observaciones)) {
                    $obs_seg .= ". Observaciones: $observaciones";
                }

                $stmt_seg = $conn->prepare("
                    INSERT INTO seguimiento_pedidos
                        (id_pedido, estado_anterior, estado_nuevo, observaciones, id_usuario_cambio, fecha_cambio)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_seg->execute([
                    $id_pedido,
                    $estado_anterior,
                    $estado_nuevo,
                    $obs_seg,
                    $id_usuario,
                    $fecha_actual
                ]);

                $conn->commit();

                if ($tipo === 'total') {
                    $_SESSION['success_message'] = "Devolución total registrada ($numero_devolucion). El pedido fue marcado como Devuelto.";
                } else {
                    $_SESSION['success_message'] = "Devolución parcial registrada ($numero_devolucion).";
                }

                redirect(SITE_URL . '/modules/pedidos/view.php?id=' . $id_pedido);

            } catch (Exception $e) {
                $conn->rollBack();
                $errors[] = 'Error al registrar la devolución: ' . $e->getMessage();
                error_log("Error en devolución pedido #$id_pedido: " . $e->getMessage());
            }
        }
    }
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <strong>Se encontraron los siguientes errores:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-arrow-return-left"></i>
                Registrar Devolución — Pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
            </h1>
            <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Pedido
            </a>
        </div>
    </div>
</div>

<form method="POST" class="needs-validation" novalidate id="form-devolucion">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="modo_forzado" value="<?php echo $modo_forzado ? '1' : '0'; ?>">

    <div class="row">
        <!-- ── Columna izquierda: ítems ── -->
        <div class="col-md-8">

            <!-- Info del pedido -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle"></i> Información del Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Obra:</strong> <?php echo htmlspecialchars($pedido['nombre_obra']); ?></p>
                            <p class="mb-1"><strong>Solicitante:</strong> <?php echo htmlspecialchars($pedido['nombre'] . ' ' . $pedido['apellido']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1">
                                <strong>Estado actual:</strong>
                                <?php if ($pedido['estado'] === 'retirado'): ?>
                                    <span class="badge bg-primary"><i class="bi bi-box-arrow-right"></i> Retirado</span>
                                <?php else: ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Recibido</span>
                                <?php endif; ?>
                            </p>
                            <p class="mb-1"><strong>Fecha pedido:</strong> <?php echo date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($modo_forzado): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Modo manual habilitado:</strong> este pedido no tenía saldo retirado pendiente.
                Se permite devolver en base a lo solicitado pendiente para corregir registros anteriores.
            </div>
            <?php endif; ?>

            <!-- Tabla de ítems con saldo -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-box-seam"></i> Materiales a Devolver
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-devolver-todo">
                        <i class="bi bi-check-all"></i> Devolver Todo
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Material</th>
                                    <th class="text-center">Retirado</th>
                                    <th class="text-center">Ya devuelto</th>
                                    <th class="text-center">Saldo</th>
                                    <th class="text-center" style="width:160px">Cantidad a devolver</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detalles as $det):
                                    $saldo = intval($det['saldo']);
                                    $max_devolver = intval($det['max_devolver']);
                                ?>
                                <tr class="<?php echo $max_devolver === 0 ? 'table-secondary' : ''; ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($det['nombre_material']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($det['unidad_medida']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-6"><?php echo number_format($det['cantidad_retirada']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?php if (intval($det['cantidad_devuelta']) > 0): ?>
                                            <span class="badge bg-warning text-dark"><?php echo number_format($det['cantidad_devuelta']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($saldo > 0): ?>
                                            <span class="badge bg-info fs-6"><?php echo number_format($saldo); ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg"></i> Completado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($max_devolver > 0): ?>
                                        <div class="input-group input-group-sm">
                                            <input type="number"
                                                   class="form-control text-center cantidad-devolver"
                                                   name="cantidades[<?php echo $det['id_detalle']; ?>]"
                                                   min="0"
                                                   max="<?php echo $max_devolver; ?>"
                                                   value="0"
                                                   data-max="<?php echo $max_devolver; ?>"
                                                   data-nombre="<?php echo htmlspecialchars($det['nombre_material']); ?>"
                                                   data-unidad="<?php echo htmlspecialchars($det['unidad_medida']); ?>">
                                            <span class="input-group-text"><?php echo htmlspecialchars($det['unidad_medida']); ?></span>
                                        </div>
                                        <small class="text-muted">Máx: <?php echo number_format($max_devolver); ?></small>
                                        <?php else: ?>
                                            <input type="hidden" name="cantidades[<?php echo $det['id_detalle']; ?>]" value="0">
                                            <span class="text-muted small">Sin cantidad disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <!-- ── Columna derecha: resumen y acción ── -->
        <div class="col-md-4">

            <!-- Resumen -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clipboard-check"></i> Resumen de Devolución
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Ítems a devolver:</span>
                        <strong id="resumen-items">0</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Total unidades:</span>
                        <strong id="resumen-unidades">0</strong>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Tipo:</span>
                        <span id="resumen-tipo" class="badge bg-secondary">—</span>
                    </div>
                </div>
            </div>

            <!-- Observaciones y submit -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-chat-text"></i> Observaciones
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <textarea class="form-control" name="observaciones" rows="3"
                                  placeholder="Motivo de la devolución, condición de los materiales, etc."></textarea>
                    </div>

                    <div class="alert alert-warning mb-3 small">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Atención:</strong> Esta acción restaurará el stock de los materiales devueltos.
                        Si todos los ítems son devueltos, el pedido cambiará a estado <strong>Devuelto</strong>.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger" id="btn-submit" disabled>
                            <i class="bi bi-arrow-return-left"></i> Registrar Devolución
                        </button>
                        <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';

    const inputs   = document.querySelectorAll('.cantidad-devolver');
    const btnAll   = document.getElementById('btn-devolver-todo');
    const btnSubmit = document.getElementById('btn-submit');
    const elItems  = document.getElementById('resumen-items');
    const elUnid   = document.getElementById('resumen-unidades');
    const elTipo   = document.getElementById('resumen-tipo');

    // Total máximo devolvible del pedido (para determinar parcial vs total)
    const totalMax = Array.from(inputs).reduce((acc, inp) => acc + parseInt(inp.dataset.max || 0), 0);

    function actualizarResumen() {
        let items = 0, unidades = 0, valido = true;

        inputs.forEach(inp => {
            const val  = parseInt(inp.value) || 0;
            const max  = parseInt(inp.dataset.max) || 0;

            if (val > max) {
                inp.classList.add('is-invalid');
                valido = false;
            } else {
                inp.classList.remove('is-invalid');
            }

            if (val > 0) {
                items++;
                unidades += val;
            }
        });

        elItems.textContent  = items;
        elUnid.textContent   = unidades;

        if (unidades === 0) {
            elTipo.textContent  = '—';
            elTipo.className    = 'badge bg-secondary';
        } else if (unidades >= totalMax) {
            elTipo.textContent = 'Total';
            elTipo.className   = 'badge bg-danger';
        } else {
            elTipo.textContent = 'Parcial';
            elTipo.className   = 'badge bg-warning text-dark';
        }

        btnSubmit.disabled = !(valido && items > 0);
    }

    inputs.forEach(inp => inp.addEventListener('input', actualizarResumen));

    btnAll.addEventListener('click', function () {
        inputs.forEach(inp => {
            inp.value = inp.dataset.max;
        });
        actualizarResumen();
    });

    // Validación HTML5 antes de enviar
    document.getElementById('form-devolucion').addEventListener('submit', function (e) {
        let valido = true;
        inputs.forEach(inp => {
            const val = parseInt(inp.value) || 0;
            const max = parseInt(inp.dataset.max) || 0;
            if (val > max) { valido = false; inp.classList.add('is-invalid'); }
        });
        if (!valido) {
            e.preventDefault();
            e.stopPropagation();
        }
    });

    actualizarResumen();
})();
</script>

<?php include '../../includes/footer.php'; ?>
