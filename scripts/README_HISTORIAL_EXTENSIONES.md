# Historial de Extensiones de Fechas de Préstamos

## Descripción
Sistema de auditoría para registrar todas las modificaciones de fechas de devolución programadas en los préstamos de herramientas.

## Características

### 1. **Trazabilidad Completa**
- Registra fecha anterior y fecha nueva
- Usuario que realizó el cambio
- Timestamp exacto de la modificación
- Opción de agregar motivo/justificación

### 2. **Integridad de Datos**
- Usa **transacciones** para garantizar que tanto la actualización como el registro en historial se completen o reviertan juntos
- Llaves foráneas para mantener integridad referencial
- Índices optimizados para consultas rápidas

### 3. **Beneficios**
- **Auditoría**: Saber quién y cuándo modificó fechas
- **Análisis**: Identificar préstamos con extensiones frecuentes
- **Reportes**: Generar estadísticas de extensiones por obra, empleado, etc.
- **Cumplimiento**: Documentación completa para auditorías internas/externas

## Instalación

```bash
# Ejecutar el script SQL
mysql -u usuario -p nombre_base_datos < scripts/add_historial_extensiones.sql
```

## Uso

### Consultar historial de un préstamo específico
```sql
SELECT h.*, u.nombre, u.apellido 
FROM historial_extensiones_prestamo h
JOIN usuarios u ON h.id_usuario_modifico = u.id_usuario
WHERE h.id_prestamo = 53
ORDER BY h.fecha_modificacion DESC;
```

### Préstamos con más extensiones
```sql
SELECT p.id_prestamo, p.numero_prestamo, COUNT(h.id_extension) as total_extensiones
FROM prestamos p
LEFT JOIN historial_extensiones_prestamo h ON p.id_prestamo = h.id_prestamo
GROUP BY p.id_prestamo
HAVING total_extensiones > 0
ORDER BY total_extensiones DESC;
```

### Extensiones por usuario
```sql
SELECT u.nombre, u.apellido, COUNT(h.id_extension) as total_extensiones
FROM historial_extensiones_prestamo h
JOIN usuarios u ON h.id_usuario_modifico = u.id_usuario
GROUP BY u.id_usuario
ORDER BY total_extensiones DESC;
```

## Mejoras Futuras
- [ ] Agregar campo de motivo obligatorio
- [ ] Notificaciones automáticas cuando se extiende una fecha
- [ ] Dashboard con métricas de extensiones
- [ ] Límite máximo de extensiones por préstamo
- [ ] Exportar historial a PDF/Excel

## Arquitectura

```
Usuario modifica fecha
        ↓
Validaciones Frontend
        ↓
AJAX Request
        ↓
Backend PHP
        ↓
BEGIN TRANSACTION
        ↓
    ┌────────────────┐
    │ 1. Obtener     │
    │ fecha anterior │
    └────────────────┘
        ↓
    ┌────────────────┐
    │ 2. Actualizar  │
    │ fecha nueva    │
    └────────────────┘
        ↓
    ┌────────────────┐
    │ 3. Insertar    │
    │ en historial   │
    └────────────────┘
        ↓
COMMIT TRANSACTION
        ↓
Respuesta exitosa
```

## Seguridad
- Validación de permisos (solo Admin y Responsables)
- Validación de fechas (no permite fechas pasadas)
- Transacciones ACID para integridad
- Logs de errores para debugging
