# Migración: Nuevas Condiciones y Estados de Herramientas

## 📋 Resumen del Cambio

Se redefinen completamente las **CONDICIONES** y **ESTADOS** de las herramientas para reflejar mejor el ciclo de vida real de las herramientas en obras de construcción.

### Fecha de Migración
**5 de Febrero de 2026**

---

## 🔄 Cambios Principales

### 1. CONDICIONES (antes y después)

| **❌ Anterior** | **✅ Nueva** | **Descripción** |
|----------------|-------------|-----------------|
| Excelente      | Nueva | Para su primer uso (herramienta sin estrenar) |
| Buena          | Usada | Automáticamente cambia después del primer uso |
| Regular        | Reparada | Cuando registra al menos una reparación completada |
| Mala           | Para Reparación | Volvió con algún defecto, necesita reparación |
| Dañada         | Perdida | No se encuentra en la obra donde fue llevada |
| Perdida        | De Baja | Se devuelve con fallas, costo de reparación no justificable |

### 2. ESTADOS (antes y después)

| **❌ Anterior** | **✅ Nuevo** | **Descripción** |
|----------------|-------------|-----------------|
| Disponible     | Disponible | Lista para ser prestada |
| Prestada       | Prestada | Actualmente en préstamo |
| Mantenimiento  | En Reparación | En proceso de reparación/mantenimiento |
| Perdida        | No Disponible | No disponible (perdida o de baja) |
| Dañada         | *(combinado con No Disponible)* | - |

---

## 📦 Archivos Creados/Modificados

### Archivos Nuevos
1. **`config/herramientas_config.php`** - Configuración centralizada de condiciones y estados
2. **`scripts/migracion_condiciones_estados.sql`** - Script SQL de migración
3. **`scripts/README_MIGRACION_CONDICIONES_ESTADOS.md`** - Esta documentación

### Archivos Modificados
- `config/config.php` - Incluye herramientas_config.php
- Todos los archivos en `modules/herramientas/` que manejan condiciones/estados

---

## 🚀 Instrucciones de Migración

### PASO 1: Backup de Base de Datos ⚠️

```bash
# CRÍTICO: Hacer backup ANTES de continuar
mysqldump -u usuario -p nombre_base_datos > backup_antes_migracion_$(date +%Y%m%d_%H%M%S).sql
```

### PASO 2: Ejecutar Script SQL

```bash
mysql -u usuario -p nombre_base_datos < scripts/migracion_condiciones_estados.sql
```

O desde phpMyAdmin:
1. Abrir phpMyAdmin
2. Seleccionar la base de datos
3. Ir a pestaña "SQL"
4. Copiar y pegar el contenido de `migracion_condiciones_estados.sql`
5. Ejecutar

### PASO 3: Verificar Migración de Datos

```sql
-- Verificar condiciones
SELECT condicion_general, COUNT(*) as cantidad 
FROM herramientas 
GROUP BY condicion_general;

-- Verificar estados
SELECT estado_actual, COUNT(*) as cantidad 
FROM herramientas_unidades 
GROUP BY estado_actual;
```

### PASO 4: Actualizar Código PHP

Los archivos PHP ya han sido actualizados para usar el nuevo archivo de configuración centralizado (`config/herramientas_config.php`).

---

## 🔧 Nueva Lógica de Negocio

### Flujo de Condiciones

```
[Nueva] ──(primer préstamo)──> [Usada]
                                  │
                                  ├──(se devuelve bien)──> [Usada]
                                  │
                                  ├──(requiere reparación)──> [Para Reparación]
                                  │                                │
                                  │                                v
                                  │                         [Se repara]
                                  │                                │
                                  │                                v
                                  ├────────────────────────> [Reparada]
                                  │
                                  ├──(se pierde)──> [Perdida]
                                  │
                                  └──(fallas graves)──> [De Baja]
```

### Flujo de Estados

```
[Disponible] ──(crear préstamo)──> [Prestada]
                                       │
                                       ├──(devolver bien)──> [Disponible]
                                       │
                                       ├──(requiere reparación)──> [En Reparación]
                                       │                                │
                                       │                                v
                                       │                         [Se completa]
                                       │                                │
                                       │                                v
                                       ├────────────────────────> [Disponible]
                                       │
                                       └──(perdida/baja)──> [No Disponible]
```

### Reglas Automáticas

1. **Nueva → Usada**: Al crear primer préstamo con herramienta "Nueva"
2. **Usada → Para Reparación**: Al marcar checkbox "Requiere Mantenimiento" en devolución
3. **Para Reparación → Reparada**: Al completar reparación en nuevo módulo
4. **Cualquiera → Perdida**: Al seleccionar "Perdida" en devolución
5. **Cualquiera → De Baja**: Al seleccionar "De Baja" en devolución

---

## 📊 Mapeo de Datos en Migración

