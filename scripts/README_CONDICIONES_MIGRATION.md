# Migración de Condiciones de Herramientas Unidades

## Descripción del Problema

El sistema tiene una inconsistencia entre la base de datos y el código PHP:

- **Base de datos**: usa el campo `condicion` con valores `excelente`, `buena`, `regular`, `mala`
- **Código PHP**: busca el campo `condicion_actual` con valores `nueva`, `usada`, `reparada`, `para_reparacion`, `perdida`, `de_baja`

Esto causa que se muestre **"No definida"** en la interfaz porque el campo `condicion_actual` no existe.

## Solución

Ejecutar el script de migración que:

1. ✅ Crea el nuevo campo `condicion_actual` con el ENUM correcto
2. ✅ Migra los datos del campo antiguo al nuevo con mapeo de valores
3. ✅ Establece valor por defecto `nueva` para nuevas unidades
4. ⚠️ Opcionalmente elimina el campo antiguo (comentado por seguridad)

## Mapeo de Valores

```
Valor Antiguo → Valor Nuevo
────────────────────────────
excelente     → nueva
buena         → usada
regular       → reparada
mala          → para_reparacion
```

## Instrucciones de Ejecución

### Opción 1: Desde phpMyAdmin
1. Abrir phpMyAdmin en http://localhost/phpmyadmin
2. Seleccionar la base de datos `sistema_constructora`
3. Ir a la pestaña "SQL"
4. Copiar y pegar el contenido de `migrate_condiciones.sql`
5. Hacer clic en "Continuar"
6. Verificar los resultados de las consultas de verificación

### Opción 2: Desde línea de comandos
```bash
C:\xampp\mysql\bin\mysql.exe -u root sistema_constructora < scripts/migrate_condiciones.sql
```

## Verificación Post-Migración

El script incluye 2 consultas de verificación:

1. **Distribución de condiciones**: Muestra cuántas unidades hay en cada condición
2. **Unidades sin condición**: Debe mostrar 0 (todas deben tener condición asignada)

## Archivos Afectados

- `scripts/migrate_condiciones.sql` - Script de migración
- `modules/herramientas/add_unit.php` - Necesita actualización (ver nota abajo)

## Nota Importante: Actualización Pendiente

Después de ejecutar la migración, debemos actualizar `add_unit.php` para:
- Asignar `condicion_actual` al crear nuevas unidades
- Por defecto, nuevas unidades deberían tener condición `nueva`

## Estado de la Migración

- [ ] Ejecutar script de migración
- [ ] Verificar resultados
- [ ] Actualizar `add_unit.php` para incluir condicion_actual
- [ ] Probar creación de nuevas unidades
- [ ] Verificar visualización en view.php
