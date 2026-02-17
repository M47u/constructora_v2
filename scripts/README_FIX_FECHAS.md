# Corrección de Fechas Incorrectas en Pedidos

## Problema Identificado

El reporte de métricas mostraba **tiempos negativos** en algunas etapas debido a:

1. **Fechas en orden incorrecto**: 20+ pedidos tenían `fecha_aprobacion` anterior a `fecha_pedido`
2. **Mezcla de fuentes de datos**: La consulta SQL usaba `COALESCE()` entre `seguimiento_pedidos` y columnas directas, causando inconsistencias
3. **Datos contradictorios**: `seguimiento_pedidos` y las columnas directas tenían fechas diferentes para el mismo pedido

### Ejemplo del Problema

**Pedido PED20260010:**
- `seguimiento_pedidos` decía: Retirado 06/01 08:54
- Columnas directas decían: Picking 06/01 09:34, Retiro 06/01 11:50
- **Cálculo SQL**: 08:54 - 09:34 = **-1.33 horas** ❌

## Solución Implementada

### 1. Corrección de la Consulta SQL

**Archivo modificado:** `modules/reportes/metricas_pedidos.php`

**Cambios:**
- ✅ Usa **solo columnas directas** (`fecha_aprobacion`, `fecha_picking`, `fecha_retiro`, `fecha_recibido`)
- ✅ Elimina `COALESCE()` con `seguimiento_pedidos` para evitar inconsistencias
- ✅ Valida que las fechas estén en **orden correcto** (fecha_fin >= fecha_inicio)
- ✅ Solo calcula promedios cuando **ambas fechas existen y son válidas**
- ✅ Rango de fechas por defecto: **últimos 6 meses** en vez del mes actual

**Nueva lógica:**
```sql
-- Antes (problema):
COALESCE(
    (SELECT MIN(fecha_cambio) FROM seguimiento_pedidos WHERE estado_nuevo = 'picking'),
    p.fecha_picking
)

-- Ahora (correcto):
p.fecha_picking  -- Solo usa la columna directa
WHERE p.fecha_picking IS NOT NULL 
  AND p.fecha_picking >= p.fecha_aprobacion  -- Valida orden
```

### 2. Script de Corrección de Datos

**Archivo:** `scripts/fix_fechas_incorrectas.sql`

Este script corrige fechas en orden incorrecto en la base de datos:

**Paso 1:** Identifica pedidos con `fecha_aprobacion < fecha_pedido`  
**Paso 2:** Crea backup de los datos incorrectos  
**Paso 3:** Corrige diferencias de timezone (agrega 3 horas si la diferencia es ≤4h)  
**Paso 4:** Para el resto, iguala aprobación = pedido + 1 minuto  
**Paso 5:** Verifica que ya no hay inconsistencias  

## Cómo Usar

### Opción 1: Solo Actualizar el Código (Recomendado)

Si no te importa excluir los pedidos con fechas incorrectas de las estadísticas:

1. Los cambios en `metricas_pedidos.php` ya ignoran pedidos con fechas inválidas
2. Recarga la página de métricas
3. Los tiempos negativos desaparecerán (esos pedidos simplemente no se cuentan)

### Opción 2: Corregir los Datos Históricos

**⚠️ ADVERTENCIA:** Esto modifica datos históricos. Haz backup primero.

```bash
# 1. Backup completo
mysqldump -u usuario -p sistema_constructora > backup_antes_fix_fechas.sql

# 2. Ejecutar script de corrección
mysql -u usuario -p sistema_constructora < scripts/fix_fechas_incorrectas.sql

# 3. Verificar resultados
```

O desde phpMyAdmin:
1. Importa `scripts/fix_fechas_incorrectas.sql`
2. Ejecuta línea por línea y verifica los resultados
3. El backup se crea automáticamente en `backup_pedidos_fechas_incorrectas`

## Verificación

### Antes de las Correcciones
```
Picking → Retiro: -1.3 horas ❌
Retiro → Entrega: -1,148.7 horas ❌
```

### Después de las Correcciones
```
Picking → Retiro: X.X horas (solo pedidos válidos)
Retiro → Entrega: X.X horas (solo pedidos válidos)
Mensaje: "Sin datos" si no hay pedidos con esa etapa registrada
```

## Prevención Futura

Para evitar este problema en el futuro:

1. **Triggers de validación:** Ya existen pero pueden no estar activos
2. **Registro correcto de estados:** Asegúrate de que el personal usa el módulo de pedidos para cambiar estados
3. **No editar fechas manualmente** en la base de datos
4. **Usar proceso_pedidos.php** para todas las transiciones de estado

## Archivos Modificados

- ✅ `modules/reportes/metricas_pedidos.php` - Consulta SQL corregida
- ✅ `scripts/fix_fechas_incorrectas.sql` - Script de corrección de datos
- ✅ `scripts/README_FIX_FECHAS.md` - Este archivo

## Diagnóstico

Para diagnosticar problemas similares en el futuro, usa:

```
http://localhost/constructora_v2/diagnostico_tiempos_negativos.php
```

Este script muestra:
- Pedidos con fechas en orden incorrecto
- Comparación entre seguimiento_pedidos y columnas directas
- Cálculo exacto de los promedios

## Notas Técnicas

### ¿Por qué ocurrió?

1. **Timezone UTC vs Local:** Las fechas se guardaron en UTC pero se mostraban en hora local (-3h)
2. **Migraciones anteriores:** Algunos scripts anteriores copiaron fechas sin validar el orden
3. **Ediciones manuales:** Administradores editaron fechas directamente en la BD

### ¿Por qué usar solo columnas directas?

- `seguimiento_pedidos` es histórico (puede tener ediciones)
- Las columnas directas son la "fuente de verdad" actual
- `COALESCE()` entre ambas creaba resultados impredecibles
- Más simple y mantenible

## Soporte

Si después de aplicar la corrección sigues viendo tiempos negativos:

1. Ejecuta el diagnóstico
2. Revisa que el script SQL se ejecutó completamente
3. Verifica que no haya triggers que estén modificando fechas
4. Consulta los logs en `backup_pedidos_fechas_incorrectas`