### Condiciones
```sql
'excelente' --> 'usada'
'buena'     --> 'usada'
'regular'   --> 'usada'
'mala'      --> 'para_reparacion'
'dañada'    --> 'de_baja'
'perdida'   --> 'perdida'
```

### Estados
```sql
'disponible'    --> 'disponible'
'prestada'      --> 'prestada'
'mantenimiento' --> 'en_reparacion'
'perdida'       --> 'no_disponible'
'dañada'        --> 'no_disponible'
```

---

## 🗄️ Nueva Tabla: historial_reparaciones

Se crea una tabla para registrar todas las reparaciones:

```sql
CREATE TABLE historial_reparaciones (
    id_reparacion INT AUTO_INCREMENT PRIMARY KEY,
    id_unidad INT NOT NULL,
    fecha_inicio_reparacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_fin_reparacion TIMESTAMP NULL,
    descripcion_problema TEXT,
    descripcion_solucion TEXT NULL,
    costo_reparacion DECIMAL(10,2) NULL,
    id_usuario_registro INT NOT NULL,
    estado_reparacion ENUM('en_proceso', 'completada', 'cancelada'),
    ...
);
```

### Funcionalidades Futuras
- Módulo de gestión de reparaciones
- Registro automático al marcar "Para Reparación"
- Historial completo por unidad
- Reportes de costos de reparación

---

## 🎨 Uso del Nuevo Sistema en PHP

### Ejemplo: Validar Condición

```php
// ANTES (hardcoded)
if (!in_array($condicion, ['excelente', 'buena', 'regular', 'mala'])) {
    $errors[] = 'Condición inválida';
}

// AHORA (usando configuración)
if (!es_condicion_valida($condicion)) {
    $errors[] = 'Condición inválida';
}
```

### Ejemplo: Mostrar Select de Condiciones

```php
<select name="condicion" class="form-select" required>
    <?php foreach (CONDICIONES_HERRAMIENTAS as $codigo => $nombre): ?>
        <option value="<?php echo $codigo; ?>">
            <?php echo $nombre; ?>
        </option>
    <?php endforeach; ?>
</select>
```

### Ejemplo: Mostrar Badge con Condición

```php
$condicion = $herramienta['condicion_general'];
$clase = get_clase_condicion($condicion);
$nombre = get_nombre_condicion($condicion);
$icono = get_icono_condicion($condicion);
?>
<span class="badge bg-<?php echo $clase; ?>">
    <i class="bi <?php echo $icono; ?>"></i>
    <?php echo $nombre; ?>
</span>
```

### Ejemplo: Determinar Nuevo Estado en Devolución

```php
$condicion_devolucion = $_POST['condicion_devolucion'];
$requiere_mantenimiento = isset($_POST['requiere_mantenimiento']);

$nuevo_estado = determinar_nuevo_estado($condicion_devolucion, $requiere_mantenimiento);

// Actualizar estado de la unidad
$query = "UPDATE herramientas_unidades SET estado_actual = ? WHERE id_unidad = ?";
$stmt->execute([$nuevo_estado, $id_unidad]);
```

---

## ✅ Lista de Verificación Post-Migración

- [ ] Backup de base de datos creado
- [ ] Script SQL ejecutado sin errores
- [ ] Datos verificados con queries de comprobación
- [ ] Formularios de creación de herramientas funcionan
- [ ] Formularios de préstamo funcionan
- [ ] Formularios de devolución funcionan
- [ ] Reportes muestran nuevas condiciones/estados
- [ ] No hay errores en logs de PHP
- [ ] Tests manuales en todas las pantallas de herramientas

---

## 🔍 Troubleshooting

### Error: "Unknown column 'condicion_general'"
**Solución**: El script SQL no se ejecutó correctamente. Verificar y re-ejecutar.

### Error: "Data truncated for column 'estado_actual'"
**Solución**: Hay estados no contemplados en la migración. Revisar datos manualmente.

### Error: "Invalid enum value"
**Solución**: Verificar que el código PHP use los nuevos valores de enum.

### Los selectores muestran valores vacíos
**Solución**: Verificar que `herramientas_config.php` esté incluido en `config.php`.

---

## 📞 Contacto y Soporte

Si encuentras problemas durante la migración:

1. Verificar logs de PHP: `error_log`
2. Verificar logs de MySQL
3. Restaurar backup si es necesario
4. Revisar este documento paso a paso

---

## 📝 Notas Importantes

⚠️ **IMPORTANTE**: Esta migración modifica la estructura de 4 tablas principales y crea 1 tabla nueva.

⚠️ **BACKUP**: Siempre hacer backup antes de ejecutar cambios estructurales.

⚠️ **TESTING**: Probar en ambiente de desarrollo antes de producción.

⚠️ **ROLLBACK**: La única forma segura de revertir es restaurar desde backup.

---

**Versión del Documento**: 1.0.0  
**Fecha**: 5 de Febrero de 2026  
**Autor**: Sistema de Gestión de Constructora  
**Estado**: ✅ Listo para Implementación
