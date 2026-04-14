<?php
/**
 * PedidoTareasHelper
 *
 * Centraliza toda la lógica de creación, activación, cierre y cancelación
 * de tareas automáticas vinculadas a las etapas de un pedido.
 *
 * Flujo (pre-creación de etapas):
 *   - Al CREAR el pedido → se pre-crean las 4 tareas operativas:
 *       · aprobacion  → habilitada=1, fecha_asignacion=ahora  (activa de inmediato)
 *       · picking     → habilitada=0, fecha_asignacion=NULL   (pendiente)
 *       · retiro      → habilitada=0, fecha_asignacion=NULL   (pendiente)
 *       · recibido    → habilitada=0, fecha_asignacion=NULL   (pendiente)
 *   - Al CERRAR una etapa → la tarea se actualiza a 'finalizada' y se ACTIVA
 *       la tarea pre-creada de la siguiente etapa (habilitada=0 → 1).
 *   - Compatibilidad con pedidos existentes: si no existe tarea pre-creada para
 *       la siguiente etapa, se crea una nueva (flujo legacy).
 *   - Al CANCELAR el pedido → todas las tareas pendientes/en_proceso (habilitadas
 *       o no) pasan a 'cancelada'.
 *
 * Columnas requeridas (ver scripts/migrate_habilitada_tareas.sql):
 *   tareas.habilitada         TINYINT(1) NOT NULL DEFAULT 1
 *   tareas.fecha_asignacion   DATETIME NULL DEFAULT NULL
 */
class PedidoTareasHelper
{
    // ----------------------------------------------------------------
    // Títulos y descripciones por etapa
    // ----------------------------------------------------------------

    private static array $titulos = [
        'creacion'   => 'Crear pedido',
        'aprobacion' => 'Aprobar pedido',
        'picking'    => 'Preparar materiales pedido (picking)',
        'retiro'     => 'Retirar materiales pedido',
        'recibido'   => 'Confirmar recepción pedido',
    ];

    private static array $descripciones = [
        'creacion'   => 'Pedido de materiales {numero} creado para la obra: {obra}.',
        'aprobacion' => 'Revisar y aprobar el pedido de materiales {numero} de la obra: {obra}.',
        'picking'    => 'Preparar y reunir los materiales del pedido {numero} en el depósito para la obra: {obra}.',
        'retiro'     => 'Retirar del depósito los materiales del pedido {numero} para la obra: {obra}.',
        'recibido'   => 'Confirmar la recepción de los materiales del pedido {numero} en la obra: {obra}.',
    ];

    // ----------------------------------------------------------------
    // API pública
    // ----------------------------------------------------------------

