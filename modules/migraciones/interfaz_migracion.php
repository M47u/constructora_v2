<?php
// interfaz_migracion.php
// Interfaz para iniciar la migración de herramientas y mostrar marcas detectadas

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Ejecutar el script de migración
    ob_start();
    include 'migrar_herramientas.php';
    $output = ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Migración de Herramientas</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2em; }
        .resultado { background: #f4f4f4; padding: 1em; border-radius: 5px; margin-top: 1em; }
        button { padding: 0.5em 1em; font-size: 1em; }
    </style>
</head>
<body>
    <h1>Migración de Herramientas</h1>
    <form method="post">
        <button type="submit">Iniciar migración</button>
    </form>
    <?php if (!empty($output)): ?>
        <div class="resultado">
            <h2>Resultado:</h2>
            <pre><?= htmlspecialchars($output) ?></pre>
        </div>
    <?php endif; ?>
</body>
</html>
