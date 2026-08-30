<?php
/**
 * Script de Corrección de Rutas en Base de Datos
 * Elimina '/Gogleanty' de las rutas guardadas para migración a raíz
 */

require_once __DIR__ . '/api/config.php';
header('Content-Type: text/html; charset=UTF-8');

echo "<h1>🔧 Corrección de Rutas de Base de Datos</h1>";
echo "<p>Este script eliminará '/Gogleanty' de las rutas de archivos guardadas en la base de datos.</p>";

if (!isset($_GET['confirm'])) {
    echo "<p style='color: orange;'>⚠️ <strong>Advertencia:</strong> Haz una copia de seguridad de tu base de datos antes de continuar.</p>";
    echo "<a href='?confirm=yes' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px;'>CONFIRMAR Y CORREGIR</a>";
    exit;
}

$db = getDBConnection();
$count = 0;

try {
    // 1. Corregir file_path en tabla media
    $sql1 = "UPDATE media SET file_path = REPLACE(file_path, '/Gogleanty/uploads', '/uploads') WHERE file_path LIKE '/Gogleanty/uploads%'";
    if ($db->query($sql1)) {
        echo "<p>✅ Rutas de archivos corregidas: " . $db->affected_rows . "</p>";
        $count += $db->affected_rows;
    } else {
        echo "<p style='color: red;'>❌ Error en media.file_path: " . $db->error . "</p>";
    }

    // 2. Corregir thumbnail_path en tabla media
    $sql2 = "UPDATE media SET thumbnail_path = REPLACE(thumbnail_path, '/Gogleanty/uploads', '/uploads') WHERE thumbnail_path LIKE '/Gogleanty/uploads%'";
    if ($db->query($sql2)) {
        echo "<p>✅ Rutas de miniaturas corregidas: " . $db->affected_rows . "</p>";
        $count += $db->affected_rows;
    } else {
        echo "<p style='color: red;'>❌ Error en media.thumbnail_path: " . $db->error . "</p>";
    }

    echo "<hr>";
    echo "<h2>🎉 Proceso completado</h2>";
    echo "<p>Total de registros actualizados: <strong>$count</strong></p>";
    echo "<p><a href='index.html'>Volver al Inicio</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>Error fatal: " . $e->getMessage() . "</p>";
}
?>
