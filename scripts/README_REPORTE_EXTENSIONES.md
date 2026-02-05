# Sistema de Reportes de Extensiones de Préstamos

## 📋 Descripción General

Este módulo proporciona una solución completa para visualizar, analizar y exportar el historial de extensiones de fechas de devolución de préstamos de herramientas. Incluye estadísticas en tiempo real, filtros avanzados y opciones de exportación profesionales.

## 🎯 Características Principales

### 1. **Visualización de Datos**
- **Tabla completa** con todas las extensiones registradas
- **Estadísticas en tiempo real**:
  - Total de extensiones
  - Total de días extendidos
  - Promedio de días por extensión
  - Obras involucradas
- **Información detallada** por cada extensión:
  - ID de extensión y préstamo
  - Obra y empleado
  - Herramientas involucradas
  - Fechas anterior y nueva
  - Días extendidos
  - Motivo de la extensión
  - Usuario que realizó el cambio
  - Fecha/hora de modificación

### 2. **Filtros Avanzados**
- **Rango de fechas**: Desde - Hasta
- **Por obra**: Filtrar extensiones de una obra específica
- **Por usuario**: Ver solo extensiones realizadas por un usuario particular
- **Combinación**: Todos los filtros pueden combinarse

### 3. **Exportación de Datos**

#### Exportación a Excel (CSV)
- Archivo `.csv` compatible con Microsoft Excel y Google Sheets
- Codificación UTF-8 con BOM para caracteres especiales
- Delimitador `;` (punto y coma) para compatibilidad internacional
- Incluye todos los campos visibles en el reporte
- Nombre de archivo: `extensiones_prestamos_YYYY-MM-DD_HHMMSS.csv`

#### Exportación a PDF
- Documento profesional en formato horizontal (landscape)
- Incluye:
  - Título y período del reporte
  - Fecha y hora de generación
  - Estadísticas resumidas
  - Tabla completa de datos
  - Motivos de extensión (si están disponibles)
  - Pie de página informativo
- Nombre de archivo: `extensiones_prestamos_YYYY-MM-DD_HHMMSS.pdf`

## 📂 Estructura de Archivos

```
modules/reportes/
├── extensiones_prestamos.php          # Página principal del reporte
├── exportar_extensiones_excel.php     # Script de exportación CSV/Excel
└── exportar_extensiones_pdf.php       # Script de exportación PDF

scripts/
├── add_historial_extensiones.sql      # Script de creación de tabla
└── README_HISTORIAL_EXTENSIONES.md    # Documentación de la tabla

includes/
└── header.php                          # Menú actualizado con enlace

modules/reportes/
└── index.php                           # Dashboard de reportes actualizado
```

## 🗄️ Base de Datos

### Tabla: `historial_extensiones_prestamo`

```sql
CREATE TABLE historial_extensiones_prestamo (
    id_extension INT AUTO_INCREMENT PRIMARY KEY,
    id_prestamo INT NOT NULL,
    fecha_anterior DATE NULL,
    fecha_nueva DATE NOT NULL,
    id_usuario_modifico INT NOT NULL,
    fecha_modificacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    motivo VARCHAR(500) NULL,
    FOREIGN KEY (id_prestamo) REFERENCES prestamos(id_prestamo) ON DELETE CASCADE,
    FOREIGN KEY (id_usuario_modifico) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    INDEX idx_id_prestamo (id_prestamo),
    INDEX idx_fecha_modificacion (fecha_modificacion)
);
```

## 🔐 Permisos de Acceso

### Usuarios Autorizados
- **Administrador** (ROLE_ADMIN): Acceso completo
- **Responsable de Obra** (ROLE_RESPONSABLE): Acceso completo

### Restricciones
- Otros roles no tienen acceso al módulo de reportes
- Redirección automática al dashboard si no hay permisos

## 📊 Consulta SQL Principal

```sql
SELECT 
    h.id_extension,
    h.id_prestamo,
    h.fecha_anterior,
    h.fecha_nueva,
    h.motivo,
    h.fecha_modificacion,
    u.nombre as usuario_nombre,
    u.apellido as usuario_apellido,
    emp.nombre as empleado_nombre,
    emp.apellido as empleado_apellido,
    o.nombre_obra,
    o.localidad as obra_localidad,
    DATEDIFF(h.fecha_nueva, h.fecha_anterior) as dias_extendidos,
    (SELECT GROUP_CONCAT(CONCAT(her.marca, ' ', her.modelo) SEPARATOR ', ')
     FROM detalle_prestamo dp
     JOIN herramientas_unidades hu ON dp.id_unidad = hu.id_unidad
     JOIN herramientas her ON hu.id_herramienta = her.id_herramienta
     WHERE dp.id_prestamo = p.id_prestamo
     LIMIT 3) as herramientas
FROM historial_extensiones_prestamo h
JOIN usuarios u ON h.id_usuario_modifico = u.id_usuario
JOIN prestamos p ON h.id_prestamo = p.id_prestamo
JOIN usuarios emp ON p.id_empleado = emp.id_usuario
JOIN obras o ON p.id_obra = o.id_obra
WHERE [condiciones de filtro]
ORDER BY h.fecha_modificacion DESC
```

## 🚀 Uso del Sistema

### Acceso al Reporte