    /**
     * Pre-crea las 4 tareas operativas del pedido al momento de su creación.
     *
     * - aprobacion : habilitada=1 (activa desde el primer momento)
     * - picking    : habilitada=0 (se activará al aprobar el pedido)
     * - retiro     : habilitada=0 (se activará al marcar picking)
     * - recibido   : habilitada=0 (se activará al marcar retiro)
     *
     * @param PDO         $conn
     * @param int         $id_pedido
     * @param string      $nombre_obra
     * @param int         $id_solicitante         Usuario que creó el pedido
     * @param string      $prioridad              baja|media|alta|urgente
     * @param string      $fecha_pedido           Y-m-d H:i:s
     * @param string|null $fecha_necesaria         Fecha límite del pedido
     * @param int|null    $id_resp_aprobacion      Responsable de aprobación
     * @param int|null    $id_resp_picking         Responsable de picking
     * @param int|null    $id_resp_retiro          Responsable de retiro
     *                                             (recibido siempre va al solicitante)
     */
    public static function onPedidoCreado(
        PDO     $conn,
        int     $id_pedido,
        string  $nombre_obra,
        int     $id_solicitante,
        string  $prioridad,
        string  $fecha_pedido,
        ?string $fecha_necesaria,
        ?int    $id_resp_aprobacion = null,
        ?int    $id_resp_picking    = null,
        ?int    $id_resp_retiro     = null
    ): void {
        $asignado_aprobacion = $id_resp_aprobacion ?? $id_solicitante;
        $asignado_picking    = $id_resp_picking    ?? $id_solicitante;
        $asignado_retiro     = $id_resp_retiro     ?? $id_solicitante;
        $asignado_recibido   = $id_solicitante; // siempre el solicitante

        $base = [
            'id_pedido'          => $id_pedido,
            'nombre_obra'        => $nombre_obra,
            'prioridad'          => $prioridad,
            'id_asignador'       => $id_solicitante,
            'estado'             => 'pendiente',
            'fecha_inicio'       => null,
            'fecha_finalizacion' => null,
            'fecha_vencimiento'  => $fecha_necesaria,
        ];

        // 1. Aprobación — habilitada desde ya
        self::insertarTarea($conn, array_merge($base, [
            'etapa'            => 'aprobacion',
            'id_empleado'      => $asignado_aprobacion,
            'fecha_asignacion' => $fecha_pedido,
            'habilitada'       => 1,
        ]));

        // 2. Picking — pre-creada, no habilitada
        self::insertarTarea($conn, array_merge($base, [
            'etapa'            => 'picking',
            'id_empleado'      => $asignado_picking,
            'fecha_asignacion' => null,
            'habilitada'       => 0,
        ]));

        // 3. Retiro — pre-creada, no habilitada
        self::insertarTarea($conn, array_merge($base, [
            'etapa'            => 'retiro',
            'id_empleado'      => $asignado_retiro,
            'fecha_asignacion' => null,
            'habilitada'       => 0,
        ]));

        // 4. Recepción en obra — pre-creada, no habilitada
        self::insertarTarea($conn, array_merge($base, [
            'etapa'            => 'recibido',
            'id_empleado'      => $asignado_recibido,
            'fecha_asignacion' => null,
            'habilitada'       => 0,
        ]));
    }

