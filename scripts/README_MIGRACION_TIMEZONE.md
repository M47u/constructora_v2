# Migración de Zona Horaria - Datos Existentes

## Problema Identificado

Los datos en la base de datos se están guardando con **UTC (GMT +00:00)** pero necesitan estar en **hora de Argentina (GMT -03:00)**.

## Solución Implementada

### 1. Configuración Actual (Ya implementado)

✅ **PHP** configurado con zona horaria Argentina:
- Archivo: `config/config.php`
- Líneas 16-17: `date_default_timezone_set('America/Argentina/Buenos_Aires');`

✅ **MySQL** configurado para nuevas conexiones:
- Archivo: `config/database.php`
- Línea 24: `$this->conn->exec("SET time_zone = '-03:00'");`

### 2. Migración de Datos Existentes (Nuevo)

⚠️ **IMPORTANTE**: Los datos ya guardados tienen 3 horas de diferencia y deben ser corregidos.

## Pasos para Ejecutar la Migración

### Paso 1: Hacer Backup de la Base de Datos

**CRÍTICO**: Antes de ejecutar cualquier script, hacer backup completo.

```bash
# Desde la línea de comandos de MySQL o phpMyAdmin
mysqldump -u root -p sistema_constructora > backup_antes_migracion_timezone.sql
```

O desde **phpMyAdmin**:
1. Ir a la base de datos `sistema_constructora`
2. Clic en pestaña "Exportar"
3. Seleccionar "Exportación rápida"
4. Formato: SQL
5. Clic en "Continuar"
6. Guardar el archivo con nombre: `backup_antes_migracion_timezone_AAAAMMDD.sql`

### Paso 2: Ejecutar el Script de Migración

#### Opción A: Desde MySQL Workbench / Cliente MySQL

```sql
USE sistema_constructora;
SOURCE C:/xampp/htdocs/constructora_v2/scripts/migrate_timezone_existing_data.sql;
```

#### Opción B: Desde phpMyAdmin

