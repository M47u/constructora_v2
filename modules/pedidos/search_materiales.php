<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

// Verificar autenticación
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición AJAX
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    http_response_code(400);
    echo json_encode(['error' => 'Petición inválida']);
    exit;
}

$database = new Database();
$conn = $database->getConnection();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Validar que tenga al menos 2 caracteres
if (strlen($search) < 2) {
    echo json_encode([]);
    exit;
}

try {
    // Patrones para búsqueda
    $patternStart = $search . '%';   
    $patternAnywhere = '%' . $search . '%'; 

    $sql = "SELECT 
                id_material,
                nombre_material,
                stock_actual,
                stock_minimo,
                precio_referencia,
                unidad_medida,
                CASE 
                    WHEN stock_actual = 0 THEN 'sin_stock'
                    WHEN stock_actual <= stock_minimo THEN 'stock_bajo'
                    ELSE 'disponible'
                END as estado_stock
            FROM materiales 
            WHERE estado = 'activo' 
            AND (
                nombre_material LIKE :patternStart
                OR nombre_material LIKE :patternAnywhere
            )
            ORDER BY 
                CASE 
                    WHEN nombre_material LIKE :patternStart THEN 1
                    ELSE 2
                END,
                nombre_material ASC
            LIMIT 20";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':patternStart', $patternStart, PDO::PARAM_STR);
    $stmt->bindValue(':patternAnywhere', $patternAnywhere, PDO::PARAM_STR);

    $stmt->execute();
    $materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear respuesta
    $response = [];
    foreach ($materiales as $material) {
        $response[] = [
            'id_material'       => $material['id_material'],
            'nombre_material'   => $material['nombre_material'],
            'stock_actual'      => (int)$material['stock_actual'],
            'stock_minimo'      => (int)$material['stock_minimo'],
            'precio_referencia' => (float)$material['precio_referencia'],
            'unidad_medida'     => $material['unidad_medida'],
            'estado_stock'      => $material['estado_stock']
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log("ERROR EN search_materiales.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error en la búsqueda']);
}
