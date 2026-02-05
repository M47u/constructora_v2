<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos
if ($_SESSION['user_role'] !== 'administrador' && $_SESSION['user_role'] !== 'responsable_obra') {
    header('Location: ../../dashboard.php?error=sin_permisos');
    exit();
}

// Inicializar conexión a la base de datos
$database = new Database();
$pdo = $database->getConnection();

// Obtener filtros
$fecha_desde = $_GET['fecha_desde'] ?? date('Y-m-01');
$fecha_hasta = $_GET['fecha_hasta'] ?? get_current_date();
$id_obra = $_GET['id_obra'] ?? '';
$id_usuario = $_GET['id_usuario'] ?? '';

try {
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
    
    // Consulta principal
    $sql = "
        SELECT 
            h.id_extension,
            h.id_prestamo,
            h.fecha_anterior,
            h.fecha_nueva,
            h.motivo,
            h.fecha_modificacion,
            u.nombre as usuario_nombre,
            u.apellido as usuario_apellido,
            emp.nombre as empleado_nombre,
            emp.apellido as empleado_apellido,
            o.nombre_obra,
            o.localidad as obra_localidad,
            DATEDIFF(h.fecha_nueva, h.fecha_anterior) as dias_extendidos,
            (SELECT GROUP_CONCAT(CONCAT(her.marca, ' ', her.modelo) SEPARATOR ', ')
             FROM detalle_prestamo dp
             JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
             JOIN herramientas her ON hu.id_herramienta = her.id_herramienta
             WHERE dp.id_prestamo = p.id_prestamo) as herramientas
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
    
    // Configurar cabeceras para descarga de CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="extensiones_prestamos_' . date('Y-m-d_His') . '.csv"');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (para que Excel lo reconozca correctamente)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Escribir encabezados
    fputcsv($output, [
        'ID Extensión',
        'ID Préstamo',
        'Obra',
        'Localidad',
        'Empleado',
        'Herramientas',
        'Fecha Anterior',
        'Fecha Nueva',
        'Días Extendidos',
        'Motivo',
        'Modificado Por',
        'Fecha/Hora Modificación'
    ], ';');
    
    // Escribir datos
    foreach ($extensiones as $ext) {
        fputcsv($output, [
            $ext['id_extension'],
            $ext['id_prestamo'],
            $ext['nombre_obra'],
            $ext['obra_localidad'],
            $ext['empleado_nombre'] . ' ' . $ext['empleado_apellido'],
            $ext['herramientas'],
            $ext['fecha_anterior'] ? date('d/m/Y', strtotime($ext['fecha_anterior'])) : 'No definida',
            date('d/m/Y', strtotime($ext['fecha_nueva'])),
            $ext['dias_extendidos'] > 0 ? $ext['dias_extendidos'] : '0',
            $ext['motivo'] ?? 'Sin motivo',
            $ext['usuario_nombre'] . ' ' . $ext['usuario_apellido'],
            date('d/m/Y H:i', strtotime($ext['fecha_modificacion']))
        ], ';');
    }
    
    fclose($output);
    exit;
    
} catch (Exception $e) {
    die("Error al generar el archivo: " . $e->getMessage());
}
