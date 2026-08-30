<?php
/**
 * Script de Diagnóstico - Verifica configuración y errores
 */

echo "<h1>🔍 Diagnóstico de Gogleanty</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Incluir configuración para usar las mismas constantes que la App
require_once __DIR__ . '/api/config.php';
header("Content-Type: text/html; charset=UTF-8");

// 1. Verificar PHP
echo "<h2>1. Configuración de PHP</h2>";
echo "Versión de PHP: <strong>" . phpversion() . "</strong><br>";

// 2. Verificar extensiones
echo "<h2>2. Extensiones PHP</h2>";
$extensions = ['mysqli', 'gd', 'exif', 'fileinfo'];
foreach ($extensions as $ext) {
    $loaded = extension_loaded($ext);
    $class = $loaded ? 'ok' : 'error';
    $status = $loaded ? '✓ Instalada' : '✗ NO instalada';
    echo "<span class='$class'>$ext: $status</span><br>";
}

// 3. Verificar GD
echo "<h2>3. Librería GD (para miniaturas)</h2>";
if (function_exists('gd_info')) {
    $gd_info = gd_info();
    echo "<pre>";
    print_r($gd_info);
    echo "</pre>";
} else {
    echo "<span class='error'>✗ GD no está disponible</span><br>";
}

// 4. Verificar directorios
echo "<h2>4. Directorios (Según Configuración)</h2>";
// Usar constantes definidas en config.php
$dirs = [
    UPLOAD_DIR,
    UPLOAD_DIR . '/images',
    UPLOAD_DIR . '/videos',
    UPLOAD_DIR . '/gifs',
    THUMBNAILS_DIR
];

foreach ($dirs as $dir) {
    // Intentar crear si no existen para verificar permisos padre
    if (!file_exists($dir)) {
        @mkdir($dir, 0777, true);
    }

    $exists = is_dir($dir);
    $writable = $exists && is_writable($dir);

    if ($exists && $writable) {
        echo "<span class='ok'>✓ $dir (existe y es escribible)</span><br>";
    } elseif ($exists) {
        echo "<span class='warning'>⚠ $dir (existe pero NO es escribible)</span><br>";
    } else {
        echo "<span class='error'>✗ $dir (NO existe)</span><br>";
    }
}

// 5. Verificar .env
echo "<h2>5. Archivo .env</h2>";
if (file_exists(__DIR__ . '/.env')) {
    echo "<span class='ok'>✓ Archivo .env existe</span><br>";
    echo "<pre>";
    echo htmlspecialchars(file_get_contents(__DIR__ . '/.env'));
    echo "</pre>";
} else {
    echo "<span class='error'>✗ Archivo .env NO existe</span><br>";
}

// 6. Verificar conexión a BD
echo "<h2>6. Conexión a Base de Datos</h2>";
try {
    // require_once ya hecho arriba
    $conn = getDBConnection();
    echo "<span class='ok'>✓ Conexión exitosa a MySQL</span><br>";

    // Verificar tablas
    $result = $conn->query("SHOW TABLES");
    echo "<br><strong>Tablas en la base de datos:</strong><br>";
    while ($row = $result->fetch_array()) {
        echo "- " . $row[0] . "<br>";
    }

    // Contar registros
    $result = $conn->query("SELECT COUNT(*) as total FROM media");
    $total = $result->fetch_assoc()['total'];
    echo "<br><strong>Total de medios en BD:</strong> $total<br>";

} catch (Exception $e) {
    echo "<span class='error'>✗ Error de conexión: " . $e->getMessage() . "</span><br>";
}

// 7. Verificar límites de subida
echo "<h2>7. Límites de Subida de Archivos</h2>";
echo "upload_max_filesize: <strong>" . ini_get('upload_max_filesize') . "</strong><br>";
echo "post_max_size: <strong>" . ini_get('post_max_size') . "</strong><br>";
echo "max_execution_time: <strong>" . ini_get('max_execution_time') . " segundos</strong><br>";
echo "memory_limit: <strong>" . ini_get('memory_limit') . "</strong><br>";

// 8. Verificar error log
echo "<h2>8. Últimos Errores de PHP</h2>";
$error_log = ini_get('error_log');
echo "Archivo de log: <strong>$error_log</strong><br>";

if (file_exists($error_log)) {
    $lines = file($error_log);
    $last_lines = array_slice($lines, -20);
    echo "<pre>";
    echo htmlspecialchars(implode('', $last_lines));
    echo "</pre>";
} else {
    // Intentar con el log de Apache
    $apache_error_log = file_exists('/var/log/apache2/error.log') ? '/var/log/apache2/error.log' : 'C:/xampp/apache/logs/error.log';
    if (file_exists($apache_error_log)) {
        echo "<br><strong>Log de Apache:</strong><br>";
        $lines = file($apache_error_log);
        $last_lines = array_slice($lines, -20);
        echo "<pre>";
        echo htmlspecialchars(implode('', $last_lines));
        echo "</pre>";
    } else {
        echo "<span class='warning'>No se encontró archivo de log</span><br>";
    }
}

// 9. Test de creación de imagen
echo "<h2>9. Test de Creación de Miniatura</h2>";
if (function_exists('imagecreatetruecolor')) {
    try {
        $test_img = imagecreatetruecolor(100, 100);
        $color = imagecolorallocate($test_img, 255, 0, 0);
        imagefilledrectangle($test_img, 0, 0, 100, 100, $color);

        $test_path = THUMBNAILS_DIR . '/test.jpg';
        // Asegurar que el directorio de thumbnails existe antes del test
        if (!is_dir(THUMBNAILS_DIR))
            @mkdir(THUMBNAILS_DIR, 0777, true);

        $result = imagejpeg($test_img, $test_path, 85);
        imagedestroy($test_img);

        if ($result && file_exists($test_path)) {
            echo "<span class='ok'>✓ Test de creación de imagen EXITOSO</span><br>";
            echo "Archivo creado: $test_path<br>";
            echo "Tamaño: " . filesize($test_path) . " bytes<br>";
            @unlink($test_path); // Limpiar
        } else {
            echo "<span class='error'>✗ No se pudo crear la imagen de prueba en " . THUMBNAILS_DIR . "</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>✗ Error: " . $e->getMessage() . "</span><br>";
    }
} else {
    echo "<span class='error'>✗ Función imagecreatetruecolor no disponible</span><br>";
}

// 10. Recomendaciones
echo "<h2>10. Recomendaciones</h2>";
$recommendations = [];

if (!extension_loaded('gd')) {
    $recommendations[] = "❌ Habilita la extensión GD en php.ini (quita el ; de ;extension=gd)";
}

if (!extension_loaded('exif')) {
    $recommendations[] = "⚠️ Habilita la extensión EXIF en php.ini para leer metadatos de fotos";
}

$upload_max = ini_get('upload_max_filesize');
if (intval($upload_max) < 100) {
    $recommendations[] = "⚠️ Aumenta upload_max_filesize en php.ini (recomendado: 500M)";
}

if (!is_writable(UPLOAD_DIR)) {
    $recommendations[] = "❌ Da permisos de escritura a la carpeta: " . UPLOAD_DIR;
}

if (empty($recommendations)) {
    echo "<span class='ok'>✓ ¡Todo está configurado correctamente!</span><br>";
} else {
    foreach ($recommendations as $rec) {
        echo "$rec<br>";
    }
}

echo "<hr>";
echo "<p><strong>Nota:</strong> Si hiciste cambios en php.ini, reinicia Apache en XAMPP.</p>";
?>
