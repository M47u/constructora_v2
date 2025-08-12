<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo SITE_URL; ?>/assets/css/style.css" rel="stylesheet">
</head>
<body>

    <?php if (is_logged_in()): ?>
    <!-- Sidebar -->
    <div class="d-flex">
        <nav id="sidebarMenu" class="d-lg-block bg-primary sidebar collapse">
            <div class="position-sticky">
                <div class="list-group list-group-flush mx-3 mt-4">
                    <a href="<?php echo SITE_URL; ?>/dashboard.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">
                        <i class="bi bi-house me-2"></i> Dashboard
                    </a>
                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                    <div class="accordion" id="sidebarObras">
                        <div class="accordion-item bg-primary border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-primary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseObras">
                                    <i class="bi bi-building-gear me-2"></i> Obras
                                </button>
                            </h2>
                            <div id="collapseObras" class="accordion-collapse collapse" data-bs-parent="#sidebarObras">
                                <div class="accordion-body p-0">
                                    <a href="<?php echo SITE_URL; ?>/modules/obras/list.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Obras</a>
                                    <?php if (has_permission(ROLE_ADMIN)): ?>
                                    <a href="<?php echo SITE_URL; ?>/modules/obras/create.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nueva Obra</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (has_permission(ROLE_ADMIN)): ?>
                    <div class="accordion" id="sidebarMateriales">
                        <div class="accordion-item bg-primary border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-primary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMateriales">
                                    <i class="bi bi-box-seam me-2"></i> Materiales
                                </button>
                            </h2>
                            <div id="collapseMateriales" class="accordion-collapse collapse" data-bs-parent="#sidebarMateriales">
                                <div class="accordion-body p-0">
                                    <a href="<?php echo SITE_URL; ?>/modules/materiales/list.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Materiales</a>
                                    <a href="<?php echo SITE_URL; ?>/modules/materiales/create.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nuevo Material</a>
                                    <a href="<?php echo SITE_URL; ?>/modules/materiales/stock_bajo.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Stock Bajo</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion" id="sidebarTransportes">
                        <div class="accordion-item bg-primary border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-primary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTransportes">
                                    <i class="bi bi-truck me-2"></i> Transportes
                                </button>
                            </h2>
                            <div id="collapseTransportes" class="accordion-collapse collapse" data-bs-parent="#sidebarTransportes">
                                <div class="accordion-body p-0">
                                    <a href="<?php echo SITE_URL; ?>/modules/transportes/list.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Transportes</a>
                                    <a href="<?php echo SITE_URL; ?>/modules/transportes/create.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nuevo Transporte</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion" id="sidebarUsuarios">
                        <div class="accordion-item bg-primary border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-primary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseUsuarios">
                                    <i class="bi bi-people me-2"></i> Usuarios
                                </button>
                            </h2>
                            <div id="collapseUsuarios" class="accordion-collapse collapse" data-bs-parent="#sidebarUsuarios">
                                <div class="accordion-body p-0">
                                    <a href="<?php echo SITE_URL; ?>/modules/usuarios/list.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Usuarios</a>
                                    <a href="<?php echo SITE_URL; ?>/modules/usuarios/create.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nuevo Usuario</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="accordion" id="sidebarHerramientas">
                        <div class="accordion-item bg-primary border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-primary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapseHerramientas">
                                    <i class="bi bi-tools me-2"></i> Herramientas
                                </button>
                            </h2>
                            <div id="collapseHerramientas" class="accordion-collapse collapse" data-bs-parent="#sidebarHerramientas">
                                <div class="accordion-body p-0">
                                    <a href="<?php echo SITE_URL; ?>/modules/herramientas/list.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Herramientas</a>
                                    <?php if (has_permission(ROLE_ADMIN)): ?>
                                    <a href="<?php echo SITE_URL; ?>/modules/herramientas/create.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nuevo Tipo</a>
                                    <?php endif; ?>
                                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                                    <hr class="dropdown-divider bg-white">
                                    <a href="<?php echo SITE_URL; ?>/modules/herramientas/prestamos.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Préstamos</a>
                                    <a href="<?php echo SITE_URL; ?>/modules/herramientas/create_prestamo.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nuevo Préstamo</a>
                                    <a href="<?php echo SITE_URL; ?>/modules/herramientas/devoluciones.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Devoluciones</a>
                                    <a href="<?php echo SITE_URL; ?>/modules/herramientas/create_devolucion.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nueva Devolución</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="accordion" id="sidebarPedidos">
                        <div class="accordion-item bg-primary border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-primary text-white" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePedidos">
                                    <i class="bi bi-cart me-2"></i> Pedidos
                                </button>
                            </h2>
                            <div id="collapsePedidos" class="accordion-collapse collapse" data-bs-parent="#sidebarPedidos">
                                <div class="accordion-body p-0">
                                    <a href="<?php echo SITE_URL; ?>/modules/pedidos/list.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Ver Pedidos</a>
                                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                                    <a href="<?php echo SITE_URL; ?>/modules/pedidos/create.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">Nuevo Pedido</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <a href="<?php echo SITE_URL; ?>/modules/tareas/list.php" class="list-group-item list-group-item-action py-2 ripple text-white bg-primary border-0">
                        <i class="bi bi-calendar-check me-2"></i> Tareas
                    </a>
                    <div class="mt-4 border-top border-white pt-3">
                        <div class="dropdown">
                            <a class="d-flex align-items-center text-white text-decoration-none dropdown-toggle" href="#" id="dropdownUser" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-person-circle me-2"></i> <?php echo $_SESSION['user_name']; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownUser">
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php">Mi Perfil</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">Cerrar Sesión</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        <main class="flex-grow-1 p-4" style="min-height: 100vh;">
    <?php else: ?>
        <main class="container-fluid mt-4">
    <?php endif; ?>
