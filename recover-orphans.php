<?php
/**
 * Recuperar Archivos Huérfanos
 * Inserta en la BD los archivos que están en uploads/ pero no en la BD
 */

require_once __DIR__ . '/api/config.php';
require_once __DIR__ . '/api/MediaController.php';

echo "<h1>🔧 Recuperación de Archivos Huérfanos</h1>";

$db = getDBConnection();
$mediaController = new MediaController();

// Buscar archivos huérfanos
$uploadedFiles = [];
$dirs = [
    'uploads/images' => 'image',
    'uploads/videos' => 'video',
    'uploads/gifs' => 'gif',
    'uploads/audios' => 'audio'
];

$orphans = [];

foreach ($dirs as $relDir => $type) {
    $dir = ROOT_PATH . '/' . $relDir;
    if (!is_dir($dir))
        continue;

    $files = array_diff(scandir($dir), ['.', '..', '.gitkeep']);

    foreach ($files as $file) {
        $filePath = $dir . '/' . $file;
        $dbPath = APP_BASE_PATH . '/' . $relDir . '/' . $file;

        // Verificar si está en BD
        $stmt = $db->prepare("SELECT id FROM media WHERE filename = ? OR original_filename = ?");
        $stmt->bind_param("ss", $file, $file);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $orphans[] = [
                'file' => $file,
                'path' => $filePath,
                'db_path' => $dbPath,
                'type' => $type,
                'dir' => $dir
            ];
        }
    }
}

if (empty($orphans)) {
    echo "<p style='color: green; font-size: 18px;'>✅ No hay archivos huérfanos para recuperar</p>";
    echo "<p><a href='diagnostico-subida.php'>← Volver al diagnóstico</a></p>";
    exit;
}

echo "<p>Encontré <strong>" . count($orphans) . "</strong> archivos para recuperar</p>";

if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    echo "<h2>Recuperando archivos...</h2>";

    $recovered = 0;
    $errors = 0;

    foreach ($orphans as $orphan) {
        try {
            $filePath = $orphan['path'];
            $filename = $orphan['file'];
            $fileType = $orphan['type'];

            // Obtener información del archivo
            $fileSize = filesize($filePath);
            $uploadDate = date('Y-m-d H:i:s', filemtime($filePath));

            // Determinar thumbnail path
            $thumbnailPath = 'uploads/thumbnails/' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';

            // Extraer metadatos básicos
            $width = null;
            $height = null;
            $duration = null;
            $dateTaken = $uploadDate; // Usar fecha del archivo

            if ($fileType === 'image' || $fileType === 'gif') {
                $imageInfo = @getimagesize($filePath);
                if ($imageInfo) {
                    $width = $imageInfo[0];
                    $height = $imageInfo[1];
                }
            }

            // Insertar en BD
            $stmt = $db->prepare("
                INSERT INTO media 
                (filename, original_filename, file_path, thumbnail_path, file_type, file_size, 
                 width, height, duration, date_taken, date_uploaded) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param(
                "sssssiiids",
                $filename,
                $filename,
                $orphan['db_path'],
                $thumbnailPath,
                $fileType,
                $fileSize,
                $width,
                $height,
                $duration,
                $dateTaken
            );

            if ($stmt->execute()) {
                echo "<p style='color: green;'>✅ Recuperado: $filename</p>";
                $recovered++;
            } else {
                echo "<p style='color: red;'>❌ Error en $filename: " . $stmt->error . "</p>";
                $errors++;
            }

        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Error en {$orphan['file']}: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }

    echo "<hr>";
    echo "<h2>Resumen</h2>";
    echo "<p>✅ Recuperados: <strong>$recovered</strong></p>";
    echo "<p>❌ Errores: <strong>$errors</strong></p>";
    echo "<p><a href='index.html' style='padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px;'>Ir a Gogleanty</a></p>";

} else {
    echo "<h2>Vista previa de archivos a recuperar:</h2>";
    echo "<ul>";
    foreach (array_slice($orphans, 0, 20) as $orphan) {
        echo "<li>{$orphan['file']} ({$orphan['type']})</li>";
    }
    if (count($orphans) > 20) {
        echo "<li><em>... y " . (count($orphans) - 20) . " más</em></li>";
    }
    echo "</ul>";

    echo "<p style='background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107;'>";
    echo "⚠️ <strong>Importante:</strong> Este proceso insertará estos archivos en la base de datos usando la fecha del archivo como fecha de captura.";
    echo "</p>";

    echo "<p>";
    echo "<a href='?confirm=yes' style='padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>✅ Recuperar Archivos</a> ";
    echo "<a href='diagnostico-subida.php' style='padding: 12px 24px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px;'>Cancelar</a>";
    echo "</p>";
}

$db->close();
?>