<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Verificar permisos (solo administradores y responsables de obra)
if ($_SESSION['user_role'] !== 'administrador' && $_SESSION['user_role'] !== 'responsable_obra') {
    header('Location: ../../dashboard.php?error=sin_permisos');
    exit();
}

$page_title = "Dashboard de Reportes";

// Inicializar conexión a la base de datos
$database = new Database(); 
$pdo = $database->getConnection();

require_once '../../includes/header.php';

// Obtener estadísticas generales
try {
    // Total de obras activas
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM obras WHERE estado = 'en_progreso'");
    $total_obras = $stmt->fetch()['total'];
    
    // Total de materiales
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM materiales");
    $total_materiales = $stmt->fetch()['total'];
    
    // Total de pedidos este mes
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pedidos_materiales WHERE MONTH(fecha_pedido) = MONTH(CURRENT_DATE()) AND YEAR(fecha_pedido) = YEAR(CURRENT_DATE())");
    $pedidos_mes = $stmt->fetch()['total'];
    
    // Valor total de materiales en stock
    $stmt = $pdo->query("SELECT SUM(stock_actual * precio_referencia) as total FROM materiales WHERE stock_actual > 0");
    $valor_stock = $stmt->fetch()['total'] ?? 0;
    
} catch (PDOException $e) {
    $error = "Error al obtener estadísticas: " . $e->getMessage();
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-graph-up"></i> Dashboard de Reportes</h1>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger" role="alert">
        <?php echo $error; ?>
    </div>
    <?php endif; ?>

    <!-- Estadísticas Generales -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($total_obras); ?></h4>
                            <p class="mb-0">Obras Activas</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-building-gear fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($total_materiales); ?></h4>
                            <p class="mb-0">Materiales Registrados</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-box-seam fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?php echo number_format($pedidos_mes); ?></h4>
                            <p class="mb-0">Pedidos Este Mes</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-cart fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4>$<?php echo number_format($valor_stock, 2); ?></h4>
                            <p class="mb-0">Valor en Stock</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-currency-dollar fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Accesos Rápidos a Reportes -->
    <div class="row">
        <div class="col-12">
            <h3 class="mb-3">Reportes Disponibles</h3>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-bar-chart-line fs-1 text-primary mb-3"></i>
                    <h5 class="card-title">Materiales por Obra</h5>
                    <p class="card-text">Analiza el consumo de materiales por obra en un rango de fechas específico.</p>
                    <a href="materiales_por_obra.php" class="btn btn-primary">
                        <i class="bi bi-eye"></i> Ver Reporte
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up fs-1 text-success mb-3"></i>
                    <h5 class="card-title">Materiales Más Consumidos</h5>
                    <p class="card-text">Identifica los materiales con mayor demanda en un período determinado.</p>
                    <a href="materiales_mas_consumidos.php" class="btn btn-success">
                        <i class="bi bi-eye"></i> Ver Reporte
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card h-100">
                <div class="card-body text-center">
                    <i class="bi bi-trophy fs-1 text-warning mb-3"></i>
                    <h5 class="card-title">Obra Mayor Consumo</h5>
                    <p class="card-text">Determina qué obra ha tenido el mayor consumo de materiales.</p>
                    <a href="obra_mayor_consumo.php" class="btn btn-warning">
                        <i class="bi bi-eye"></i> Ver Reporte
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Información Adicional -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-info-circle"></i> Información sobre los Reportes</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Características de los Reportes:</h6>
                            <ul>
                                <li>Filtros por rango de fechas</li>
                                <li>Gráficos interactivos</li>
                                <li>Exportación a Excel</li>
                                <li>Datos en tiempo real</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Permisos de Acceso:</h6>
                            <ul>
                                <li>Administradores: Acceso completo</li>
                                <li>Responsables de Obra: Acceso completo</li>
                                <li>Otros roles: Sin acceso</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
