<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden imprimir materiales
if (!has_permission(ROLE_ADMIN)) {
    die('No autorizado.');
}

$database = new Database();
$conn = $database->getConnection();

$material_id = (int)($_GET['id'] ?? 0);

if ($material_id <= 0) {
    die('ID de material no proporcionado.');
}

try {
    $stmt = $conn->prepare("SELECT * FROM materiales WHERE id_material = ?");
    $stmt->execute([$material_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        die('Material no encontrado.');
    }

} catch (Exception $e) {
    die('Error al obtener datos del material: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir Material</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #111;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo img {
            max-width: 220px;
            height: auto;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .meta {
            margin-bottom: 14px;
        }
        .meta p {
            margin: 4px 0;
        }
        .section-title {
            margin: 16px 0 8px;
            font-size: 16px;
        }
        .materials {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .materials th,
        .materials td {
            border: 1px solid #000;
            padding: 6px;
            text-align: left;
            font-size: 13px;
        }
        .materials th {
            background-color: #f2f2f2;
        }
        .badge {
            padding: 2px 6px;
            border: 1px solid #000;
            border-radius: 3px;
            font-size: 12px;
            display: inline-block;
        }
        .footer {
            margin-top: 36px;
            text-align: center;
            font-size: 13px;
        }
        @media print {
            body {
                margin: 0;
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="logo">
        <img src="<?php echo SITE_URL; ?>/assets/img/logo_san_simon.png" alt="SAN SIMON SRL">
    </div>

    <div class="header">
        <h2>Material #<?php echo str_pad((string)$material['id_material'], 4, '0', STR_PAD_LEFT); ?></h2>
        <p><strong><?php echo htmlspecialchars($material['nombre_material']); ?></strong></p>
    </div>

    <div class="meta">
        <p><strong>Unidad:</strong> <?php echo htmlspecialchars($material['unidad_medida']); ?></p>
        <p><strong>Precio Referencia:</strong> $<?php echo number_format((float)$material['precio_referencia'], 2); ?></p>
        <p><strong>Stock Actual:</strong> <?php echo number_format((float)$material['stock_actual']); ?></p>
        <p><strong>Stock Minimo:</strong> <?php echo number_format((float)$material['stock_minimo']); ?></p>
    </div>

    <script>
        window.onload = function () {
            window.print();
        };
    </script>
</body>
</html>
