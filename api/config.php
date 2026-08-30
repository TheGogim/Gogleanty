<?php
/**
 * Configuración de la aplicación
 */

// Cargar variables de entorno
function loadEnv($path)
{
    if (!file_exists($path)) {
        throw new Exception("Archivo .env no encontrado. Ejecuta setup.php primero.");
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

// Cargar .env
loadEnv(__DIR__ . '/../.env');

// Configuración de base de datos
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'gogleanty_db');
define('DB_PORT', $_ENV['DB_PORT'] ?? 3306);

// Configuración de la aplicación
define('APP_NAME', $_ENV['APP_NAME'] ?? 'Gogleanty');
define('APP_ENV', $_ENV['APP_ENV'] ?? 'local');
define('APP_DEBUG', $_ENV['APP_DEBUG'] ?? true);
define('APP_URL', $_ENV['APP_URL'] ?? 'http://localhost');

// Detectar automáticamente la ruta base de la aplicación
// Esto permite que funcione tanto en /Gogleanty como en la raíz o en producción
$script_name = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = dirname(dirname($script_name)); // Sube dos niveles desde /api/index.php
if ($base_path === '/' || $base_path === '\\' || $base_path === '.') {
    $base_path = '';
}
define('APP_BASE_PATH', $base_path);

// Directorios físicos (rutas absolutas del servidor)
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_DIR', ROOT_PATH . '/' . ($_ENV['UPLOAD_DIR'] ?? 'uploads'));
define('THUMBNAILS_DIR', ROOT_PATH . '/' . ($_ENV['THUMBNAILS_DIR'] ?? 'uploads/thumbnails'));

// Configuración de archivos
define('MAX_FILE_SIZE', $_ENV['MAX_FILE_SIZE'] ?? 524288000); // 500MB
define('ALLOWED_IMAGE_TYPES', explode(',', $_ENV['ALLOWED_IMAGE_TYPES'] ?? 'jpg,jpeg,png,gif,webp,heic'));
define('ALLOWED_VIDEO_TYPES', explode(',', $_ENV['ALLOWED_VIDEO_TYPES'] ?? 'mp4,mov,avi,mkv,webm,m4v'));
define('THUMBNAIL_WIDTH', $_ENV['THUMBNAIL_WIDTH'] ?? 400);
define('THUMBNAIL_HEIGHT', $_ENV['THUMBNAIL_HEIGHT'] ?? 400);
define('THUMBNAIL_QUALITY', $_ENV['THUMBNAIL_QUALITY'] ?? 85);

// Configuración de paginación
define('ITEMS_PER_PAGE', $_ENV['ITEMS_PER_PAGE'] ?? 50);

// Configuración de zona horaria
date_default_timezone_set('America/Bogota');

// Configuración de errores
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Función para obtener conexión a la base de datos
function getDBConnection()
{
    static $conn = null;

    if ($conn === null) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

        if ($conn->connect_error) {
            throw new Exception("Error de conexión a la base de datos: " . $conn->connect_error);
        }

        $conn->set_charset("utf8mb4");
    }

    return $conn;
}

// Headers para API
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>