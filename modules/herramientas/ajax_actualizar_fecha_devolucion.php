<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Establecer cabecera JSON
header('Content-Type: application/json');

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar sesión
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

// Verificar permisos
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['administrador', 'responsable_obra'])) {
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

// Validar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y validar parámetros
$id_prestamo = (int)($_POST['id_prestamo'] ?? 0);
$fecha_devolucion_programada = $_POST['fecha_devolucion_programada'] ?? '';
$motivo = trim($_POST['motivo'] ?? '');

if ($id_prestamo <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID de préstamo inválido']);
    exit;
}

if (empty($fecha_devolucion_programada)) {
    echo json_encode(['success' => false, 'message' => 'La fecha de devolución es requerida']);
    exit;
}

if (empty($motivo)) {
    echo json_encode(['success' => false, 'message' => 'El motivo de la extensión es requerido']);
    exit;
}

// Validar formato de fecha
$fecha_obj = DateTime::createFromFormat('Y-m-d', $fecha_devolucion_programada);
if (!$fecha_obj || $fecha_obj->format('Y-m-d') !== $fecha_devolucion_programada) {
    echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido']);
    exit;
}

// Validar que la fecha no sea anterior a hoy
$hoy = new DateTime();
$hoy->setTime(0, 0, 0);
if ($fecha_obj < $hoy) {
    echo json_encode(['success' => false, 'message' => 'La fecha no puede ser anterior a la fecha actual']);
    exit;
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Iniciar transacción para garantizar integridad
    $conn->beginTransaction();
    
    // Obtener la fecha anterior para el historial
    $query_check = "SELECT id_prestamo, fecha_devolucion_programada FROM prestamos WHERE id_prestamo = ?";
    $stmt_check = $conn->prepare($query_check);
    $stmt_check->execute([$id_prestamo]);
    $prestamo_actual = $stmt_check->fetch();
    
    if (!$prestamo_actual) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'El préstamo no existe']);
        exit;
    }
    
    $fecha_anterior = $prestamo_actual['fecha_devolucion_programada'];
    
    // Actualizar la fecha de devolución programada
    $query_update = "UPDATE prestamos SET fecha_devolucion_programada = ? WHERE id_prestamo = ?";
    $stmt_update = $conn->prepare($query_update);
    
    if (!$stmt_update->execute([$fecha_devolucion_programada, $id_prestamo])) {
        $conn->rollBack();
        $errorInfo = $stmt_update->errorInfo();
        error_log("Error SQL al actualizar fecha: " . json_encode($errorInfo));
        echo json_encode(['success' => false, 'message' => 'Error al actualizar la fecha en la base de datos']);
        exit;
    }
    
    // Registrar en el historial de extensiones
    $query_historial = "INSERT INTO historial_extensiones_prestamo 
                        (id_prestamo, fecha_anterior, fecha_nueva, id_usuario_modifico, motivo) 
                        VALUES (?, ?, ?, ?, ?)";
    $stmt_historial = $conn->prepare($query_historial);
    
    if (!$stmt_historial->execute([$id_prestamo, $fecha_anterior, $fecha_devolucion_programada, $_SESSION['user_id'], $motivo])) {
        $conn->rollBack();
        error_log("Error al registrar historial de extensión");
        echo json_encode(['success' => false, 'message' => 'Error al registrar el historial de la extensión']);
        exit;
    }
    
    // Confirmar transacción
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Fecha de devolución actualizada exitosamente',
        'nueva_fecha' => date('d/m/Y', strtotime($fecha_devolucion_programada)),
        'fecha_anterior' => $fecha_anterior ? date('d/m/Y', strtotime($fecha_anterior)) : 'No definida'
    ]);
    
} catch (PDOException $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error PDO al actualizar fecha de devolución: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log("Error al actualizar fecha de devolución: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error interno: ' . $e->getMessage()]);
}
