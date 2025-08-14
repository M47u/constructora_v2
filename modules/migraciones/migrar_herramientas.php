<?php
// ===========================================
// CONFIGURACIÓN
// ===========================================
$host_old = "localhost";
$db_old   = "bakcup_constructora";
$host_new = "localhost";
$db_new   = "sistema_constructora";
$user     = "root";
$pass     = "root";

$min_frecuencia_marca = 5; // Palabras que aparezcan 5 veces o más se asumen marcas

// ===========================================
// CONEXIONES
// ===========================================
$pdo_old = new PDO("mysql:host=$host_old;dbname=$db_old;charset=utf8", $user, $pass);
$pdo_new = new PDO("mysql:host=$host_new;dbname=$db_new;charset=utf8", $user, $pass);
$pdo_old->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo_new->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ===========================================
// 1. LEER TODOS LOS REGISTROS
// ===========================================
$sql = "SELECT nombreherramienta FROM herramientas";
$stmt = $pdo_old->query($sql);

$frases = [];
$palabras = [];
$registros = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $nombre = trim($row['nombreherramienta']);
    $nombre_limpio = preg_replace('/[^\p{L}\p{N}&\- ]+/u', ' ', $nombre);
    $registros[] = [
        'original' => $nombre,
        'limpio' => $nombre_limpio
    ];
    // Contar palabras
    $tokens = preg_split('/\s+/', $nombre_limpio);
    foreach ($tokens as $token) {
        $token = trim($token);
        if (mb_strlen($token, 'UTF-8') > 1) {
            $t_lower = mb_strtolower($token, 'UTF-8');
            $palabras[$t_lower] = ($palabras[$t_lower] ?? 0) + 1;
        }
    }
    // Contar frases de hasta 3 palabras (para marcas compuestas)
    $tokens_count = count($tokens);
    for ($i = 0; $i < $tokens_count; $i++) {
        for ($len = 2; $len <= 3; $len++) {
            if ($i + $len <= $tokens_count) {
                $frase = implode(" ", array_slice($tokens, $i, $len));
                $f_lower = mb_strtolower($frase, 'UTF-8');
                $frases[$f_lower] = ($frases[$f_lower] ?? 0) + 1;
            }
        }
    }
}

// ===========================================
// 2. DETECTAR MARCAS
// ===========================================
$marcas_palabra = array_filter($palabras, function($count) use ($min_frecuencia_marca) {
    return $count >= $min_frecuencia_marca;
});
$marcas_frase = array_filter($frases, function($count) use ($min_frecuencia_marca) {
    return $count >= $min_frecuencia_marca;
});
$marcas_detectadas = array_merge(array_keys($marcas_palabra), array_keys($marcas_frase));
$marcas_detectadas = array_unique($marcas_detectadas);
usort($marcas_detectadas, function($a, $b) {
    return mb_strlen($b, 'UTF-8') - mb_strlen($a, 'UTF-8');
});

echo "Marcas detectadas automáticamente:\n";
print_r($marcas_detectadas);

// ===========================================
// 3. MIGRAR DATOS
// ===========================================
foreach ($registros as $registro) {
    $nombre = $registro['original'];
    $nombre_limpio = $registro['limpio'];
    $marca_encontrada = null;
    foreach ($marcas_detectadas as $marca) {
        if (stripos($nombre_limpio, $marca) !== false) {
            $marca_encontrada = ucwords($marca);
            break;
        }
    }
    if ($marca_encontrada) {
        $insert = $pdo_new->prepare("INSERT INTO herramientas (marca) VALUES (?)");
        $insert->execute([$marca_encontrada]);
    } else {
        $pendiente = $pdo_new->prepare("INSERT INTO herramientas_pendientes (nombreherramienta) VALUES (?)");
        $pendiente->execute([$nombre]);
    }
}
echo "Migración completada.\n";
?>
