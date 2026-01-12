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
    
    <style>
    @media print {
        .no-print { display: none !important; }
        .print-only { display: block !important; }
        .print-hide { display: none !important; }
        body { margin: 0; padding: 20px; }
        .container-fluid { max-width: none; }
    }
    </style>
</head>
<body>
    <?php if (is_logged_in()): ?>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="bi bi-building"></i> <?php echo SITE_NAME; ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/dashboard.php">
                            <i class="bi bi-house"></i> Inicio
                        </a>
                    </li>
                    
                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-building-gear"></i> Obras
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/obras/list.php">Ver Obras</a></li>
                            <?php if (has_permission(ROLE_ADMIN)): ?>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/obras/create.php">Nueva Obra</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (has_permission(ROLE_ADMIN)): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-box-seam"></i> Materiales
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/materiales/list.php">Ver Materiales</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/materiales/create.php">Nuevo Material</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/materiales/stock_bajo.php">Stock Bajo</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-truck"></i> Transportes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/transportes/list.php">Ver Transportes</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/transportes/create.php">Nuevo Transporte</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-people"></i> Usuarios
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/usuarios/list.php">Ver Usuarios</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/usuarios/create.php">Nuevo Usuario</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-tools"></i> Herramientas
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/herramientas/list.php">Ver Herramientas</a></li>
                            <?php if (has_permission(ROLE_ADMIN)): ?>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/herramientas/create.php">Nuevo Tipo</a></li>
                            <?php endif; ?>
                            <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/herramientas/prestamos.php">Ver Préstamos</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/herramientas/create_prestamo.php">Nuevo Préstamo</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/herramientas/devoluciones.php">Ver Devoluciones</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/herramientas/create_devolucion.php">Nueva Devolución</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-cart"></i> Pedidos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/pedidos/list.php">Ver Pedidos</a></li>
                            <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/pedidos/create.php">Nuevo Pedido</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    
                    <?php if (has_permission([ROLE_ADMIN, ROLE_RESPONSABLE])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-graph-up"></i> Reportes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/reportes/index.php">Dashboard de Reportes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/reportes/materiales_por_obra.php">Materiales por Obra</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/reportes/materiales_mas_consumidos.php">Materiales Más Consumidos</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/reportes/obra_mayor_consumo.php">Obra Mayor Consumo</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/reportes/herramientas_prestadas.php">Herramientas Prestadas</a></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/modules/reportes/metricas_pedidos.php">Métricas de Pedidos</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo SITE_URL; ?>/modules/tareas/list.php">
                            <i class="bi bi-calendar-check"></i> Tareas
                        </a>
                    </li>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo $_SESSION['user_name']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/profile.php">Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php">Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="container-fluid mt-4">
