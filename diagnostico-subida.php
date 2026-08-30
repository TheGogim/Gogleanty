<?php
/**
 * Diagnóstico de Subida de Archivos
 * Detecta por qué algunos archivos no se guardan en la BD
 */

require_once __DIR__ . '/api/config.php';

echo "<h1>🔍 Diagnóstico de Subida</h1>";

// Verificar archivos en uploads vs BD
$db = getDBConnection();

echo "<h2>1. Archivos en uploads/</h2>";
$uploadedFiles = [];
$dirs = ['uploads/images', 'uploads/videos', 'uploads/gifs', 'uploads/audios'];

foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            if ($file !== '.gitkeep') {
                $uploadedFiles[] = $file;
            }
        }
    }
}

echo "<p>Total archivos en uploads/: <strong>" . count($uploadedFiles) . "</strong></p>";

echo "<h2>2. Archivos en Base de Datos</h2>";
$result = $db->query("SELECT COUNT(*) as total FROM media");
$dbCount = $result->fetch_assoc()['total'];
echo "<p>Total registros en BD: <strong>$dbCount</strong></p>";

echo "<h2>3. Archivos huérfanos (en uploads pero NO en BD)</h2>";
$orphans = [];

foreach ($uploadedFiles as $file) {
    $stmt = $db->prepare("SELECT id FROM media WHERE filename = ? OR original_filename = ?");
    $stmt->bind_param("ss", $file, $file);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $orphans[] = $file;
    }
}

if (empty($orphans)) {
    echo "<p style='color: green;'>✅ No hay archivos huérfanos</p>";
} else {
    echo "<p style='color: red;'>❌ Encontré <strong>" . count($orphans) . "</strong> archivos huérfanos:</p>";
    echo "<ul>";
    foreach (array_slice($orphans, 0, 20) as $orphan) {
        echo "<li>$orphan</li>";
    }
    if (count($orphans) > 20) {
        echo "<li><em>... y " . (count($orphans) - 20) . " más</em></li>";
    }
    echo "</ul>";

    echo "<h3>🔧 Intentar recuperar archivos huérfanos</h3>";
    echo "<p><a href='recover-orphans.php' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;'>Recuperar Archivos</a></p>";
}

echo "<h2>4. Verificar errores en MediaController</h2>";

// Intentar subir un archivo de prueba y capturar errores
echo "<p>Voy a revisar el log de errores de PHP...</p>";

$errorLog = ini_get('error_log');
if ($errorLog && file_exists($errorLog)) {
    $errors = file_get_contents($errorLog);
    $recentErrors = array_slice(explode("\n", $errors), -50);

    echo "<h3>Últimos errores de PHP:</h3>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars(implode("\n", $recentErrors));
    echo "</pre>";
} else {
    echo "<p>No se encontró log de errores de PHP</p>";
}

echo "<h2>5. Verificar estructura de tabla media</h2>";
$result = $db->query("DESCRIBE media");
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Campo</th><th>Tipo</th><th>Null</th><th>Key</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "</tr>";
}
echo "</table>";

echo "<h2>6. Último registro insertado</h2>";
$result = $db->query("SELECT * FROM media ORDER BY id DESC LIMIT 1");
if ($row = $result->fetch_assoc()) {
    echo "<pre style='background: #f5f5f5; padding: 10px; border-radius: 4px;'>";
    print_r($row);
    echo "</pre>";
} else {
    echo "<p>No hay registros en la tabla</p>";
}

$db->close();
?>