    /**
     * Cierra la tarea de aprobación y activa la de picking.
     * Llamar cuando el pedido pasa a estado 'aprobado'.
     */
    public static function onPedidoAprobado(
        PDO     $conn,
        int     $id_pedido,
        string  $nombre_obra,
        int     $id_aprobador,
        string  $prioridad,
        string  $fecha_aprobacion,
        ?string $fecha_necesaria
    ): void {
        self::cerrarTareaEtapa($conn, $id_pedido, 'aprobacion', $id_aprobador, $fecha_aprobacion);

        $id_resp_picking = self::obtenerResponsableEtapa($conn, $id_pedido, 'picking') ?? $id_aprobador;

        self::activarOInsertarTareaEtapa($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'picking',
            'id_empleado'        => $id_resp_picking,
            'id_asignador'       => $id_aprobador,
            'nombre_obra'        => $nombre_obra,
            'prioridad'          => $prioridad,
            'estado'             => 'pendiente',
            'fecha_asignacion'   => $fecha_aprobacion,
            'fecha_inicio'       => null,
            'fecha_finalizacion' => null,
            'fecha_vencimiento'  => $fecha_necesaria,
        ]);
    }

    /**
     * Cierra la tarea de picking y activa la de retiro.
     * Llamar cuando el pedido pasa a estado 'picking'.
     */
    public static function onPedidoPicking(
        PDO     $conn,
        int     $id_pedido,
        string  $nombre_obra,
        int     $id_picking_por,
        string  $prioridad,
        string  $fecha_picking,
        ?string $fecha_necesaria
    ): void {
        self::cerrarTareaEtapa($conn, $id_pedido, 'picking', $id_picking_por, $fecha_picking);

        $id_resp_retiro = self::obtenerResponsableEtapa($conn, $id_pedido, 'retiro') ?? $id_picking_por;

        self::activarOInsertarTareaEtapa($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'retiro',
            'id_empleado'        => $id_resp_retiro,
            'id_asignador'       => $id_picking_por,
            'nombre_obra'        => $nombre_obra,
            'prioridad'          => $prioridad,
            'estado'             => 'pendiente',
            'fecha_asignacion'   => $fecha_picking,
            'fecha_inicio'       => null,
            'fecha_finalizacion' => null,
            'fecha_vencimiento'  => $fecha_necesaria,
        ]);
    }

    /**
     * Cierra la tarea de retiro y activa la de recepción.
     * Llamar cuando el pedido pasa a estado 'retirado'.
     */
    public static function onPedidoRetirado(
        PDO     $conn,
        int     $id_pedido,
        string  $nombre_obra,
        int     $id_retirador,
        string  $prioridad,
        string  $fecha_retiro,
        ?string $fecha_necesaria
    ): void {
        self::cerrarTareaEtapa($conn, $id_pedido, 'retiro', $id_retirador, $fecha_retiro);

        // La tarea de recepción siempre se asigna al solicitante original del pedido
        $stmt_sol = $conn->prepare("SELECT id_solicitante FROM pedidos_materiales WHERE id_pedido = ?");
        $stmt_sol->execute([$id_pedido]);
        $id_solicitante = (int) ($stmt_sol->fetchColumn() ?: $id_retirador);

        self::activarOInsertarTareaEtapa($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'recibido',
            'id_empleado'        => $id_solicitante,
            'id_asignador'       => $id_retirador,
            'nombre_obra'        => $nombre_obra,
            'prioridad'          => $prioridad,
            'estado'             => 'pendiente',
            'fecha_asignacion'   => $fecha_retiro,
            'fecha_inicio'       => null,
            'fecha_finalizacion' => null,
            'fecha_vencimiento'  => $fecha_necesaria,
        ]);
    }

    /**
     * Cierra la tarea de recepción (etapa final).
     * Llamar cuando el pedido pasa a estado 'recibido'.
     */
    public static function onPedidoRecibido(PDO $conn, int $id_pedido, int $id_receptor, string $fecha_recibido): void
    {
        self::cerrarTareaEtapa($conn, $id_pedido, 'recibido', $id_receptor, $fecha_recibido);
    }

    /**
     * Cancela todas las tareas pendientes/en_proceso de un pedido,
     * incluidas las pre-creadas con habilitada=0.
     * Llamar cuando el pedido se cancela desde cualquier estado.
     */
    public static function onPedidoCancelado(PDO $conn, int $id_pedido): void
    {
        $stmt = $conn->prepare("
            UPDATE tareas t
            JOIN   pedido_tareas pt ON pt.id_tarea = t.id_tarea
            SET    t.estado             = 'cancelada',
                   t.fecha_finalizacion = NOW(),
                   t.observaciones      = CONCAT(COALESCE(t.observaciones,''), ' [Cancelada: pedido cancelado]')
            WHERE  pt.id_pedido = ?
              AND  t.estado IN ('pendiente','en_proceso')
        ");
        $stmt->execute([$id_pedido]);
    }

    // ----------------------------------------------------------------
    // Flujo inverso: tarea → pedido
    // ----------------------------------------------------------------

    /**
     * Punto de entrada del flujo inverso.
     * Llamar cuando una tarea de tipo 'pedido' es marcada como 'finalizada'.
     *
     * Guarda: si la tarea tiene habilitada=0, el avance es ignorado para
     * impedir que etapas fuera de secuencia avancen el pedido.
     *
     * Efectos (tarea habilitada):
     *  - Avanza el estado del pedido al estado correspondiente.
     *  - Registra el cambio en seguimiento_pedidos.
     *  - Descuenta stock si la etapa es 'retiro'.
     *  - Activa la tarea pre-creada de la siguiente etapa (o la crea si no existe).
     */
    public static function onTareaEtapaFinalizada(PDO $conn, array $tarea): void
    {
        if (($tarea['tipo'] ?? '') !== 'pedido'
            || empty($tarea['etapa_pedido'])
            || empty($tarea['id_pedido'])) {
            return;
        }

        // Bloquear si la tarea no está habilitada (etapa fuera de secuencia)
        if (isset($tarea['habilitada']) && (int)$tarea['habilitada'] === 0) {
            return;
        }

        $id_pedido    = (int) $tarea['id_pedido'];
        $etapa        = $tarea['etapa_pedido'];
        $id_usuario   = (int) $tarea['id_empleado'];
        $fecha_actual = date('Y-m-d H:i:s');

        // Mapa etapa → estado esperado → estado nuevo → columnas del pedido
        $mapa = [
            'aprobacion' => [
                'estado_esperado' => 'pendiente',
                'estado_nuevo'    => 'aprobado',
                'campo_usuario'   => 'id_aprobado_por',
                'campo_fecha'     => 'fecha_aprobacion',
            ],
            'picking' => [
                'estado_esperado' => 'aprobado',
                'estado_nuevo'    => 'picking',
                'campo_usuario'   => 'id_picking_por',
                'campo_fecha'     => 'fecha_picking',
            ],
            'retiro' => [
                'estado_esperado' => 'picking',
                'estado_nuevo'    => 'retirado',
                'campo_usuario'   => 'id_retirado_por',
                'campo_fecha'     => 'fecha_retiro',
            ],
            'recibido' => [
                'estado_esperado' => 'retirado',
                'estado_nuevo'    => 'recibido',
                'campo_usuario'   => 'id_recibido_por',
                'campo_fecha'     => 'fecha_recibido',
            ],
        ];

        if (!isset($mapa[$etapa])) return;
        $cfg = $mapa[$etapa];

        // Obtener pedido y verificar estado esperado
        $stmt = $conn->prepare("
            SELECT p.prioridad, p.fecha_necesaria, p.estado, p.id_solicitante, o.nombre_obra
            FROM pedidos_materiales p
            JOIN obras o ON o.id_obra = p.id_obra
            WHERE p.id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido || $pedido['estado'] !== $cfg['estado_esperado']) {
            return; // Pedido no encontrado o ya avanzado por otra vía
        }

        // Avanzar estado del pedido
        $stmt = $conn->prepare("
            UPDATE pedidos_materiales
            SET    estado                    = ?,
                   {$cfg['campo_usuario']}   = ?,
                   {$cfg['campo_fecha']}     = ?
            WHERE  id_pedido = ?
        ");
        $stmt->execute([$cfg['estado_nuevo'], $id_usuario, $fecha_actual, $id_pedido]);

        // Registrar en seguimiento
        self::registrarSeguimiento(
            $conn, $id_pedido,
            $pedido['estado'], $cfg['estado_nuevo'],
            $id_usuario, 'Avance automático desde finalización de tarea'
        );

        // Descontar stock al retirar
        if ($etapa === 'retiro') {
            self::descontarStock($conn, $id_pedido, $id_usuario, $fecha_actual);
        }

        // Activar la tarea pre-creada de la siguiente etapa (si existe)
        $siguiente = self::etapaSiguiente($etapa);
        if ($siguiente) {
            if ($etapa === 'retiro') {
                $id_resp = (int) ($pedido['id_solicitante'] ?: $id_usuario);
            } else {
                $id_resp = self::obtenerResponsableEtapa($conn, $id_pedido, $siguiente) ?? $id_usuario;
            }
            self::activarOInsertarTareaEtapa($conn, [
                'id_pedido'          => $id_pedido,
                'etapa'              => $siguiente,
                'id_empleado'        => $id_resp,
                'id_asignador'       => $id_usuario,
                'nombre_obra'        => $pedido['nombre_obra'],
                'prioridad'          => $pedido['prioridad'],
                'estado'             => 'pendiente',
                'fecha_asignacion'   => $fecha_actual,
                'fecha_inicio'       => null,
                'fecha_finalizacion' => null,
                'fecha_vencimiento'  => $pedido['fecha_necesaria'],
            ]);
        }
    }

    // ----------------------------------------------------------------
    // Métodos privados internos
    // ----------------------------------------------------------------

    /**
     * Activa una tarea pre-creada (habilitada=0) para la etapa indicada,
     * completando su fecha de asignación, asignador y empleado real.
     *
     * Si no existe tarea pre-creada (pedidos anteriores a esta migración),
     * crea una nueva tarea (compatibilidad retroactiva).
     */
    private static function activarOInsertarTareaEtapa(PDO $conn, array $d): void
    {
        // Buscar tarea pre-creada pendiente de habilitación para esta etapa
        $stmt = $conn->prepare("
            SELECT t.id_tarea
            FROM   pedido_tareas pt
            JOIN   tareas        t ON pt.id_tarea = t.id_tarea
            WHERE  pt.id_pedido = ?
              AND  pt.etapa     = ?
              AND  t.habilitada = 0
            LIMIT 1
        ");
        $stmt->execute([$d['id_pedido'], $d['etapa']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // Activar tarea pre-creada: asignar fecha, responsable real y habilitar
            $stmt_upd = $conn->prepare("
                UPDATE tareas SET
                    habilitada       = 1,
                    fecha_asignacion = ?,
                    id_asignador     = ?,
                    id_empleado      = ?
                WHERE id_tarea = ?
            ");
            $stmt_upd->execute([
                $d['fecha_asignacion'],
                $d['id_asignador'],
                $d['id_empleado'],
                $row['id_tarea'],
            ]);
        } else {
            // Fallback: crear tarea nueva (compatibilidad con pedidos pre-migración)
            self::insertarTarea($conn, array_merge($d, ['habilitada' => 1]));
        }
    }

    /**
     * Obtiene el id de usuario pre-asignado para una etapa del pedido.
     */
    private static function obtenerResponsableEtapa(PDO $conn, int $id_pedido, string $etapa): ?int
    {
        $columna_map = [
            'aprobacion' => 'id_responsable_aprobacion',
            'picking'    => 'id_responsable_picking',
            'retiro'     => 'id_responsable_retiro',
            'recibido'   => 'id_responsable_recibido',
        ];

        $columna = $columna_map[$etapa] ?? null;
        if (!$columna) return null;

        $stmt = $conn->prepare("SELECT {$columna} FROM pedidos_materiales WHERE id_pedido = ?");
        $stmt->execute([$id_pedido]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($row && !empty($row[$columna])) ? (int) $row[$columna] : null;
    }

    /**
     * Inserta una nueva tarea y la vincula en pedido_tareas.
     * Si ya existe una entrada para esa etapa en pedido_tareas, la actualiza
     * (upsert por etapa — previene duplicados).
     *
     * Requiere que la migración migrate_habilitada_tareas.sql haya sido aplicada
     * (columnas: habilitada, fecha_asignacion nullable).
     */
    private static function insertarTarea(PDO $conn, array $d): void
    {
        $titulo      = self::buildTitulo($d['etapa'], $d['id_pedido']);
        $descripcion = self::buildDescripcion($d['etapa'], $d['id_pedido'], $d['nombre_obra']);
        $habilitada  = isset($d['habilitada']) ? (int) $d['habilitada'] : 1;

        $tiempo_real = null;
        if (!empty($d['fecha_inicio']) && !empty($d['fecha_finalizacion'])) {
            $diff        = strtotime($d['fecha_finalizacion']) - strtotime($d['fecha_inicio']);
            $tiempo_real = max(0, (int) round($diff / 3600));
        }

        $stmt = $conn->prepare("
            INSERT INTO tareas
                (id_empleado, titulo, descripcion, prioridad, id_asignador,
                 estado, fecha_asignacion, fecha_inicio, fecha_finalizacion,
                 tiempo_real, fecha_vencimiento, tipo, id_pedido, etapa_pedido, habilitada)
            VALUES (?, ?, ?, ?, ?,  ?, ?, ?, ?,  ?, ?,  'pedido', ?, ?, ?)
        ");
        $stmt->execute([
            $d['id_empleado'],
            $titulo,
            $descripcion,
            $d['prioridad'],
            $d['id_asignador'],
            $d['estado'],
            $d['fecha_asignacion'] ?: null,
            $d['fecha_inicio']       ?: null,
            $d['fecha_finalizacion'] ?: null,
            $tiempo_real,
            $d['fecha_vencimiento']  ?: null,
            $d['id_pedido'],
            $d['etapa'],
            $habilitada,
        ]);
        $id_tarea = (int) $conn->lastInsertId();

        // Vínculo en pedido_tareas (upsert por etapa — previene duplicados)
        $stmt_link = $conn->prepare("
            INSERT INTO pedido_tareas (id_pedido, id_tarea, etapa)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE id_tarea = VALUES(id_tarea)
        ");
        $stmt_link->execute([$d['id_pedido'], $id_tarea, $d['etapa']]);
    }

    /**
     * Cierra la tarea habilitada (activa) de la etapa indicada,
     * asignando el usuario real y calculando el tiempo transcurrido.
     *
     * Solo cierra tareas con habilitada=1 para evitar cerrar
     * tareas pre-creadas que nunca fueron activadas.
     */
    private static function cerrarTareaEtapa(
        PDO    $conn,
        int    $id_pedido,
        string $etapa,
        int    $id_usuario_real,
        string $fecha_fin
    ): void {
        $stmt = $conn->prepare("
            SELECT t.id_tarea, t.fecha_asignacion
            FROM   pedido_tareas pt
            JOIN   tareas        t ON pt.id_tarea = t.id_tarea
            WHERE  pt.id_pedido = ?
              AND  pt.etapa     = ?
              AND  t.estado     IN ('pendiente','en_proceso')
              AND  t.habilitada = 1
            LIMIT 1
        ");
        $stmt->execute([$id_pedido, $etapa]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tarea) return;

        $diff        = strtotime($fecha_fin) - strtotime($tarea['fecha_asignacion'] ?? $fecha_fin);
        $tiempo_real = max(0, (int) round($diff / 3600));

        $stmt_upd = $conn->prepare("
            UPDATE tareas SET
                id_empleado        = ?,
                estado             = 'finalizada',
                fecha_inicio       = COALESCE(fecha_inicio, ?),
                fecha_finalizacion = ?,
                tiempo_real        = ?
            WHERE id_tarea = ?
        ");
        $stmt_upd->execute([
            $id_usuario_real,
            $fecha_fin,
            $fecha_fin,
            $tiempo_real,
            $tarea['id_tarea'],
        ]);
    }

    /** Devuelve la etapa que sigue a la dada en el flujo del pedido. */
    private static function etapaSiguiente(string $etapa): ?string
    {
        $flujo = [
            'aprobacion' => 'picking',
            'picking'    => 'retiro',
            'retiro'     => 'recibido',
            'recibido'   => null,
        ];
        return $flujo[$etapa] ?? null;
    }

    /** Descuenta el stock de materiales cuando el pedido pasa a 'retirado'. */
    private static function descontarStock(PDO $conn, int $id_pedido, int $id_usuario, string $fecha_actual): void
    {
        $stmt = $conn->prepare("
            SELECT id_detalle, id_material,
                   COALESCE(NULLIF(cantidad_entregada, 0), cantidad_solicitada) AS cantidad_a_descontar
            FROM detalle_pedidos_materiales
            WHERE id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt_stock = $conn->prepare("UPDATE materiales SET stock_actual = stock_actual - ? WHERE id_material = ?");
        $stmt_set   = $conn->prepare("UPDATE detalle_pedidos_materiales SET cantidad_retirada = ? WHERE id_detalle = ?");
        $stmt_log   = $conn->prepare("INSERT INTO logs_sistema (id_usuario, accion, modulo, descripcion, fecha_creacion) VALUES (?, 'stock_salida', 'materiales', ?, ?)");

        foreach ($detalles as $d) {
            $cantidad = intval($d['cantidad_a_descontar']);
            if ($cantidad > 0) {
                $stmt_stock->execute([$cantidad, $d['id_material']]);
                $stmt_set->execute([$cantidad, $d['id_detalle']]);
                $stmt_log->execute([
                    $id_usuario,
                    "Retiro pedido #{$id_pedido} - Material ID: {$d['id_material']} - Cantidad: {$cantidad}",
                    $fecha_actual,
                ]);
            }
        }
    }

    /** Inserta un registro en seguimiento_pedidos. */
    private static function registrarSeguimiento(
        PDO    $conn,
        int    $id_pedido,
        string $estado_anterior,
        string $estado_nuevo,
        int    $id_usuario,
        string $observaciones
    ): void {
        $stmt = $conn->prepare("
            INSERT INTO seguimiento_pedidos
                (id_pedido, estado_anterior, estado_nuevo, observaciones, id_usuario_cambio, fecha_cambio)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$id_pedido, $estado_anterior, $estado_nuevo, $observaciones, $id_usuario]);
    }

    private static function buildTitulo(string $etapa, int $id_pedido): string
    {
        $base = self::$titulos[$etapa] ?? $etapa;
        return "$base #" . str_pad($id_pedido, 4, '0', STR_PAD_LEFT);
    }

    private static function buildDescripcion(string $etapa, int $id_pedido, string $nombre_obra): string
    {
        $tpl    = self::$descripciones[$etapa] ?? 'Tarea de pedido {numero}.';
        $numero = '#' . str_pad($id_pedido, 4, '0', STR_PAD_LEFT);
        return str_replace(['{numero}', '{obra}'], [$numero, $nombre_obra], $tpl);
    }
}
