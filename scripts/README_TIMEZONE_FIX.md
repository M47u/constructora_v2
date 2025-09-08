# Corrección de Zona Horaria - Sistema Constructora

## Problema Identificado
El sistema tenía inconsistencias en el manejo de fechas y horas debido a:
1. Falta de configuración de zona horaria en la base de datos
2. Uso inconsistente de funciones de fecha entre PHP y MySQL
3. No había funciones centralizadas para el manejo de fechas

## Soluciones Implementadas

### 1. Configuración de Zona Horaria en PHP
- **Archivo**: `config/config.php`
- **Línea 16**: `date_default_timezone_set('America/Argentina/Buenos_Aires');`
- **Zona horaria**: -03:00 (Argentina)

### 2. Configuración de Zona Horaria en Base de Datos
- **Archivo**: `config/database.php`
- **Línea 25**: `$this->conn->exec("SET time_zone = '-03:00'");`
- **Efecto**: Cada conexión a la base de datos usa la zona horaria correcta

### 3. Funciones de Utilidad para Fechas
- **Archivo**: `config/config.php` (líneas 70-102)
- **Funciones agregadas**:
  - `format_date($date, $format = 'd/m/Y')`
  - `format_datetime($datetime, $format = 'd/m/Y H:i')`
  - `get_current_date($format = 'Y-m-d')`
  - `get_current_datetime($format = 'Y-m-d H:i:s')`
  - `is_date_valid($date)`
  - `add_days_to_date($date, $days, $format = 'Y-m-d')`
  - `get_date_difference($date1, $date2)`

### 4. Archivos Actualizados
- `modules/pedidos/create.php`: Uso de `get_current_date()` en lugar de `date('Y-m-d')`
- `modules/reportes/herramientas_prestadas.php`: Uso de `get_current_date()`
- `modules/herramientas/create_prestamo.php`: Uso de `format_datetime()` y `get_current_datetime()`

### 5. Script SQL de Corrección
- **Archivo**: `scripts/fix_timezone.sql`
- **Propósito**: Configurar permanentemente la zona horaria en MySQL
- **Uso**: Ejecutar en phpMyAdmin o línea de comandos MySQL

## Instrucciones de Aplicación

### Paso 1: Ejecutar Script SQL
```sql
-- Ejecutar en phpMyAdmin o MySQL CLI
SOURCE scripts/fix_timezone.sql;
```

### Paso 2: Verificar Configuración
1. Abrir cualquier página del sistema
2. Verificar que las fechas se muestren correctamente
3. Crear un nuevo préstamo o pedido para verificar timestamps

### Paso 3: Verificación de Funcionamiento
- Las fechas deben mostrarse en formato argentino (dd/mm/yyyy)
- Las horas deben estar en zona horaria -03:00
- Los timestamps de la base de datos deben coincidir con la hora local

## Beneficios de la Corrección

1. **Consistencia**: Todas las fechas y horas usan la misma zona horaria
2. **Precisión**: Los timestamps son exactos para Argentina
3. **Mantenibilidad**: Funciones centralizadas para manejo de fechas
4. **Escalabilidad**: Fácil cambio de zona horaria si es necesario

## Notas Importantes

- La zona horaria configurada es `America/Argentina/Buenos_Aires` (-03:00)
- Todas las funciones de fecha respetan esta configuración
- Los timestamps en la base de datos se almacenan en UTC pero se muestran en hora local
- Para cambiar la zona horaria, modificar solo `config/config.php` y `config/database.php`

## Archivos Modificados

1. `config/config.php` - Funciones de utilidad para fechas
2. `config/database.php` - Configuración de zona horaria en conexión
3. `modules/pedidos/create.php` - Uso de funciones centralizadas
4. `modules/reportes/herramientas_prestadas.php` - Uso de funciones centralizadas
5. `modules/herramientas/create_prestamo.php` - Uso de funciones centralizadas
6. `scripts/fix_timezone.sql` - Script de corrección SQL (nuevo)
7. `scripts/README_TIMEZONE_FIX.md` - Esta documentación (nuevo)
