<?php
/**
 * Script de prueba para verificar la configuración de zona horaria
 * Ejecutar desde el navegador: http://localhost/constructora_v2/test_timezone.php
 */

require_once 'config/config.php';
require_once 'config/database.php';

echo "<h1>Prueba de Configuración de Zona Horaria</h1>";
echo "<hr>";

// 1. Verificar configuración de PHP
echo "<h2>1. Configuración de PHP</h2>";
echo "<p><strong>Zona horaria configurada:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>Fecha y hora actual (PHP):</strong> " . date('Y-m-d H:i:s') . "</p>";
echo "<p><strong>Fecha y hora actual (formato argentino):</strong> " . format_datetime(get_current_datetime()) . "</p>";

// 2. Verificar funciones de utilidad
echo "<h2>2. Funciones de Utilidad</h2>";
echo "<p><strong>get_current_date():</strong> " . get_current_date() . "</p>";
echo "<p><strong>get_current_datetime():</strong> " . get_current_datetime() . "</p>";
echo "<p><strong>format_date('2024-01-15'):</strong> " . format_date('2024-01-15') . "</p>";
echo "<p><strong>format_datetime('2024-01-15 14:30:00'):</strong> " . format_datetime('2024-01-15 14:30:00') . "</p>";

// 3. Verificar conexión a base de datos
echo "<h2>3. Configuración de Base de Datos</h2>";
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Verificar zona horaria de la base de datos
    $stmt = $conn->query("SELECT @@session.time_zone as timezone, NOW() as fecha_actual");
    $result = $stmt->fetch();
    
    echo "<p><strong>Zona horaria de la sesión MySQL:</strong> " . $result['timezone'] . "</p>";
    echo "<p><strong>Fecha y hora actual (MySQL):</strong> " . $result['fecha_actual'] . "</p>";
    
    // Verificar que las fechas coincidan (con tolerancia de 1 minuto)
    $php_time = strtotime(get_current_datetime());
    $mysql_time = strtotime($result['fecha_actual']);
    $diff = abs($php_time - $mysql_time);
    
    if ($diff <= 60) {
        echo "<p style='color: green;'><strong>✓ Sincronización:</strong> Las fechas de PHP y MySQL están sincronizadas (diferencia: {$diff} segundos)</p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Error de sincronización:</strong> Las fechas difieren en {$diff} segundos</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Error de conexión:</strong> " . $e->getMessage() . "</p>";
}

// 4. Prueba de funciones de fecha
echo "<h2>4. Pruebas de Funciones de Fecha</h2>";
$test_date = '2024-01-15';
$test_datetime = '2024-01-15 14:30:00';

echo "<p><strong>is_date_valid('{$test_date}'):</strong> " . (is_date_valid($test_date) ? 'Sí' : 'No') . "</p>";
echo "<p><strong>add_days_to_date('{$test_date}', 7):</strong> " . add_days_to_date($test_date, 7) . "</p>";
echo "<p><strong>get_date_difference('{$test_date}', '2024-01-20'):</strong> " . get_date_difference($test_date, '2024-01-20') . " días</p>";

// 5. Verificar formato de fechas en diferentes contextos
echo "<h2>5. Formatos de Fecha</h2>";
$now = get_current_datetime();
echo "<p><strong>Formato estándar:</strong> " . format_date($now) . "</p>";
echo "<p><strong>Formato con hora:</strong> " . format_datetime($now) . "</p>";
echo "<p><strong>Formato ISO:</strong> " . format_date($now, 'Y-m-d') . "</p>";
echo "<p><strong>Formato completo:</strong> " . format_datetime($now, 'd/m/Y H:i:s') . "</p>";

// 6. Resumen
echo "<h2>6. Resumen</h2>";
echo "<div style='background-color: #f8f9fa; padding: 15px; border-radius: 5px;'>";
echo "<p><strong>Estado de la configuración:</strong></p>";
echo "<ul>";
echo "<li>Zona horaria PHP: " . date_default_timezone_get() . "</li>";
echo "<li>Zona horaria MySQL: " . (isset($result['timezone']) ? $result['timezone'] : 'No disponible') . "</li>";
echo "<li>Funciones de utilidad: Disponibles</li>";
echo "<li>Formato de fechas: dd/mm/yyyy hh:mm</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><em>Prueba completada el " . format_datetime(get_current_datetime()) . "</em></p>";
?>
