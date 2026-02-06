<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->check_session();

// Solo administradores y responsables pueden registrar devoluciones
if (!has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])) {
    echo json_encode(['success' => false, 'error' => 'Permisos insuficientes']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

try {
    // Validar datos recibidos
    $id_prestamo = (int)($_POST['id_prestamo'] ?? 0);
    $id_unidad = (int)($_POST['id_unidad'] ?? 0);
    $condicion_devolucion = $_POST['condicion_devolucion'] ?? '';
    $observaciones = sanitize_input($_POST['observaciones_devolucion'] ?? '');
    $requiere_mantenimiento = (int)($_POST['requiere_mantenimiento'] ?? 0);
    
    // Validaciones
    if ($id_prestamo <= 0) {
        throw new Exception('ID de préstamo inválido');
    }
    
    if ($id_unidad <= 0) {
        throw new Exception('ID de unidad inválido');
    }
    
    if (!es_condicion_valida($condicion_devolucion)) {
        throw new Exception('Condición de devolución inválida');
    }
    
    // Verificar que el préstamo existe y no tiene devolución registrada
    $query_check = "SELECT p.id_prestamo, d.id_devolucion 
                    FROM prestamos p
                    LEFT JOIN devoluciones d ON p.id_prestamo = d.id_prestamo
                    WHERE p.id_prestamo = ?";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->execute([$id_prestamo]);
    $prestamo = $stmt_check->fetch();
    
    if (!$prestamo) {
        throw new Exception('El préstamo no existe');
    }
    
    if ($prestamo['id_devolucion']) {
        throw new Exception('Este préstamo ya tiene una devolución registrada');
    }
    
    // Verificar que la unidad pertenece al préstamo
    $query_detalle = "SELECT id_detalle FROM detalle_prestamo 
                      WHERE id_prestamo = ? AND id_unidad = ?";
    $stmt_detalle = $conn->prepare($query_detalle);
    $stmt_detalle->execute([$id_prestamo, $id_unidad]);
    $detalle = $stmt_detalle->fetch();
    
    if (!$detalle) {
        throw new Exception('La herramienta no pertenece a este préstamo');
    }
    
    $conn->beginTransaction();
    
    // 1. Registrar la devolución principal
    $query_devolucion = "INSERT INTO devoluciones (id_prestamo, id_recibido_por, observaciones_devolucion)
                         VALUES (?, ?, ?)";
    $stmt_devolucion = $conn->prepare($query_devolucion);
    $stmt_devolucion->execute([$id_prestamo, $_SESSION['user_id'], $observaciones]);
    $id_devolucion = $conn->lastInsertId();
    
    // 2. Registrar el detalle de devolución para esta unidad
    // Determinar el nuevo estado basado en la condición y si requiere mantenimiento
    $nuevo_estado = determinar_nuevo_estado($condicion_devolucion, $requiere_mantenimiento);
    
    $query_detalle_dev = "INSERT INTO detalle_devolucion 
                          (id_devolucion, id_unidad, condicion_devolucion, observaciones_devolucion)
                          VALUES (?, ?, ?, ?)";
    $stmt_detalle_dev = $conn->prepare($query_detalle_dev);
    $stmt_detalle_dev->execute([$id_devolucion, $id_unidad, $condicion_devolucion, $observaciones]);
    
    // 3. Actualizar el estado de la unidad
    $query_update = "UPDATE herramientas_unidades 
                     SET estado_actual = ? 
                     WHERE id_unidad = ?";
    $stmt_update = $conn->prepare($query_update);
    $stmt_update->execute([$nuevo_estado, $id_unidad]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Devolución registrada exitosamente',
        'id_devolucion' => $id_devolucion,
        'nuevo_estado' => $nuevo_estado
    ]);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error en devolución rápida: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
