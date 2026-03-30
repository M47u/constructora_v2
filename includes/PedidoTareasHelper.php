<?php
/**
 * PedidoTareasHelper
 *
 * Centraliza toda la lógica de creación, cierre y cancelación
 * de tareas automáticas vinculadas a las etapas de un pedido.
 *
 * Flujo Opción C (híbrido):
 *   - Al ABRIR una etapa  → se crea una tarea en estado 'pendiente'
 *                           asignada al responsable pre-definido al crear el pedido
 *   - Al CERRAR una etapa → la tarea se actualiza a 'finalizada'
 *                           con el usuario real y el tiempo transcurrido
 *   - Al CANCELAR pedido  → tareas pendientes pasan a 'cancelada'
 *
 * Etapas y cuándo ocurren:
 *   creacion   → Al crear el pedido         (cierra inmediatamente como finalizada)
 *   aprobacion → Al crear el pedido         (queda pendiente, asignada al responsable_aprobacion)
 *   picking    → Al aprobar el pedido       (queda pendiente, asignada al responsable_picking)
 *   retiro     → Al marcar como picking     (queda pendiente, asignada al responsable_retiro)
 *   recibido   → Al marcar como retirado    (queda pendiente, asignada al responsable_recibido)
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
     * Crea la tarea de CREACIÓN (finalizada al instante) y
     * la tarea de APROBACIÓN (pendiente) cuando se genera un nuevo pedido.
     *
     * @param PDO    $conn
     * @param int    $id_pedido
     * @param string $numero_pedido          Ej: "PED20260001"
     * @param string $nombre_obra
     * @param int    $id_solicitante         Usuario que creó el pedido
     * @param string $prioridad              baja|media|alta|urgente
     * @param string $fecha_pedido           Y-m-d H:i:s
     * @param string|null $fecha_necesaria   Fecha límite del pedido (para vencimiento)
     * @param int|null $id_resp_aprobacion   Responsable pre-asignado para la etapa de aprobación
     * @param int|null $id_resp_picking      Responsable pre-asignado para la etapa de picking
     * @param int|null $id_resp_retiro       Responsable pre-asignado para la etapa de retiro.
     *                                       La recepción se asigna automáticamente al id_solicitante.
     */
    public static function onPedidoCreado(
        PDO    $conn,
        int    $id_pedido,
        string $numero_pedido,
        string $nombre_obra,
        int    $id_solicitante,
        string $prioridad,
        string $fecha_pedido,
        ?string $fecha_necesaria,
        ?int   $id_resp_aprobacion = null,
        ?int   $id_resp_picking    = null,
        ?int   $id_resp_retiro     = null
    ): void {
        // --- Etapa 1: Creación (ya ocurrió, cerrar de inmediato) ---
        self::insertarTarea($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'creacion',
            'id_empleado'        => $id_solicitante,
            'id_asignador'       => $id_solicitante,
            'numero_pedido'      => $numero_pedido,
            'nombre_obra'        => $nombre_obra,
            'prioridad'          => $prioridad,
            'estado'             => 'finalizada',
            'fecha_asignacion'   => $fecha_pedido,
            'fecha_inicio'       => $fecha_pedido,
            'fecha_finalizacion' => $fecha_pedido,
            'fecha_vencimiento'  => null,
        ]);

        // --- Etapa 2: Aprobación (pendiente, asignada al responsable pre-definido) ---
        $asignado_aprobacion = $id_resp_aprobacion ?? $id_solicitante;
        self::insertarTarea($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'aprobacion',
            'id_empleado'        => $asignado_aprobacion,
            'id_asignador'       => $id_solicitante,
            'numero_pedido'      => $numero_pedido,
            'nombre_obra'        => $nombre_obra,
            'prioridad'          => $prioridad,
            'estado'             => 'pendiente',
            'fecha_asignacion'   => $fecha_pedido,
            'fecha_inicio'       => null,
            'fecha_finalizacion' => null,
            'fecha_vencimiento'  => $fecha_necesaria,
        ]);
    }

    /**
     * Cierra la tarea de aprobación y abre la de PICKING.
     * Llamar cuando el pedido pasa a estado 'aprobado'.
     * El responsable de picking se lee de la columna pre-asignada en pedidos_materiales.
     */
    public static function onPedidoAprobado(
        PDO    $conn,
        int    $id_pedido,
        string $numero_pedido,
        string $nombre_obra,
        int    $id_aprobador,
        string $prioridad,
        string $fecha_aprobacion,
        ?string $fecha_necesaria
    ): void {
        // Cerrar tarea de aprobación con el usuario real que la aprobó
        self::cerrarTareaEtapa($conn, $id_pedido, 'aprobacion', $id_aprobador, $fecha_aprobacion);

        // Obtener responsable pre-asignado para picking
        $id_resp_picking = self::obtenerResponsableEtapa($conn, $id_pedido, 'picking') ?? $id_aprobador;

        // Abrir tarea de picking asignada al responsable pre-definido
        self::insertarTarea($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'picking',
            'id_empleado'        => $id_resp_picking,
            'id_asignador'       => $id_aprobador,
            'numero_pedido'      => $numero_pedido,
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
     * Cierra la tarea de picking y abre la de retiro.
     * Llamar cuando el pedido pasa a estado 'picking'.
     * El responsable de retiro se lee de la columna pre-asignada en pedidos_materiales.
     */
    public static function onPedidoPicking(
        PDO    $conn,
        int    $id_pedido,
        string $numero_pedido,
        string $nombre_obra,
        int    $id_picking_por,
        string $prioridad,
        string $fecha_picking,
        ?string $fecha_necesaria
    ): void {
        // Cerrar tarea de picking con el usuario real que la realizó
        self::cerrarTareaEtapa($conn, $id_pedido, 'picking', $id_picking_por, $fecha_picking);

        // Obtener responsable pre-asignado para retiro
        $id_resp_retiro = self::obtenerResponsableEtapa($conn, $id_pedido, 'retiro') ?? $id_picking_por;

        // Abrir tarea de retiro asignada al responsable pre-definido
        self::insertarTarea($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'retiro',
            'id_empleado'        => $id_resp_retiro,
            'id_asignador'       => $id_picking_por,
            'numero_pedido'      => $numero_pedido,
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
     * Cierra la tarea de retiro y abre la de recepción.
     * Llamar cuando el pedido pasa a estado 'retirado'.
     * El responsable de recepción se lee de la columna pre-asignada en pedidos_materiales.
     */
    public static function onPedidoRetirado(
        PDO    $conn,
        int    $id_pedido,
        string $numero_pedido,
        string $nombre_obra,
        int    $id_retirador,
        string $prioridad,
        string $fecha_retiro,
        ?string $fecha_necesaria
    ): void {
        // Cerrar tarea de retiro con el usuario real que retiró
        self::cerrarTareaEtapa($conn, $id_pedido, 'retiro', $id_retirador, $fecha_retiro);

        // La tarea de recepción se asigna al solicitante original del pedido
        // (es quien espera los materiales en obra y confirma la llegada)
        $stmt_sol = $conn->prepare("SELECT id_solicitante FROM pedidos_materiales WHERE id_pedido = ?");
        $stmt_sol->execute([$id_pedido]);
        $id_solicitante_pedido = (int) ($stmt_sol->fetchColumn() ?: $id_retirador);

        self::insertarTarea($conn, [
            'id_pedido'          => $id_pedido,
            'etapa'              => 'recibido',
            'id_empleado'        => $id_solicitante_pedido,
            'id_asignador'       => $id_retirador,
            'numero_pedido'      => $numero_pedido,
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
     * Cierra la tarea de recepción.
     * Llamar cuando el pedido pasa a estado 'recibido'.
     */
    public static function onPedidoRecibido(PDO $conn, int $id_pedido, int $id_receptor, string $fecha_recibido): void
    {
        self::cerrarTareaEtapa($conn, $id_pedido, 'recibido', $id_receptor, $fecha_recibido);
    }

    /**
     * Cancela todas las tareas pendientes/en_proceso de un pedido.
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
     * Punto de entrada principal del flujo inverso.
     * Llamar cuando una tarea de tipo 'pedido' es marcada como 'finalizada'.
     *
     * Efectos:
     *  - Avanza el estado del pedido al estado correspondiente
     *  - Registra el cambio en seguimiento_pedidos
     *  - Descuenta stock si la etapa es 'retiro'
     *  - Crea la tarea de la siguiente etapa (si corresponde)
     *
     * No hace nada si la tarea no es de tipo 'pedido', o si el pedido
     * ya no está en el estado esperado (para evitar doble avance).
     */
    public static function onTareaEtapaFinalizada(PDO $conn, array $tarea): void
    {
        if (($tarea['tipo'] ?? '') !== 'pedido'
            || empty($tarea['etapa_pedido'])
            || empty($tarea['id_pedido'])) {
            return;
        }

        $id_pedido  = (int) $tarea['id_pedido'];
        $etapa      = $tarea['etapa_pedido'];
        $id_usuario = (int) $tarea['id_empleado'];
        $fecha_actual = date('Y-m-d H:i:s');

        // Mapa etapa → estado actual esperado → estado nuevo → columnas a actualizar
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

        // Obtener pedido y verificar que está en el estado esperado
        $stmt = $conn->prepare("
            SELECT p.numero_pedido, p.prioridad, p.fecha_necesaria, p.estado, p.id_solicitante, o.nombre_obra
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
            SET    estado              = ?,
                   {$cfg['campo_usuario']} = ?,
                   {$cfg['campo_fecha']}   = ?
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

        // Crear tarea de la siguiente etapa (si existe)
        $siguiente = self::etapaSiguiente($etapa);
        if ($siguiente) {
            // La etapa 'recibido' se asigna al solicitante original del pedido
            // (es quien espera los materiales en obra y confirma la llegada)
            if ($etapa === 'retiro') {
                $id_resp = (int) ($pedido['id_solicitante'] ?: $id_usuario);
            } else {
                $id_resp = self::obtenerResponsableEtapa($conn, $id_pedido, $siguiente) ?? $id_usuario;
            }
            self::insertarTarea($conn, [
                'id_pedido'          => $id_pedido,
                'etapa'              => $siguiente,
                'id_empleado'        => $id_resp,
                'id_asignador'       => $id_usuario,
                'numero_pedido'      => $pedido['numero_pedido'],
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
     * Obtiene el id de usuario pre-asignado para una etapa del pedido.
     * Devuelve null si no hay responsable asignado o la columna no existe.
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
     * Inserta una tarea y la vincula en pedido_tareas.
     * Si ya existe una entrada en pedido_tareas para esa etapa, la actualiza.
     */
    private static function insertarTarea(PDO $conn, array $d): void
    {
        $titulo      = self::buildTitulo($d['etapa'], $d['numero_pedido']);
        $descripcion = self::buildDescripcion($d['etapa'], $d['numero_pedido'], $d['nombre_obra']);

        $tiempo_real = null;
        if (!empty($d['fecha_inicio']) && !empty($d['fecha_finalizacion'])) {
            $diff        = strtotime($d['fecha_finalizacion']) - strtotime($d['fecha_inicio']);
            $tiempo_real = max(0, (int) round($diff / 3600));
        }

        $stmt = $conn->prepare("
            INSERT INTO tareas
                (id_empleado, titulo, descripcion, prioridad, id_asignador,
                 estado, fecha_asignacion, fecha_inicio, fecha_finalizacion,
                 tiempo_real, fecha_vencimiento, tipo, id_pedido, etapa_pedido)
            VALUES (?, ?, ?, ?, ?,  ?, ?, ?, ?,  ?, ?,  'pedido', ?, ?)
        ");
        $stmt->execute([
            $d['id_empleado'],
            $titulo,
            $descripcion,
            $d['prioridad'],
            $d['id_asignador'],
            $d['estado'],
            $d['fecha_asignacion'],
            $d['fecha_inicio']       ?: null,
            $d['fecha_finalizacion'] ?: null,
            $tiempo_real,
            $d['fecha_vencimiento']  ?: null,
            $d['id_pedido'],
            $d['etapa'],
        ]);
        $id_tarea = (int) $conn->lastInsertId();

        // Vínculo en pedido_tareas (upsert por etapa)
        $stmt_link = $conn->prepare("
            INSERT INTO pedido_tareas (id_pedido, id_tarea, etapa)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE id_tarea = VALUES(id_tarea)
        ");
        $stmt_link->execute([$d['id_pedido'], $id_tarea, $d['etapa']]);
    }

    /**
     * Cierra una tarea pendiente de la etapa indicada,
     * asignando el usuario real y calculando el tiempo transcurrido.
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
            JOIN   tareas        t  ON pt.id_tarea = t.id_tarea
            WHERE  pt.id_pedido = ?
              AND  pt.etapa     = ?
              AND  t.estado IN ('pendiente','en_proceso')
            LIMIT 1
        ");
        $stmt->execute([$id_pedido, $etapa]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tarea) return;

        $diff        = strtotime($fecha_fin) - strtotime($tarea['fecha_asignacion']);
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

    private static function buildTitulo(string $etapa, string $numero_pedido): string
    {
        $base = self::$titulos[$etapa] ?? $etapa;
        return "$base #{$numero_pedido}";
    }

    private static function buildDescripcion(string $etapa, string $numero_pedido, string $nombre_obra): string
    {
        $tpl = self::$descripciones[$etapa] ?? 'Tarea de pedido {numero}.';
        return str_replace(['{numero}', '{obra}'], [$numero_pedido, $nombre_obra], $tpl);
    }
}
