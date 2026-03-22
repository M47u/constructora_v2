<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = "Métricas de Eficiencia por Usuario";

// ── Filtros ──────────────────────────────────────────────────────────────────
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-d', strtotime('-3 months'));
$fecha_fin    = $_GET['fecha_fin']    ?? date('Y-m-d');
$id_usuario   = isset($_GET['id_usuario']) && is_numeric($_GET['id_usuario'])
                    ? (int)$_GET['id_usuario'] : 0;
$id_obra      = isset($_GET['id_obra']) && is_numeric($_GET['id_obra'])
                    ? (int)$_GET['id_obra'] : 0;

if (!strtotime($fecha_inicio) || !strtotime($fecha_fin) || $fecha_inicio > $fecha_fin) {
    $fecha_inicio = date('Y-m-d', strtotime('-3 months'));
    $fecha_fin    = date('Y-m-d');
}

$fi_dt = $fecha_inicio . ' 00:00:00';
$ff_dt = $fecha_fin    . ' 23:59:59';

try {
    $database = new Database();
    $conn = $database->getConnection();

    // ── Detectar si la migración de pedido-tareas ya fue aplicada ───────────
    $stmt_col = $conn->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'tareas'
          AND COLUMN_NAME  = 'tipo'
    ");
    $has_tipo = (bool)$stmt_col->fetchColumn();

    // ── Lista de usuarios para el selector ──────────────────────────────────
    $stmt_ulist = $conn->query("SELECT id_usuario, nombre, apellido, rol
                                FROM usuarios WHERE estado = 'activo'
                                ORDER BY nombre, apellido");
    $lista_usuarios = $stmt_ulist->fetchAll(PDO::FETCH_ASSOC);

    // ── Lista de obras para el selector ─────────────────────────────────────
    $stmt_obras = $conn->query("SELECT id_obra, nombre_obra FROM obras ORDER BY nombre_obra");
    $lista_obras = $stmt_obras->fetchAll(PDO::FETCH_ASSOC);

    // Nombre de la obra filtrada (para mostrar en la cabecera)
    $nombre_obra_filtrada = '';
    if ($id_obra) {
        foreach ($lista_obras as $ob) {
            if ($ob['id_obra'] == $id_obra) {
                $nombre_obra_filtrada = $ob['nombre_obra'];
                break;
            }
        }
    }

    // ── 1. MÉTRICAS DE TAREAS POR USUARIO ───────────────────────────────────
    $p_tareas = [$fi_dt, $ff_dt];
    $w_usr    = $id_usuario ? 'AND u.id_usuario = ?' : '';
    if ($id_usuario) $p_tareas[] = $id_usuario;

    $sql_tareas = "
        SELECT
            u.id_usuario,
            u.nombre,
            u.apellido,
            u.rol,
            COUNT(t.id_tarea)                                                                              AS total_tareas,
            SUM(t.estado = 'finalizada')                                                                   AS finalizadas,
            SUM(t.estado IN ('pendiente','en_proceso'))                                                    AS activas,
            SUM(t.estado = 'cancelada')                                                                    AS canceladas,
            SUM(t.estado = 'finalizada'
                AND (t.fecha_vencimiento IS NULL
                     OR t.fecha_finalizacion <= t.fecha_vencimiento))                                      AS a_tiempo,
            SUM(t.estado = 'finalizada'
                AND t.fecha_vencimiento IS NOT NULL
                AND t.fecha_finalizacion > t.fecha_vencimiento)                                            AS con_retraso,
            ROUND(AVG(CASE
                WHEN t.tiempo_estimado > 0 AND t.tiempo_real > 0
                THEN (t.tiempo_real / t.tiempo_estimado) * 100
            END), 1)                                                                                       AS ratio_eficiencia,
            ROUND(AVG(CASE WHEN t.tiempo_real > 0 THEN t.tiempo_real END), 1)                             AS promedio_hrs_real,
            COALESCE(SUM(t.tiempo_real), 0)                                                                AS total_hrs,
            ROUND(AVG(CASE
                WHEN t.fecha_inicio IS NOT NULL
                THEN TIMESTAMPDIFF(MINUTE, t.fecha_asignacion, t.fecha_inicio) / 60.0
            END), 1)                                                                                        AS promedio_reaccion_hrs
        FROM usuarios u
        INNER JOIN tareas t ON t.id_empleado = u.id_usuario
        WHERE u.estado = 'activo'
          AND t.fecha_asignacion BETWEEN ? AND ?
          $w_usr
        GROUP BY u.id_usuario, u.nombre, u.apellido, u.rol
        ORDER BY finalizadas DESC, total_tareas DESC
    ";
    $stmt_t = $conn->prepare($sql_tareas);
    $stmt_t->execute($p_tareas);
    $metricas_tareas = $stmt_t->fetchAll(PDO::FETCH_ASSOC);

    // Indexar por id_usuario
    $datos = [];
    foreach ($metricas_tareas as $r) {
        $datos[$r['id_usuario']] = array_merge($r, [
            'etapas'    => [],
            'prestamos' => [],
        ]);
    }

    // ── 2. MÉTRICAS DE ETAPAS DE PEDIDOS POR USUARIO ────────────────────────
    // UNION de las 3 etapas del flujo de pedido (filtrable por obra)
    $w_ap   = $id_usuario ? 'AND p.id_aprobado_por = ?' : '';
    $w_re   = $id_usuario ? 'AND p.id_retirado_por  = ?' : '';
    $w_rc   = $id_usuario ? 'AND p.id_recibido_por  = ?' : '';
    $w_obra = $id_obra    ? 'AND p.id_obra = ?'          : '';

    // Params de una sola rama: [fecha_inicio, fecha_fin, (id_usuario?), (id_obra?)]
    $p_e = [$fecha_inicio, $fecha_fin];
    if ($id_usuario) $p_e[] = $id_usuario;
    if ($id_obra)    $p_e[] = $id_obra;
    $p_et = array_merge($p_e, $p_e, $p_e);

    $sql_etapas = "
        SELECT id_usuario, etapa, SUM(cantidad) AS cantidad,
               ROUND(AVG(promedio_hrs), 1) AS promedio_hrs
        FROM (
            SELECT p.id_aprobado_por AS id_usuario, 'aprobacion' AS etapa,
                   COUNT(*) AS cantidad,
                   AVG(TIMESTAMPDIFF(MINUTE, p.fecha_pedido, p.fecha_aprobacion) / 60.0) AS promedio_hrs
            FROM pedidos_materiales p
            WHERE p.fecha_aprobacion IS NOT NULL
              AND DATE(p.fecha_pedido) BETWEEN ? AND ?
              $w_ap $w_obra
            GROUP BY p.id_aprobado_por

            UNION ALL

            SELECT p.id_retirado_por, 'retiro',
                   COUNT(*),
                   AVG(TIMESTAMPDIFF(MINUTE, p.fecha_aprobacion, p.fecha_retiro) / 60.0)
            FROM pedidos_materiales p
            WHERE p.fecha_retiro IS NOT NULL AND p.fecha_aprobacion IS NOT NULL
              AND DATE(p.fecha_pedido) BETWEEN ? AND ?
              $w_re $w_obra
            GROUP BY p.id_retirado_por

            UNION ALL

            SELECT p.id_recibido_por, 'recibido',
                   COUNT(*),
                   AVG(TIMESTAMPDIFF(MINUTE, p.fecha_retiro, p.fecha_recibido) / 60.0)
            FROM pedidos_materiales p
            WHERE p.fecha_recibido IS NOT NULL AND p.fecha_retiro IS NOT NULL
              AND DATE(p.fecha_pedido) BETWEEN ? AND ?
              $w_rc $w_obra
            GROUP BY p.id_recibido_por
        ) sub
        GROUP BY id_usuario, etapa
    ";
    $stmt_e = $conn->prepare($sql_etapas);
    $stmt_e->execute($p_et);
    foreach ($stmt_e->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = $row['id_usuario'];
        if (!isset($datos[$uid])) {
            // Usuario que solo procesa pedidos (sin tareas asignadas en el período)
            $uinfo = array_filter($lista_usuarios, fn($u) => $u['id_usuario'] == $uid);
            $uinfo = $uinfo ? reset($uinfo) : ['nombre' => '?', 'apellido' => '', 'rol' => ''];
            $datos[$uid] = array_merge($uinfo, [
                'id_usuario'          => $uid,
                'total_tareas'        => 0, 'finalizadas'     => 0,
                'activas'             => 0, 'canceladas'      => 0,
                'a_tiempo'            => 0, 'con_retraso'     => 0,
                'ratio_eficiencia'    => null,
                'promedio_hrs_real'   => null,
                'total_hrs'           => 0,
                'promedio_reaccion_hrs' => null,
                'etapas'              => [],
                'prestamos'           => [],
            ]);
        }
        $datos[$uid]['etapas'][$row['etapa']] = [
            'cantidad'     => (int)$row['cantidad'],
            'promedio_hrs' => (float)$row['promedio_hrs'],
        ];
    }

    // ── 3. MÉTRICAS DE PRÉSTAMOS DE HERRAMIENTAS POR USUARIO ────────────────
    $p_pr = [$fecha_inicio, $fecha_fin];
    $w_pr = $id_usuario ? 'AND p.id_empleado = ?' : '';
    if ($id_usuario) $p_pr[] = $id_usuario;

    $sql_prest = "
        SELECT
            p.id_empleado                       AS id_usuario,
            COUNT(*)                            AS total_prestamos,
            SUM(p.estado = 'devuelto')          AS devueltos,
            SUM(p.estado = 'activo')            AS activos,
            SUM(p.estado = 'vencido')           AS vencidos
        FROM prestamos p
        WHERE DATE(p.fecha_retiro) BETWEEN ? AND ?
          $w_pr
        GROUP BY p.id_empleado
    ";
    $stmt_pr = $conn->prepare($sql_prest);
    $stmt_pr->execute($p_pr);
    foreach ($stmt_pr->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $uid = $row['id_usuario'];
        if (isset($datos[$uid])) {
            $datos[$uid]['prestamos'] = $row;
        }
    }

    // ── 4. KPIs GLOBALES ─────────────────────────────────────────────────────
    $total_usuarios_eval   = count($datos);
    $total_finalizadas     = array_sum(array_column(array_values($datos), 'finalizadas'));
    $total_tareas_periodo  = array_sum(array_column(array_values($datos), 'total_tareas'));

    $cumplimientos = [];
    $eficiencias   = [];
    foreach ($datos as $d) {
        if ($d['finalizadas'] > 0) {
            $cumplimientos[] = ($d['a_tiempo'] / $d['finalizadas']) * 100;
        }
        if ($d['ratio_eficiencia'] !== null) {
            $eficiencias[] = $d['ratio_eficiencia'];
        }
    }
    $promedio_cumplimiento = !empty($cumplimientos)
        ? round(array_sum($cumplimientos) / count($cumplimientos), 1) : 0;
    $promedio_eficiencia   = !empty($eficiencias)
        ? round(array_sum($eficiencias) / count($eficiencias), 1) : 0;

    // ── 5. DETALLE POR USUARIO (si se filtra uno) ────────────────────────────
    $detalle_tareas   = [];
    $detalle_pedidos  = [];
    $desglose_por_obra = [];
    if ($id_usuario) {
        // Tareas individuales
        $col_tipo      = $has_tipo ? "t.tipo"           : "'manual'";
        $col_etapa     = $has_tipo ? "t.etapa_pedido"   : "NULL";
        $col_id_pedido = $has_tipo ? "t.id_pedido"      : "NULL";

        $stmt_dt = $conn->prepare("
            SELECT t.id_tarea, t.titulo, t.estado, t.prioridad,
                   t.fecha_asignacion, t.fecha_inicio, t.fecha_finalizacion,
                   t.fecha_vencimiento, t.tiempo_estimado, t.tiempo_real,
                   $col_tipo       AS tipo,
                   $col_etapa      AS etapa_pedido,
                   pm.numero_pedido,
                   TIMESTAMPDIFF(MINUTE, t.fecha_asignacion, t.fecha_inicio) AS min_reaccion,
                   CASE
                       WHEN t.fecha_vencimiento IS NULL THEN 'sin_fecha'
                       WHEN t.estado = 'finalizada' AND t.fecha_finalizacion <= t.fecha_vencimiento THEN 'a_tiempo'
                       WHEN t.estado = 'finalizada' AND t.fecha_finalizacion >  t.fecha_vencimiento THEN 'con_retraso'
                       WHEN t.estado != 'finalizada' AND t.fecha_vencimiento < CURDATE() THEN 'vencida'
                       ELSE 'en_plazo'
                   END AS estado_plazo
            FROM tareas t
            " . ($has_tipo ? "LEFT JOIN pedidos_materiales pm ON pm.id_pedido = t.id_pedido" : "") . "
            WHERE t.id_empleado = ?
              AND t.fecha_asignacion BETWEEN ? AND ?
            ORDER BY t.fecha_asignacion DESC
        ");
        $stmt_dt->execute([$id_usuario, $fi_dt, $ff_dt]);
        $detalle_tareas = $stmt_dt->fetchAll(PDO::FETCH_ASSOC);

        // Pedidos donde participó (cualquier etapa)
        $stmt_dp = $conn->prepare("
            SELECT DISTINCT p.id_pedido, p.numero_pedido, p.estado,
                   p.fecha_pedido, p.fecha_aprobacion, p.fecha_retiro, p.fecha_recibido,
                   o.nombre_obra,
                   CASE
                       WHEN p.id_aprobado_por = ?  THEN 'aprobacion'
                       WHEN p.id_retirado_por  = ?  THEN 'retiro'
                       WHEN p.id_recibido_por  = ?  THEN 'recibido'
                       WHEN p.id_solicitante   = ?  THEN 'creacion'
                       ELSE 'otro'
                   END AS rol_en_pedido
            FROM pedidos_materiales p
            JOIN obras o ON o.id_obra = p.id_obra
            WHERE (p.id_aprobado_por = ? OR p.id_retirado_por = ?
                   OR p.id_recibido_por = ? OR p.id_solicitante = ?)
              AND DATE(p.fecha_pedido) BETWEEN ? AND ?
                        ORDER BY p.id_pedido DESC
        ");
        $stmt_dp->execute([
            $id_usuario, $id_usuario, $id_usuario, $id_usuario,
            $id_usuario, $id_usuario, $id_usuario, $id_usuario,
            $fecha_inicio, $fecha_fin,
        ]);
        $detalle_pedidos = $stmt_dp->fetchAll(PDO::FETCH_ASSOC);

        // ── Desglose por obra: participación del usuario agrupada por obra ──
        // Muestra cuántos pedidos procesó en cada etapa por obra y el tiempo promedio
        $stmt_dob = $conn->prepare("
            SELECT
                o.id_obra,
                o.nombre_obra,
                SUM(sub.etapa = 'aprobacion')                                              AS cant_aprobacion,
                ROUND(AVG(CASE WHEN sub.etapa = 'aprobacion' THEN sub.horas END), 1)       AS hrs_aprobacion,
                SUM(sub.etapa = 'retiro')                                                  AS cant_retiro,
                ROUND(AVG(CASE WHEN sub.etapa = 'retiro'     THEN sub.horas END), 1)       AS hrs_retiro,
                SUM(sub.etapa = 'recibido')                                                AS cant_recibido,
                ROUND(AVG(CASE WHEN sub.etapa = 'recibido'   THEN sub.horas END), 1)       AS hrs_recibido,
                COUNT(DISTINCT sub.id_pedido)                                              AS total_pedidos
            FROM (
                SELECT p.id_obra, p.id_pedido, 'aprobacion' AS etapa,
                       TIMESTAMPDIFF(MINUTE, p.fecha_pedido, p.fecha_aprobacion) / 60.0 AS horas
                FROM pedidos_materiales p
                WHERE p.id_aprobado_por = ?
                  AND p.fecha_aprobacion IS NOT NULL
                  AND DATE(p.fecha_pedido) BETWEEN ? AND ?

                UNION ALL

                SELECT p.id_obra, p.id_pedido, 'retiro',
                       TIMESTAMPDIFF(MINUTE, p.fecha_aprobacion, p.fecha_retiro) / 60.0
                FROM pedidos_materiales p
                WHERE p.id_retirado_por = ?
                  AND p.fecha_retiro IS NOT NULL AND p.fecha_aprobacion IS NOT NULL
                  AND DATE(p.fecha_pedido) BETWEEN ? AND ?

                UNION ALL

                SELECT p.id_obra, p.id_pedido, 'recibido',
                       TIMESTAMPDIFF(MINUTE, p.fecha_retiro, p.fecha_recibido) / 60.0
                FROM pedidos_materiales p
                WHERE p.id_recibido_por = ?
                  AND p.fecha_recibido IS NOT NULL AND p.fecha_retiro IS NOT NULL
                  AND DATE(p.fecha_pedido) BETWEEN ? AND ?
            ) sub
            JOIN obras o ON o.id_obra = sub.id_obra
            GROUP BY o.id_obra, o.nombre_obra
            ORDER BY total_pedidos DESC
        ");
        $stmt_dob->execute([
            $id_usuario, $fecha_inicio, $fecha_fin,
            $id_usuario, $fecha_inicio, $fecha_fin,
            $id_usuario, $fecha_inicio, $fecha_fin,
        ]);
        $desglose_por_obra = $stmt_dob->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error_db = $e->getMessage();
    error_log("metricas_usuarios error: " . $e->getMessage());
    $datos = []; $lista_usuarios = [];
    $total_usuarios_eval = $total_finalizadas = $total_tareas_periodo = 0;
    $promedio_cumplimiento = $promedio_eficiencia = 0;
    $detalle_tareas = $detalle_pedidos = [];
    $desglose_por_obra = [];
    $lista_obras = [];
    $nombre_obra_filtrada = '';
}

// ── Helpers de presentación ──────────────────────────────────────────────────
function rol_badge(string $rol): string {
    $map = [
        'administrador'    => ['bg-danger',  'Admin'],
        'responsable_obra' => ['bg-warning text-dark', 'Responsable'],
        'empleado'         => ['bg-secondary','Empleado'],
    ];
    [$cls, $label] = $map[$rol] ?? ['bg-light text-dark', $rol];
    return "<span class='badge $cls'>$label</span>";
}
function fmt_hrs(?float $h): string {
    if ($h === null || $h < 0) return '<span class="text-muted">—</span>';
    if ($h < 1) return round($h * 60) . ' min';
    return number_format($h, 1) . ' h';
}
function cumplimiento_bar(int $a_tiempo, int $finalizadas): string {
    if ($finalizadas === 0) return '<span class="text-muted">—</span>';
    $pct = round(($a_tiempo / $finalizadas) * 100);
    $cls = $pct >= 80 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger');
    return "<div class='d-flex align-items-center gap-1'>
                <div class='progress flex-grow-1' style='height:8px;'>
                    <div class='progress-bar $cls' style='width:{$pct}%'></div>
                </div>
                <small class='text-nowrap'>{$pct}%</small>
            </div>";
}
function eficiencia_badge(?float $ratio): string {
    if ($ratio === null) return '<span class="text-muted">—</span>';
    // ratio < 100 = terminó más rápido de lo estimado (eficiente)
    // ratio > 100 = tardó más de lo estimado
    $cls = $ratio <= 90 ? 'bg-success' : ($ratio <= 110 ? 'bg-info' : ($ratio <= 130 ? 'bg-warning' : 'bg-danger'));
    return "<span class='badge $cls'>" . number_format($ratio, 1) . "%</span>";
}

include '../../includes/header.php';
?>

<!-- ── Cabecera ──────────────────────────────────────────────────────────── -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">
        <i class="bi bi-person-lines-fill"></i> Métricas de Eficiencia por Usuario
    </h1>
    <a href="index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Reportes
    </a>
</div>

<?php if (isset($error_db)): ?>
<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($error_db); ?></div>
<?php endif; ?>

<!-- ── Filtros ───────────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label fw-semibold">Fecha inicio</label>
                <input type="date" name="fecha_inicio" class="form-control"
                       value="<?php echo $fecha_inicio; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Fecha fin</label>
                <input type="date" name="fecha_fin" class="form-control"
                       value="<?php echo $fecha_fin; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Obra (opcional)</label>
                <select name="id_obra" class="form-select">
                    <option value="">— Todas —</option>
                    <?php foreach ($lista_obras as $ob): ?>
                    <option value="<?php echo $ob['id_obra']; ?>"
                        <?php echo $id_obra === (int)$ob['id_obra'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($ob['nombre_obra']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold">Usuario (opcional)</label>
                <select name="id_usuario" class="form-select">
                    <option value="">— Todos —</option>
                    <?php foreach ($lista_usuarios as $u): ?>
                    <option value="<?php echo $u['id_usuario']; ?>"
                        <?php echo $id_usuario === (int)$u['id_usuario'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Aplicar
                </button>
            </div>
            <?php
            $qs_export = http_build_query([
                'fecha_inicio' => $fecha_inicio,
                'fecha_fin'    => $fecha_fin,
                'id_obra'      => $id_obra,
                'id_usuario'   => $id_usuario,
            ]);
            ?>
            <div class="col-md-3 text-end">
                <a href="exportar_metricas_usuarios_excel.php?<?php echo $qs_export; ?>"
                   class="btn btn-success me-2">
                    <i class="bi bi-file-earmark-excel"></i> Excel
                </a>
                <a href="exportar_metricas_usuarios_pdf.php?<?php echo $qs_export; ?>"
                   class="btn btn-danger" target="_blank">
                    <i class="bi bi-file-earmark-pdf"></i> PDF
                </a>
            </div>
        </form>
        <?php if ($id_obra && $nombre_obra_filtrada): ?>
        <div class="mt-2">
            <span class="badge bg-primary fs-6">
                <i class="bi bi-building"></i> Filtrando por obra: <?php echo htmlspecialchars($nombre_obra_filtrada); ?>
            </span>
            <a href="?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&id_usuario=<?php echo $id_usuario; ?>"
               class="btn btn-sm btn-outline-secondary ms-2">
                <i class="bi bi-x"></i> Quitar filtro de obra
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── KPI Cards ─────────────────────────────────────────────────────────── -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                    <i class="bi bi-people-fill text-primary fs-4"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $total_usuarios_eval; ?></div>
                    <small class="text-muted">Usuarios evaluados</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-10 p-3">
                    <i class="bi bi-check2-circle text-success fs-4"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo number_format($total_finalizadas); ?></div>
                    <small class="text-muted">Tareas completadas</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-10 p-3">
                    <i class="bi bi-calendar-check text-info fs-4"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $promedio_cumplimiento; ?>%</div>
                    <small class="text-muted">Cumplimiento promedio</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                    <i class="bi bi-stopwatch text-warning fs-4"></i>
                </div>
                <div>
                    <div class="fs-3 fw-bold"><?php echo $promedio_eficiencia > 0 ? $promedio_eficiencia . '%' : '—'; ?></div>
                    <small class="text-muted">Eficiencia tiempo prom.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (empty($datos)): ?>
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-person-x text-muted" style="font-size:3rem;"></i>
        <h5 class="mt-3 text-muted">Sin datos para el período seleccionado</h5>
        <p class="text-muted">Ajuste el rango de fechas o verifique que existan tareas registradas.</p>
    </div>
</div>
<?php else: ?>

<!-- ── Gráfico ───────────────────────────────────────────────────────────── -->
<?php if (!$id_usuario && count($datos) > 0): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-bar-chart-fill"></i> Comparativo de desempeño</h5>
    </div>
    <div class="card-body">
        <canvas id="graficoUsuarios" height="90"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- ── Tabla ranking ─────────────────────────────────────────────────────── -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-trophy-fill text-warning"></i> Ranking de usuarios</h5>
        <small class="text-muted">Período: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> — <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Usuario</th>
                        <th class="text-center">Tareas<br><small class="text-muted fw-normal">Total / Fin.</small></th>
                        <th class="text-center">Activas</th>
                        <th style="min-width:160px;">Cumplimiento</th>
                        <th class="text-center">Eficiencia<br><small class="text-muted fw-normal">real/estim.</small></th>
                        <th class="text-center">T. Reacción<br><small class="text-muted fw-normal">asig.→inicio</small></th>
                        <th class="text-center">Aprobación<br><small class="text-muted fw-normal">cant. / prom.</small></th>
                        <th class="text-center">Retiro<br><small class="text-muted fw-normal">cant. / prom.</small></th>
                        <th class="text-center">Recepción<br><small class="text-muted fw-normal">cant. / prom.</small></th>
                        <th class="text-center">Préstamos<br><small class="text-muted fw-normal">Total / Venc.</small></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php $rank = 1; foreach ($datos as $d): ?>
                    <tr class="<?php echo ($id_usuario && $d['id_usuario'] == $id_usuario) ? 'table-primary' : ''; ?>">
                        <td class="ps-3">
                            <?php if ($rank === 1): ?><i class="bi bi-trophy-fill text-warning"></i>
                            <?php elseif ($rank === 2): ?><i class="bi bi-trophy-fill text-secondary"></i>
                            <?php elseif ($rank === 3): ?><i class="bi bi-trophy-fill" style="color:#cd7f32;"></i>
                            <?php else: ?><span class="text-muted"><?php echo $rank; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($d['nombre'] . ' ' . $d['apellido']); ?></div>
                            <?php echo rol_badge($d['rol']); ?>
                        </td>
                        <td class="text-center">
                            <span class="fw-bold"><?php echo $d['total_tareas']; ?></span>
                            <span class="text-muted"> / </span>
                            <span class="text-success fw-bold"><?php echo $d['finalizadas']; ?></span>
                        </td>
                        <td class="text-center">
                            <?php echo $d['activas'] > 0
                                ? "<span class='badge bg-info'>{$d['activas']}</span>"
                                : "<span class='text-muted'>—</span>"; ?>
                        </td>
                        <td><?php echo cumplimiento_bar((int)$d['a_tiempo'], (int)$d['finalizadas']); ?></td>
                        <td class="text-center"><?php echo eficiencia_badge($d['ratio_eficiencia']); ?></td>
                        <td class="text-center"><?php echo fmt_hrs($d['promedio_reaccion_hrs']); ?></td>
                        <?php
                        $etapas_label = ['aprobacion' => null, 'retiro' => null, 'recibido' => null];
                        foreach ($etapas_label as $etapa => $_):
                            $e = $d['etapas'][$etapa] ?? null;
                        ?>
                        <td class="text-center">
                            <?php if ($e): ?>
                                <span class="badge bg-primary"><?php echo $e['cantidad']; ?></span>
                                <br><small class="text-muted"><?php echo fmt_hrs((float)$e['promedio_hrs']); ?></small>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php endforeach; ?>
                        <td class="text-center">
                            <?php $pr = $d['prestamos'] ?? []; ?>
                            <?php if (!empty($pr)): ?>
                                <?php echo $pr['total_prestamos']; ?>
                                <?php if ($pr['vencidos'] > 0): ?>
                                    / <span class="text-danger fw-bold"><?php echo $pr['vencidos']; ?> venc.</span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&id_usuario=<?php echo $d['id_usuario']; ?>"
                               class="btn btn-sm btn-outline-primary" title="Ver detalle">
                                <i class="bi bi-eye"></i>
                            </a>
                        </td>
                    </tr>
                <?php $rank++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Desglose por obra -->
<?php if (!empty($desglose_por_obra)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bi bi-building-fill text-primary"></i> Desempeño por obra
        </h5>
        <small class="text-muted">Tiempos promedio que tardó en cada etapa según la obra</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Obra</th>
                        <th class="text-center">Pedidos</th>
                        <th class="text-center" colspan="2">
                            <span class="badge bg-info text-dark">Aprobación</span>
                        </th>
                        <th class="text-center" colspan="2">
                            <span class="badge bg-primary">Retiro</span>
                        </th>
                        <th class="text-center" colspan="2">
                            <span class="badge bg-success">Recepción</span>
                        </th>
                    </tr>
                    <tr class="table-secondary" style="font-size:0.78rem;">
                        <th class="ps-3"></th>
                        <th></th>
                        <th class="text-center text-muted">Cant.</th>
                        <th class="text-center text-muted">Prom. hrs</th>
                        <th class="text-center text-muted">Cant.</th>
                        <th class="text-center text-muted">Prom. hrs</th>
                        <th class="text-center text-muted">Cant.</th>
                        <th class="text-center text-muted">Prom. hrs</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($desglose_por_obra as $ob):
                    // Referencia global de la etapa para detectar si es lento o rápido
                    $ref_ap = $datos[$id_usuario]['etapas']['aprobacion']['promedio_hrs'] ?? null;
                    $ref_re = $datos[$id_usuario]['etapas']['retiro']['promedio_hrs']     ?? null;
                    $ref_rc = $datos[$id_usuario]['etapas']['recibido']['promedio_hrs']   ?? null;

                    // Función inline para colorear según si está por encima/debajo del promedio global
                    $cls_hrs = function(?float $val, ?float $ref): string {
                        if ($val === null || $ref === null || $ref == 0) return '';
                        return $val <= $ref * 0.9 ? 'text-success fw-bold'
                             : ($val >= $ref * 1.2 ? 'text-danger fw-bold' : '');
                    };
                ?>
                <tr>
                    <td class="ps-3 fw-semibold"><?php echo htmlspecialchars($ob['nombre_obra']); ?></td>
                    <td class="text-center">
                        <span class="badge bg-secondary"><?php echo $ob['total_pedidos']; ?></span>
                    </td>
                    <!-- Aprobación -->
                    <td class="text-center">
                        <?php echo $ob['cant_aprobacion'] > 0
                            ? "<span class='badge bg-info text-dark'>{$ob['cant_aprobacion']}</span>"
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td class="text-center <?php echo $cls_hrs($ob['hrs_aprobacion'], $ref_ap); ?>">
                        <?php echo $ob['hrs_aprobacion'] !== null && $ob['cant_aprobacion'] > 0
                            ? fmt_hrs((float)$ob['hrs_aprobacion'])
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <!-- Retiro -->
                    <td class="text-center">
                        <?php echo $ob['cant_retiro'] > 0
                            ? "<span class='badge bg-primary'>{$ob['cant_retiro']}</span>"
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td class="text-center <?php echo $cls_hrs($ob['hrs_retiro'], $ref_re); ?>">
                        <?php echo $ob['hrs_retiro'] !== null && $ob['cant_retiro'] > 0
                            ? fmt_hrs((float)$ob['hrs_retiro'])
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <!-- Recepción -->
                    <td class="text-center">
                        <?php echo $ob['cant_recibido'] > 0
                            ? "<span class='badge bg-success'>{$ob['cant_recibido']}</span>"
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td class="text-center <?php echo $cls_hrs($ob['hrs_recibido'], $ref_rc); ?>">
                        <?php echo $ob['hrs_recibido'] !== null && $ob['cant_recibido'] > 0
                            ? fmt_hrs((float)$ob['hrs_recibido'])
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="p-2 border-top">
            <small class="text-muted">
                <span class="text-success fw-bold">Verde</span> = más rápido que su promedio global &nbsp;|&nbsp;
                <span class="text-danger fw-bold">Rojo</span> = más lento que su promedio global (&gt;20%)
            </small>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Detalle individual ────────────────────────────────────────────────── -->
<?php if ($id_usuario && isset($datos[$id_usuario])): ?>
<?php $d = $datos[$id_usuario]; ?>
<div class="row mb-4">
    <!-- Resumen del usuario -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-person-circle"></i> Perfil de eficiencia</h5>
            </div>
            <div class="card-body">
                <h4 class="mb-0"><?php echo htmlspecialchars($d['nombre'] . ' ' . $d['apellido']); ?></h4>
                <div class="mb-3"><?php echo rol_badge($d['rol']); ?></div>
                <table class="table table-sm">
                    <tr><th class="text-muted fw-normal">Total tareas</th><td class="text-end fw-bold"><?php echo $d['total_tareas']; ?></td></tr>
                    <tr><th class="text-muted fw-normal">Finalizadas</th><td class="text-end text-success fw-bold"><?php echo $d['finalizadas']; ?></td></tr>
                    <tr><th class="text-muted fw-normal">Activas</th><td class="text-end text-info fw-bold"><?php echo $d['activas']; ?></td></tr>
                    <tr><th class="text-muted fw-normal">Canceladas</th><td class="text-end text-muted"><?php echo $d['canceladas']; ?></td></tr>
                    <tr><th class="text-muted fw-normal">A tiempo</th><td class="text-end"><?php echo $d['a_tiempo']; ?> / <?php echo $d['finalizadas']; ?></td></tr>
                    <tr><th class="text-muted fw-normal">Con retraso</th><td class="text-end text-danger"><?php echo $d['con_retraso']; ?></td></tr>
                    <tr><th class="text-muted fw-normal">Eficiencia tiempo</th><td class="text-end"><?php echo eficiencia_badge($d['ratio_eficiencia']); ?></td></tr>
                    <tr><th class="text-muted fw-normal">Prom. hrs / tarea</th><td class="text-end"><?php echo fmt_hrs($d['promedio_hrs_real']); ?></td></tr>
                    <tr><th class="text-muted fw-normal">Total hrs acumuladas</th><td class="text-end fw-bold"><?php echo fmt_hrs((float)$d['total_hrs']); ?></td></tr>
                    <tr><th class="text-muted fw-normal">Tiempo de reacción prom.</th><td class="text-end"><?php echo fmt_hrs($d['promedio_reaccion_hrs']); ?></td></tr>
                </table>
                <?php foreach (['aprobacion' => 'Aprobaciones', 'retiro' => 'Retiros', 'recibido' => 'Recepciones'] as $et => $label): ?>
                <?php if (isset($d['etapas'][$et])): $e = $d['etapas'][$et]; ?>
                <div class="alert alert-info py-2 mb-2">
                    <strong><?php echo $label; ?>:</strong> <?php echo $e['cantidad']; ?> pedidos
                    — prom. <?php echo fmt_hrs((float)$e['promedio_hrs']); ?> por etapa
                </div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Gráfico donuts cumplimiento -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header"><h5 class="mb-0"><i class="bi bi-pie-chart-fill"></i> Distribución de tareas</h5></div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="graficoPie" style="max-height:280px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de tareas del usuario -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-list-task"></i> Tareas en el período</h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($detalle_tareas)): ?>
        <p class="text-muted p-3 mb-0">Sin tareas en el período.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Tarea</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Prioridad</th>
                        <th class="text-center">Plazo</th>
                        <th class="text-center">Estim.</th>
                        <th class="text-center">Real</th>
                        <th class="text-center">Reacción</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detalle_tareas as $t):
                    $plazo_cls = match($t['estado_plazo']) {
                        'a_tiempo'   => 'text-success',
                        'con_retraso'=> 'text-danger',
                        'vencida'    => 'text-danger fw-bold',
                        default      => 'text-muted',
                    };
                    $plazo_icon = match($t['estado_plazo']) {
                        'a_tiempo'    => 'bi-check-circle-fill text-success',
                        'con_retraso' => 'bi-exclamation-triangle-fill text-danger',
                        'vencida'     => 'bi-x-circle-fill text-danger',
                        default       => 'bi-dash text-muted',
                    };
                    $estado_cls = match($t['estado']) {
                        'finalizada' => 'bg-success',
                        'en_proceso' => 'bg-info',
                        'cancelada'  => 'bg-secondary',
                        default      => 'bg-warning text-dark',
                    };
                ?>
                <tr>
                    <td class="ps-3">
                        <div><?php echo htmlspecialchars($t['titulo']); ?></div>
                        <?php if ($t['tipo'] === 'pedido' && $t['numero_pedido']): ?>
                        <small class="text-muted"><i class="bi bi-box-seam"></i> <?php echo $t['numero_pedido']; ?>
                            — <?php echo ucfirst($t['etapa_pedido'] ?? ''); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($t['tipo'] ?? 'manual') === 'pedido'): ?>
                            <span class="badge bg-primary"><i class="bi bi-box-seam"></i> Pedido</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-pencil-square"></i> Manual</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $estado_cls; ?>"><?php echo ucfirst(str_replace('_',' ',$t['estado'])); ?></span></td>
                    <td><?php echo ucfirst($t['prioridad']); ?></td>
                    <td class="text-center">
                        <i class="bi <?php echo $plazo_icon; ?>"></i>
                    </td>
                    <td class="text-center"><?php echo fmt_hrs($t['tiempo_estimado'] ? (float)$t['tiempo_estimado'] : null); ?></td>
                    <td class="text-center"><?php echo fmt_hrs($t['tiempo_real'] ? (float)$t['tiempo_real'] : null); ?></td>
                    <td class="text-center">
                        <?php echo $t['min_reaccion'] !== null
                            ? fmt_hrs(round($t['min_reaccion'] / 60, 1))
                            : '<span class="text-muted">—</span>'; ?>
                    </td>
                    <td>
                        <a href="<?php echo SITE_URL; ?>/modules/tareas/view.php?id=<?php echo $t['id_tarea']; ?>"
                           class="btn btn-sm btn-outline-info" title="Ver tarea">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Tabla de pedidos del usuario -->
<?php if (!empty($detalle_pedidos)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-cart-fill"></i> Participación en pedidos</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID Pedido</th>
                        <th>Obra</th>
                        <th>Rol en pedido</th>
                        <th>Estado</th>
                        <th class="text-center">Tiempo en etapa</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($detalle_pedidos as $p):
                    // Calcular tiempo en la etapa específica de este usuario
                    $tiempo_etapa = null;
                    switch ($p['rol_en_pedido']) {
                        case 'aprobacion':
                            if ($p['fecha_aprobacion'] && $p['fecha_pedido'])
                                $tiempo_etapa = round(
                                    (strtotime($p['fecha_aprobacion']) - strtotime($p['fecha_pedido'])) / 3600, 1);
                            break;
                        case 'retiro':
                            if ($p['fecha_retiro'] && $p['fecha_aprobacion'])
                                $tiempo_etapa = round(
                                    (strtotime($p['fecha_retiro']) - strtotime($p['fecha_aprobacion'])) / 3600, 1);
                            break;
                        case 'recibido':
                            if ($p['fecha_recibido'] && $p['fecha_retiro'])
                                $tiempo_etapa = round(
                                    (strtotime($p['fecha_recibido']) - strtotime($p['fecha_retiro'])) / 3600, 1);
                            break;
                    }
                    $rol_labels = ['creacion' => 'Creador', 'aprobacion' => 'Aprobador',
                                   'retiro' => 'Retiró', 'recibido' => 'Recibió'];
                    $estado_cls_p = match($p['estado']) {
                        'recibido' => 'bg-success', 'cancelado' => 'bg-danger',
                        'retirado' => 'bg-primary', 'aprobado'  => 'bg-info',
                        default    => 'bg-warning text-dark',
                    };
                ?>
                <tr>
                    <td class="ps-3"><strong>#<?php echo (int)$p['id_pedido']; ?></strong></td>
                    <td><?php echo htmlspecialchars($p['nombre_obra']); ?></td>
                    <td><span class="badge bg-secondary"><?php echo $rol_labels[$p['rol_en_pedido']] ?? $p['rol_en_pedido']; ?></span></td>
                    <td><span class="badge <?php echo $estado_cls_p; ?>"><?php echo ucfirst($p['estado']); ?></span></td>
                    <td class="text-center"><?php echo fmt_hrs($tiempo_etapa); ?></td>
                    <td>
                        <a href="<?php echo SITE_URL; ?>/modules/pedidos/view.php?id=<?php echo $p['id_pedido']; ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php elseif ($id_usuario): ?>
<div class="alert alert-warning">No se encontraron datos para este usuario en el período seleccionado.</div>
<?php endif; ?>

<?php endif; // empty($datos) ?>

<!-- ── Charts ────────────────────────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
<?php if (!$id_usuario && count($datos) > 1): ?>
// Gráfico de barras comparativo
(function() {
    const labels = <?php echo json_encode(array_map(fn($d) => $d['nombre'] . ' ' . substr($d['apellido'],0,1) . '.', array_values($datos))); ?>;
    const finalizadas = <?php echo json_encode(array_column(array_values($datos), 'finalizadas')); ?>;
    const activas = <?php echo json_encode(array_column(array_values($datos), 'activas')); ?>;
    const conRetraso = <?php echo json_encode(array_column(array_values($datos), 'con_retraso')); ?>;
    new Chart(document.getElementById('graficoUsuarios'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Finalizadas', data: finalizadas, backgroundColor: '#198754', borderRadius: 4 },
                { label: 'Activas',     data: activas,     backgroundColor: '#0dcaf0', borderRadius: 4 },
                { label: 'Con retraso', data: conRetraso,  backgroundColor: '#dc3545', borderRadius: 4 },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: { x: { stacked: false }, y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
})();
<?php endif; ?>

<?php if ($id_usuario && isset($datos[$id_usuario])): ?>
// Gráfico de torta distribución tareas
(function() {
    const d = <?php echo json_encode([
        'finalizadas' => (int)$datos[$id_usuario]['finalizadas'],
        'activas'     => (int)$datos[$id_usuario]['activas'],
        'canceladas'  => (int)$datos[$id_usuario]['canceladas'],
        'con_retraso' => (int)$datos[$id_usuario]['con_retraso'],
    ]); ?>;
    new Chart(document.getElementById('graficoPie'), {
        type: 'doughnut',
        data: {
            labels: ['Finalizadas a tiempo', 'Activas', 'Canceladas', 'Con retraso'],
            datasets: [{
                data: [
                    d.finalizadas - d.con_retraso,
                    d.activas, d.canceladas, d.con_retraso
                ],
                backgroundColor: ['#198754','#0dcaf0','#6c757d','#dc3545'],
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'right' } },
            cutout: '65%',
        }
    });
})();
<?php endif; ?>
</script>

<?php include '../../includes/footer.php'; ?>
