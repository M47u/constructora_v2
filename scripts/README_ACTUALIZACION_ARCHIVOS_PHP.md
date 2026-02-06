# Actualización de Archivos PHP al Nuevo Sistema de Condiciones y Estados

**Fecha**: 2024
**Sistema**: Gestión de Constructora v2.0
**Tipo**: Actualización de código para usar configuración centralizada

---

## 📋 Objetivo

Actualizar todos los archivos PHP del módulo de herramientas para usar el nuevo sistema centralizado de condiciones y estados definido en `config/herramientas_config.php`, eliminando referencias hardcoded.

---

## ✅ Archivos Actualizados

### 1. **config/herramientas_config.php**
**Cambios realizados**:
- Actualizado `CONDICIONES_CSS_CLASSES` para incluir prefijo `bg-`
- Actualizado `ESTADOS_CSS_CLASSES` para incluir prefijo `bg-`

**Resultado**: Badges se muestran correctamente con fondo de color

---

### 2. **modules/herramientas/create.php**
**Cambios realizados**:
- Reemplazada validación hardcoded: `if (!in_array($condicion_general, ['excelente', 'buena', 'regular', 'mala']))`
- Nueva validación: `if (!es_condicion_valida($condicion_general))`
- Reemplazadas opciones hardcoded del select con generación dinámica:
```php
<?php foreach (CONDICIONES_HERRAMIENTAS as $codigo => $nombre): ?>
    <option value="<?php echo $codigo; ?>"><?php echo $nombre; ?></option>
<?php endforeach; ?>
```

**Resultado**: Nuevas condiciones disponibles automáticamente en el formulario

---

### 3. **modules/herramientas/edit.php**
**Cambios realizados**:
- Validación: `if (!es_condicion_valida($condicion_general))`
- Select dinámico con todas las condiciones del array centralizado
- Agregada ayuda contextual sobre condición general

**Resultado**: Edición de herramientas usa nuevas condiciones

---

### 4. **modules/herramientas/add_unit.php**
**Cambios realizados**:
- Validación de estado: `if (!es_estado_valido($estado_actual))`
- Select dinámico con generación de iconos:
```php
<?php foreach (ESTADOS_HERRAMIENTAS as $codigo => $nombre): ?>
    <option value="<?php echo $codigo; ?>">
        <?php echo get_icono_estado($codigo) . ' ' . $nombre; ?>
    </option>
<?php endforeach; ?>
```

**Resultado**: Solo estados válidos disponibles al agregar unidades

---

### 5. **modules/herramientas/create_devolucion.php**
**Cambios realizados**:
- Validación: `if (!es_condicion_valida($condicion_devolucion[$unidad_id]))`
- Lógica de determinación de estado:
```php
$requiere_mantenimiento = false; // TODO: Agregar checkbox
$new_unit_status = determinar_nuevo_estado($condicion, $requiere_mantenimiento);
```
- Select dinámico para condiciones de devolución

**Resultado**: Devoluciones usan nueva lógica de estados

**⚠️ NOTA**: Se dejó comentario TODO para agregar checkbox de mantenimiento por unidad si es necesario

---

### 6. **modules/herramientas/create_prestamo.php**
**Cambios realizados**:
- Validación: `if (!es_condicion_valida($condicion_retiro[$unidad_id]))`
- Badges usando funciones centralizadas:
```php
$condicion_class = get_clase_condicion($detalle['condicion_retiro']);
echo get_icono_condicion($detalle['condicion_retiro']) . ' ' . get_nombre_condicion($detalle['condicion_retiro']);
```
- Resumen estadístico generado dinámicamente
- JavaScript actualizado para contar todas las condiciones:
```javascript
const condiciones = {};
<?php foreach (CONDICIONES_HERRAMIENTAS as $codigo => $nombre): ?>
condiciones['<?php echo $codigo; ?>'] = 0;
<?php endforeach; ?>
```

**Resultado**: Préstamos manejan todas las nuevas condiciones con estadísticas automáticas

---

### 7. **modules/herramientas/view.php**
**Cambios realizados**:
- Array de estados generado dinámicamente:
```php
foreach (ESTADOS_HERRAMIENTAS as $codigo => $nombre) {
    $estados_unidades[$codigo] = [
        'count' => 0,
        'class' => get_clase_estado($codigo),
        'icon' => get_icono_estado($codigo),
        'nombre' => $nombre
    ];
}
```
- Badges de estado usando funciones centralizadas
- Condición general muestra badge con icono:
```php
<span class="badge <?php echo get_clase_condicion($herramienta['condicion_general']); ?>">
    <?php echo get_icono_condicion($herramienta['condicion_general']) . ' ' . get_nombre_condicion($herramienta['condicion_general']); ?>
</span>
```

**Resultado**: Vista de herramienta muestra todos los estados y condiciones correctamente

---

### 8. **modules/herramientas/ajax_devolucion_rapida.php**
**Cambios realizados**:
- Validación: `if (!es_condicion_valida($condicion_devolucion))`
- Determinación de estado: `$nuevo_estado = determinar_nuevo_estado($condicion_devolucion, $requiere_mantenimiento);`

**Resultado**: Devolución rápida AJAX usa lógica centralizada

---

### 9. **modules/herramientas/view_prestamo.php**
**Cambios realizados**:
- Badges de condición de retiro usando funciones centralizadas
- Badges de estado actual usando funciones centralizadas
- Eliminados todos los switch/case hardcoded

**Resultado**: Vista de préstamo muestra información con nuevas condiciones/estados

---

### 10. **modules/herramientas/view_devolucion.php**
**Cambios realizados**:
- Badges de condición de devolución usando `get_clase_condicion()` y `get_icono_condicion()`
- Eliminado switch/case hardcoded

