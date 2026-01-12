# Manual de Usuario - Módulo de Pedidos de Materiales

## Tabla de Contenidos

1. [Introducción](#introducción)
2. [Roles y Permisos](#roles-y-permisos)
3. [Estados del Pedido](#estados-del-pedido)
4. [Crear un Nuevo Pedido](#crear-un-nuevo-pedido)
5. [Visualizar Pedidos](#visualizar-pedidos)
6. [Gestión de Etapas del Pedido](#gestión-de-etapas-del-pedido)
7. [Procesar Pedidos](#procesar-pedidos)
8. [Editar un Pedido](#editar-un-pedido)
9. [Métricas y Reportes](#métricas-y-reportes)
10. [Preguntas Frecuentes](#preguntas-frecuentes)

---

## Introducción

El **Módulo de Pedidos de Materiales** permite gestionar el ciclo completo de solicitud, aprobación, retiro y entrega de materiales para las obras en construcción. Este sistema garantiza un seguimiento detallado de cada etapa del pedido, asignación de responsables y control de inventario.

### Características Principales

- ✅ Creación de pedidos con múltiples materiales
- ✅ Verificación automática de stock disponible
- ✅ Flujo de aprobación con 4 etapas
- ✅ Asignación de usuarios responsables por etapa
- ✅ Registro de fechas y horas de cada evento
- ✅ Control de inventario automático
- ✅ Historial completo de cambios
- ✅ Métricas y análisis de rendimiento

---

## Roles y Permisos

### 🔴 Administrador
- **Permisos completos** en todo el módulo
- Crear, editar y eliminar pedidos
- Aprobar, rechazar y cancelar pedidos
- **Editar etapas**: Modificar usuarios y fechas de todas las etapas
- Acceso a todos los reportes y métricas

### 🟡 Responsable de Obra
- Crear pedidos para sus obras
- Ver pedidos de sus obras
- Aprobar/rechazar pedidos
- Retirar y confirmar recepción de materiales
- Acceso a reportes generales

### 🟢 Trabajador
- Ver pedidos relacionados con sus tareas
- **No puede** crear ni modificar pedidos

---

## Estados del Pedido

El sistema maneja 5 estados diferentes para los pedidos:

### 📋 Pendiente (Amarillo)
- **Descripción**: Pedido creado, esperando aprobación
- **Siguiente acción**: Aprobar o rechazar
- **Quién puede actuar**: Administrador o Responsable

### ✅ Aprobado (Azul claro)
- **Descripción**: Pedido aprobado, listo para retiro
- **Siguiente acción**: Retirar materiales
- **Efecto**: Stock se reserva pero no se descuenta
- **Quién puede actuar**: Administrador o Responsable

### 📦 Retirado (Amarillo)
- **Descripción**: Materiales retirados de almacén
- **Siguiente acción**: Confirmar entrega en obra
- **Efecto**: **Stock se descuenta** del inventario
- **Quién puede actuar**: Administrador o Responsable

### ✔️ Entregado (Verde) *[anteriormente "Recibido"]*
- **Descripción**: Materiales recibidos y confirmados en obra
- **Estado final**: Pedido completado exitosamente
- **Efecto**: Cierre del ciclo del pedido

### ❌ Cancelado (Rojo)
- **Descripción**: Pedido cancelado en cualquier etapa
- **Efecto**: 
  - Si estaba en "Pendiente": No afecta stock
  - Si estaba en "Retirado": **Devuelve stock** al inventario
- **Irreversible**: No se puede reactivar

---

## Crear un Nuevo Pedido

### Paso 1: Acceder al Formulario

1. En el menú principal, ir a **Pedidos** → **Nuevo Pedido**
2. O desde la lista de pedidos, clic en botón **[+ Nuevo Pedido]**

### Paso 2: Información Básica

**Campos obligatorios:**

- **Obra**: Seleccionar la obra destino del pedido
  - Solo aparecen obras en estado "Planificada" o "En Progreso"
  
- **Observaciones** *(opcional)*: Agregar notas o instrucciones especiales

### Paso 3: Agregar Materiales

#### Búsqueda de Materiales

1. Usar el campo **"Buscar material..."**
   - Escribe el nombre del material
   - Aparecerá un listado filtrado

2. Hacer clic sobre el material deseado

#### Verificación de Stock

Al seleccionar un material, el sistema muestra:

- ✅ **Stock Disponible**: Cantidad actual en inventario
- ⚠️ **Stock Insuficiente**: Si no hay suficiente, aparece alerta
- 💰 **Precio Referencia**: Valor unitario del material

#### Configurar Cantidad

1. **Cantidad Solicitada**: Ingresar la cantidad deseada
   - El sistema valida contra el stock disponible
   
2. **Estados posibles**:
   - ✅ **Stock Completo**: Verde - Hay suficiente stock
   - ⚠️ **Stock Parcial**: Amarillo - Hay stock pero no suficiente
   - ❌ **Sin Stock**: Rojo - No hay stock disponible

#### Agregar Múltiples Materiales

- Repetir el proceso para cada material
- Los materiales aparecen listados en una tabla
- Se puede eliminar materiales antes de guardar

### Paso 4: Revisión y Confirmación

**Antes de guardar, verificar:**

- ✅ Obra correcta
- ✅ Todos los materiales necesarios agregados
- ✅ Cantidades correctas
- ✅ Disponibilidad de stock

**Botones de acción:**

- **[Guardar Pedido]**: Crea el pedido en estado "Pendiente"
- **[Cancelar]**: Descarta el pedido y vuelve a la lista

### Resultado

- Sistema genera **número de pedido único** automáticamente
- Estado inicial: **Pendiente**
- Usuario creador queda registrado
- Fecha y hora de creación se registran automáticamente

---

## Visualizar Pedidos

### Lista de Pedidos

**Acceso**: Menú **Pedidos** → **Ver Pedidos**

#### Filtros Disponibles

1. **Por Estado**: 
   - Todos / Pendiente / Aprobado / Retirado / Entregado / Cancelado

2. **Por Obra**: 
   - Filtrar pedidos de una obra específica

3. **Por Fecha**:
   - Rango personalizado de fechas

4. **Búsqueda**:
   - Por número de pedido u observaciones

#### Información Mostrada

| Columna | Descripción |
|---------|-------------|
| **#** | Número de pedido |
| **Obra** | Nombre de la obra destino |
| **Fecha** | Fecha de creación |
| **Estado** | Badge de color según estado actual |
| **Valor Total** | Suma del valor de todos los materiales |
| **Acciones** | Botones de acción disponibles |

#### Acciones Rápidas

- 👁️ **Ver**: Ver detalles completos del pedido
- ✏️ **Editar**: Modificar materiales (solo si está Pendiente)
- ✅ **Aprobar**: Aprobar el pedido (solo Administrador/Responsable)
- 🔄 **Procesar**: Cambiar estado del pedido

---

## Gestión de Etapas del Pedido

### Las 4 Etapas

Cada pedido pasa por 4 etapas controladas:

```
1. CREACIÓN → 2. APROBACIÓN → 3. RETIRO → 4. ENTREGA
```

### Visualización de Etapas

En la **vista detallada** del pedido, se muestra un **timeline** con:

- ✅ Etapas completadas (verde con check)
- ⏳ Etapa actual (azul)
- ⚪ Etapas pendientes (gris)

Para cada etapa se muestra:
- 👤 Usuario responsable
- 📅 Fecha y hora
- ✔️ Estado de completado

### Editar Etapas (Solo Administradores)

#### ¿Por qué editar etapas?

- Corregir errores en fechas
- Reasignar responsables
- Ajustar tiempos por eventos especiales
- Auditoría y control de procesos

#### Acceso

1. Ir a **Ver Pedido**
2. Clic en botón **[✏️ Editar Etapas]** (solo visible para Administradores)

#### Formulario de Edición

**Información del Pedido**:
- Número de pedido
- Obra
- Estado actual → Estado nuevo (si cambia)
- Valor total

**Alertas importantes**:
- 💡 **Alerta informativa**: Explica cómo se determina el estado automáticamente
- ⚠️ **Alerta de advertencia**: Todos los cambios quedan registrados

#### Editar cada Etapa

##### 1️⃣ Creación (Solo lectura)
- **Usuario**: Creador del pedido (no editable)
- **Fecha/Hora**: Fecha de creación (no editable)

##### 2️⃣ Aprobación
- **Usuario que Aprobó**: Seleccionar de lista de usuarios
  - Solo usuarios activos
  - Si hay usuario pero no fecha → ⚠️ Error de validación
  
- **Fecha/Hora de Aprobación**: 
  - Debe ser **posterior** a fecha de creación
  - Si está vacía pero hay usuario → ⚠️ Error de validación

##### 3️⃣ Retiro
- **Usuario que Retiró**: Seleccionar responsable del retiro
- **Fecha/Hora de Retiro**: 
  - Debe ser posterior a fecha de aprobación
  - Validación de coherencia

##### 4️⃣ Entrega
- **Usuario que Recibió**: Seleccionar quien confirma recepción
- **Fecha/Hora de Recepción**: 
  - Debe ser posterior a fecha de retiro
  - Validación de secuencia lógica

#### Validaciones Automáticas

El sistema valida en **tiempo real**:

✅ **Validación de Coherencia**:
- No puede haber usuario sin fecha
- No puede haber fecha sin usuario

✅ **Validación Cronológica**:
- Las fechas deben seguir orden lógico
- Creación < Aprobación < Retiro < Entrega

✅ **Vista Previa de Estado**:
- Muestra el nuevo estado antes de guardar
- Ejemplo: "Pendiente → Entregado" con flecha

#### Guardar Cambios

Al hacer clic en **[💾 Guardar Cambios]**:

1. Sistema valida todas las reglas
2. Si hay errores, muestra mensajes específicos
3. Si todo está OK:
   - Actualiza las etapas
   - **Actualiza el estado automáticamente**
   - Registra en historial de ediciones
   - Guarda IP del administrador
   - Registra fecha/hora del cambio

#### Historial de Ediciones

Toda edición se registra en tabla `historial_edicion_etapas_pedidos`:

- 📝 Usuario administrador que editó
- 🕐 Fecha y hora de la edición
- 🌐 Dirección IP
- 📋 Valores anteriores y nuevos

---

## Procesar Pedidos

### Estados y Transiciones Permitidas

| Estado Actual | Acciones Permitidas |
|---------------|---------------------|
| **Pendiente** | ✅ Aprobar<br>❌ Cancelar |
| **Aprobado** | 📦 Retirar<br>❌ Cancelar |
| **Retirado** | ✔️ Marcar como Entregado<br>❌ Cancelar |
| **Entregado** | *(Estado final, sin acciones)* |
| **Cancelado** | *(Estado final, sin acciones)* |

### Aprobar un Pedido

#### Requisitos:
- Ser Administrador o Responsable
- Pedido en estado "Pendiente"

#### Pasos:
1. Ir a **Ver Pedido** o desde la lista
2. Clic en **[✅ Aprobar]**
3. Confirmar la acción

#### Efectos:
- ✅ Estado cambia a "Aprobado"
- 👤 Se registra usuario que aprobó
- 📅 Se registra fecha/hora de aprobación
- 🔔 Materiales quedan reservados (stock no se descuenta aún)

### Retirar Materiales

#### Requisitos:
- Ser Administrador o Responsable
- Pedido en estado "Aprobado"
- **Tener stock disponible** de todos los materiales

#### Pasos:
1. Ver pedido aprobado
2. Clic en **[📦 Retirar]**
3. Sistema muestra confirmación con detalle de materiales
4. Confirmar retiro

#### Efectos:
- ✅ Estado cambia a "Retirado"
- 👤 Se registra usuario que retiró
- 📅 Se registra fecha/hora de retiro
- ⚠️ **IMPORTANTE**: Stock se **descuenta del inventario**

### Marcar como Entregado

#### Requisitos:
- Ser Administrador o Responsable
- Pedido en estado "Retirado"

#### Pasos:
1. Ver pedido retirado
2. Clic en **[✔️ Marcar como Entregado]**
3. Confirmar recepción

#### Efectos:
- ✅ Estado cambia a "Entregado" *(se muestra como verde)*
- 👤 Se registra usuario que recibió
- 📅 Se registra fecha/hora de recepción
- 🏁 Cierre del ciclo del pedido

### Cancelar un Pedido

#### Requisitos:
- Ser Administrador o Responsable
- Pedido **NO** puede estar en "Entregado" ni "Cancelado"

#### Pasos:
1. Ver pedido a cancelar
2. Clic en **[❌ Cancelar]**
3. Sistema solicita confirmación
4. Confirmar cancelación

#### Efectos según estado:

**Si estaba en "Pendiente":**
- Estado → Cancelado
- No afecta inventario (no se había descontado)

**Si estaba en "Aprobado":**
- Estado → Cancelado
- Libera reserva de materiales
- No afecta inventario real

**Si estaba en "Retirado":**
- Estado → Cancelado
- ⚠️ **IMPORTANTE**: **Stock se devuelve al inventario**
- Registra devolución en historial

---

## Editar un Pedido

### ¿Cuándo se puede editar?

✅ **Solo pedidos en estado "Pendiente"**

❌ **No se puede editar** si el pedido está:
- Aprobado
- Retirado
- Entregado
- Cancelado

### ¿Qué se puede editar?

- 📝 Observaciones
- ➕ Agregar nuevos materiales
- ➖ Eliminar materiales
- 🔢 Modificar cantidades

### Pasos para Editar

1. Ir a lista de pedidos
2. Localizar pedido en estado "Pendiente"
3. Clic en **[✏️ Editar]**
4. Modificar lo necesario
5. Clic en **[💾 Guardar Cambios]**

### Restricciones

- No se puede cambiar la **obra destino**
- No se puede cambiar el **estado** (usar "Procesar" en su lugar)
- Debe mantener al menos 1 material

---

## Métricas y Reportes

### Reporte: Métricas de Pedidos

**Acceso**: Menú **Reportes** → **Métricas de Pedidos**

#### Filtros Disponibles

- **Rango de Fechas**: Inicio y fin del período a analizar
- **Obra Específica**: Filtrar métricas de una obra (opcional)

#### Secciones del Reporte

### 1. 📊 Estadísticas Generales

Muestra tarjetas con:
- Cantidad de pedidos por estado
- Porcentaje de cada estado
- Total general de pedidos

**Colores de badges:**
- 🟡 Pendiente: Amarillo
- 🔵 Aprobado: Azul claro
- 🟡 Retirado: Amarillo
- 🟢 Entregado: Verde
- 🔴 Cancelado: Rojo

### 2. ⏱️ Tiempos Promedio Entre Etapas

**Análisis de eficiencia** del proceso:

- **Creación → Aprobación**: Tiempo de aprobación
- **Aprobación → Retiro**: Tiempo de preparación
- **Retiro → Entrega**: Tiempo de transporte
- **Tiempo Total**: Ciclo completo promedio

**Visualización:**
- Barras de progreso proporcionales
- Horas y días promedio
- Porcentaje de cada etapa respecto al total

**Interpretación:**
- ✅ Tiempos cortos = Proceso eficiente
- ⚠️ Tiempos largos = Posibles cuellos de botella

### 3. ⚠️ Pedidos Atrasados

Lista de pedidos con **más de 48 horas** en el mismo estado.

**Información mostrada:**
- Número de pedido (con enlace directo)
- Obra
- Estado actual
- Tiempo en ese estado (días y horas)

**Interpretación:**
- 🟢 Lista vacía = ¡Excelente! Todo en tiempo
- 🔴 Pedidos listados = Requieren atención urgente

### 4. 📈 Gráfico de Tendencia Diaria

Gráfico de líneas mostrando:
- Cantidad de pedidos creados por día
- Tendencia en el período seleccionado

**Utilidad:**
- Identificar días de mayor demanda
- Planificar recursos
- Detectar patrones estacionales

### 5. 🏆 Top 10 Materiales Más Pedidos

Ranking de materiales con mayor demanda:

**Columnas:**
- Posición (#)
- Nombre del material
- Cantidad de pedidos que lo incluyen
- Cantidad total solicitada
- Valor total acumulado

**Utilidad:**
- Planificación de compras
- Identificar materiales críticos
- Optimizar inventario

### 6. 🏗️ Rendimiento por Obra

Análisis comparativo de obras:

**Métricas por obra:**
- Total de pedidos
- Pedidos completados (entregados)
- Tasa de éxito (%)
- Valor total de pedidos

**Indicadores de color:**
- 🟢 ≥ 80% éxito: Verde (Excelente)
- 🟡 50-79% éxito: Amarillo (Regular)
- 🔴 < 50% éxito: Rojo (Requiere atención)

---

## Preguntas Frecuentes

### ❓ ¿Qué pasa si apruebo un pedido sin stock?

El sistema **no permite** aprobar pedidos sin stock. Primero debe ingresar materiales al inventario.

### ❓ ¿Puedo cancelar un pedido ya entregado?

**No**. Los pedidos en estado "Entregado" son finales y no se pueden cancelar. Si hubo un error, debe registrarse como una devolución de materiales.

### ❓ ¿Se puede modificar un pedido aprobado?

**No**. Solo se pueden editar pedidos en estado "Pendiente". Si necesita cambios, debe:
1. Cancelar el pedido actual
2. Crear un nuevo pedido con los datos correctos

### ❓ ¿Cuándo se descuenta el stock del inventario?

El stock se descuenta **al marcar el pedido como "Retirado"**, no al aprobar. Esto permite planificar sin afectar el inventario inmediatamente.

### ❓ ¿Qué pasa si cancelo un pedido que ya fue retirado?

El sistema **devuelve automáticamente el stock** al inventario, como si los materiales hubieran sido devueltos al almacén.

### ❓ ¿Puedo cambiar el responsable de una etapa después?

**Sí**, pero solo los **Administradores** pueden hacerlo a través de la función **"Editar Etapas"**. Todos los cambios quedan registrados en el historial.

### ❓ ¿Cómo sé quién aprobó un pedido?

En la vista detallada del pedido, en el **timeline de etapas**, se muestra el nombre del usuario que ejecutó cada acción (aprobación, retiro, entrega).

### ❓ ¿Los pedidos cancelados aparecen en reportes?

Sí, aparecen en las estadísticas generales para tener visibilidad completa. Puedes filtrarlos si deseas excluirlos del análisis.

### ❓ ¿Qué significa "Stock Parcial"?

Indica que hay stock disponible, pero **no suficiente** para cubrir la cantidad solicitada. Puedes:
- Reducir la cantidad
- Dividir en dos pedidos
- Esperar a que llegue más inventario

### ❓ ¿Se pueden crear pedidos para obras canceladas?

**No**. Solo aparecen obras en estado "Planificada" o "En Progreso" al crear un pedido.

### ❓ ¿Cómo funcionan las validaciones de fechas?

El sistema valida que las fechas sigan un **orden cronológico lógico**:
```
Fecha Creación < Fecha Aprobación < Fecha Retiro < Fecha Entrega
```

Si intentas guardar fechas fuera de orden, el sistema mostrará un error específico.

### ❓ ¿Puedo exportar los reportes?

Actualmente los reportes se pueden imprimir (Ctrl+P). La funcionalidad de exportación a Excel está planificada para futuras versiones.

### ❓ ¿Cada cuánto se actualiza el reporte de métricas?

Los reportes muestran **datos en tiempo real**. Cada vez que actualizas la página o cambias los filtros, se consultan los datos actuales de la base de datos.

### ❓ ¿Qué es el "historial de ediciones"?

Es un registro completo de todos los cambios realizados por administradores en las etapas de pedidos. Incluye:
- Quién hizo el cambio
- Cuándo lo hizo
- Desde qué dirección IP
- Qué valores cambió

Este historial es **permanente** y sirve para auditoría.

---

## Glosario de Términos

| Término | Definición |
|---------|------------|
| **Stock** | Cantidad disponible de un material en inventario |
| **Etapa** | Fase del proceso del pedido (Creación, Aprobación, Retiro, Entrega) |
| **Timeline** | Línea de tiempo visual que muestra el progreso del pedido |
| **Badge** | Etiqueta de color que indica el estado del pedido |
| **Responsable** | Usuario asignado a ejecutar una acción en una etapa |
| **Stock Parcial** | Hay material disponible pero no en cantidad suficiente |
| **Stock Reservado** | Stock comprometido para un pedido aprobado |
| **Tasa de Éxito** | Porcentaje de pedidos completados vs total de pedidos |
| **Tiempo de Ciclo** | Duración total desde creación hasta entrega |
| **Cuello de Botella** | Etapa que demora más tiempo y retrasa el proceso |

---

## Soporte y Contacto

Para reportar errores, solicitar nuevas funcionalidades o recibir capacitación adicional, contactar al **Administrador del Sistema**.

**Última actualización**: Enero 2026  
**Versión del sistema**: 2.0

---

## Anexos

### A. Atajos de Teclado

| Tecla | Acción |
|-------|--------|
| `Ctrl + P` | Imprimir vista actual |
| `F5` | Actualizar datos |
| `Esc` | Cerrar modal/diálogo |

### B. Códigos de Color

| Color | Significado |
|-------|-------------|
| 🟢 Verde | Éxito, completado, disponible |
| 🔵 Azul | Información, en proceso |
| 🟡 Amarillo | Advertencia, pendiente |
| 🔴 Rojo | Error, cancelado, crítico |
| ⚫ Gris | Inactivo, no disponible |

### C. Flujo Completo del Proceso

```
┌─────────────────┐
│  CREAR PEDIDO   │ ← Usuario/Responsable
│   (Pendiente)   │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  APROBAR        │ ← Admin/Responsable
│   (Aprobado)    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  RETIRAR        │ ← Admin/Responsable
│   (Retirado)    │   ** DESCUENTA STOCK **
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  ENTREGAR       │ ← Admin/Responsable
│   (Entregado)   │
└─────────────────┘
         
         
    En cualquier momento antes de Entregado:
              ↓
      ┌─────────────┐
      │  CANCELAR   │
      │ (Cancelado) │
      └─────────────┘
```

---

**Fin del Manual de Usuario**
