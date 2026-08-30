<?php
/**
 * Script de configuración automática para Gogleanty (Google Photos Clone)
 * Este script crea la base de datos, tablas y archivo .env automáticamente
 */

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // XAMPP por defecto no tiene contraseña
define('DB_NAME', 'gogleanty_db');
define('DB_PORT', 3306);

// Configuración de la aplicación
define('UPLOAD_DIR', __DIR__ . '/uploads');
define('THUMBNAILS_DIR', __DIR__ . '/uploads/thumbnails');

echo "=== GOGLEANTY - Configuración Automática ===\n\n";

// Paso 1: Conectar a MySQL
echo "[1/6] Conectando a MySQL...\n";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, '', DB_PORT);
    if ($conn->connect_error) {
        throw new Exception("Error de conexión: " . $conn->connect_error);
    }
    echo "✓ Conexión exitosa a MySQL\n\n";
} catch (Exception $e) {
    die("✗ Error: " . $e->getMessage() . "\n");
}

// Paso 2: Crear base de datos
echo "[2/6] Creando base de datos...\n";
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "✓ Base de datos '" . DB_NAME . "' creada/verificada\n\n";
} else {
    die("✗ Error al crear la base de datos: " . $conn->error . "\n");
}

// Seleccionar la base de datos
$conn->select_db(DB_NAME);

// Paso 3: Crear tablas
echo "[3/6] Creando tablas...\n";

// Tabla de medios (fotos, videos, GIFs)
$sql_media = "CREATE TABLE IF NOT EXISTS media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    thumbnail_path VARCHAR(500),
    file_type ENUM('image', 'video', 'gif') NOT NULL,
    mime_type VARCHAR(100),
    file_size BIGINT,
    width INT,
    height INT,
    duration FLOAT DEFAULT NULL,
    date_taken DATETIME,
    date_uploaded DATETIME DEFAULT CURRENT_TIMESTAMP,
    camera_make VARCHAR(100),
    camera_model VARCHAR(100),
    focal_length VARCHAR(50),
    aperture VARCHAR(50),
    iso VARCHAR(50),
    exposure_time VARCHAR(50),
    gps_latitude DECIMAL(10, 8),
    gps_longitude DECIMAL(11, 8),
    gps_altitude DECIMAL(10, 2),
    location_name VARCHAR(255),
    description TEXT,
    is_favorite BOOLEAN DEFAULT FALSE,
    INDEX idx_date_taken (date_taken),
    INDEX idx_file_type (file_type),
    INDEX idx_favorite (is_favorite)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_media) === TRUE) {
    echo "✓ Tabla 'media' creada\n";
} else {
    die("✗ Error al crear tabla 'media': " . $conn->error . "\n");
}

// Tabla de álbumes
$sql_albums = "CREATE TABLE IF NOT EXISTS albums (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    cover_media_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cover_media_id) REFERENCES media(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_albums) === TRUE) {
    echo "✓ Tabla 'albums' creada\n";
} else {
    die("✗ Error al crear tabla 'albums': " . $conn->error . "\n");
}

// Tabla de relación entre álbumes y medios
$sql_album_media = "CREATE TABLE IF NOT EXISTS album_media (
    album_id INT,
    media_id INT,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (album_id, media_id),
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_album_media) === TRUE) {
    echo "✓ Tabla 'album_media' creada\n";
} else {
    die("✗ Error al crear tabla 'album_media': " . $conn->error . "\n");
}

// Tabla de etiquetas
$sql_tags = "CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_tags) === TRUE) {
    echo "✓ Tabla 'tags' creada\n";
} else {
    die("✗ Error al crear tabla 'tags': " . $conn->error . "\n");
}

// Tabla de relación entre medios y etiquetas
$sql_media_tags = "CREATE TABLE IF NOT EXISTS media_tags (
    media_id INT,
    tag_id INT,
    PRIMARY KEY (media_id, tag_id),
    FOREIGN KEY (media_id) REFERENCES media(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($sql_media_tags) === TRUE) {
    echo "✓ Tabla 'media_tags' creada\n\n";
} else {
    die("✗ Error al crear tabla 'media_tags': " . $conn->error . "\n");
}

// Paso 4: Crear directorios necesarios
echo "[4/6] Creando directorios...\n";
$directories = [
    UPLOAD_DIR,
    THUMBNAILS_DIR,
    UPLOAD_DIR . '/images',
    UPLOAD_DIR . '/videos',
    UPLOAD_DIR . '/gifs'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "✓ Directorio creado: " . basename($dir) . "\n";
        } else {
            echo "✗ Error al crear directorio: " . basename($dir) . "\n";
        }
    } else {
        echo "✓ Directorio ya existe: " . basename($dir) . "\n";
    }
}
echo "\n";

// Paso 5: Crear archivo .env
echo "[5/6] Creando archivo .env...\n";
$env_content = "# Configuración de Base de Datos
DB_HOST=" . DB_HOST . "
DB_USER=" . DB_USER . "
DB_PASS=" . DB_PASS . "
DB_NAME=" . DB_NAME . "
DB_PORT=" . DB_PORT . "

# Configuración de la Aplicación
APP_NAME=Gogleanty
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

# Directorios
UPLOAD_DIR=uploads
THUMBNAILS_DIR=uploads/thumbnails

# Configuración de archivos
MAX_FILE_SIZE=524288000
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,gif,webp,heic
ALLOWED_VIDEO_TYPES=mp4,mov,avi,mkv,webm,m4v
THUMBNAIL_WIDTH=400
THUMBNAIL_HEIGHT=400
THUMBNAIL_QUALITY=85

# Configuración de paginación
ITEMS_PER_PAGE=50
";

if (file_put_contents(__DIR__ . '/.env', $env_content)) {
    echo "✓ Archivo .env creado exitosamente\n\n";
} else {
    echo "✗ Error al crear archivo .env\n\n";
}

// Paso 6: Crear archivo .htaccess para Apache
echo "[6/6] Creando archivo .htaccess...\n";
$htaccess_content = "# Habilitar reescritura de URLs
RewriteEngine On

# Redirigir todo a index.php excepto archivos existentes
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^api/(.*)$ api/index.php [QSA,L]

# Configuración de seguridad
<FilesMatch \"\\.(env|log)$\">
    Order allow,deny
    Deny from all
</FilesMatch>

# Habilitar CORS para desarrollo local
Header set Access-Control-Allow-Origin \"*\"
Header set Access-Control-Allow-Methods \"GET, POST, PUT, DELETE, OPTIONS\"
Header set Access-Control-Allow-Headers \"Content-Type, Authorization\"

# Configuración de tipos MIME
AddType image/webp .webp
AddType video/mp4 .mp4
AddType video/webm .webm
";

if (file_put_contents(__DIR__ . '/.htaccess', $htaccess_content)) {
    echo "✓ Archivo .htaccess creado\n\n";
} else {
    echo "✗ Error al crear .htaccess\n\n";
}

// Cerrar conexión
$conn->close();

echo "=== ¡Configuración completada exitosamente! ===\n\n";
echo "Próximos pasos:\n";
echo "1. Asegúrate de que XAMPP esté ejecutándose (Apache y MySQL)\n";
echo "2. Abre tu navegador en: http://localhost/Gogleanty\n";
echo "3. Comienza a subir tus fotos y videos\n\n";
echo "Directorios creados:\n";
echo "- uploads/images - Para imágenes\n";
echo "- uploads/videos - Para videos\n";
echo "- uploads/gifs - Para GIFs animados\n";
echo "- uploads/thumbnails - Para miniaturas generadas\n\n";
?>
