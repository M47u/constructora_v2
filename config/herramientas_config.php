<?php
/**
 * Configuración de Condiciones y Estados de Herramientas
 * Sistema de Gestión de Constructora v2.0
 * 
 * Este archivo centraliza las definiciones de condiciones y estados
 * para mantener consistencia en todo el sistema
 */

// =====================================================
// CONDICIONES DE HERRAMIENTAS
// =====================================================

/**
 * Condiciones posibles para una herramienta
 * Se aplica a: condicion_general, condicion_retiro, condicion_devolucion
 */
define('CONDICIONES_HERRAMIENTAS', [
    'nueva' => 'Nueva',
    'usada' => 'Usada',
    'reparada' => 'Reparada',
    'para_reparacion' => 'Para Reparación',
    'perdida' => 'Perdida',
    'de_baja' => 'De Baja'
]);

/**
 * Clases CSS para cada condición (para badges y colores)
 */
define('CONDICIONES_CSS_CLASSES', [
    'nueva' => 'bg-success',
    'usada' => 'bg-primary',
    'reparada' => 'bg-info',
    'para_reparacion' => 'bg-warning',
    'perdida' => 'bg-danger',
    'de_baja' => 'bg-secondary'
]);

/**
 * Iconos Bootstrap para cada condición
 */
define('CONDICIONES_ICONS', [
    'nueva' => 'bi-star-fill',
    'usada' => 'bi-check-circle',
    'reparada' => 'bi-wrench',
    'para_reparacion' => 'bi-exclamation-triangle',
    'perdida' => 'bi-question-circle',
    'de_baja' => 'bi-x-circle'
]);

// =====================================================
// ESTADOS DE HERRAMIENTAS
// =====================================================

/**
 * Estados posibles para una unidad de herramienta
 * Se aplica a: estado_actual en herramientas_unidades
 */
define('ESTADOS_HERRAMIENTAS', [
    'disponible' => 'Disponible',
    'prestada' => 'Prestada',
    'en_reparacion' => 'En Reparación',
    'no_disponible' => 'No Disponible'
]);

/**
 * Clases CSS para cada estado (para badges y colores)
 */
define('ESTADOS_CSS_CLASSES', [
    'disponible' => 'bg-success',
    'prestada' => 'bg-warning text-dark',
    'en_reparacion' => 'bg-info',
    'no_disponible' => 'bg-danger'
]);

/**
 * Iconos Bootstrap para cada estado
 */
define('ESTADOS_ICONS', [
    'disponible' => 'bi-check-circle',
    'prestada' => 'bi-box-arrow-up',
    'en_reparacion' => 'bi-tools',
    'no_disponible' => 'bi-x-circle'
]);

// =====================================================
// FUNCIONES AUXILIARES
// =====================================================

/**
 * Valida si una condición es válida
 * 
 * @param string $condicion La condición a validar
 * @return bool True si es válida, false si no
 */
function es_condicion_valida($condicion) {
    return array_key_exists($condicion, CONDICIONES_HERRAMIENTAS);
}

/**
 * Valida si un estado es válido
 * 
 * @param string $estado El estado a validar
 * @return bool True si es válido, false si no
 */
function es_estado_valido($estado) {
    return array_key_exists($estado, ESTADOS_HERRAMIENTAS);
}

/**
 * Obtiene el nombre legible de una condición
 * 
 * @param string $condicion Código de la condición
 * @return string Nombre legible de la condición
 */
function get_nombre_condicion($condicion) {
    return CONDICIONES_HERRAMIENTAS[$condicion] ?? 'Desconocida';
}

/**
 * Obtiene el nombre legible de un estado
 * 
 * @param string $estado Código del estado
 * @return string Nombre legible del estado
 */
function get_nombre_estado($estado) {
    return ESTADOS_HERRAMIENTAS[$estado] ?? 'Desconocido';
}

/**
 * Obtiene la clase CSS para una condición
 * 
 * @param string $condicion Código de la condición
 * @return string Clase CSS de Bootstrap
 */
function get_clase_condicion($condicion) {
    return CONDICIONES_CSS_CLASSES[$condicion] ?? 'secondary';
}

/**
 * Obtiene la clase CSS para un estado
 * 
 * @param string $estado Código del estado
 * @return string Clase CSS de Bootstrap
 */
function get_clase_estado($estado) {
    return ESTADOS_CSS_CLASSES[$estado] ?? 'secondary';
}

/**
 * Obtiene el ícono para una condición
 * 
 * @param string $condicion Código de la condición
 * @return string Clase del ícono de Bootstrap
 */
function get_icono_condicion($condicion) {
    return CONDICIONES_ICONS[$condicion] ?? 'bi-circle';
}

/**
 * Obtiene el ícono para un estado
 * 
 * @param string $estado Código del estado
 * @return string Clase del ícono de Bootstrap
 */
function get_icono_estado($estado) {
    return ESTADOS_ICONS[$estado] ?? 'bi-circle';
}

/**
 * Determina el nuevo estado de una herramienta según su condición de devolución
 * 
 * @param string $condicion_devolucion Condición en la que se devuelve
 * @param bool $requiere_mantenimiento Si requiere mantenimiento según checkbox
 * @return string Nuevo estado para la herramienta
 */
function determinar_nuevo_estado($condicion_devolucion, $requiere_mantenimiento = false) {
    // Si está perdida o de baja, no está disponible
    if (in_array($condicion_devolucion, ['perdida', 'de_baja'])) {
        return 'no_disponible';
    }
    
    // Si requiere reparación o mantenimiento explícito
    if ($condicion_devolucion === 'para_reparacion' || $requiere_mantenimiento) {
        return 'en_reparacion';
    }
    
    // En cualquier otro caso, está disponible
    return 'disponible';
}

/**
 * Determina la nueva condición después del primer uso
 * 
 * @param string $condicion_actual Condición actual de la herramienta
 * @return string Nueva condición después del primer uso
 */
function determinar_condicion_despues_uso($condicion_actual) {
    // Si está nueva, pasa a usada después del primer préstamo
    if ($condicion_actual === 'nueva') {
        return 'usada';
    }
    
    // Mantiene la condición actual
    return $condicion_actual;
}

// =====================================================
// LÓGICA DE REPARACIONES
// =====================================================

/**
 * Verifica si una condición requiere crear registro de reparación
 * 
 * @param string $condicion Condición de la herramienta
 * @return bool True si requiere registro de reparación
 */
function requiere_registro_reparacion($condicion) {
    return $condicion === 'para_reparacion';
}

/**
 * Determina la condición después de completar una reparación
 * 
 * @return string Condición después de reparar
 */
function condicion_despues_reparacion() {
    return 'reparada';
}
