<script>
let contadorMateriales = 0;
const materialesData = JSON.parse(localStorage.getItem('materialesData')) || [];

function agregarMaterial(id = null, cantidad = null, stockDisponible = 0, precioReferencia = 0) {
    contadorMateriales++;
    const container = document.getElementById('materiales-container');
    const emptyMessage = document.getElementById('empty-message');

    const materialRow = document.createElement('div');
    materialRow.className = 'material-row border rounded p-3 mb-3';
    materialRow.id = `material-${contadorMateriales}`;

    materialRow.innerHTML = `
        <div class="row align-items-end">
            <div class="col-md-5">
                <label class="form-label">Material <span class="text-danger">*</span></label>
                <div class="material-search-container position-relative">
                    <input type="text" 
                           class="form-control material-search-input" 
                           id="material-search-${contadorMateriales}"
                           placeholder="Escriba al menos 3 caracteres para buscar..."
                           autocomplete="off"
                           oninput="filtrarMateriales(${contadorMateriales})"
                           onfocus="mostrarListaMateriales(${contadorMateriales})"
                           onblur="setTimeout(() => ocultarListaMateriales(${contadorMateriales}), 200)"
                           value="${id ? materialesData.find(m => m.id_material == id).nombre_material : ''}"
                           required>
                    <input type="hidden" class="material-select" name="materiales[]" id="material-hidden-${contadorMateriales}" value="${id || ''}">
                    <div class="material-dropdown" id="material-dropdown-${contadorMateriales}">
                        <div class="material-list" id="material-list-${contadorMateriales}">
                            <!-- Los materiales se cargarán dinámicamente -->
                        </div>
                    </div>
                </div>
                <div class="invalid-feedback">
                    Por favor seleccione un material.
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Cantidad <span class="text-danger">*</span></label>
                <input type="number" class="form-control cantidad-input" name="cantidades[]" 
                       min="1" step="1" onchange="actualizarResumen()" value="${cantidad || ''}" required>
                <div class="invalid-feedback">
                    Ingrese una cantidad válida.
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado Stock</label>
                <div id="stock-status-${contadorMateriales}" class="form-control-plaintext">
                    <span class="badge bg-secondary">${id ? (stockDisponible > 0 ? 'Disponible' : 'Sin Stock') : 'Sin seleccionar'}</span>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-outline-danger btn-sm" onclick="eliminarMaterial(${contadorMateriales})">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div id="info-material-${contadorMateriales}" class="mt-2" style="display: none;">
            <small class="text-muted">
                <strong>Stock disponible:</strong> <span class="stock-disponible">${stockDisponible}</span> 
                <span class="unidad-medida"></span> | 
                <strong>Precio:</strong> $<span class="precio-referencia">${precioReferencia.toFixed(2)}</span>
            </small>
        </div>
        <div id="alert-material-${contadorMateriales}" class="mt-2" style="display: none;"></div>
    `;

    container.appendChild(materialRow);
    emptyMessage.style.display = 'none';
    actualizarResumen();
}

function eliminarMaterial(id) {
    const materialRow = document.getElementById(`material-${id}`);
    materialRow.remove();
    
    const container = document.getElementById('materiales-container');
    const emptyMessage = document.getElementById('empty-message');
    
    if (container.children.length === 0) {
        emptyMessage.style.display = 'block';
    }
    
    actualizarResumen();
    validarMaterialesDuplicados();
}

function actualizarInfoMaterial(id, materialData) {
    const infoDiv = document.getElementById(`info-material-${id}`);
    const alertDiv = document.getElementById(`alert-material-${id}`);
    
    if (materialData) {
        const stock = parseInt(materialData.stock_actual);
        const precio = parseFloat(materialData.precio_referencia);
        const unidad = materialData.unidad_medida;
        const minimo = parseInt(materialData.stock_minimo);
        
        // Mostrar información del material
        infoDiv.style.display = 'block';
        infoDiv.querySelector('.stock-disponible').textContent = stock;
        infoDiv.querySelector('.unidad-medida').textContent = unidad;
        infoDiv.querySelector('.precio-referencia').textContent = precio.toFixed(2);
        
        // Mostrar alerta si stock bajo
        if (stock <= minimo && stock > 0) {
            alertDiv.innerHTML = '<div class="alert alert-warning alert-sm mb-0"><i class="bi bi-exclamation-triangle"></i> Este material tiene stock bajo.</div>';
            alertDiv.style.display = 'block';
        } else if (stock === 0) {
            alertDiv.innerHTML = '<div class="alert alert-danger alert-sm mb-0"><i class="bi bi-x-circle"></i> Este material no tiene stock disponible.</div>';
            alertDiv.style.display = 'block';
        } else {
            alertDiv.style.display = 'none';
        }
    } else {
        infoDiv.style.display = 'none';
        alertDiv.style.display = 'none';
    }
    
    actualizarEstadoStock(id);
    actualizarResumen();
    validarMaterialesDuplicados();
}

