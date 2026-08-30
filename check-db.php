<?php
/**
 * Script de Verificación y Consulta de Base de Datos
 * Verifica el estado de la base de datos y muestra información útil
 */

// Cargar configuración
if (!file_exists(__DIR__ . '/.env')) {
    die("❌ Error: Archivo .env no encontrado. Ejecuta setup.php primero.\n");
}

// Cargar variables de entorno
$env = parse_ini_file(__DIR__ . '/.env');

define('DB_HOST', $env['DB_HOST'] ?? 'localhost');
define('DB_USER', $env['DB_USER'] ?? 'root');
define('DB_PASS', $env['DB_PASS'] ?? '');
define('DB_NAME', $env['DB_NAME'] ?? 'gogleanty_db');
define('DB_PORT', $env['DB_PORT'] ?? 3306);

echo "=== GOGLEANTY - Verificación de Base de Datos ===\n\n";

// Conectar a MySQL
echo "[1/5] Conectando a MySQL...\n";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    echo "✓ Conexión exitosa a la base de datos '" . DB_NAME . "'\n\n";
} catch (Exception $e) {
    die("✗ Error: " . $e->getMessage() . "\n\nAsegúrate de que:\n1. XAMPP esté ejecutándose\n2. MySQL esté activo\n3. Hayas ejecutado setup.php\n");
}

// Verificar tablas
echo "[2/5] Verificando tablas...\n";
$required_tables = ['media', 'albums', 'album_media', 'tags', 'media_tags'];
$existing_tables = [];

$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_array()) {
    $existing_tables[] = $row[0];
}

$all_tables_exist = true;
foreach ($required_tables as $table) {
    if (in_array($table, $existing_tables)) {
        echo "✓ Tabla '$table' existe\n";
    } else {
        echo "✗ Tabla '$table' NO existe\n";
        $all_tables_exist = false;
    }
}

if (!$all_tables_exist) {
    die("\n❌ Faltan tablas. Ejecuta setup.php para crearlas.\n");
}
echo "\n";

// Contar registros
echo "[3/5] Contando registros...\n";

// Medios
$result = $conn->query("SELECT COUNT(*) as total FROM media");
$media_count = $result->fetch_assoc()['total'];
echo "📷 Medios totales: $media_count\n";

// Por tipo
$result = $conn->query("SELECT file_type, COUNT(*) as count FROM media GROUP BY file_type");
while ($row = $result->fetch_assoc()) {
    $icon = $row['file_type'] === 'image' ? '🖼️' : ($row['file_type'] === 'video' ? '🎬' : '🎞️');
    echo "   $icon " . ucfirst($row['file_type']) . "s: " . $row['count'] . "\n";
}

// Álbumes
$result = $conn->query("SELECT COUNT(*) as total FROM albums");
$albums_count = $result->fetch_assoc()['total'];
echo "📁 Álbumes: $albums_count\n";

// Favoritos
$result = $conn->query("SELECT COUNT(*) as total FROM media WHERE is_favorite = 1");
$favorites_count = $result->fetch_assoc()['total'];
echo "⭐ Favoritos: $favorites_count\n\n";

// Calcular espacio usado
echo "[4/5] Calculando espacio en disco...\n";
$result = $conn->query("SELECT SUM(file_size) as total_size FROM media");
$total_size = $result->fetch_assoc()['total_size'] ?? 0;

function formatBytes($bytes)
{
    if ($bytes == 0)
        return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

echo "💾 Espacio usado: " . formatBytes($total_size) . "\n\n";

// Mostrar últimos medios
echo "[5/5] Últimos 10 medios subidos:\n";
$result = $conn->query("
    SELECT id, original_filename, file_type, date_taken, date_uploaded 
    FROM media 
    ORDER BY date_uploaded DESC 
    LIMIT 10
");

if ($result->num_rows > 0) {
    echo str_repeat("-", 80) . "\n";
    printf("%-5s %-30s %-10s %-20s\n", "ID", "Archivo", "Tipo", "Fecha Subida");
    echo str_repeat("-", 80) . "\n";

    while ($row = $result->fetch_assoc()) {
        $filename = strlen($row['original_filename']) > 30
            ? substr($row['original_filename'], 0, 27) . '...'
            : $row['original_filename'];

        printf(
            "%-5s %-30s %-10s %-20s\n",
            $row['id'],
            $filename,
            $row['file_type'],
            $row['date_uploaded']
        );
    }
    echo str_repeat("-", 80) . "\n";
} else {
    echo "No hay medios en la base de datos todavía.\n";
}

echo "\n";

// Verificar directorios
echo "Verificando directorios de uploads...\n";
$directories = [
    'uploads',
    'uploads/images',
    'uploads/videos',
    'uploads/gifs',
    'uploads/thumbnails'
];

foreach ($directories as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        $files = count(glob($path . '/*'));
        echo "✓ $dir ($files archivos)\n";
    } else {
        echo "✗ $dir (no existe)\n";
    }
}

echo "\n=== Verificación Completada ===\n\n";

// Mostrar información de configuración
echo "Configuración actual:\n";
echo "- Base de datos: " . DB_NAME . "\n";
echo "- Host: " . DB_HOST . ":" . DB_PORT . "\n";
echo "- Usuario: " . DB_USER . "\n";
echo "- URL de la aplicación: http://localhost/Gogleanty\n";
echo "\n";

// Sugerencias
if ($media_count == 0) {
    echo "💡 Sugerencia: No tienes medios todavía. Abre la aplicación y sube algunas fotos.\n";
}

if ($albums_count == 0) {
    echo "💡 Sugerencia: Crea álbumes para organizar mejor tus fotos.\n";
}

echo "\n";

$conn->close();
?>