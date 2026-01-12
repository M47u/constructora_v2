# Sistema de Etapas de Pedidos

## Descripción

Este sistema implementa un flujo completo de etapas para los pedidos de materiales, permitiendo un seguimiento detallado desde la creación hasta la recepción final.

## Etapas del Pedido

El sistema maneja 4 etapas principales que deben seguirse en orden:

1. **Creación** - El usuario solicita el pedido
2. **Aprobación** - Un responsable o administrador aprueba el pedido
3. **Retiro** - El material es retirado del almacén (se descuenta del stock)
4. **Recibido** - El material es recibido en destino (finaliza el pedido)

### Estados Adicionales

- **Cancelado** - Puede aplicarse desde cualquier etapa
- **Entregado/En Camino** - Estados legacy mantenidos por compatibilidad

## Flujo de Trabajo

```
Pendiente → Aprobado → Retirado → Recibido
     ↓          ↓          ↓
     └──────────┴──────────┴──> Cancelado
```

### Reglas del Flujo

1. **No se puede retroceder**: Una vez que un pedido avanza a una etapa, no puede volver a etapas anteriores
2. **No se puede saltar etapas**: Debe completarse cada etapa en orden (excepto para cancelar)
3. **Fechas coherentes**: Las fechas de etapas posteriores deben ser >= a las anteriores
4. **Descontrol de stock**: El stock se descuenta cuando el pedido pasa a estado "Retirado"

## Archivos Modificados/Creados

### Scripts de Base de Datos

- `scripts/add_pedidos_stages.sql` - Script de migración principal
  - Agrega nuevas columnas: `id_retirado_por`, `fecha_retiro`, `id_recibido_por`, `fecha_recibido`
  - Actualiza ENUM de estados
  - Crea tabla `historial_edicion_etapas_pedidos`
  - Crea vista `vista_pedidos_etapas_completas`
  - Implementa trigger de validación `validate_pedido_stage_order`

### Archivos PHP

1. **process.php** - Procesamiento de pedidos
   - Actualizado para manejar estados `retirado` y `recibido`
   - Lógica de descuento de stock en etapa de retiro
   - Validación de flujo de etapas

2. **edit_stages.php** - Edición de etapas (Solo Admin)
   - Permite asignar usuarios a cada etapa
   - Permite editar fechas de cada etapa
   - Registra todos los cambios en historial
   - Muestra historial de ediciones

3. **view.php** - Vista de pedido
   - Muestra timeline visual de las 4 etapas
   - Indica qué usuario procesó cada etapa
   - Muestra fechas de cada etapa
   - Botón de edición para administradores

## Instalación

### Paso 1: Ejecutar Script de Migración

```bash
mysql -u usuario -p nombre_base_datos < scripts/add_pedidos_stages.sql
```

O desde phpMyAdmin, importar el archivo `add_pedidos_stages.sql`

### Paso 2: Verificar Tablas Creadas

Verificar que se hayan creado correctamente:
- Columnas nuevas en `pedidos_materiales`
- Tabla `historial_edicion_etapas_pedidos`
- Vista `vista_pedidos_etapas_completas`
- Trigger `validate_pedido_stage_order`

### Paso 3: Verificar Permisos

Asegurarse de que los usuarios con rol `ROLE_ADMIN` tengan acceso a:
- `edit_stages.php`

Los usuarios con rol `ROLE_RESPONSABLE` pueden:
- Procesar pedidos (aprobar, retirar, marcar como recibido)

## Funcionalidades para Administradores

### Editar Etapas

Los administradores pueden:

1. **Asignar/Cambiar Usuarios** en cada etapa
   - Usuario solicitante (creación)
   - Usuario que aprobó
   - Usuario que retiró
   - Usuario que recibió

2. **Editar Fechas** de cada etapa
   - Fecha de creación
   - Fecha de aprobación
   - Fecha de retiro
   - Fecha de recepción

3. **Ver Historial Completo** de cambios
   - Todos los cambios quedan registrados
   - Se guarda: usuario editor, IP, fecha, valores anteriores y nuevos

### Validaciones

El sistema valida automáticamente:

- ✅ Fechas coherentes (no anteriores a etapas previas)
- ✅ Orden correcto de etapas
- ✅ No permitir retrocesos en estados
- ✅ Usuarios válidos y activos
- ✅ Permisos de usuario

## Gestión de Stock

### Cuando se Retira un Pedido

Al marcar un pedido como "Retirado":
1. Se descuenta la cantidad solicitada del stock actual
2. Se registra en logs_sistema
3. No se puede cancelar sin devolver el stock manualmente

### Cuando se Cancela

Si se cancela un pedido en estado "Pendiente":
- El stock NO se devuelve (porque nunca se descontó)

Si se cancela después de "Retirado":
- Requiere intervención manual para devolver stock

## Consultas Útiles

### Ver Estado Completo de un Pedido

```sql
SELECT * FROM vista_pedidos_etapas_completas WHERE id_pedido = X;
```

### Ver Historial de Ediciones

```sql
SELECT * FROM historial_edicion_etapas_pedidos 
WHERE id_pedido = X 
ORDER BY fecha_edicion DESC;
```

### Pedidos por Estado

```sql
SELECT estado, COUNT(*) as total 
FROM pedidos_materiales 
GROUP BY estado;
```

## Mejoras Futuras Sugeridas

- [ ] Notificaciones automáticas al cambiar de etapa
- [ ] Dashboard con KPIs de tiempo promedio por etapa
- [ ] Firma digital en cada etapa
- [ ] Subir fotos/documentos en cada etapa
- [ ] API REST para integración con app móvil
- [ ] Reportes de rendimiento por usuario
- [ ] Geolocalización al retirar/recibir

## Soporte

Para dudas o problemas con este sistema, contactar al desarrollador o revisar los logs en:
- `logs_sistema` - Tabla de base de datos
- Logs PHP del servidor

## Versión

- **Versión**: 1.0.0
- **Fecha**: Enero 2026
- **Autor**: Sistema de Gestión Constructora
