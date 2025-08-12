// JavaScript principal para el Sistema Constructora

document.addEventListener("DOMContentLoaded", () => {
  // Importar Bootstrap
  const bootstrap = window.bootstrap

  // Inicializar tooltips de Bootstrap
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
  var tooltipList = tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))

  // Inicializar popovers de Bootstrap
  var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
  var popoverList = popoverTriggerList.map((popoverTriggerEl) => new bootstrap.Popover(popoverTriggerEl))

  // Confirmación para eliminaciones
  const deleteButtons = document.querySelectorAll(".btn-delete")
  deleteButtons.forEach((button) => {
    button.addEventListener("click", function (e) {
      e.preventDefault()
      const itemName = this.dataset.itemName || "este elemento"

      if (confirm(`¿Está seguro que desea eliminar ${itemName}? Esta acción no se puede deshacer.`)) {
        window.location.href = this.href
      }
    })
  })

  // Auto-hide alerts después de 5 segundos
  const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
  alerts.forEach((alert) => {
    setTimeout(() => {
      const bsAlert = new bootstrap.Alert(alert)
      bsAlert.close()
    }, 5000)
  })

  // Validación de formularios
  const forms = document.querySelectorAll(".needs-validation")
  forms.forEach((form) => {
    form.addEventListener("submit", (e) => {
      if (!form.checkValidity()) {
        e.preventDefault()
        e.stopPropagation()
      }
      form.classList.add("was-validated")
    })
  })

  // Búsqueda en tiempo real para tablas
  const searchInputs = document.querySelectorAll(".table-search")
  searchInputs.forEach((input) => {
    input.addEventListener("keyup", function () {
      const searchTerm = this.value.toLowerCase()
      const tableId = this.dataset.table
      const table = document.getElementById(tableId)
      const rows = table.querySelectorAll("tbody tr")

      rows.forEach((row) => {
        const text = row.textContent.toLowerCase()
        row.style.display = text.includes(searchTerm) ? "" : "none"
      })
    })
  })

  // Contador de caracteres para textareas
  const textareas = document.querySelectorAll("textarea[maxlength]")
  textareas.forEach((textarea) => {
    const maxLength = textarea.getAttribute("maxlength")
    const counter = document.createElement("small")
    counter.className = "text-muted"
    counter.textContent = `0/${maxLength}`
    textarea.parentNode.appendChild(counter)

    textarea.addEventListener("input", function () {
      const currentLength = this.value.length
      counter.textContent = `${currentLength}/${maxLength}`

      if (currentLength > maxLength * 0.9) {
        counter.className = "text-warning"
      } else {
        counter.className = "text-muted"
      }
    })
  })
})

// Funciones utilitarias
function showLoading() {
  const overlay = document.createElement("div")
  overlay.className = "spinner-overlay"
  overlay.innerHTML = `
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    `
  document.body.appendChild(overlay)
}

function hideLoading() {
  const overlay = document.querySelector(".spinner-overlay")
  if (overlay) {
    overlay.remove()
  }
}

function showAlert(message, type = "info") {
  const alertContainer = document.getElementById("alert-container") || document.body
  const alert = document.createElement("div")
  alert.className = `alert alert-${type} alert-dismissible fade show`
  alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `

  alertContainer.insertBefore(alert, alertContainer.firstChild)

  // Auto-hide después de 5 segundos
  setTimeout(() => {
    const bsAlert = new window.bootstrap.Alert(alert)
    bsAlert.close()
  }, 5000)
}

// Función para formatear fechas
function formatDate(dateString) {
  const date = new Date(dateString)
  return date.toLocaleDateString("es-AR", {
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  })
}

// Función para formatear moneda
function formatCurrency(amount) {
  return new Intl.NumberFormat("es-AR", {
    style: "currency",
    currency: "ARS",
  }).format(amount)
}

// Función para generar códigos QR (requiere librería externa)
function generateQR(text, elementId) {
  // Requiere que la librería qrcode.min.js esté cargada en la página
  const container = document.getElementById(elementId)
  if (!container) {
    console.error(`Elemento con id '${elementId}' no encontrado.`)
    return
  }
  // Limpiar contenido previo
  container.innerHTML = ""
  // Generar el QR
  new QRCode(container, {
    text: text,
    width: 128,
    height: 128,
    colorDark: "#000000",
    colorLight: "#ffffff",
    correctLevel: QRCode.CorrectLevel.H
  })
}

// Función para validar email
function isValidEmail(email) {
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
  return emailRegex.test(email)
}

// Función para validar teléfono argentino
function isValidPhone(phone) {
  const phoneRegex = /^(\+54|0)?[1-9]\d{8,9}$/
  return phoneRegex.test(phone.replace(/\s|-/g, ""))
}

// Exportar funciones para uso global
window.SistemaConstructora = {
  showLoading,
  hideLoading,
  showAlert,
  formatDate,
  formatCurrency,
  generateQR,
  isValidEmail,
  isValidPhone,
}
