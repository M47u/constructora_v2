# 🕐 Guía Completa - Corrección de Zona Horaria

## 📋 Resumen Ejecutivo

**Problema:** Todos los registros se guardaban con GMT +00:00 en lugar de GMT -03:00 (Argentina)

**Solución:** Configuración de zona horaria en PHP y MySQL + Migración de datos existentes

**Estado:** ✅ Completado

---

## 🔧 Cambios Implementados

### 1. Configuración PHP (`config/config.php`)

```php
// Líneas 16-17
date_default_timezone_set('America/Argentina/Buenos_Aires');
$timezone = 'America/Argentina/Buenos_Aires';
```

### 2. Configuración MySQL (`config/database.php`)

```php
// Línea 24
$this->conn->exec("SET time_zone = '-03:00'");
```

### 3. Migración de Datos Existentes

**Archivo:** `scripts/migrate_timezone_existing_data.sql`

**Acción:** Resta 3 horas a todas las fechas existentes

**Tablas Afectadas:**
- usuarios (fecha_registro, fecha_ultimo_acceso)
- obras (fecha_inicio, fecha_fin_estimada, fecha_actualizacion)
- materiales (fecha_registro, fecha_actualizacion)
- pedidos_materiales (fecha_pedido, fecha_actualizacion)
- seguimiento_pedidos (fecha_cambio)
- historial_edicion_etapas_pedidos (fecha_edicion)
- herramientas (fecha_actualizacion)
- herramientas_unidades (fecha_registro, fecha_actualizacion)
- prestamos (fecha_retiro, fecha_devolucion_estimada, fecha_actualizacion)
- devoluciones (fecha_devolucion)
- tareas (fecha_asignacion, fecha_vencimiento, fecha_finalizacion, fecha_actualizacion)
- notificaciones (fecha_creacion, fecha_lectura)
- logs_sistema (fecha_hora)

---

## 🚀 Cómo Verificar

### Paso 1: Ejecutar Script de Verificación

Accede a: `http://localhost/constructora_v2/verificar_timezone.php`

### Paso 2: Revisar Resultados

El script verifica automáticamente:
- ✅ Configuración de PHP
- ✅ Configuración de MySQL
- ✅ Sincronización entre PHP y MySQL
- ✅ Datos migrados
- ✅ Nuevos registros

### Paso 3: Interpretación de Resultados

#### 🟢 Verde (Todo Correcto)
- PHP: America/Argentina/Buenos_Aires
- MySQL: -03:00
- Diferencia PHP-MySQL: < 60 segundos
- Test de inserción: < 5 segundos

#### 🟡 Amarillo (Advertencia)
- Desincronización menor
- Revisar manualmente algunos registros

#### 🔴 Rojo (Error)
- Configuración incorrecta
- Reiniciar Apache y MySQL
- Contactar al administrador

---

## 📝 Pruebas Manuales Recomendadas

### 1. Crear Nuevo Pedido
```
1. Ir a Módulo de Pedidos
2. Crear un nuevo pedido
3. Verificar que la fecha/hora corresponde a Argentina
```

### 2. Verificar Seguimiento de Pedidos
```
1. Abrir un pedido existente
2. Cambiar etapa (ej: de Creación a Aprobación)
3. Verificar fecha de cambio en historial
```

### 3. Revisar Reportes
```
1. Ir a Métricas de Pedidos
2. Verificar que las fechas sean coherentes
3. Confirmar que las horas están en GMT -03:00
```

---

## 🔍 Diagnóstico de Problemas

### Problema: "Los nuevos registros siguen con GMT +00:00"

**Solución:**
```bash
1. Verificar config/database.php línea 24
2. Reiniciar Apache: sudo service apache2 restart
3. Ejecutar verificar_timezone.php
```

### Problema: "Diferencia de horas en reportes"

**Solución:**
```bash
1. Verificar que la migración se ejecutó correctamente
2. Revisar tabla seguimiento_pedidos
3. SELECT * FROM seguimiento_pedidos ORDER BY id_seguimiento DESC LIMIT 10;
```

### Problema: "Error en conexión a base de datos"

**Solución:**
```bash
1. Verificar credenciales en config/database.php
2. Verificar que MySQL está corriendo
3. Verificar permisos del usuario de BD
```

---

## 📊 Archivos Modificados/Creados

### Archivos de Configuración
- ✅ `config/config.php` (líneas 16-17)
- ✅ `config/database.php` (línea 24)

### Scripts de Migración
- ✅ `scripts/migrate_timezone_existing_data.sql`
- ✅ `scripts/README_TIMEZONE_COMPLETO.md` (este archivo)

### Scripts de Verificación
- ✅ `verificar_timezone.php` (raíz del proyecto)

---

## ⚠️ Notas Importantes

### ❗ CRITICAL
- **NUNCA** ejecutar el script de migración más de una vez (restaría 3 horas adicionales)
- Siempre hacer backup antes de ejecutar migraciones
- La configuración de timezone debe estar en database.php (se ejecuta en cada conexión)

### 💡 Recomendaciones
- Ejecutar `verificar_timezone.php` después de cualquier cambio en configuración
- Revisar logs del sistema periódicamente
- Mantener sincronizado el servidor con NTP

### 🔒 Seguridad
- El archivo `verificar_timezone.php` debe ser accesible solo para administradores
- Considerar mover a carpeta protegida en producción

---

## 📞 Soporte

Si encuentras algún problema:

1. Ejecutar `verificar_timezone.php`
2. Copiar el resultado completo
3. Revisar logs de Apache/MySQL
4. Contactar al administrador del sistema

---

## ✅ Checklist de Implementación

- [x] Configurar timezone en PHP
- [x] Configurar timezone en MySQL
- [x] Crear script de migración
- [x] Ejecutar migración en base de datos
- [x] Verificar datos existentes
- [x] Crear script de verificación
- [x] Probar con nuevos registros
- [x] Documentar cambios
- [ ] Informar a usuarios
- [ ] Monitorear por 1 semana

---

**Última actualización:** <?php echo date('Y-m-d H:i:s'); ?>

**Versión:** 1.0.0

**Estado:** ✅ Producción
