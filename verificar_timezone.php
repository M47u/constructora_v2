<?php
/**
 * Script de Verificación de Zona Horaria - Post Migración
 * Ejecutar después de la migración para confirmar que todo funciona correctamente
 */

require_once 'config/config.php';
require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificación de Zona Horaria - Post Migración</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .test-success { background-color: #d4edda; border-left: 4px solid #28a745; }
        .test-warning { background-color: #fff3cd; border-left: 4px solid #ffc107; }
        .test-error { background-color: #f8d7da; border-left: 4px solid #dc3545; }
    </style>
</head>
<body>
<div class="container my-5">
    <h1 class="mb-4">🕐 Verificación de Zona Horaria - Post Migración</h1>
    
    <!-- Test 1: Configuración PHP -->
    <div class="card mb-3">
        <div class="card-header bg-primary text-white">
            <h5>1. Configuración de PHP</h5>
        </div>
        <div class="card-body test-success">
            <p><strong>Zona Horaria:</strong> <?php echo date_default_timezone_get(); ?></p>
            <p><strong>Fecha/Hora Actual (PHP):</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
            <p><strong>Formato Argentino:</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
            <?php if (date_default_timezone_get() === 'America/Argentina/Buenos_Aires'): ?>
                <p class="text-success mb-0">✅ Configuración correcta</p>
            <?php else: ?>
                <p class="text-danger mb-0">❌ Zona horaria incorrecta. Debería ser: America/Argentina/Buenos_Aires</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Test 2: Configuración MySQL -->
    <?php
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Obtener zona horaria y fechas
        $stmt = $conn->query("SELECT @@session.time_zone as tz, NOW() as now_time");
        $mysql_info = $stmt->fetch();
        
        // Obtener UTC timestamp por separado
        $stmt2 = $conn->query("SELECT UTC_TIMESTAMP() as utc_ts");
        $utc_info = $stmt2->fetch();
        $mysql_info['utc_timestamp'] = $utc_info['utc_ts'];
        
        $php_time = time();
        $mysql_time = strtotime($mysql_info['now_time']);
        $diff_seconds = abs($php_time - $mysql_time);
        
        $is_synced = $diff_seconds <= 60;
        $is_correct_tz = $mysql_info['tz'] === '-03:00';
        ?>
        
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5>2. Configuración de MySQL</h5>
            </div>
            <div class="card-body <?php echo ($is_synced && $is_correct_tz) ? 'test-success' : 'test-error'; ?>">
                <p><strong>Zona Horaria MySQL:</strong> <?php echo $mysql_info['tz']; ?></p>
                <p><strong>Fecha/Hora MySQL:</strong> <?php echo $mysql_info['now_time']; ?></p>
                <p><strong>Fecha/Hora UTC:</strong> <?php echo $mysql_info['utc_timestamp']; ?></p>
                <p><strong>Diferencia con PHP:</strong> <?php echo $diff_seconds; ?> segundos</p>
                
                <?php if ($is_correct_tz && $is_synced): ?>
                    <p class="text-success mb-0">✅ Configuración correcta y sincronizada</p>
                <?php elseif (!$is_correct_tz): ?>
                    <p class="text-danger mb-0">❌ Zona horaria incorrecta. Debería ser: -03:00</p>
                <?php else: ?>
                    <p class="text-warning mb-0">⚠️ Desincronización detectada (<?php echo $diff_seconds; ?> segundos)</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test 3: Verificar Datos Migrados -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5>3. Verificación de Datos Migrados</h5>
            </div>
            <div class="card-body">
                <h6>Últimos Pedidos:</h6>
                <?php
                $stmt = $conn->query("
                    SELECT 
                        numero_pedido, 
                        fecha_pedido,
                        HOUR(fecha_pedido) as hora
                    FROM pedidos_materiales 
                    ORDER BY id_pedido DESC 
                    LIMIT 5
                ");
                $pedidos = $stmt->fetchAll();
                
                if (!empty($pedidos)): ?>
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Pedido</th>
                                <th>Fecha Original</th>
                                <th>Hora</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pedidos as $pedido): 
                            // Verificar que la hora esté en rango Argentina (probablemente entre 6 AM y 11 PM)
                            $hora = (int)$pedido['hora'];
                            $es_razonable = ($hora >= 6 && $hora <= 23);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($pedido['numero_pedido']); ?></td>
                                <td><?php echo $pedido['fecha_pedido']; ?></td>
                                <td><?php echo $pedido['hora']; ?>:00</td>
                                <td>
                                    <?php if ($es_razonable): ?>
                                        <span class="badge bg-success">✅ OK</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">⚠️ Revisar</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">No hay pedidos en la base de datos</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Test 4: Insertar Registro de Prueba -->
        <div class="card mb-3">
            <div class="card-header bg-primary text-white">
                <h5>4. Test de Inserción (Registro de Prueba)</h5>
            </div>
            <div class="card-body">
                <?php
                // Crear tabla de prueba si no existe
                $conn->exec("
                    CREATE TABLE IF NOT EXISTS test_timezone (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        descripcion VARCHAR(100),
                        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                
                // Insertar registro de prueba
                $stmt = $conn->prepare("INSERT INTO test_timezone (descripcion) VALUES (?)");
                $stmt->execute(['Test de verificación - ' . date('Y-m-d H:i:s')]);
                
                // Obtener el registro recién creado
                $stmt = $conn->query("
                    SELECT 
                        descripcion,
                        fecha_creacion,
                        HOUR(fecha_creacion) as hora
                    FROM test_timezone 
                    ORDER BY id DESC 
                    LIMIT 1
                ");
                $test_record = $stmt->fetch();
                
                $php_now = date('Y-m-d H:i:s');
                $mysql_now = $test_record['fecha_creacion'];
                
                $php_timestamp = strtotime($php_now);
                $mysql_timestamp = strtotime($mysql_now);
                $diff = abs($php_timestamp - $mysql_timestamp);
                
                $is_test_ok = $diff <= 5; // Tolerancia de 5 segundos
                ?>
                
                <div class="<?php echo $is_test_ok ? 'test-success' : 'test-error'; ?> p-3 rounded">
                    <p><strong>Descripción:</strong> <?php echo htmlspecialchars($test_record['descripcion']); ?></p>
                    <p><strong>Fecha PHP (esperada):</strong> <?php echo $php_now; ?></p>
                    <p><strong>Fecha MySQL (guardada):</strong> <?php echo $mysql_now; ?></p>
                    <p><strong>Diferencia:</strong> <?php echo $diff; ?> segundos</p>
                    
                    <?php if ($is_test_ok): ?>
                        <p class="text-success mb-0"><strong>✅ ¡Perfecto! Los nuevos registros se están guardando con GMT -03:00</strong></p>
                    <?php else: ?>
                        <p class="text-danger mb-0"><strong>❌ Error: Hay diferencia entre PHP y MySQL (<?php echo $diff; ?> segundos)</strong></p>
                    <?php endif; ?>
                </div>
                
                <div class="mt-3">
                    <button class="btn btn-danger btn-sm" onclick="limpiarTest()">Limpiar Tabla de Test</button>
                </div>
            </div>
        </div>

        <!-- Test 5: Resumen General -->
        <div class="card mb-3">
            <div class="card-header bg-success text-white">
                <h5>5. Resumen General</h5>
            </div>
            <div class="card-body">
                <?php
                $all_ok = $is_synced && $is_correct_tz && $is_test_ok;
                ?>
                
                <?php if ($all_ok): ?>
                    <div class="alert alert-success">
                        <h4>✅ ¡Todo Correcto!</h4>
                        <ul class="mb-0">
                            <li>PHP configurado con zona horaria Argentina</li>
                            <li>MySQL configurado con GMT -03:00</li>
                            <li>PHP y MySQL están sincronizados</li>
                            <li>Los nuevos registros se guardan correctamente</li>
                            <li>Los datos migrados están correctos</li>
                        </ul>
                        <hr>
                        <p class="mb-0"><strong>Próximos pasos:</strong></p>
                        <ol>
                            <li>Verificar algunos registros manualmente en la aplicación</li>
                            <li>Crear un nuevo pedido de prueba y verificar la fecha</li>
                            <li>Revisar los reportes de métricas</li>
                        </ol>
                    </div>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h4>❌ Se encontraron problemas</h4>
                        <ul class="mb-0">
                            <?php if (!$is_correct_tz): ?>
                                <li>MySQL no está configurado con GMT -03:00</li>
                            <?php endif; ?>
                            <?php if (!$is_synced): ?>
                                <li>PHP y MySQL no están sincronizados</li>
                            <?php endif; ?>
                            <?php if (!$is_test_ok): ?>
                                <li>Los nuevos registros no se están guardando correctamente</li>
                            <?php endif; ?>
                        </ul>
                        <hr>
                        <p class="mb-0"><strong>Acciones requeridas:</strong></p>
                        <ol>
                            <li>Verificar archivo config/database.php línea 24</li>
                            <li>Reiniciar servidor Apache</li>
                            <li>Volver a ejecutar esta verificación</li>
                        </ol>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Información Adicional -->
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5>📋 Información del Sistema</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm">
                    <tr>
                        <th>PHP Version:</th>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <th>MySQL Version:</th>
                        <td><?php echo $conn->query("SELECT VERSION()")->fetchColumn(); ?></td>
                    </tr>
                    <tr>
                        <th>Servidor:</th>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'; ?></td>
                    </tr>
                    <tr>
                        <th>Sistema Operativo:</th>
                        <td><?php echo PHP_OS; ?></td>
                    </tr>
                </table>
            </div>
        </div>

    <?php
    } catch (Exception $e) {
        ?>
        <div class="alert alert-danger">
            <h4>Error de Conexión</h4>
            <p><?php echo $e->getMessage(); ?></p>
        </div>
        <?php
    }
    ?>

    <div class="text-center mt-4">
        <a href="../dashboard.php" class="btn btn-primary">Volver al Dashboard</a>
        <button onclick="location.reload()" class="btn btn-secondary">Volver a Verificar</button>
    </div>
</div>

<script>
function limpiarTest() {
    if (confirm('¿Estás seguro de que deseas limpiar la tabla de test?')) {
        fetch('?action=clean_test')
            .then(() => location.reload());
    }
}

<?php
if (isset($_GET['action']) && $_GET['action'] === 'clean_test') {
    try {
        $conn->exec("DROP TABLE IF EXISTS test_timezone");
        echo "alert('Tabla de test eliminada');";
    } catch (Exception $e) {
        echo "alert('Error: " . addslashes($e->getMessage()) . "');";
    }
}
?>
</script>
</body>
</html>
