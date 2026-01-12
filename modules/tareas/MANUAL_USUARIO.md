# Manual de Usuario - Módulo de Tareas

## Índice
1. [Introducción](#introducción)
2. [Acceso al Módulo](#acceso-al-módulo)
3. [Roles y Permisos](#roles-y-permisos)
4. [Dashboard de Tareas](#dashboard-de-tareas)
5. [Listar Tareas](#listar-tareas)
6. [Crear Nueva Tarea](#crear-nueva-tarea)
7. [Ver Detalles de una Tarea](#ver-detalles-de-una-tarea)
8. [Actualizar Estado de Tarea](#actualizar-estado-de-tarea)
9. [Editar Tarea](#editar-tarea)
10. [Imprimir Tarea](#imprimir-tarea)
11. [Filtros y Búsqueda](#filtros-y-búsqueda)
12. [Notificaciones](#notificaciones)
13. [Preguntas Frecuentes](#preguntas-frecuentes)

---

## Introducción

El **Módulo de Tareas** es una herramienta de gestión que permite a los administradores y responsables de obra asignar tareas específicas a los empleados, realizar seguimiento del progreso y mantener un control efectivo del trabajo asignado.

### Características principales:
- ✅ Asignación de tareas a empleados
- 📊 Dashboard con estadísticas en tiempo real
- 🎯 Sistema de prioridades (Baja, Media, Alta, Urgente)
- 📅 Control de fechas de vencimiento
- 🔄 Seguimiento de estados (Pendiente, En Proceso, Finalizada, Cancelada)
- 📝 Registro de progreso y observaciones
- 🖨️ Impresión de tareas
- 🔍 Filtros avanzados de búsqueda

---

## Acceso al Módulo

Para acceder al módulo de tareas:

1. Inicie sesión en el sistema con su usuario y contraseña
2. En el menú principal, haga clic en **"Tareas"**
3. Por defecto, será redirigido al **Dashboard de Tareas**

---

## Roles y Permisos

El módulo cuenta con tres niveles de acceso:

### 👨‍💼 Administrador
- ✅ Crear, editar y eliminar tareas
- ✅ Asignar tareas a cualquier empleado
- ✅ Ver todas las tareas del sistema
- ✅ Acceso completo al dashboard general
- ✅ Editar tareas de cualquier usuario

### 👷 Responsable de Obra
- ✅ Crear y asignar tareas a empleados
- ✅ Ver tareas asignadas por ellos
- ✅ Editar tareas que ellos crearon
- ✅ Acceso al dashboard general
- ❌ No puede editar tareas de otros responsables

### 👤 Empleado
- ❌ No puede crear tareas
- ✅ Ver solo sus propias tareas asignadas
- ✅ Actualizar el estado de sus tareas
- ✅ Agregar observaciones a sus tareas
- ✅ Ver dashboard personal
- ❌ No puede editar información básica de la tarea

---

## Dashboard de Tareas

El dashboard proporciona una vista general del estado de las tareas.

### Vista de Empleado

Al ingresar como empleado, verá:

#### Estadísticas Personales
```
┌─────────────────────────────────────────────┐
│  📊 Mis Tareas                              │
├─────────────────────────────────────────────┤
│  Total: 15                                  │
│  Pendientes: 5                              │
│  En Proceso: 8                              │
│  Finalizadas: 2                             │
│  Vencidas: 1                                │
└─────────────────────────────────────────────┘
```

#### Mis Tareas Activas
- Lista de tareas pendientes y en proceso
- Ordenadas por prioridad y fecha de vencimiento
- Indicadores visuales de estado y prioridad
- Botones de acción rápida

#### Tareas Completadas Recientemente
- Últimas 5 tareas finalizadas
- Con fecha de finalización

### Vista de Administrador/Responsable

Al ingresar como administrador o responsable, verá:

#### Resumen General
```
┌─────────────────────────────────────────────┐
│  📊 Resumen de Tareas                       │
├─────────────────────────────────────────────┤
│  Total de Tareas: 45                        │
│  Pendientes: 15                             │
│  En Proceso: 20                             │
│  Finalizadas: 8                             │
│  Vencidas: 2                                │
└─────────────────────────────────────────────┘
```

#### Tareas Urgentes y Vencidas
- Top 10 tareas con mayor prioridad
- Tareas vencidas que requieren atención inmediata
- Información del empleado asignado

#### Empleados con Más Tareas Pendientes
- Ranking de empleados por carga de trabajo
- Útil para distribución equitativa de tareas

---

## Listar Tareas

La vista de lista muestra todas las tareas según su rol.

### Acceso
- Clic en **"Tareas"** → **"Lista de Tareas"**

### Información Mostrada

La tabla incluye las siguientes columnas:

| Columna | Descripción |
|---------|-------------|
| **#** | Número ID de la tarea |
| **Título** | Nombre descriptivo de la tarea |
| **Empleado** | Persona asignada a la tarea |
| **Asignador** | Quien creó/asignó la tarea |
| **Estado** | Pendiente / En Proceso / Finalizada / Cancelada |
| **Prioridad** | Baja / Media / Alta / Urgente |
| **Vencimiento** | Fecha límite para completar |
| **Progreso** | Porcentaje de avance (0-100%) |
| **Acciones** | Botones para ver, editar, imprimir |

### Indicadores Visuales

#### Estados:
- 🟡 **Pendiente** - Amarillo
- 🔵 **En Proceso** - Azul
- 🟢 **Finalizada** - Verde
- 🔴 **Cancelada** - Rojo

#### Prioridades:
- 🟢 **Baja** - Verde claro
- 🟡 **Media** - Amarillo
- 🟠 **Alta** - Naranja
- 🔴 **Urgente** - Rojo

#### Vencimiento:
- 🔴 **Vencida** - Fecha en rojo (pasó la fecha límite)
- ⚠️ **Por vencer** - Advertencia si vence en menos de 3 días

### Paginación
- Se muestran 25 tareas por página
- Navegación en la parte inferior de la tabla

---

## Crear Nueva Tarea

Solo disponible para **Administradores** y **Responsables**.

### Pasos para crear una tarea:

1. **Acceso**
   - Clic en **"Nueva Tarea"** desde el menú o lista de tareas

2. **Completar Formulario**

   #### Campos Obligatorios (*)
   
   - **Empleado Asignado*** 
     - Seleccione de la lista de empleados activos
     - Solo puede asignar a un empleado a la vez
   
   - **Título***
     - Nombre descriptivo de la tarea (máx. 200 caracteres)
     - Ejemplo: "Instalación de sistema eléctrico - Edificio A"
   
   - **Descripción***
     - Detalle completo de lo que debe realizarse
     - Sea específico sobre los entregables esperados
   
   - **Prioridad***
     - **Baja**: Tareas rutinarias sin urgencia
     - **Media**: Trabajo regular con plazo normal
     - **Alta**: Requiere atención prioritaria
     - **Urgente**: Necesita acción inmediata
   
   #### Campos Opcionales
   
   - **Fecha de Vencimiento**
     - Fecha límite para completar la tarea
     - No puede ser anterior a la fecha actual
   
   - **Obra Asociada**
     - Vincula la tarea a una obra específica
     - Útil para reportes por proyecto
   
   - **Tiempo Estimado**
     - Horas estimadas para completar la tarea
     - Ayuda en la planificación
   
   - **Observaciones**
     - Notas adicionales o consideraciones especiales

3. **Guardar**
   - Clic en el botón **"Crear Tarea"**
   - El sistema validará los datos
   - Recibirá confirmación de creación exitosa

### Ejemplo de Tarea Completa

```
Empleado: Juan Pérez
Título: Revisión de instalaciones sanitarias
Descripción: Verificar el correcto funcionamiento de todas las 
             instalaciones sanitarias del piso 3, incluyendo:
             - Grifería
             - Desagües
             - Conexiones
             - Presión de agua
Prioridad: Alta
Fecha de Vencimiento: 15/01/2026
Obra: Edificio Residencial Los Sauces
Tiempo Estimado: 8 horas
Observaciones: Revisar especialmente los baños del departamento 302
```

---

## Ver Detalles de una Tarea

Muestra toda la información completa de una tarea específica.

### Acceso
- Desde la lista de tareas, clic en el botón **"Ver"** (👁️)
- Desde el dashboard, clic en el título de cualquier tarea

### Información Mostrada

#### Sección: Información de la Tarea

```
┌─────────────────────────────────────────────────┐
│ Título: Instalación eléctrica Piso 2           │
│ Estado: En Proceso                              │
│ Prioridad: Alta                                 │
│                                                 │
│ Descripción:                                    │
│ Instalar todo el sistema eléctrico del piso 2  │
│ incluyendo tomacorrientes, interruptores y      │
│ cableado según planos.                          │
│                                                 │
│ Obra: Edificio Los Álamos                       │
│ Asignado por: María González                    │
│ Fecha de Asignación: 05/01/2026 09:30          │
│ Fecha de Vencimiento: 20/01/2026                │
│ Progreso: 45%                                   │
│                                                 │
│ Tiempo Estimado: 40 horas                       │
│ Tiempo Real: 18 horas                           │
└─────────────────────────────────────────────────┘
```

#### Sección: Asignación

- **Empleado Asignado**: Nombre completo y email
- **Asignado por**: Responsable que creó la tarea
- **Fecha de Asignación**: Cuándo se creó la tarea

#### Sección: Fechas

- **Fecha de Inicio**: Cuándo comenzó a trabajarse
- **Fecha de Vencimiento**: Límite para completar
- **Fecha de Finalización**: Cuándo se completó (si aplica)

#### Sección: Observaciones

- Notas iniciales del asignador
- Actualizaciones del empleado
- Historial de cambios

#### Botones de Acción

Según su rol y estado de la tarea:

- **📝 Actualizar Estado** (Empleados con tareas no finalizadas)
- **✏️ Editar** (Admin/Responsable)
- **🖨️ Imprimir** (Todos)
- **◀️ Volver** (Todos)

---

## Actualizar Estado de Tarea

Permite a los empleados reportar el progreso de sus tareas.

### ¿Quién puede actualizar?

- **Empleados**: Solo sus propias tareas
- **Administradores**: Cualquier tarea
- **Responsables**: Tareas que ellos crearon

### Pasos para actualizar:

1. **Acceso**
   - Vista de tarea → Botón **"Actualizar Estado"**
   - O desde la lista de tareas

2. **Formulario de Actualización**

   #### Nuevo Estado*
   
   - **Pendiente** → **En Proceso**: Cuando comienza a trabajar
   - **En Proceso** → **Finalizada**: Cuando termina la tarea
   - **Cualquiera** → **Cancelada**: Si no se puede completar
   
   #### Progreso (0-100%)*
   
   - Indique el porcentaje de avance
   - 0% = No iniciada
   - 50% = A mitad de camino
   - 100% = Completada
   
   #### Tiempo Trabajado
   
   - Horas dedicadas hasta el momento
   - Útil para comparar con el tiempo estimado
   
   #### Observaciones
   
   - Describa el trabajo realizado
   - Mencione inconvenientes o hallazgos
   - Si finaliza, indique los entregables

3. **Fechas Automáticas**

   El sistema registra automáticamente:
   - **Fecha de Inicio**: Al pasar de Pendiente a En Proceso
   - **Fecha de Finalización**: Al marcar como Finalizada

### Ejemplo de Actualización

```
Estado Anterior: Pendiente
Nuevo Estado: En Proceso
Progreso: 30%
Tiempo Trabajado: 12 horas
Observaciones: 
Se completó la instalación del cableado principal.
Falta realizar las conexiones de tomacorrientes.
Se detectó que se necesitan 5 cajas adicionales.
```

### Validaciones del Sistema

- ❌ No puede marcar como finalizada si el progreso no es 100%
- ❌ No puede reducir el progreso una vez incrementado
- ⚠️ Advertencia si se excede el tiempo estimado
- ⚠️ Alerta si la tarea está vencida

---

## Editar Tarea

Permite modificar la información de una tarea existente.

### Permisos:
- **Administradores**: Cualquier tarea
- **Responsables**: Solo tareas que ellos crearon
- **Empleados**: No pueden editar (solo actualizar estado)

### Campos Editables:

- ✅ Empleado asignado (se puede reasignar)
- ✅ Título
- ✅ Descripción
- ✅ Fecha de vencimiento
- ✅ Prioridad
- ✅ Obra asociada
- ✅ Tiempo estimado
- ✅ Observaciones

### Campos No Editables:

- ❌ ID de la tarea
- ❌ Asignador original
- ❌ Fecha de asignación
- ❌ Fecha de inicio (se establece automáticamente)
- ❌ Fecha de finalización (se establece automáticamente)

### Consideraciones:

⚠️ **Reasignar empleado**: Si reasigna una tarea que ya está en proceso, el nuevo empleado verá todo el historial.

⚠️ **Cambiar fecha de vencimiento**: El empleado recibirá notificación del cambio.

⚠️ **Modificar prioridad**: Útil cuando cambian las circunstancias del proyecto.

---

## Imprimir Tarea

Genera un documento imprimible con todos los detalles de la tarea.

### Acceso:
- Desde la vista de tarea → Botón **"Imprimir"** (🖨️)
- Se abre en una nueva pestaña

### Contenido del Documento:

```
═══════════════════════════════════════════
         SAN SIMON SRL
    ORDEN DE TRABAJO - TAREA #123
═══════════════════════════════════════════

INFORMACIÓN GENERAL
─────────────────────────────────────────
Título: Instalación eléctrica Piso 2
Estado: En Proceso
Prioridad: Alta
Fecha de Emisión: 05/01/2026

ASIGNACIÓN
─────────────────────────────────────────
Empleado: Juan Pérez
Email: juan.perez@example.com
Asignado por: María González
Fecha de Asignación: 05/01/2026

DETALLES DE LA TAREA
─────────────────────────────────────────
Descripción:
[Descripción completa de la tarea]

Obra: Edificio Los Álamos
Fecha de Vencimiento: 20/01/2026

TIEMPOS
─────────────────────────────────────────
Tiempo Estimado: 40 horas
Tiempo Real: 18 horas
Progreso: 45%

OBSERVACIONES
─────────────────────────────────────────
[Observaciones y notas]

─────────────────────────────────────────
Firma del Empleado: _____________________
Fecha: ___/___/______
═══════════════════════════════════════════
```

### Opciones de Impresión:

1. **Imprimir**: Enviar a impresora física
2. **Guardar como PDF**: Desde el diálogo de impresión
3. **Compartir**: Copiar enlace o enviar por email

---

## Filtros y Búsqueda

El sistema ofrece múltiples opciones para filtrar y buscar tareas.

### Panel de Filtros

Ubicado en la parte superior de la lista de tareas.

#### Filtro por Empleado
- Lista desplegable con todos los empleados
- Muestra solo tareas del empleado seleccionado
- **Empleados**: Este filtro está preestablecido y no se puede cambiar

#### Filtro por Estado
- ⚪ Todos
- 🟡 Pendiente
- 🔵 En Proceso
- 🟢 Finalizada
- 🔴 Cancelada

#### Filtro por Prioridad
- ⚪ Todas
- 🟢 Baja
- 🟡 Media
- 🟠 Alta
- 🔴 Urgente

#### Filtro por Vencimiento
- **Todas**: Sin filtro de fecha
- **Vencidas**: Pasaron la fecha límite y no están finalizadas
- **Hoy**: Vencen el día actual
- **Esta Semana**: Vencen en los próximos 7 días

#### Búsqueda por Texto
- Busca en el título y descripción de las tareas
- Escriba palabras clave y presione Enter
- Ejemplo: "eléctrico", "piso 2", "instalación"

### Combinación de Filtros

Puede combinar múltiples filtros simultáneamente:

**Ejemplo 1**: Tareas urgentes de Juan Pérez que están pendientes
```
Empleado: Juan Pérez
Estado: Pendiente
Prioridad: Urgente
```

**Ejemplo 2**: Tareas vencidas en proceso
```
Estado: En Proceso
Vencimiento: Vencidas
```

### Limpiar Filtros

Para volver a ver todas las tareas:
1. Haga clic en **"Limpiar Filtros"**
2. O seleccione "Todos" en cada filtro

---

## Notificaciones

El sistema envía notificaciones automáticas en los siguientes casos:

### Para Empleados:

📧 **Nueva Tarea Asignada**
- Cuando se les asigna una nueva tarea
- Incluye detalles básicos y fecha de vencimiento

📧 **Tarea Modificada**
- Cuando el responsable edita una tarea asignada
- Especifica qué campos cambiaron

⏰ **Recordatorio de Vencimiento**
- 3 días antes del vencimiento
- 1 día antes del vencimiento
- El día del vencimiento

⚠️ **Tarea Vencida**
- Notificación diaria mientras esté vencida y no finalizada

### Para Administradores/Responsables:

📧 **Tarea Finalizada**
- Cuando un empleado marca su tarea como finalizada
- Incluye observaciones finales

📧 **Tarea Vencida sin Finalizar**
- Resumen diario de tareas vencidas
- Agrupado por empleado

📊 **Reporte Semanal**
- Estadísticas de tareas completadas
- Rendimiento del equipo

---

## Preguntas Frecuentes

### ❓ ¿Puedo asignar una tarea a varios empleados?

**R:** No, cada tarea solo puede asignarse a un empleado. Si necesita que varios empleados trabajen en lo mismo, debe crear una tarea separada para cada uno, o asignar a un empleado y que este coordine con otros.

### ❓ ¿Qué pasa si una tarea se vence?

**R:** La tarea aparecerá destacada en rojo en la lista. El empleado recibirá notificaciones diarias hasta que la complete. Los administradores verán estas tareas en el panel de "Tareas Vencidas".

### ❓ ¿Puedo cambiar el empleado asignado después de crear la tarea?

**R:** Sí, los administradores y el responsable que creó la tarea pueden editarla y reasignarla a otro empleado. El nuevo empleado recibirá una notificación.

### ❓ ¿Cómo marco una tarea como completada?

**R:** Como empleado:
1. Ingrese a la tarea
2. Clic en "Actualizar Estado"
3. Cambie el estado a "Finalizada"
4. Asegúrese de que el progreso esté en 100%
5. Agregue observaciones sobre lo realizado
6. Guarde los cambios

### ❓ ¿Puedo cancelar una tarea después de iniciarla?

**R:** Sí, tanto empleados como administradores pueden cambiar el estado a "Cancelada". Se recomienda agregar observaciones explicando el motivo.

### ❓ ¿Qué es el "Tiempo Estimado" y "Tiempo Real"?

**R:** 
- **Tiempo Estimado**: Horas que se espera que tome completar la tarea (establecido al crearla)
- **Tiempo Real**: Horas efectivamente trabajadas (actualizado por el empleado)

Esto ayuda a mejorar estimaciones futuras.

### ❓ ¿Los empleados pueden ver tareas de otros empleados?

**R:** No, los empleados solo ven sus propias tareas asignadas. Solo administradores y responsables ven todas las tareas.

### ❓ ¿Cómo sé qué tareas son más urgentes?

**R:** En el dashboard y la lista, las tareas se ordenan por:
1. Prioridad (Urgente primero)
2. Fecha de vencimiento (las más próximas primero)

Use los filtros para ver solo tareas urgentes o próximas a vencer.

### ❓ ¿Puedo imprimir varias tareas a la vez?

**R:** Actualmente solo se puede imprimir una tarea a la vez. Para imprimir múltiples tareas, debe hacerlo individualmente.

### ❓ ¿Se puede vincular una tarea a una obra específica?

**R:** Sí, al crear o editar una tarea puede seleccionar una obra de la lista. Esto es útil para generar reportes por proyecto.

### ❓ ¿Qué diferencia hay entre "Observaciones" iniciales y las que agrega el empleado?

**R:** 
- **Observaciones iniciales**: Las agrega el responsable al crear la tarea con instrucciones o consideraciones
- **Observaciones del empleado**: Se agregan al actualizar el estado para reportar avances, problemas o resultados

### ❓ ¿Puedo exportar la lista de tareas a Excel?

**R:** Actualmente esta función no está disponible. Puede usar la impresión individual o tomar screenshots de la lista filtrada.

### ❓ ¿Las tareas finalizadas se eliminan del sistema?

**R:** No, las tareas finalizadas se mantienen en el sistema para historial y reportes. Puede filtrar para ocultarlas de la vista principal.

---

## Soporte Técnico

Si tiene problemas o dudas adicionales sobre el uso del módulo de tareas, contacte a:

📧 **Email**: soporte@sansimon.com.ar  
📞 **Teléfono**: (370) XXX-XXXX  
🕐 **Horario de Atención**: Lunes a Viernes, 8:00 - 17:00 hs

---

**Manual de Usuario - Módulo de Tareas**  
**Versión**: 1.0  
**Fecha**: Enero 2026  
**Sistema de Gestión Constructora - SAN SIMON SRL**
