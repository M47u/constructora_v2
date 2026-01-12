<?php
require_once '../../config/config.php';
require_once '../../includes/auth.php';
require_once '../../config/database.php';

$auth = new Auth();
$auth->check_session();

// Solo administradores pueden editar etapas
if (!has_permission([ROLE_ADMIN])) {
    $_SESSION['error_message'] = 'Solo los administradores pueden editar las etapas de los pedidos.';
    redirect(SITE_URL . '/modules/pedidos/view.php?id=' . ($_GET['id'] ?? 0));
    exit();
}

$page_title = 'Editar Etapas de Pedido';

$database = new Database();
$conn = $database->getConnection();

$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;
$errors = [];
$success = false;

if ($id_pedido <= 0) {
    $_SESSION['error_message'] = 'ID de pedido inválido.';
    redirect(SITE_URL . '/modules/pedidos/list.php');
    exit();
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $errors[] = "Token de seguridad inválido. Por favor, recargue la página.";
    } else {
        // VALIDACIONES DE COHERENCIA
        
        // Validar que si hay usuario, debe haber fecha
        if (!empty($_POST['id_aprobado_por']) && empty($_POST['fecha_aprobacion'])) {
            $errors[] = "Debe especificar la fecha de aprobación si asigna un usuario que aprobó.";
        }
        if (!empty($_POST['id_picking_por']) && empty($_POST['fecha_picking'])) {
            $errors[] = "Debe especificar la fecha de picking si asigna un usuario que realizó el picking.";
        }
        if (!empty($_POST['id_retirado_por']) && empty($_POST['fecha_retiro'])) {
            $errors[] = "Debe especificar la fecha de retiro si asigna un usuario que retiró.";
        }
        if (!empty($_POST['id_recibido_por']) && empty($_POST['fecha_recibido'])) {
            $errors[] = "Debe especificar la fecha de recepción si asigna un usuario que recibió.";
        }
        
        // Validar coherencia de fechas (cada etapa >= anterior)
        $fecha_creacion = strtotime($_POST['fecha_pedido']);
        
        if (!empty($_POST['fecha_aprobacion'])) {
            $fecha_aprobacion = strtotime($_POST['fecha_aprobacion']);
            if ($fecha_aprobacion < $fecha_creacion) {
                $errors[] = "La fecha de aprobación no puede ser anterior a la fecha de creación.";
            }
        }
        
        if (!empty($_POST['fecha_picking'])) {
            $fecha_picking = strtotime($_POST['fecha_picking']);
            if ($fecha_picking < $fecha_creacion) {
                $errors[] = "La fecha de picking no puede ser anterior a la fecha de creación.";
            }
            if (!empty($_POST['fecha_aprobacion']) && $fecha_picking < strtotime($_POST['fecha_aprobacion'])) {
                $errors[] = "La fecha de picking no puede ser anterior a la fecha de aprobación.";
            }
        }
        
        if (!empty($_POST['fecha_retiro'])) {
            $fecha_retiro = strtotime($_POST['fecha_retiro']);
            if ($fecha_retiro < $fecha_creacion) {
                $errors[] = "La fecha de retiro no puede ser anterior a la fecha de creación.";
            }
            if (!empty($_POST['fecha_aprobacion']) && $fecha_retiro < strtotime($_POST['fecha_aprobacion'])) {
                $errors[] = "La fecha de retiro no puede ser anterior a la fecha de aprobación.";
            }
            if (!empty($_POST['fecha_picking']) && $fecha_retiro < strtotime($_POST['fecha_picking'])) {
                $errors[] = "La fecha de retiro no puede ser anterior a la fecha de picking.";
            }
        }
        
        if (!empty($_POST['fecha_recibido'])) {
            $fecha_recibido = strtotime($_POST['fecha_recibido']);
            if ($fecha_recibido < $fecha_creacion) {
                $errors[] = "La fecha de recepción no puede ser anterior a la fecha de creación.";
            }
            if (!empty($_POST['fecha_aprobacion']) && $fecha_recibido < strtotime($_POST['fecha_aprobacion'])) {
                $errors[] = "La fecha de recepción no puede ser anterior a la fecha de aprobación.";
            }
            if (!empty($_POST['fecha_picking']) && $fecha_recibido < strtotime($_POST['fecha_picking'])) {
                $errors[] = "La fecha de recepción no puede ser anterior a la fecha de picking.";
            }
            if (!empty($_POST['fecha_retiro']) && $fecha_recibido < strtotime($_POST['fecha_retiro'])) {
                $errors[] = "La fecha de recepción no puede ser anterior a la fecha de retiro.";
            }
        }
        
        if (empty($errors)) {
        try {
            $conn->beginTransaction();
            
            $id_usuario_editor = $_SESSION['user_id'];
            $fecha_actual = date('Y-m-d H:i:s');
            $ip_usuario = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            
            // Obtener valores anteriores
            $stmt_old = $conn->prepare("SELECT id_solicitante, fecha_pedido, id_aprobado_por, fecha_aprobacion, 
                                               id_picking_por, fecha_picking,
                                               id_retirado_por, fecha_retiro, id_recibido_por, fecha_recibido 
                                        FROM pedidos_materiales WHERE id_pedido = ?");
            $stmt_old->execute([$id_pedido]);
            $valores_anteriores = $stmt_old->fetch();
            
            if (!$valores_anteriores) {
                throw new Exception("El pedido no existe.");
            }
            
            // Preparar statement para actualizar
            $campos_actualizar = [];
            $valores_actualizar = [];
            
            // ETAPA CREACIÓN
            if (isset($_POST['id_solicitante']) && $_POST['id_solicitante'] != $valores_anteriores['id_solicitante']) {
                $campos_actualizar[] = "id_solicitante = ?";
                $valores_actualizar[] = $_POST['id_solicitante'];
                
                // Registrar cambio
                $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                    (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                    VALUES (?, 'creacion', 'usuario', ?, ?, ?, ?, ?)");
                $stmt_hist->execute([$id_pedido, $valores_anteriores['id_solicitante'], $_POST['id_solicitante'], 
                                    $id_usuario_editor, $ip_usuario, $fecha_actual]);
            }
            
            if (isset($_POST['fecha_pedido']) && $_POST['fecha_pedido'] != $valores_anteriores['fecha_pedido']) {
                $campos_actualizar[] = "fecha_pedido = ?";
                $valores_actualizar[] = $_POST['fecha_pedido'];
                
                // Registrar cambio
                $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                    (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                    VALUES (?, 'creacion', 'fecha', ?, ?, ?, ?, ?)");
                $stmt_hist->execute([$id_pedido, $valores_anteriores['fecha_pedido'], $_POST['fecha_pedido'], 
                                    $id_usuario_editor, $ip_usuario, $fecha_actual]);
            }
            
            // ETAPA APROBACIÓN
            if (isset($_POST['id_aprobado_por'])) {
                $nuevo_aprobado = empty($_POST['id_aprobado_por']) ? null : $_POST['id_aprobado_por'];
                if ($nuevo_aprobado != $valores_anteriores['id_aprobado_por']) {
                    $campos_actualizar[] = "id_aprobado_por = ?";
                    $valores_actualizar[] = $nuevo_aprobado;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'aprobacion', 'usuario', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['id_aprobado_por'], $nuevo_aprobado, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            if (isset($_POST['fecha_aprobacion'])) {
                $nueva_fecha = empty($_POST['fecha_aprobacion']) ? null : $_POST['fecha_aprobacion'];
                if ($nueva_fecha != $valores_anteriores['fecha_aprobacion']) {
                    $campos_actualizar[] = "fecha_aprobacion = ?";
                    $valores_actualizar[] = $nueva_fecha;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'aprobacion', 'fecha', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['fecha_aprobacion'], $nueva_fecha, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            // ETAPA PICKING
            if (isset($_POST['id_picking_por'])) {
                $nuevo_picking = empty($_POST['id_picking_por']) ? null : $_POST['id_picking_por'];
                if ($nuevo_picking != $valores_anteriores['id_picking_por']) {
                    $campos_actualizar[] = "id_picking_por = ?";
                    $valores_actualizar[] = $nuevo_picking;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'picking', 'usuario', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['id_picking_por'], $nuevo_picking, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            if (isset($_POST['fecha_picking'])) {
                $nueva_fecha = empty($_POST['fecha_picking']) ? null : $_POST['fecha_picking'];
                if ($nueva_fecha != $valores_anteriores['fecha_picking']) {
                    $campos_actualizar[] = "fecha_picking = ?";
                    $valores_actualizar[] = $nueva_fecha;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'picking', 'fecha', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['fecha_picking'], $nueva_fecha, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            // ETAPA RETIRO
            if (isset($_POST['id_retirado_por'])) {
                $nuevo_retirado = empty($_POST['id_retirado_por']) ? null : $_POST['id_retirado_por'];
                if ($nuevo_retirado != $valores_anteriores['id_retirado_por']) {
                    $campos_actualizar[] = "id_retirado_por = ?";
                    $valores_actualizar[] = $nuevo_retirado;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'retiro', 'usuario', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['id_retirado_por'], $nuevo_retirado, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            if (isset($_POST['fecha_retiro'])) {
                $nueva_fecha = empty($_POST['fecha_retiro']) ? null : $_POST['fecha_retiro'];
                if ($nueva_fecha != $valores_anteriores['fecha_retiro']) {
                    $campos_actualizar[] = "fecha_retiro = ?";
                    $valores_actualizar[] = $nueva_fecha;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'retiro', 'fecha', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['fecha_retiro'], $nueva_fecha, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            // ETAPA RECIBIDO
            if (isset($_POST['id_recibido_por'])) {
                $nuevo_recibido = empty($_POST['id_recibido_por']) ? null : $_POST['id_recibido_por'];
                if ($nuevo_recibido != $valores_anteriores['id_recibido_por']) {
                    $campos_actualizar[] = "id_recibido_por = ?";
                    $valores_actualizar[] = $nuevo_recibido;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'recibido', 'usuario', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['id_recibido_por'], $nuevo_recibido, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            if (isset($_POST['fecha_recibido'])) {
                $nueva_fecha = empty($_POST['fecha_recibido']) ? null : $_POST['fecha_recibido'];
                if ($nueva_fecha != $valores_anteriores['fecha_recibido']) {
                    $campos_actualizar[] = "fecha_recibido = ?";
                    $valores_actualizar[] = $nueva_fecha;
                    
                    $stmt_hist = $conn->prepare("INSERT INTO historial_edicion_etapas_pedidos 
                        (id_pedido, etapa, campo_editado, valor_anterior, valor_nuevo, id_usuario_editor, ip_usuario, fecha_edicion) 
                        VALUES (?, 'recibido', 'fecha', ?, ?, ?, ?, ?)");
                    $stmt_hist->execute([$id_pedido, $valores_anteriores['fecha_recibido'], $nueva_fecha, 
                                        $id_usuario_editor, $ip_usuario, $fecha_actual]);
                }
            }
            
            // DETERMINAR ESTADO BASADO EN ETAPAS COMPLETADAS
            $nuevo_estado = 'pendiente'; // Por defecto
            
            // Obtener los valores que se van a guardar (POST o anteriores)
            $id_recibido_final = isset($_POST['id_recibido_por']) && !empty($_POST['id_recibido_por']) 
                ? $_POST['id_recibido_por'] 
                : $valores_anteriores['id_recibido_por'];
            
            $id_retirado_final = isset($_POST['id_retirado_por']) && !empty($_POST['id_retirado_por'])
                ? $_POST['id_retirado_por']
                : $valores_anteriores['id_retirado_por'];
            
            $id_picking_final = isset($_POST['id_picking_por']) && !empty($_POST['id_picking_por'])
                ? $_POST['id_picking_por']
                : $valores_anteriores['id_picking_por'];
            
            $id_aprobado_final = isset($_POST['id_aprobado_por']) && !empty($_POST['id_aprobado_por'])
                ? $_POST['id_aprobado_por']
                : $valores_anteriores['id_aprobado_por'];
            
            // Determinar el estado según la etapa más avanzada completada (se permite saltar etapas)
            if ($id_recibido_final) {
                $nuevo_estado = 'recibido';
            } elseif ($id_retirado_final) {
                $nuevo_estado = 'retirado';
            } elseif ($id_picking_final) {
                $nuevo_estado = 'picking';
            } elseif ($id_aprobado_final) {
                $nuevo_estado = 'aprobado';
            } else {
                $nuevo_estado = 'pendiente';
            }
            
            // Agregar el estado a los campos a actualizar
            $campos_actualizar[] = "estado = ?";
            $valores_actualizar[] = $nuevo_estado;
            
            // Registrar cambio de estado en seguimiento si es diferente
            $stmt_estado_actual = $conn->prepare("SELECT estado FROM pedidos_materiales WHERE id_pedido = ?");
            $stmt_estado_actual->execute([$id_pedido]);
            $estado_actual = $stmt_estado_actual->fetchColumn();
            
            if ($estado_actual !== $nuevo_estado) {
                $stmt_seguimiento = $conn->prepare("INSERT INTO seguimiento_pedidos 
                    (id_pedido, estado_anterior, estado_nuevo, observaciones, id_usuario_cambio, fecha_cambio) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt_seguimiento->execute([
                    $id_pedido, 
                    $estado_actual, 
                    $nuevo_estado, 
                    'Estado actualizado automáticamente desde edición de etapas', 
                    $id_usuario_editor, 
                    $fecha_actual
                ]);
            }
            
            // Ejecutar actualización si hay cambios
            if (!empty($campos_actualizar)) {
                $sql = "UPDATE pedidos_materiales SET " . implode(", ", $campos_actualizar) . " WHERE id_pedido = ?";
                $valores_actualizar[] = $id_pedido;
                $stmt_update = $conn->prepare($sql);
                $stmt_update->execute($valores_actualizar);
            }
            
            $conn->commit();
            $success = true;
            
            // Redireccionar
            header("Location: view.php?id=" . $id_pedido . "&edited=1");
            exit();
            
        } catch (Exception $e) {
            $conn->rollBack();
            $errors[] = "Error al actualizar las etapas: " . $e->getMessage();
            error_log("Error editando etapas pedido ID " . $id_pedido . ": " . $e->getMessage());
        }
        } // Fin de if (empty($errors))
    }
}

try {
    // Obtener información del pedido
    $stmt = $conn->prepare("SELECT p.*, o.nombre_obra
                            FROM pedidos_materiales p
                            LEFT JOIN obras o ON p.id_obra = o.id_obra
                            WHERE p.id_pedido = ?");
    $stmt->execute([$id_pedido]);
    $pedido = $stmt->fetch();
    
    if (!$pedido) {
        $_SESSION['error_message'] = 'No se encontró el pedido con ID: ' . $id_pedido;
        redirect(SITE_URL . '/modules/pedidos/list.php');
        exit();
    }
    
    // Obtener lista de usuarios activos
    $stmt_usuarios = $conn->prepare("SELECT id_usuario, nombre, apellido, rol FROM usuarios WHERE estado = 'activo' ORDER BY nombre, apellido");
    $stmt_usuarios->execute();
    $usuarios = $stmt_usuarios->fetchAll();
    
    // Si no hay usuarios, intentar sin filtro de estado
    if (empty($usuarios)) {
        $stmt_usuarios = $conn->prepare("SELECT id_usuario, nombre, apellido, rol FROM usuarios ORDER BY nombre, apellido");
        $stmt_usuarios->execute();
        $usuarios = $stmt_usuarios->fetchAll();
    }
    
    // Obtener historial de ediciones
    $stmt_hist = $conn->prepare("SELECT h.*, u.nombre, u.apellido 
                                FROM historial_edicion_etapas_pedidos h
                                LEFT JOIN usuarios u ON h.id_usuario_editor = u.id_usuario
                                WHERE h.id_pedido = ?
                                ORDER BY h.fecha_edicion DESC");
    $stmt_hist->execute([$id_pedido]);
    $historial = $stmt_hist->fetchAll();
    
} catch (Exception $e) {
    error_log("Error al obtener pedido para edición: " . $e->getMessage());
    $_SESSION['error_message'] = 'Error al cargar el pedido: ' . $e->getMessage();
    redirect(SITE_URL . '/modules/pedidos/list.php');
    exit();
}

include '../../includes/header.php';
?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>Nota:</strong> Al editar las etapas, el estado del pedido se actualizará automáticamente según la etapa más avanzada completada:
    <ul class="mb-0 mt-2">
        <li><strong>Pendiente:</strong> Solo tiene creación</li>
        <li><strong>Aprobado:</strong> Tiene usuario que aprobó</li>
        <li><strong>Retirado:</strong> Tiene usuario que retiró</li>
        <li><strong>Recibido:</strong> Tiene usuario que recibió (estado final)</li>
    </ul>
</div>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3">
                <i class="bi bi-pencil-square"></i> Editar Etapas - Pedido #<?php echo str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT); ?>
            </h1>
            <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Volver al Pedido
            </a>
        </div>
    </div>
</div>

<div class="alert alert-warning">
    <i class="bi bi-exclamation-triangle"></i>
    <strong>Atención:</strong> Esta funcionalidad es solo para administradores. Todos los cambios quedan registrados en el historial.
    Asegúrese de mantener la coherencia en las fechas y usuarios asignados.
</div>

<form method="POST" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Información básica -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle"></i> Información del Pedido
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Obra:</strong> <?php echo htmlspecialchars($pedido['nombre_obra']); ?></p>
                            <p><strong>Estado Actual:</strong> 
                                <?php 
                                $badge_class = [
                                    'pendiente' => 'bg-warning text-dark',
                                    'aprobado' => 'bg-info',
                                    'retirado' => 'bg-warning',
                                    'recibido' => 'bg-success',
                                    'cancelado' => 'bg-danger'
                                ];
                                $estado_texto = [
                                    'pendiente' => 'Pendiente',
                                    'aprobado' => 'Aprobado',
                                    'retirado' => 'Retirado',
                                    'recibido' => 'Entregado',
                                    'cancelado' => 'Cancelado'
                                ];
                                echo '<span class="badge ' . ($badge_class[$pedido['estado']] ?? 'bg-secondary') . '">' . ($estado_texto[$pedido['estado']] ?? ucfirst($pedido['estado'])) . '</span>';
                                ?>
                                <span id="nuevo-estado-badge" class="ms-2" style="display: none;">
                                    <i class="bi bi-arrow-right"></i>
                                    <span class="badge" id="estado-nuevo"></span>
                                </span>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Número:</strong> <?php echo htmlspecialchars($pedido['numero_pedido']); ?></p>
                            <p><strong>Valor Total:</strong> $<?php echo number_format($pedido['valor_total'], 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Etapas editables -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-diagram-3"></i> Etapas del Pedido
                    </h5>
                </div>
                <div class="card-body">
                    
                    <!-- Etapa 1: Creación -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="fw-bold text-primary">
                            <i class="bi bi-1-circle-fill"></i> Creación
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario Solicitante <span class="text-danger">*</span></label>
                                <select name="id_solicitante" class="form-select" required>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?php echo $u['id_usuario']; ?>" 
                                                <?php echo $u['id_usuario'] == $pedido['id_solicitante'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?> 
                                            (<?php echo ucfirst($u['rol']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Creación <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="fecha_pedido" class="form-control" 
                                       value="<?php echo date('Y-m-d\TH:i', strtotime($pedido['fecha_pedido'])); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Etapa 2: Aprobación -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="fw-bold text-info">
                            <i class="bi bi-2-circle-fill"></i> Aprobación
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario que Aprobó</label>
                                <select name="id_aprobado_por" class="form-select">
                                    <option value="">-- Sin aprobar --</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?php echo $u['id_usuario']; ?>" 
                                                <?php echo $u['id_usuario'] == $pedido['id_aprobado_por'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?> 
                                            (<?php echo ucfirst($u['rol']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Aprobación</label>
                                <input type="datetime-local" name="fecha_aprobacion" class="form-control" 
                                       value="<?php echo $pedido['fecha_aprobacion'] ? date('Y-m-d\TH:i', strtotime($pedido['fecha_aprobacion'])) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Etapa 3: Picking -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="fw-bold text-warning">
                            <i class="bi bi-3-circle-fill"></i> Picking (Preparación)
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario que Preparó (Picking)</label>
                                <select name="id_picking_por" class="form-select">
                                    <option value="">-- Sin preparar --</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?php echo $u['id_usuario']; ?>" 
                                                <?php echo $u['id_usuario'] == $pedido['id_picking_por'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?> 
                                            (<?php echo ucfirst($u['rol']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Picking</label>
                                <input type="datetime-local" name="fecha_picking" class="form-control" 
                                       value="<?php echo $pedido['fecha_picking'] ? date('Y-m-d\TH:i', strtotime($pedido['fecha_picking'])) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Etapa 4: Retiro -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="fw-bold text-primary">
                            <i class="bi bi-4-circle-fill"></i> Retiro
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario que Retiró</label>
                                <select name="id_retirado_por" class="form-select">
                                    <option value="">-- Sin retirar --</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?php echo $u['id_usuario']; ?>" 
                                                <?php echo $u['id_usuario'] == $pedido['id_retirado_por'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?> 
                                            (<?php echo ucfirst($u['rol']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Retiro</label>
                                <input type="datetime-local" name="fecha_retiro" class="form-control" 
                                       value="<?php echo $pedido['fecha_retiro'] ? date('Y-m-d\TH:i', strtotime($pedido['fecha_retiro'])) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Etapa 5: Recibido -->
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="fw-bold text-success">
                            <i class="bi bi-5-circle-fill"></i> Recibido
                        </h6>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Usuario que Recibió</label>
                                <select name="id_recibido_por" class="form-select">
                                    <option value="">-- Sin recibir --</option>
                                    <?php foreach ($usuarios as $u): ?>
                                        <option value="<?php echo $u['id_usuario']; ?>" 
                                                <?php echo $u['id_usuario'] == $pedido['id_recibido_por'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['nombre'] . ' ' . $u['apellido']); ?> 
                                            (<?php echo ucfirst($u['rol']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Recepción</label>
                                <input type="datetime-local" name="fecha_recibido" class="form-control" 
                                       value="<?php echo $pedido['fecha_recibido'] ? date('Y-m-d\TH:i', strtotime($pedido['fecha_recibido'])) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-save"></i> Guardar Cambios
                        </button>
                        <a href="view.php?id=<?php echo $pedido['id_pedido']; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Historial de cambios -->
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clock-history"></i> Historial de Ediciones
                    </h5>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <?php if (empty($historial)): ?>
                        <p class="text-muted text-center">No hay ediciones registradas</p>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($historial as $h): ?>
                                <div class="timeline-item mb-3 pb-3 border-bottom">
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($h['fecha_edicion'])); ?>
                                    </small>
                                    <div class="fw-bold">
                                        <?php echo htmlspecialchars($h['nombre'] . ' ' . $h['apellido']); ?>
                                    </div>
                                    <div class="small">
                                        Etapa: <span class="badge bg-secondary"><?php echo ucfirst($h['etapa']); ?></span>
                                        Campo: <span class="badge bg-info"><?php echo ucfirst($h['campo_editado']); ?></span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <i class="bi bi-arrow-right"></i> 
                                        De: <code><?php echo htmlspecialchars($h['valor_anterior'] ?: 'vacío'); ?></code>
                                        A: <code><?php echo htmlspecialchars($h['valor_nuevo'] ?: 'vacío'); ?></code>
                                    </div>
                                    <?php if ($h['ip_usuario']): ?>
                                        <div class="small text-muted">
                                            IP: <?php echo htmlspecialchars($h['ip_usuario']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Referencias a campos
    const campos = {
        aprobacion: {
            usuario: document.querySelector('select[name="id_aprobado_por"]'),
            fecha: document.querySelector('input[name="fecha_aprobacion"]')
        },
        picking: {
            usuario: document.querySelector('select[name="id_picking_por"]'),
            fecha: document.querySelector('input[name="fecha_picking"]')
        },
        retiro: {
            usuario: document.querySelector('select[name="id_retirado_por"]'),
            fecha: document.querySelector('input[name="fecha_retiro"]')
        },
        recibido: {
            usuario: document.querySelector('select[name="id_recibido_por"]'),
            fecha: document.querySelector('input[name="fecha_recibido"]')
        }
    };
    
    const fechaCreacion = document.querySelector('input[name="fecha_pedido"]');
    const nuevoEstadoBadge = document.getElementById('nuevo-estado-badge');
    const estadoNuevo = document.getElementById('estado-nuevo');
    
    // Función para actualizar vista previa del estado
    function actualizarVistaEstado() {
        let estado = 'pendiente';
        let badgeClass = 'bg-warning text-dark';
        
        // Determinar estado según etapas
        if (campos.recibido.usuario.value) {
            estado = 'recibido';
            badgeClass = 'bg-success';
        } else if (campos.retiro.usuario.value) {
            estado = 'retirado';
            badgeClass = 'bg-warning';
        } else if (campos.picking.usuario.value) {
            estado = 'picking';
            badgeClass = 'bg-warning text-dark';
        } else if (campos.aprobacion.usuario.value) {
            estado = 'aprobado';
            badgeClass = 'bg-info';
        } else {
            estado = 'pendiente';
            badgeClass = 'bg-warning text-dark';
        }
        
        // Textos personalizados para estados
        const estadoTextos = {
            'recibido': 'Entregado',
            'picking': 'En Picking',
            'retirado': 'Retirado',
            'aprobado': 'Aprobado',
            'pendiente': 'Pendiente'
        };
        
        // Mostrar solo si es diferente al estado actual
        const estadoActual = '<?php echo $pedido['estado']; ?>';
        if (estado !== estadoActual) {
            nuevoEstadoBadge.style.display = 'inline';
            estadoNuevo.className = 'badge ' + badgeClass;
            estadoNuevo.textContent = estadoTextos[estado] || estado.charAt(0).toUpperCase() + estado.slice(1);
        } else {
            nuevoEstadoBadge.style.display = 'none';
        }
    }
    
    // Función para validar coherencia de fechas
    function validarCoherenciaFechas() {
        const fechas = {
            creacion: fechaCreacion.value ? new Date(fechaCreacion.value) : null,
            aprobacion: campos.aprobacion.fecha.value ? new Date(campos.aprobacion.fecha.value) : null,
            picking: campos.picking.fecha.value ? new Date(campos.picking.fecha.value) : null,
            retiro: campos.retiro.fecha.value ? new Date(campos.retiro.fecha.value) : null,
            recibido: campos.recibido.fecha.value ? new Date(campos.recibido.fecha.value) : null
        };
        
        let errores = [];
        
        // Validar aprobación >= creación
        if (fechas.aprobacion && fechas.creacion && fechas.aprobacion < fechas.creacion) {
            errores.push('La fecha de aprobación debe ser posterior a la fecha de creación');
            campos.aprobacion.fecha.classList.add('is-invalid');
        } else {
            campos.aprobacion.fecha.classList.remove('is-invalid');
        }
        
        // Validar picking >= creación y >= aprobación
        if (fechas.picking) {
            if (fechas.creacion && fechas.picking < fechas.creacion) {
                errores.push('La fecha de picking debe ser posterior a la fecha de creación');
                campos.picking.fecha.classList.add('is-invalid');
            } else if (fechas.aprobacion && fechas.picking < fechas.aprobacion) {
                errores.push('La fecha de picking debe ser posterior a la fecha de aprobación');
                campos.picking.fecha.classList.add('is-invalid');
            } else {
                campos.picking.fecha.classList.remove('is-invalid');
            }
        }
        
        // Validar retiro >= creación, >= aprobación y >= picking
        if (fechas.retiro) {
            if (fechas.creacion && fechas.retiro < fechas.creacion) {
                errores.push('La fecha de retiro debe ser posterior a la fecha de creación');
                campos.retiro.fecha.classList.add('is-invalid');
            } else if (fechas.aprobacion && fechas.retiro < fechas.aprobacion) {
                errores.push('La fecha de retiro debe ser posterior a la fecha de aprobación');
                campos.retiro.fecha.classList.add('is-invalid');
            } else if (fechas.picking && fechas.retiro < fechas.picking) {
                errores.push('La fecha de retiro debe ser posterior a la fecha de picking');
                campos.retiro.fecha.classList.add('is-invalid');
            } else {
                campos.retiro.fecha.classList.remove('is-invalid');
            }
        }
        
        // Validar recibido >= todas las anteriores
        if (fechas.recibido) {
            if (fechas.creacion && fechas.recibido < fechas.creacion) {
                errores.push('La fecha de recepción debe ser posterior a la fecha de creación');
                campos.recibido.fecha.classList.add('is-invalid');
            } else if (fechas.aprobacion && fechas.recibido < fechas.aprobacion) {
                errores.push('La fecha de recepción debe ser posterior a la fecha de aprobación');
                campos.recibido.fecha.classList.add('is-invalid');
            } else if (fechas.picking && fechas.recibido < fechas.picking) {
                errores.push('La fecha de recepción debe ser posterior a la fecha de picking');
                campos.recibido.fecha.classList.add('is-invalid');
            } else if (fechas.retiro && fechas.recibido < fechas.retiro) {
                errores.push('La fecha de recepción debe ser posterior a la fecha de retiro');
                campos.recibido.fecha.classList.add('is-invalid');
            } else {
                campos.recibido.fecha.classList.remove('is-invalid');
            }
        }
        
        return errores;
    }
    
    // Función para validar que si hay usuario, debe haber fecha
    function validarUsuarioConFecha(etapa) {
        const campo = campos[etapa];
        if (campo.usuario.value && !campo.fecha.value) {
            campo.fecha.classList.add('is-invalid');
            campo.fecha.required = true;
            return false;
        } else {
            campo.fecha.classList.remove('is-invalid');
            campo.fecha.required = false;
            return true;
        }
    }
    
    // Event listeners para validación en tiempo real
    Object.keys(campos).forEach(etapa => {
        campos[etapa].usuario.addEventListener('change', function() {
            validarUsuarioConFecha(etapa);
            validarCoherenciaFechas();
            actualizarVistaEstado();
        });
        
        campos[etapa].fecha.addEventListener('change', function() {
            validarUsuarioConFecha(etapa);
            validarCoherenciaFechas();
        });
    });
    
    fechaCreacion.addEventListener('change', validarCoherenciaFechas);
    
    // Actualizar vista inicial
    actualizarVistaEstado();
    
    // Validar al enviar el formulario
    document.querySelector('form').addEventListener('submit', function(e) {
        let valido = true;
        
        // Validar usuario con fecha
        Object.keys(campos).forEach(etapa => {
            if (!validarUsuarioConFecha(etapa)) {
                valido = false;
            }
        });
        
        // Validar coherencia de fechas
        const errores = validarCoherenciaFechas();
        if (errores.length > 0) {
            valido = false;
            alert('Errores de validación:\n\n' + errores.join('\n'));
        }
        
        if (!valido) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>
