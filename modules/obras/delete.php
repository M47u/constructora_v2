<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Verificar permisos - Solo administradores pueden eliminar obras
if (!has_permission(ROLE_ADMIN)) {
    redirect(SITE_URL . '/dashboard.php');
}

$page_title = 'Eliminar Obra';

$database = new Database();
$conn = $database->getConnection();

$obra_id = (int)($_GET['id'] ?? 0);

if ($obra_id <= 0) {
    redirect(SITE_URL . '/modules/obras/list.php');
}

// Obtener datos de la obra
try {
    $query = "SELECT o.*, u.nombre, u.apellido 
              FROM obras o 
              JOIN usuarios u ON o.id_responsable = u.id_usuario 
              WHERE o.id_obra = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$obra_id]);
    $obra = $stmt->fetch();

    if (!$obra) {
        redirect(SITE_URL . '/modules/obras/list.php');
    }

    // Verificar si la obra ya está eliminada
    if ($obra['estado'] === 'eliminada') {
        $_SESSION['error_message'] = "Esta obra ya ha sido eliminada";
        redirect(SITE_URL . '/modules/obras/list.php');
    }

    // Obtener estadísticas de la obra
    $stats = [];
    
    // Contar pedidos
    $stmt_pedidos = $conn->prepare("SELECT COUNT(*) as total FROM pedidos_materiales WHERE id_obra = ?");
    $stmt_pedidos->execute([$obra_id]);
    $stats['pedidos'] = $stmt_pedidos->fetch()['total'];

    // Contar préstamos
    $stmt_prestamos = $conn->prepare("SELECT COUNT(*) as total FROM prestamos WHERE id_obra = ?");
    $stmt_prestamos->execute([$obra_id]);
    $stats['prestamos'] = $stmt_prestamos->fetch()['total'];

    // Contar tareas
    $stmt_tareas = $conn->prepare("SELECT COUNT(*) as total FROM tareas WHERE id_obra = ?");
    $stmt_tareas->execute([$obra_id]);
    $stats['tareas'] = $stmt_tareas->fetch()['total'];

} catch (Exception $e) {
    error_log("Error al obtener obra: " . $e->getMessage());
    redirect(SITE_URL . '/modules/obras/list.php');
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        $conn->beginTransaction();

        // Cambiar estado de la obra a 'eliminada'
        $update_query = "UPDATE obras SET 
                        estado = 'eliminada',
                        fecha_modificacion = NOW()
                        WHERE id_obra = ?";
        
        $stmt_update = $conn->prepare($update_query);
        $stmt_update->execute([$obra_id]);

        // Registrar la eliminación en un log (opcional)
        $log_query = "INSERT INTO logs_sistema (accion, tabla_afectada, id_registro, usuario_id, detalles, fecha_accion) 
                      VALUES ('ELIMINAR', 'obras', ?, ?, ?, NOW())";
        
        $detalles = "Obra eliminada: " . $obra['nombre_obra'] . " (Cliente: " . $obra['cliente'] . ")";
        
        try {
            $stmt_log = $conn->prepare($log_query);
            $stmt_log->execute(['ELIMINAR', 'obras', $obra_id, $_SESSION['user_id'], $detalles]);
        } catch (Exception $e) {
            // Si no existe la tabla de logs, continuar sin error
            error_log("No se pudo registrar en logs: " . $e->getMessage());
        }

        $conn->commit();

        $_SESSION['success_message'] = "La obra '{$obra['nombre_obra']}' ha sido eliminada exitosamente";
        redirect(SITE_URL . '/modules/obras/list.php');

    } catch (Exception $e) {
        $conn->rollBack();
        error_log("Error al eliminar obra: " . $e->getMessage());
        $_SESSION['error_message'] = "Error al eliminar la obra. Intente nuevamente.";
    }
}

include '../../includes/header.php';
?>

<div id="alert-container"></div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-exclamation-triangle text-danger"></i> Eliminar Obra
            </h1>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver
            </a>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <i class="bi bi-exclamation-triangle"></i> Confirmación de Eliminación
            </div>
            <div class="card-body">
                <div class="alert alert-danger">
                    <h5 class="alert-heading">
                        <i class="bi bi-exclamation-triangle"></i> ¡Atención!
                    </h5>
                    <p class="mb-0">
                        Está a punto de eliminar la obra. Esta acción cambiará el estado de la obra a "eliminada" 
                        y no podrá ser revertida fácilmente.
                    </p>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Información de la Obra</h6>
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Nombre:</strong></td>
                                <td><?php echo htmlspecialchars($obra['nombre_obra']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Cliente:</strong></td>
                                <td><?php echo htmlspecialchars($obra['cliente']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Ubicación:</strong></td>
                                <td><?php echo htmlspecialchars($obra['localidad'] . ', ' . $obra['provincia']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Responsable:</strong></td>
                                <td><?php echo htmlspecialchars($obra['nombre'] . ' ' . $obra['apellido']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Estado Actual:</strong></td>
                                <td>
                                    <?php
                                    $badge_class = '';
                                    switch ($obra['estado']) {
                                        case 'planificada':
                                            $badge_class = 'bg-info';
                                            break;
                                        case 'en_progreso':
                                            $badge_class = 'bg-warning text-dark';
                                            break;
                                        case 'finalizada':
                                            $badge_class = 'bg-success';
                                            break;
                                        case 'cancelada':
                                            $badge_class = 'bg-danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $obra['estado'])); ?>
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-muted">Datos Asociados</h6>
                        <div class="alert alert-warning">
                            <p class="mb-2"><strong>Esta obra tiene asociados:</strong></p>
                            <ul class="mb-0">
                                <li><?php echo $stats['pedidos']; ?> pedido(s) de materiales</li>
                                <li><?php echo $stats['prestamos']; ?> préstamo(s) de herramientas</li>
                                <li><?php echo $stats['tareas']; ?> tarea(s)</li>
                            </ul>
                        </div>

                        <?php if ($stats['pedidos'] > 0 || $stats['prestamos'] > 0 || $stats['tareas'] > 0): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Nota:</strong> Los datos asociados no serán eliminados, 
                            pero quedarán vinculados a una obra eliminada.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <hr>

                <form method="POST" class="text-center">
                    <div class="mb-3">
                        <div class="form-check d-inline-block">
                            <input class="form-check-input" type="checkbox" id="confirm_checkbox" required>
                            <label class="form-check-label" for="confirm_checkbox">
                                Confirmo que deseo eliminar esta obra
                            </label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-center gap-3">
                        <a href="list.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" name="confirm_delete" class="btn btn-danger" id="delete_button" disabled>
                            <i class="bi bi-trash"></i> Eliminar Obra
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkbox = document.getElementById('confirm_checkbox');
    const deleteButton = document.getElementById('delete_button');

    checkbox.addEventListener('change', function() {
        deleteButton.disabled = !this.checked;
    });

    // Confirmación adicional antes del envío
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm('¿Está completamente seguro de que desea eliminar esta obra? Esta acción no se puede deshacer fácilmente.')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
