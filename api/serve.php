<?php
/**
 * EL PORTERO - Servicio de entrega segura de archivos
 * Verifica permisos antes de entregar cualquier archivo multimedia.
 */

require_once __DIR__ . '/config.php';
header("Content-Type: text/html; charset=UTF-8");

// Obtener parámetros
$mediaId = $_GET['id'] ?? null;
$token = $_GET['token'] ?? null;
$thumbnail = isset($_GET['thumb']) && $_GET['thumb'] === '1';

if (!$mediaId) {
    http_response_code(400);
    die('ID requerido');
}

// Conectar a BD
$db = getDBConnection();

// 1. Obtener información del medio
$stmt = $db->prepare("SELECT * FROM media WHERE id = ?");
$stmt->bind_param("i", $mediaId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die('Medio no encontrado');
}

$media = $result->fetch_assoc();
$stmt->close();

// 2. Determinar ruta del archivo (Thumbnail o Original)
// NOTA: Usamos las constantes definidas en config.php para evitar problemas de ruta
// La BD guarda rutas relativas tipo "uploads/videos/video.mp4" o "/uploads/..."
// Debemos limpiar la ruta base para obtener la ruta física correcta

// Función auxiliar para limpiar rutas de la BD
function getPhysicalPath($dbPath)
{
    if (empty($dbPath))
        return null;

    // Si la ruta en BD tiene APP_BASE_PATH, lo quitamos
    if (APP_BASE_PATH !== '' && strpos($dbPath, APP_BASE_PATH) === 0) {
        $cleanPath = substr($dbPath, strlen(APP_BASE_PATH));
    } else {
        $cleanPath = $dbPath;
    }

    // Quitar slash inicial (ej: "/uploads/..." -> "uploads/...")
    $cleanPath = ltrim($cleanPath, '/');

    // Combinar con ROOT_PATH
    return ROOT_PATH . '/' . $cleanPath;
}

$filePath = $thumbnail
    ? getPhysicalPath($media['thumbnail_path'])
    : getPhysicalPath($media['file_path']);

if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    die('Archivo físico no encontrado');
}

// 3. VERIFICACIÓN DE PERMISOS (EL PORTERO)
$allowed = false;

// A. Verificar Sesión de Admin
// Importante: Usar el mismo nombre de sesión que AuthController
if (session_status() === PHP_SESSION_NONE) {
    session_name('GOGLEANTY_SESSION');
    session_start();
}

if (isset($_SESSION['user_id'])) {
    $allowed = true;
}

// B. Verificar Bearer Token (App Móvil)
if (!$allowed) {
    $authHeader = '';
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $authHeader = $headers['authorization'] ?? '';
    }
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $bearerToken = trim($matches[1]);
        $stmtBearer = $db->prepare("
            SELECT s.user_id
            FROM sessions s
            WHERE s.id = ? AND s.expires_at > NOW() AND s.is_mobile = 1
        ");
        $stmtBearer->bind_param("s", $bearerToken);
        $stmtBearer->execute();
        if ($stmtBearer->get_result()->num_rows > 0) {
            $allowed = true;
        }
        $stmtBearer->close();
    }
}

// C. Verificar Token de Compartir
if (!$allowed && $token) {
    $stmt = $db->prepare("
        SELECT s.id 
        FROM album_shares s
        INNER JOIN album_media am ON s.album_id = am.album_id
        WHERE s.share_token = ? 
        AND am.media_id = ?
        AND (s.expires_at IS NULL OR s.expires_at > NOW())
    ");
    $stmt->bind_param("si", $token, $mediaId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $allowed = true;

        // Registrar vista simplificada (opcional, para no saturar)
        // en ShareController ya se registra la vista al cargar el álbum
    }
    $stmt->close();
}

if (!$allowed) {
    http_response_code(403);
    die('<h1>Error 403 - Acceso denegado</h1>');
}

// 4. ENTREGAR ARCHIVO (Streaming optimizado)
$mimeType = $thumbnail ? 'image/jpeg' : $media['mime_type'];
$fileSize = filesize($filePath);
$lastModified = filemtime($filePath);
$etag = md5($filePath . $lastModified);

// Headers de Caché
header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
header("Etag: $etag");
header("Cache-Control: public, max-age=31536000"); // 1 año

// Verificar caché del cliente
if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag) {
    http_response_code(304);
    exit;
}

// Limpiar buffer de salida para evitar corrupción
while (ob_get_level())
    ob_end_clean();

// Streaming de video y audio
if (!$thumbnail && (strpos($mimeType, 'video/') === 0 || strpos($mimeType, 'audio/') === 0)) {
    $fp = @fopen($filePath, 'rb');

    $size = $fileSize; // Tamaño total
    $length = $size;   // Longitud a enviar
    $start = 0;        // Byte de inicio
    $end = $size - 1;  // Byte final

    header('Content-Type: ' . $mimeType);
    header('Accept-Ranges: bytes');

    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;

        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        if ($range == '-') {
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
        }

        $c_end = ($c_end > $end) ? $end : $c_end;

        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;

        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
    }

    header("Content-Range: bytes $start-$end/$size");
    header("Content-Length: " . $length);

    // Buffer de lectura optimizado
    $buffer = 1024 * 8;
    while (!feof($fp) && ($p = ftell($fp)) <= $end) {
        if ($p + $buffer > $end) {
            $buffer = $end - $p + 1;
        }
        set_time_limit(0);
        echo fread($fp, $buffer);
        flush();
    }
    fclose($fp);
} else {
    // Entrega normal (imágenes)
    header("Content-Type: $mimeType");
    header("Content-Disposition: inline; filename=\"" . basename($filePath) . "\"");
    header("Content-Length: " . $fileSize);
    readfile($filePath);
}
exit;
?>