1. **Desde el menú principal**:
   - Navegación → Reportes → Extensiones de Préstamos

2. **Desde el Dashboard de Reportes**:
   - Dashboard → Reportes → Extensiones de Préstamos

### Aplicar Filtros

1. Seleccionar rango de fechas (Desde - Hasta)
2. Opcionalmente seleccionar Obra específica
3. Opcionalmente seleccionar Usuario que modificó
4. Hacer clic en "Filtrar"
5. Para limpiar filtros, hacer clic en "Limpiar Filtros"

### Exportar Datos

#### Excel/CSV:
```
1. Aplicar filtros deseados
2. Clic en "Exportar a Excel"
3. El archivo se descarga automáticamente
4. Abrir con Excel/LibreOffice/Google Sheets
```

#### PDF:
```
1. Aplicar filtros deseados
2. Clic en "Exportar a PDF"
3. El archivo se descarga automáticamente
4. Abrir con visor de PDF
```

## 📈 Estadísticas Calculadas

### Total Extensiones
- Cuenta todos los registros de extensión en el período filtrado

### Total Días Extendidos
- Suma de todos los días agregados en las extensiones
- Solo cuenta extensiones con `dias_extendidos > 0`

### Promedio Días/Extensión
- `Total Días Extendidos / Total Extensiones`
- Redondeado a 1 decimal

### Obras Involucradas
- Cantidad de obras únicas con extensiones registradas

## 🔄 Integración con Otros Módulos

### Préstamos
- Enlace directo desde ID de préstamo a `view_prestamo.php`
- Se abre en nueva pestaña para no perder el reporte

### Usuarios
- Muestra quién realizó cada extensión
- Información del empleado que tiene el préstamo

### Obras
- Filtrado por obra específica
- Muestra obra y localidad en cada registro

## 🎨 Interfaz de Usuario

### Tarjetas de Estadísticas
- **Color primario**: Total extensiones
- **Color success**: Total días extendidos
- **Color info**: Promedio días/extensión
- **Color warning**: Obras involucradas

### Tabla de Datos
- **Cabecera fija**: Se mantiene visible al hacer scroll
- **Hover effect**: Resalta fila al pasar el mouse
- **Badges**: Muestra días extendidos en badge azul
- **Enlaces**: ID de préstamo es clickeable
- **Tooltips**: Información adicional en hover

### Responsive Design
- **Desktop**: Tabla completa con todos los campos
- **Tablet**: Ajuste automático de columnas
- **Mobile**: Scroll horizontal para ver todos los datos

## 🛠️ Mantenimiento

### Logs de Errores
- Errores se registran en log de PHP: `error_log()`
- Mensajes de error mostrados al usuario son genéricos

### Performance
- Índices en `id_prestamo` y `fecha_modificacion`
- LIMIT en subconsulta de herramientas (máx 3)
- Consultas preparadas (prepared statements)

### Backup
- Incluir tabla `historial_extensiones_prestamo` en backups
- Datos históricos son críticos para auditoría

## 📋 Casos de Uso

### 1. Auditoría Mensual
```
- Filtrar: Fecha Desde = 01/12/2025, Fecha Hasta = 31/12/2025
- Revisar: Total de extensiones y días extendidos
- Exportar: PDF para reportes gerenciales
```

### 2. Análisis por Obra
```
- Filtrar: Obra específica
- Revisar: Cuántas extensiones tiene esa obra
- Analizar: Motivos más frecuentes
- Exportar: Excel para análisis detallado
```

### 3. Control de Usuario
```
- Filtrar: Usuario específico
- Revisar: Extensiones autorizadas por ese usuario
- Verificar: Motivos y justificaciones
```

### 4. Reporte Anual
```
- Filtrar: Fecha Desde = 01/01/2025, Fecha Hasta = 31/12/2025
- Exportar: PDF con estadísticas anuales
- Archivar: Documentación para cumplimiento
```

## 🔮 Mejoras Futuras

### Corto Plazo
- [ ] Gráficos de tendencias (Chart.js)
- [ ] Exportación a Excel nativo (PHPSpreadsheet)
- [ ] Dashboard de métricas en tiempo real
- [ ] Alertas por exceso de extensiones

### Mediano Plazo
- [ ] Límite máximo de extensiones por préstamo
- [ ] Notificaciones automáticas por email
- [ ] Análisis predictivo de extensiones
- [ ] API REST para integración

### Largo Plazo
- [ ] Machine Learning para detectar patrones
- [ ] Integración con sistema de aprobaciones
- [ ] App móvil para consultas
- [ ] Business Intelligence integrado

## 📞 Soporte

### Problemas Comunes

**No se muestran datos:**
- Verificar que se hayan ejecutado las migraciones SQL
- Comprobar que existen extensiones en el período filtrado
- Revisar permisos de usuario

**Error al exportar:**
- Verificar que la librería FPDF esté instalada
- Comprobar permisos de escritura en directorio temp
- Revisar logs de PHP

**Filtros no funcionan:**
- Limpiar caché del navegador
- Verificar formato de fechas (YYYY-MM-DD)
- Comprobar que los datos existan en BD

## 📄 Licencia

Este módulo es parte del Sistema de Gestión de Constructora v2.0

---

**Última actualización**: Febrero 2026  
**Versión**: 1.0.0  
**Autor**: Sistema de Gestión de Constructora
