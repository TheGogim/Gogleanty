<?php
/**
 * Procesador de Metadatos de Google Photos
 * Lee los archivos .json de Google Photos y restaura los metadatos en las fotos
 */

require_once __DIR__ . '/api/config.php';

echo "==============================================\n";
echo "   PROCESADOR DE GOOGLE PHOTOS METADATA\n";
echo "==============================================\n\n";

// Directorio donde están las fotos descargadas de Google Photos
$sourceDir = __DIR__ . '/google-photos-import';

if (!is_dir($sourceDir)) {
    mkdir($sourceDir, 0777, true);
    die("📁 Creé la carpeta 'google-photos-import'\n\n" .
        "Por favor:\n" .
        "1. Copia todas tus fotos y archivos .json de Google Photos ahí\n" .
        "2. Ejecuta este script de nuevo\n\n");
}

// Buscar todos los archivos .json
$jsonFiles = glob($sourceDir . '/*.json');

if (empty($jsonFiles)) {
    die("❌ No encontré archivos .json en la carpeta google-photos-import\n\n");
}

echo "✅ Encontré " . count($jsonFiles) . " archivos de metadatos\n\n";

$processed = 0;
$errors = 0;

foreach ($jsonFiles as $jsonFile) {
    try {
        // Leer el JSON
        $metadata = json_decode(file_get_contents($jsonFile), true);

        if (!$metadata) {
            echo "⚠️  Error leyendo: " . basename($jsonFile) . "\n";
            $errors++;
            continue;
        }

        // Buscar la imagen correspondiente
        $imageName = str_replace('.json', '', basename($jsonFile));
        $imagePath = $sourceDir . '/' . $imageName;

        if (!file_exists($imagePath)) {
            echo "⚠️  No encontré la imagen para: " . basename($jsonFile) . "\n";
            $errors++;
            continue;
        }

        // Extraer fecha de los metadatos de Google
        $timestamp = null;

        if (isset($metadata['photoTakenTime']['timestamp'])) {
            $timestamp = $metadata['photoTakenTime']['timestamp'];
        } elseif (isset($metadata['creationTime']['timestamp'])) {
            $timestamp = $metadata['creationTime']['timestamp'];
        }

        if ($timestamp) {
            // Convertir timestamp a fecha
            $date = date('Y-m-d H:i:s', $timestamp);

            // Actualizar la fecha del archivo
            touch($imagePath, $timestamp);

            echo "✅ Procesado: " . basename($imageName) . " → " . $date . "\n";
            $processed++;
        } else {
            echo "⚠️  Sin fecha: " . basename($imageName) . "\n";
            $errors++;
        }

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n==============================================\n";
echo "   RESUMEN\n";
echo "==============================================\n";
echo "✅ Procesados: $processed\n";
echo "⚠️  Errores: $errors\n\n";

if ($processed > 0) {
    echo "🎉 ¡Listo! Ahora puedes subir las fotos desde la carpeta:\n";
    echo "   google-photos-import/\n\n";
    echo "Las fechas de los archivos ya están corregidas.\n";
    echo "Gogleanty leerá las fechas correctas al subirlas.\n\n";
}
?>