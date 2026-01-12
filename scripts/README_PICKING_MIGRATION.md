# Migración: Agregar Etapa "Picking" al Sistema de Pedidos

## 📋 Descripción General

Esta migración agrega una nueva etapa llamada **"Picking"** (preparación de materiales) al flujo de pedidos de materiales. La etapa se ubica entre "Aprobación" y "Retiro", creando el siguiente flujo de 5 etapas:

```
1. Creación → 2. Aprobación → 3. Picking → 4. Retiro → 5. Entrega
```

## 🎯 Objetivo

Permitir el seguimiento de la etapa de preparación de materiales en el almacén, donde un usuario específico se encarga de separar y organizar los materiales aprobados antes de que sean retirados.

## 📦 Archivos Involucrados

### Scripts SQL
- `scripts/add_picking_stage.sql` - Script de migración principal (bases existentes)
- `scripts/add_pedidos_stages.sql` - Script completo del sistema de etapas (nuevas instalaciones)

### Archivos PHP Modificados
- `modules/pedidos/edit_stages.php` - Formulario de edición de etapas
- `modules/pedidos/view.php` - Visualización de pedidos con timeline
- `modules/pedidos/list.php` - Listado de pedidos con badges
- `modules/pedidos/process.php` - Procesamiento de pedidos
- `modules/reportes/metricas_pedidos.php` - Dashboard de métricas

## 🔧 Cambios en Base de Datos

### Nuevas Columnas
```sql
ALTER TABLE pedidos_materiales
    ADD COLUMN id_picking_por INT NULL,
    ADD COLUMN fecha_picking TIMESTAMP NULL;
```

### ENUMs Actualizados
```sql
-- Estado ahora incluye 'picking'
ENUM('pendiente','aprobado','picking','retirado','recibido','en_camino','entregado','devuelto','cancelado')
```

### Nueva Foreign Key
```sql
CONSTRAINT fk_pedidos_picking_por 
FOREIGN KEY (id_picking_por) REFERENCES usuarios(id_usuario)
```

### Índice Agregado
```sql
CREATE INDEX idx_picking_por ON pedidos_materiales(id_picking_por)
```

### Trigger Actualizado
- `before_update_pedidos_materiales_etapas`: Ahora valida coherencia de picking

### Vista Actualizada
- `vista_pedidos_etapas_completas`: Incluye joins para usuario y fecha de picking

## 📝 Instrucciones de Migración

### Bases de Datos Existentes

**Para entorno LOCAL:**
```bash
# 1. Hacer backup
mysqldump -u root constructora > backup_antes_picking.sql

# 2. Ejecutar migración
mysql -u root constructora < scripts/add_picking_stage.sql
```

**Para entorno PRODUCCIÓN (Hostinger):**
```bash
# 1. Hacer backup desde phpMyAdmin o CLI
mysqldump -h HOST -u USER -p DATABASE > backup_antes_picking.sql

# 2. Ejecutar migración
mysql -h HOST -u USER -p DATABASE < scripts/add_picking_stage.sql
```

### Nuevas Instalaciones
Para instalaciones nuevas, usar directamente:
```bash
mysql -u root constructora < scripts/add_pedidos_stages.sql
```

## ✅ Verificación Post-Migración

El script incluye verificaciones automáticas al final:

```sql
-- Verificar columnas
SELECT * FROM information_schema.COLUMNS 
WHERE TABLE_NAME = 'pedidos_materiales' 
AND COLUMN_NAME IN ('id_picking_por', 'fecha_picking');

-- Verificar vista
SELECT * FROM vista_pedidos_etapas_completas LIMIT 1;

-- Verificar trigger
SHOW TRIGGERS LIKE 'pedidos_materiales';
```

### Pruebas Manuales
1. Crear un pedido nuevo
2. Aprobar el pedido
3. Asignar picking desde `modules/pedidos/edit_stages.php`
4. Verificar timeline en `modules/pedidos/view.php`
5. Verificar métricas en `modules/reportes/metricas_pedidos.php`

## 🎨 Cambios Visuales

### Nuevos Badges
```php
// En list.php y process.php
'picking' => 'bg-warning' + 'box-seam' icon + 'En Picking' text
```

### Métricas Nuevas
- **Aprobación → Picking**: Tiempo promedio entre aprobación y picking
- **Picking → Retiro**: Tiempo promedio entre picking y retiro

### Timeline Actualizado
```
1. Creación (info)
2. Aprobación (info)
3. Picking (warning) ← NUEVO
4. Retiro (primary)
5. Recibido (success)
```

## 🔒 Validaciones Implementadas

### Backend (PHP)
- Si se asigna `id_picking_por`, debe haber `fecha_picking`
- `fecha_picking` debe ser >= `fecha_aprobacion`
- `fecha_picking` debe ser <= `fecha_retiro`
- Coherencia con todas las fechas anteriores y posteriores

### Base de Datos (Triggers)
- No se puede picking sin aprobación previa
- No se puede retirar sin picking (si picking fue iniciado)
- Fechas deben seguir orden cronológico

### Frontend (JavaScript)
- Validación en tiempo real de fechas
- Actualización automática del badge de estado
- Highlight de campos inválidos

## 📊 Flujo de Trabajo Actualizado

### Antes (4 etapas)
```
Pendiente → Aprobado → Retirado → Entregado
```

### Ahora (5 etapas)
```
Pendiente → Aprobado → Picking → Retirado → Entregado
           (Admin)    (Almacén)  (Chofer)   (Obra)
```

## 🚨 Consideraciones Importantes

1. **Retrocompatibilidad**: Los pedidos existentes sin `id_picking_por` seguirán funcionando normalmente
2. **Queries híbridas**: Las métricas usan `COALESCE()` para buscar en múltiples fuentes
3. **Estados automáticos**: El estado se actualiza automáticamente según las etapas completadas
4. **Historial completo**: Todos los cambios se registran en `historial_edicion_etapas_pedidos`

## 🐛 Troubleshooting

### Error: "Column already exists"
**Solución**: El script usa `IF NOT EXISTS`, es seguro re-ejecutarlo

### Error: "Constraint already exists"
**Solución**: El script verifica existencia antes de crear

### Métricas muestran 0.0 horas
**Verificar**:
1. Que existan pedidos con estado 'entregado'
2. Que las fechas estén en el rango seleccionado
3. Que los pedidos tengan picking completado

### Timeline no muestra picking
**Verificar**:
1. Que la vista `vista_pedidos_etapas_completas` esté actualizada
2. Que el JOIN con `usuarios upk` esté en la consulta
3. Que los campos existan en la BD

## 📅 Historial de Cambios

- **2025-01-09**: Creación inicial del sistema de picking
  - Agregadas columnas `id_picking_por` y `fecha_picking`
  - Actualizado ENUM de estados
  - Creado script de migración `add_picking_stage.sql`
  - Actualizados 5 archivos PHP principales
  - Agregadas métricas de tiempo picking

## 👤 Autor
Sistema desarrollado para Constructora - Módulo de Pedidos de Materiales

## 📄 Licencia
Uso interno exclusivo
