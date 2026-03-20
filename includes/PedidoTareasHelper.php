<?php
/**
 * PedidoTareasHelper
 *
 * Centraliza toda la lógica de creación, cierre y cancelación
 * de tareas automáticas vinculadas a las etapas de un pedido.
 *
 * Flujo Opción C (híbrido):
 *   - Al ABRIR una etapa  → se crea una tarea en estado 'pendiente'
 *   - Al CERRAR una etapa → la tarea se actualiza a 'finalizada'
 *                           con el usuario real y el tiempo transcurrido
 *   - Al CANCELAR pedido  → tareas pendientes pasan a 'cancelada'
 *
 * Etapas y cuándo ocurren:
 *   creacion   → Al crear el pedido         (cierra inmediatamente como finalizada)
 *   aprobacion → Al crear el pedido         (queda pendiente hasta que alguien la apruebe)
 *   retiro     → Al aprobar el pedido       (queda pendiente hasta el retiro)
 *   recibido   → Al marcar como retirado    (queda pendiente hasta la recepción)
 */
class PedidoTareasHelper
{
    // ----------------------------------------------------------------
    // Títulos y descripciones por etapa
    // ----------------------------------------------------------------

    private static array $titulos = [
        'creacion'   => 'Crear pedido',
        'aprobacion' => 'Aprobar pedido',
        'retiro'     => 'Retirar materiales pedido',
        'recibido'   => 'Confirmar recepción pedido',
    ];

    private static array $descripciones = [
        'creacion'   => 'Pedido de materiales {numero} creado para la obra: {obra}.',
        'aprobacion' => 'Revisar y aprobar el pedido de materiales {numero} de la obra: {obra}.',
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
     * @param string $numero_pedido   Ej: "PED20260001"
     * @param string $nombre_obra
     * @param int    $id_solicitante  Usuario que creó el pedido
     * @param string $prioridad       baja|media|alta|urgente
     * @param string $fecha_pedido    Y-m-d H:i:s
     * @param string|null $fecha_necesaria  Fecha límite del pedido (para vencimiento)
     */
    public static function onPedidoCreado(
        PDO    $conn,
        int    $id_pedido,
        string $numero_pedido,
        string $nombre_obra,
        int    $id_solicitante,
        string $prioridad,
        string $fecha_pedido,
        ?string $fecha_necesaria
    ): void {
        // --- Etapa 1: Creación (ya ocurrió, cerrar de inmediato) ---
        self::insertarTarea($conn, [
            'id_pedido'         => $id_pedido,
            'etapa'             => 'creacion',
            'id_empleado'       => $id_solicitante,
            'id_asignador'      => $id_solicitante,
            'numero_pedido'     => $numero_pedido,
            'nombre_obra'       => $nombre_obra,
            'prioridad'         => $prioridad,
            'estado'            => 'finalizada',
            'fecha_asignacion'  => $fecha_pedido,
            'fecha_inicio'      => $fecha_pedido,
            'fecha_finalizacion'=> $fecha_pedido,
            'fecha_vencimiento' => null,
        ]);

        // --- Etapa 2: Aprobación (pendiente: espera que un admin la apruebe) ---
        self::insertarTarea($conn, [
            'id_pedido'         => $id_pedido,
            'etapa'             => 'aprobacion',
            'id_empleado'       => $id_solicitante,   // se reasignará al aprobador real
            'id_asignador'      => $id_solicitante,
            'numero_pedido'     => $numero_pedido,
            'nombre_obra'       => $nombre_obra,
            'prioridad'         => $prioridad,
            'estado'            => 'pendiente',
            'fecha_asignacion'  => $fecha_pedido,
            'fecha_inicio'      => null,
            'fecha_finalizacion'=> null,
            'fecha_vencimiento' => $fecha_necesaria,
        ]);
    }

    /**
     * Cierra la tarea de aprobación y abre la de retiro.
     * Llamar cuando el pedido pasa a estado 'aprobado'.
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
        // Cerrar tarea de aprobación
        self::cerrarTareaEtapa($conn, $id_pedido, 'aprobacion', $id_aprobador, $fecha_aprobacion);

        // Abrir tarea de retiro (pendiente)
        self::insertarTarea($conn, [
            'id_pedido'         => $id_pedido,
            'etapa'             => 'retiro',
            'id_empleado'       => $id_aprobador,     // se reasignará al que retira
            'id_asignador'      => $id_aprobador,
            'numero_pedido'     => $numero_pedido,
            'nombre_obra'       => $nombre_obra,
            'prioridad'         => $prioridad,
            'estado'            => 'pendiente',
            'fecha_asignacion'  => $fecha_aprobacion,
            'fecha_inicio'      => null,
            'fecha_finalizacion'=> null,
            'fecha_vencimiento' => $fecha_necesaria,
        ]);
    }

    /**
     * Cierra la tarea de retiro y abre la de recepción.
     * Llamar cuando el pedido pasa a estado 'retirado'.
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
        // Cerrar tarea de retiro
        self::cerrarTareaEtapa($conn, $id_pedido, 'retiro', $id_retirador, $fecha_retiro);

        // Abrir tarea de recepción (pendiente)
        self::insertarTarea($conn, [
            'id_pedido'         => $id_pedido,
            'etapa'             => 'recibido',
            'id_empleado'       => $id_retirador,     // se reasignará al que recibe
            'id_asignador'      => $id_retirador,
            'numero_pedido'     => $numero_pedido,
            'nombre_obra'       => $nombre_obra,
            'prioridad'         => $prioridad,
            'estado'            => 'pendiente',
            'fecha_asignacion'  => $fecha_retiro,
            'fecha_inicio'      => null,
            'fecha_finalizacion'=> null,
            'fecha_vencimiento' => $fecha_necesaria,
        ]);
    }

    /**
     * Cierra la tarea de recepción.
     * Llamar cuando el pedido pasa a estado 'recibido'.
     */
    public static function onPedidoRecibido(
        PDO    $conn,
        int    $id_pedido,
        int    $id_receptor,
        string $fecha_recibido
    ): void {
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
            SET    t.estado            = 'cancelada',
                   t.fecha_finalizacion = NOW(),
                   t.observaciones     = CONCAT(COALESCE(t.observaciones,''), ' [Cancelada: pedido cancelado]')
            WHERE  pt.id_pedido = ?
              AND  t.estado IN ('pendiente','en_proceso')
        ");
        $stmt->execute([$id_pedido]);
    }

    // ----------------------------------------------------------------
    // Métodos privados internos
    // ----------------------------------------------------------------

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
            $d['fecha_inicio']      ?: null,
            $d['fecha_finalizacion'] ?: null,
            $tiempo_real,
            $d['fecha_vencimiento'] ?: null,
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
        // Buscar la tarea activa (pendiente o en_proceso) de esa etapa
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

        if (!$tarea) return;   // ya fue cerrada o no existe

        $diff        = strtotime($fecha_fin) - strtotime($tarea['fecha_asignacion']);
        $tiempo_real = max(0, (int) round($diff / 3600));

        $stmt_upd = $conn->prepare("
            UPDATE tareas SET
                id_empleado      = ?,
                estado           = 'finalizada',
                fecha_inicio     = COALESCE(fecha_inicio, ?),
                fecha_finalizacion = ?,
                tiempo_real      = ?
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