function actualizarEstadoStock(id) {
    const hiddenInput = document.getElementById(`material-hidden-${id}`);
    const cantidadInput = document.querySelector(`#material-${id} .cantidad-input`);
    const statusDiv = document.getElementById(`stock-status-${id}`);
    
    if (hiddenInput.value && cantidadInput.value) {
        // Buscar el material en los datos
        const materialData = materialesData.find(m => m.id_material == hiddenInput.value);
        
        if (materialData) {
            const stock = parseInt(materialData.stock_actual);
            const cantidad = parseInt(cantidadInput.value);
            
            let statusHtml = '';
            
            if (stock === 0) {
                statusHtml = '<span class="badge bg-danger"><i class="bi bi-x-circle"></i> Sin Stock</span>';
            } else if (stock < cantidad) {
                const faltante = cantidad - stock;
                statusHtml = `<span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle"></i> Parcial</span>
                             <small class="d-block text-danger">Faltan: ${faltante}</small>`;
            } else {
                statusHtml = '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Disponible</span>';
            }
            
            statusDiv.innerHTML = statusHtml;
        }
    } else {
        statusDiv.innerHTML = '<span class="badge bg-secondary">Sin seleccionar</span>';
    }
}

function validarMaterialesDuplicados() {
    const selects = document.querySelectorAll('.material-select');
    const materialesSeleccionados = [];
    let hayDuplicados = false;
    
    // Limpiar estilos previos
    selects.forEach(select => {
        const searchInput = select.parentNode.querySelector('.material-search-input');
        searchInput.classList.remove('is-invalid');
        const feedback = select.parentNode.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.textContent = 'Por favor seleccione un material.';
        }
    });
    
    // Verificar duplicados
    selects.forEach(select => {
        const valor = select.value;
        if (valor) {
            if (materialesSeleccionados.includes(valor)) {
                // Material duplicado encontrado
                const searchInput = select.parentNode.querySelector('.material-search-input');
                searchInput.classList.add('is-invalid');
                const feedback = select.parentNode.parentNode.querySelector('.invalid-feedback');
                if (feedback) {
                    feedback.textContent = 'Este material ya fue seleccionado.';
                }
                hayDuplicados = true;
            } else {
                materialesSeleccionados.push(valor);
            }
        }
    });
    
    // Mostrar alerta general si hay duplicados
    const alertContainer = document.getElementById('alert-container');
    if (hayDuplicados) {
        alertContainer.innerHTML = `
            <div class="alert alert-warning alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> 
                <strong>Atención:</strong> No puede seleccionar el mismo material más de una vez.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
    } else {
        alertContainer.innerHTML = '';
    }
    
    return !hayDuplicados;
}

function actualizarResumen() {
    let totalItems = 0;
    let valorTotal = 0;
    let disponibles = 0;
    let parciales = 0;
    let sinStock = 0;
    
    document.querySelectorAll('.material-row').forEach(row => {
        const hiddenInput = row.querySelector('.material-select');
        const cantidadInput = row.querySelector('.cantidad-input');
        
        if (hiddenInput.value && cantidadInput.value) {
            const materialData = materialesData.find(m => m.id_material == hiddenInput.value);
            
            if (materialData) {
                const stock = parseInt(materialData.stock_actual);
                const precio = parseFloat(materialData.precio_referencia);
                const cantidad = parseInt(cantidadInput.value);
                
                totalItems++;
                valorTotal += cantidad * precio;
                
                if (stock === 0) {
                    sinStock++;
                } else if (stock < cantidad) {
                    parciales++;
                } else {
                    disponibles++;
                }
            }
        }
    });
    
    document.getElementById('total-items').textContent = totalItems;
    document.getElementById('valor-total').textContent = '$' + valorTotal.toLocaleString('es-AR', {minimumFractionDigits: 2});
    document.getElementById('items-disponibles').textContent = disponibles;
    document.getElementById('items-parciales').textContent = parciales;
    document.getElementById('items-sin-stock').textContent = sinStock;
}

// Función mejorada para búsqueda inteligente de materiales
function buscarMaterialesInteligente(searchTerm) {
    const term = searchTerm.toLowerCase().trim();
    
    if (term.length < 3) {
        return [];
    }
    
    // Función para calcular relevancia de coincidencia
    function calcularRelevancia(material, searchTerm) {
        const nombre = material.nombre_material.toLowerCase();
        const palabras = nombre.split(/\s+/);
        let score = 0;
        
        // Coincidencia exacta al inicio del nombre (máxima prioridad)
        if (nombre.startsWith(searchTerm)) {
            score += 100;
        }
        
        // Coincidencia al inicio de cualquier palabra (alta prioridad)
        for (let palabra of palabras) {
            if (palabra.startsWith(searchTerm)) {
                score += 50;
                break;
            }
        }
        
        // Coincidencia de palabra completa (media prioridad)
        if (palabras.includes(searchTerm)) {
            score += 30;
        }
        
        // Coincidencia parcial en cualquier parte (baja prioridad)
        if (nombre.includes(searchTerm) && score === 0) {
            score += 10;
        }
        
        // Bonus por longitud de coincidencia
        const coincidenceRatio = searchTerm.length / nombre.length;
        score += coincidenceRatio * 5;
        
        return score;
    }
    
    // Filtrar y ordenar materiales por relevancia
    const materialesConScore = materialesData
        .map(material => ({
            ...material,
            relevancia: calcularRelevancia(material, term)
        }))
        .filter(material => material.relevancia > 0)
        .sort((a, b) => b.relevancia - a.relevancia)
        .slice(0, 20); // Limitar a 20 resultados
    
    return materialesConScore;
}

// Funciones para el buscador de materiales
function filtrarMateriales(id) {
    const searchInput = document.getElementById(`material-search-${id}`);
    const materialList = document.getElementById(`material-list-${id}`);
    const dropdown = document.getElementById(`material-dropdown-${id}`);
    const searchTerm = searchInput.value.toLowerCase().trim();
    
    // Limpiar lista anterior
    materialList.innerHTML = '';
    
    // Solo buscar si hay al menos 3 caracteres
    if (searchTerm.length < 3) {
        dropdown.style.display = 'none';
        return;
    }
    
    // Usar búsqueda inteligente
    const materialesFiltrados = buscarMaterialesInteligente(searchTerm);
    
    if (materialesFiltrados.length > 0) {
        materialesFiltrados.forEach(material => {
            const option = document.createElement('div');
            option.className = 'material-option';
            option.onclick = () => seleccionarMaterial(id, material.id_material, material.nombre_material, material);
            
            // Determinar clase de stock
            let stockClass = 'text-success';
            let stockText = 'Disponible';
            let stockIcon = 'bi-check-circle';
            
            if (material.stock_actual === 0) {
                stockClass = 'text-danger';
                stockText = 'Sin stock';
                stockIcon = 'bi-x-circle';
            } else if (material.stock_actual <= material.stock_minimo) {
                stockClass = 'text-warning';
                stockText = 'Stock bajo';
                stockIcon = 'bi-exclamation-triangle';
            }
            
            // Resaltar término de búsqueda en el nombre | linea 666 <i class="bi bi-currency-dollar"></i> $${parseFloat(material.precio_referencia).toFixed(2)}
            const nombreResaltado = material.nombre_material.replace(
                new RegExp(`(${searchTerm})`, 'gi'),
                '<mark>$1</mark>'
            );
            
            option.innerHTML = `
                <div class="material-name">${nombreResaltado}</div>
                <div class="material-info">
                    <small class="text-muted">
                        <span class="${stockClass}">
                            <i class="bi ${stockIcon}"></i> ${material.stock_actual} ${material.unidad_medida} (${stockText})
                        </span> 
                        
                    </small>
                </div>
            `;
            
            materialList.appendChild(option);
        });
        
        dropdown.style.display = 'block';
    } else {
        // Mostrar mensaje de no resultados
        const noResults = document.createElement('div');
        noResults.className = 'no-results text-muted p-3 text-center';
        noResults.innerHTML = `
            <i class="bi bi-search"></i> 
            No se encontraron materiales que coincidan con "<strong>${searchTerm}</strong>"
            <br><small>Intente con términos más específicos o diferentes palabras clave</small>
        `;
        materialList.appendChild(noResults);
        dropdown.style.display = 'block';
    }
}

function mostrarListaMateriales(id) {
    const searchInput = document.getElementById(`material-search-${id}`);
    if (searchInput.value.length >= 3) {
        filtrarMateriales(id);
    }
}

function ocultarListaMateriales(id) {
    const dropdown = document.getElementById(`material-dropdown-${id}`);
    dropdown.style.display = 'none';
}

function seleccionarMaterial(id, materialId, materialName, materialData) {
    const searchInput = document.getElementById(`material-search-${id}`);
    const hiddenInput = document.getElementById(`material-hidden-${id}`);
    const dropdown = document.getElementById(`material-dropdown-${id}`);
    
    // Actualizar inputs
    searchInput.value = materialName;
    hiddenInput.value = materialId;
    
    // Ocultar dropdown
    dropdown.style.display = 'none';
    
    // Actualizar información del material
    actualizarInfoMaterial(id, materialData);
    
    // Validar duplicados
    validarMaterialesDuplicados();
}

// Event listeners para actualizar cuando se cambie la cantidad
document.addEventListener('input', function(e) {
    if (e.target.classList.contains('cantidad-input')) {
        const materialRow = e.target.closest('.material-row');
        const id = materialRow.id.split('-')[1];
        actualizarEstadoStock(id);
        actualizarResumen();
    }
});

// Event listener para validar duplicados al cambiar material
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('material-select')) {
        validarMaterialesDuplicados();
    }
});

// Agregar un material por defecto al cargar
document.addEventListener('DOMContentLoaded', function() {
    agregarMaterial();
    inicializarMateriales();
});

// Validación del formulario
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                // Validar materiales duplicados antes de enviar
                if (!validarMaterialesDuplicados()) {
                    event.preventDefault();
                    event.stopPropagation();
                    return false;
                }
                
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>