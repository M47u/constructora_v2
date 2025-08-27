# Corrección del Problema de Stock en Herramientas

## Problema Identificado

El sistema tenía un problema donde al eliminar una unidad de herramienta, el stock se reducía dos veces:
1. Una vez por el trigger `tr_herramientas_stock_delete` (correcto)
2. Otra vez por el código PHP que actualizaba manualmente el stock (incorrecto)

Esto causaba que si tenías stock 1, al eliminar una unidad quedara en -1.

## Archivos Corregidos

### 1. `modules/herramientas/delete_unit.php`
- **Problema**: Actualizaba manualmente el stock restando 1
- **Solución**: Eliminada la actualización manual, el trigger se encarga automáticamente

### 2. `modules/herramientas/add_unit.php`
- **Problema**: Actualizaba manualmente el stock sumando unidades
- **Solución**: Eliminada la actualización manual, el trigger se encarga automáticamente

### 3. `modules/herramientas/update_unit_status.php`
- **Problema**: Lógica compleja para manejar cambios de estado
- **Solución**: Simplificada, ahora usa un trigger para actualizaciones de estado

## Nuevo Trigger Creado

Se creó el trigger `tr_herramientas_stock_update` que maneja automáticamente las actualizaciones de estado de unidades.

## Instrucciones para Aplicar las Correcciones

### Paso 1: Ejecutar el Script SQL
```sql
-- Ejecutar el archivo: scripts/apply_herramientas_fix.sql
-- Este script:
-- 1. Crea el trigger para actualizaciones de estado
-- 2. Corrige el stock_total de todas las herramientas existentes
-- 3. Muestra un reporte de la corrección
```

### Paso 2: Verificar la Corrección
Después de ejecutar el script, verificar que:
- El stock_total coincida con el número real de unidades
- No haya valores negativos en stock_total
- Las operaciones de agregar/eliminar unidades funcionen correctamente

### Paso 3: Probar las Funcionalidades
1. Agregar una nueva unidad a una herramienta
2. Eliminar una unidad de una herramienta
3. Cambiar el estado de una unidad
4. Verificar que el stock se actualice correctamente en todos los casos

## Estructura de Triggers Final

- `tr_herramientas_stock_insert`: Se ejecuta al INSERTAR unidades
- `tr_herramientas_stock_delete`: Se ejecuta al ELIMINAR unidades  
- `tr_herramientas_stock_update`: Se ejecuta al ACTUALIZAR el estado de unidades

Todos los triggers usan el mismo enfoque: contar todas las unidades de la herramienta y actualizar el stock_total.

## Beneficios de la Corrección

1. **Consistencia**: El stock siempre refleja el número real de unidades
2. **Simplicidad**: No hay lógica duplicada entre PHP y triggers
3. **Confiabilidad**: Los triggers garantizan que el stock se actualice correctamente
4. **Mantenibilidad**: Código más limpio y fácil de mantener