1. Abrir phpMyAdmin (http://localhost/phpmyadmin)
2. Seleccionar base de datos `sistema_constructora`
3. Ir a pestaña "SQL"
4. Copiar todo el contenido del archivo `migrate_timezone_existing_data.sql`
5. Pegar en el editor SQL
6. Clic en "Continuar"

#### Opción C: Desde línea de comandos

```bash
cd C:\xampp\mysql\bin
mysql -u root -p sistema_constructora < C:\xampp\htdocs\constructora_v2\scripts\migrate_timezone_existing_data.sql
```

### Paso 3: Verificar los Resultados

El script muestra automáticamente:

1. **Estado inicial**: Fecha del servidor antes de migración
2. **Resumen**: Cantidad de registros actualizados por tabla
3. **Ejemplos**: Últimos 5 pedidos y tareas con fechas corregidas
4. **Estado final**: Fecha del servidor después de migración

#### Verificación Manual

Revisa algunos registros específicos:

```sql
-- Ver pedidos recientes
SELECT 
    numero_pedido,
    fecha_pedido,
    fecha_aprobacion,
    estado
FROM pedidos_materiales
ORDER BY id_pedido DESC
LIMIT 10;

-- Ver tareas recientes
SELECT 
    titulo,
    fecha_inicio,
    fecha_fin,
    estado
FROM tareas
ORDER BY id_tarea DESC
LIMIT 10;

-- Ver préstamos
SELECT 
    fecha_prestamo,
    fecha_devolucion_programada,
    estado
FROM prestamos
ORDER BY id_prestamo DESC
LIMIT 10;
```

**Validar que las fechas ahora tengan 3 horas MENOS** que antes.

Ejemplo:
- **ANTES**: `2026-01-06 15:30:00` (UTC)
- **DESPUÉS**: `2026-01-06 12:30:00` (Argentina)

### Paso 4: Verificar en la Aplicación

1. Ingresar a la aplicación web
2. Revisar pedidos, tareas, préstamos
3. Verificar que las fechas se vean correctas
4. Crear un registro de prueba y confirmar que la fecha es correcta

## Tablas Afectadas por la Migración

El script actualiza las siguientes tablas y columnas:

| Tabla | Columnas Actualizadas |
|-------|----------------------|
| **usuarios** | fecha_creacion, fecha_actualizacion, ultimo_acceso |
| **obras** | fecha_inicio, fecha_fin, fecha_creacion, fecha_actualizacion |
| **materiales** | fecha_creacion, fecha_actualizacion |
| **pedidos_materiales** | fecha_pedido, fecha_aprobacion, fecha_retiro, fecha_recibido, fecha_entrega, fecha_creacion, fecha_actualizacion |
| **seguimiento_pedidos** | fecha_estado |
| **historial_edicion_etapas_pedidos** | fecha_edicion |
| **herramientas** | fecha_compra, fecha_creacion, fecha_actualizacion |
| **prestamos** | fecha_prestamo, fecha_devolucion_programada, fecha_devolucion_real, fecha_creacion, fecha_actualizacion |
| **devoluciones** | fecha_devolucion |
| **tareas** | fecha_inicio, fecha_fin, fecha_creacion, fecha_actualizacion |
| **transportes** | fecha_creacion, fecha_actualizacion |
| **movimientos_stock** | fecha_movimiento |
| **notificaciones** | fecha_creacion, fecha_lectura |

## ¿Qué hace exactamente el script?

Para cada columna de tipo `DATETIME` o `TIMESTAMP`:

```sql
UPDATE tabla 
SET columna_fecha = DATE_SUB(columna_fecha, INTERVAL 3 HOUR)
WHERE columna_fecha IS NOT NULL;
```

Esto **resta 3 horas** a cada fecha existente.

## Datos Nuevos (después de la migración)

Una vez ejecutado el script:

✅ Los datos **nuevos** se guardarán correctamente en GMT -03:00 gracias a:
- `date_default_timezone_set('America/Argentina/Buenos_Aires')` en PHP
- `SET time_zone = '-03:00'` en cada conexión MySQL

✅ Los datos **migrados** estarán en GMT -03:00

✅ Todo el sistema estará sincronizado

## Precauciones

⚠️ **Este script debe ejecutarse UNA SOLA VEZ**

Si se ejecuta múltiples veces, restará 3 horas cada vez, causando fechas incorrectas.

### ¿Cómo saber si ya se ejecutó?

Crear un registro de control:

```sql
CREATE TABLE IF NOT EXISTS migraciones_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre_migracion VARCHAR(100) NOT NULL,
    fecha_ejecucion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ejecutado_por VARCHAR(100),
    UNIQUE KEY (nombre_migracion)
);

-- Registrar esta migración
INSERT INTO migraciones_sistema (nombre_migracion, ejecutado_por) 
VALUES ('migrate_timezone_existing_data', USER());
```

Antes de ejecutar el script principal, verificar:

```sql
SELECT * FROM migraciones_sistema 
WHERE nombre_migracion = 'migrate_timezone_existing_data';
```

Si retorna resultados, **NO ejecutar nuevamente**.

## Rollback (Si algo sale mal)

Si necesitas revertir los cambios:

### Opción 1: Restaurar el Backup

```bash
mysql -u root -p sistema_constructora < backup_antes_migracion_timezone.sql
```

### Opción 2: Sumar 3 horas nuevamente

Cambiar `DATE_SUB` por `DATE_ADD` en todas las líneas del script y ejecutar nuevamente.

## Configuración de MySQL para el Futuro

Para que MySQL siempre use GMT -03:00 (opcional pero recomendado):

Editar archivo de configuración de MySQL (`my.ini` en Windows):

```ini
[mysqld]
default-time-zone = '-03:00'
```

Reiniciar MySQL:

```bash
# Desde servicios de Windows o
net stop mysql
net start mysql
```

## Verificación Final

Ejecutar archivo de prueba existente:

```
http://localhost/constructora_v2/test_timezone.php
```

Debe mostrar:
- ✅ Zona horaria PHP: `America/Argentina/Buenos_Aires`
- ✅ Zona horaria MySQL: `-03:00`
- ✅ Sincronización: Las fechas están sincronizadas

## Soporte

Si encuentras problemas durante la migración:

1. **NO ejecutar el script nuevamente**
2. Restaurar el backup
3. Contactar al administrador del sistema
4. Revisar logs de MySQL para errores

## Checklist de Ejecución

- [ ] Backup de base de datos realizado
- [ ] Backup verificado y descargado
- [ ] Script de migración ejecutado
- [ ] Resultados del script revisados
- [ ] Verificación manual de datos realizada
- [ ] Pruebas en aplicación web completadas
- [ ] Registro de migración guardado
- [ ] Documentación actualizada

---

**Fecha de creación**: Enero 2026  
**Versión**: 1.0  
**Autor**: Sistema Constructora v2