**Resultado**: Vista de devolución muestra condiciones correctamente

---

## 🎯 Funciones Centralizadas Utilizadas

### Validación:
- `es_condicion_valida($condicion)` - Verifica si condición es válida
- `es_estado_valido($estado)` - Verifica si estado es válido

### Obtención de Información:
- `get_nombre_condicion($condicion)` - Retorna nombre legible
- `get_nombre_estado($estado)` - Retorna nombre legible
- `get_clase_condicion($condicion)` - Retorna clase CSS para badge
- `get_clase_estado($estado)` - Retorna clase CSS para badge
- `get_icono_condicion($condicion)` - Retorna clase de ícono Bootstrap
- `get_icono_estado($estado)` - Retorna clase de ícono Bootstrap

### Lógica de Negocio:
- `determinar_nuevo_estado($condicion_devolucion, $requiere_mantenimiento)` - Determina estado tras devolución
- `determinar_condicion_despues_uso($condicion_actual)` - Nueva → Usada en primer préstamo

---

## 📊 Mapeo de Condiciones

### Condiciones Antiguas → Nuevas:
- `excelente` → `nueva`
- `buena` → `usada`
- `regular` → `reparada`
- `mala` → `para_reparacion`
- `dañada` → Eliminada (ahora es condición + estado)
- `perdida` → `perdida` (sin cambios)

### Nuevas condiciones agregadas:
- `de_baja` - Herramientas dadas de baja permanentemente

---

## 📊 Mapeo de Estados

### Estados Antiguos → Nuevos:
- `disponible` → `disponible` (sin cambios)
- `prestada` → `prestada` (sin cambios)
- `mantenimiento` → `en_reparacion`
- `dañada` → Eliminado (ahora se maneja con condición)
- `perdida` → `no_disponible`

### Nuevos estados agregados:
- `no_disponible` - Abarca perdida, de baja, etc.

---

## 🔄 Lógica de Transición de Estados

### Al devolver una herramienta:

**Condición: `nueva`, `usada`, `reparada`**
- ✅ Estado: `disponible`

**Condición: `para_reparacion`**
- 🔧 Estado: `en_reparacion`

**Condición: `perdida`, `de_baja`**
- ❌ Estado: `no_disponible`

**Si checkbox "Requiere Mantenimiento" = true (cualquier condición)**
- 🔧 Estado: `en_reparacion`

---

## 🧪 Pruebas Recomendadas

### 1. Crear Tipo de Herramienta
- [ ] Verificar que select muestra 6 condiciones
- [ ] Validar que se puede seleccionar "Nueva"
- [ ] Validar que se puede seleccionar "Para Reparación"
- [ ] Confirmar que validación funciona

### 2. Agregar Unidades
- [ ] Verificar que select muestra 4 estados con iconos
- [ ] Confirmar que "Disponible" es default
- [ ] Validar que no acepta estados inválidos

### 3. Crear Préstamo
- [ ] Verificar resumen estadístico dinámico
- [ ] Confirmar que muestra todas las condiciones
- [ ] Validar que cuenta correctamente
- [ ] Verificar badges con iconos y colores

### 4. Crear Devolución
- [ ] Verificar que condiciones se muestran correctamente
- [ ] Confirmar lógica de determinación de estado
- [ ] Validar "Para Reparación" → "En Reparación"
- [ ] Validar "Perdida" → "No Disponible"

### 5. Vistas
- [ ] Verificar view.php muestra condición con badge e icono
- [ ] Confirmar lista de estados muestra todos correctamente
- [ ] Validar view_prestamo.php muestra condiciones
- [ ] Validar view_devolucion.php muestra condiciones

### 6. AJAX
- [ ] Verificar devolución rápida funciona
- [ ] Confirmar transición de estados correcta

---

## 📝 Archivos NO Modificados

Los siguientes archivos tienen referencias hardcoded pero son archivos auxiliares o de corrección:

- `modules/herramientas/fix_estados_incorrectos.php` - Script de corrección (no necesita actualización)

---

## ✨ Beneficios Obtenidos

1. **Mantenibilidad**: Cambios futuros solo requieren actualizar `herramientas_config.php`
2. **Consistencia**: Mismos nombres, clases e iconos en toda la aplicación
3. **Escalabilidad**: Fácil agregar nuevas condiciones/estados
4. **Validación**: Única fuente de verdad para validaciones
5. **DRY**: Eliminado código duplicado en múltiples archivos

---

## 🎨 Iconos Utilizados

### Condiciones:
- Nueva: ⭐ `bi-star-fill`
- Usada: ✅ `bi-check-circle`
- Reparada: 🔧 `bi-wrench`
- Para Reparación: ⚠️ `bi-exclamation-triangle`
- Perdida: ❓ `bi-question-circle`
- De Baja: ❌ `bi-x-circle`

### Estados:
- Disponible: ✅ `bi-check-circle`
- Prestada: 📤 `bi-box-arrow-up`
- En Reparación: 🔧 `bi-tools`
- No Disponible: ❌ `bi-x-circle`

---

## ⚡ Próximos Pasos

1. Realizar pruebas exhaustivas del flujo completo
2. Considerar agregar checkbox "Requiere Mantenimiento" por unidad en devoluciones
3. Implementar módulo de reparaciones usando `historial_reparaciones`
4. Agregar reportes de herramientas por condición/estado

---

## 📞 Soporte

Si encuentras algún problema con las nuevas condiciones o estados:
1. Verificar que `config/herramientas_config.php` está incluido en `config.php`
2. Revisar logs de PHP por errores de funciones no encontradas
3. Confirmar que la migración SQL se ejecutó correctamente
4. Validar que datos en BD usan nuevos valores ENUM

---

**Fin del documento de actualización**
