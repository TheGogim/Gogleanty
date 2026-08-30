# 📸 Gogleanty - Google Photos Clone Local

Una réplica completa de Google Fotos diseñada para funcionar 100% en tu entorno local Windows, con todas las características modernas de visualización de fotos y videos.

## ✨ Características

- 📷 **Visualización de Fotos y Videos** - Soporta JPG, PNG, GIF, MP4, MOV, AVI, WebM y más
- 🎬 **Previsualización de Videos** - Reproducción directa en el navegador
- 📊 **Metadatos EXIF** - Muestra información de cámara, ubicación GPS, fecha de captura
- 🖼️ **Miniaturas Automáticas** - Generación automática de thumbnails
- 📅 **Timeline Inteligente** - Organización automática por fechas
- ⭐ **Favoritos** - Marca tus fotos favoritas
- 📁 **Álbumes** - Organiza tus fotos en colecciones
- 🔍 **Búsqueda** - Busca por nombre de archivo o ubicación
- 📱 **Diseño Responsivo** - Funciona en desktop y móvil
- 🎨 **Interfaz Moderna** - Diseño premium con animaciones suaves

## 🛠️ Requisitos

- **XAMPP** (Apache + MySQL + PHP)
- **PHP 7.4+** con extensiones:
  - `mysqli`
  - `gd` o `imagick` (para procesamiento de imágenes)
  - `exif` (para leer metadatos)
- **Navegador moderno** (Chrome, Firefox, Edge)

## 📦 Instalación

### Paso 1: Configurar XAMPP

1. Descarga e instala [XAMPP](https://www.apachefriends.org/)
2. Abre el Panel de Control de XAMPP
3. Inicia **Apache** y **MySQL**

### Paso 2: Configurar el Proyecto

1. Copia la carpeta `Gogleanty` a `C:\xampp\htdocs\`
2. Abre tu navegador y ve a: `http://localhost/phpmyadmin`
3. Verifica que MySQL esté funcionando

### Paso 3: Ejecutar Setup Automático

1. Abre tu navegador
2. Ve a: `http://localhost/Gogleanty/setup.php`
3. El script automáticamente:
   - ✅ Creará la base de datos `gogleanty_db`
   - ✅ Creará todas las tablas necesarias
   - ✅ Generará el archivo `.env`
   - ✅ Creará los directorios de uploads
   - ✅ Configurará los permisos

### Paso 4: ¡Listo!

Abre `http://localhost/Gogleanty` y comienza a usar tu galería de fotos local.

## 📖 Uso

### Subir Fotos y Videos

**Método 1: Botón de Subida**
- Haz clic en el botón de subida (↑) en la esquina superior derecha
- Selecciona uno o varios archivos
- Espera a que se complete la subida

**Método 2: Arrastrar y Soltar**
- Arrastra archivos desde tu explorador de Windows
- Suéltalos en cualquier parte de la ventana
- Los archivos se subirán automáticamente

### Visualizar Fotos

- Haz clic en cualquier foto para abrirla en pantalla completa
- Usa las flechas ← → para navegar entre fotos
- Presiona `ESC` para cerrar el visor

### Ver Metadatos

Cuando abres una foto, el panel derecho muestra:
- 📅 Fecha y hora de captura
- 📐 Dimensiones (ancho × alto)
- 💾 Tamaño del archivo
- 📷 Marca y modelo de cámara
- ⚙️ Configuración de la foto (ISO, apertura, velocidad de obturación)
- 📍 Ubicación GPS (si está disponible)

### Organizar con Álbumes

1. Ve a la sección "Álbumes" en el menú lateral
2. Crea un nuevo álbum
3. Agrega fotos desde tu biblioteca

### Buscar Fotos

- Usa la barra de búsqueda en la parte superior
- Busca por nombre de archivo
- Busca por ubicación
- Los resultados se actualizan en tiempo real

## 🗂️ Estructura del Proyecto

```
Gogleanty/
├── api/
│   ├── config.php           # Configuración y conexión DB
│   ├── index.php            # Enrutador API REST
│   ├── MediaController.php  # Controlador de medios
│   └── AlbumController.php  # Controlador de álbumes
├── css/
│   └── styles.css           # Estilos de la aplicación
├── js/
│   └── app.js               # Lógica de la aplicación
├── uploads/
│   ├── images/              # Fotos subidas
│   ├── videos/              # Videos subidos
│   ├── gifs/                # GIFs animados
│   └── thumbnails/          # Miniaturas generadas
├── index.html               # Página principal
├── setup.php                # Script de instalación
├── .env                     # Variables de entorno (generado)
├── .htaccess                # Configuración Apache (generado)
└── README.md                # Este archivo
```

## 🔧 Configuración Avanzada

### Cambiar Tamaño Máximo de Archivo

Edita `.env`:
```
MAX_FILE_SIZE=524288000  # 500MB en bytes
```

### Cambiar Calidad de Miniaturas

Edita `.env`:
```
THUMBNAIL_WIDTH=400
THUMBNAIL_HEIGHT=400
THUMBNAIL_QUALITY=85  # 0-100
```

### Agregar Más Tipos de Archivo

Edita `.env`:
```
ALLOWED_IMAGE_TYPES=jpg,jpeg,png,gif,webp,heic,raw
ALLOWED_VIDEO_TYPES=mp4,mov,avi,mkv,webm,m4v,flv
```

## 🎯 API Endpoints

La aplicación incluye una API REST completa:

### Medios
- `GET /api/media` - Obtener todos los medios
- `GET /api/media/{id}` - Obtener un medio específico
- `POST /api/media` - Subir nuevo medio
- `PUT /api/media/{id}` - Actualizar medio
- `DELETE /api/media/{id}` - Eliminar medio

### Álbumes
- `GET /api/albums` - Obtener todos los álbumes
- `GET /api/albums/{id}` - Obtener un álbum específico
- `POST /api/albums` - Crear nuevo álbum
- `PUT /api/albums/{id}` - Actualizar álbum
- `DELETE /api/albums/{id}` - Eliminar álbum

### Otros
- `GET /api/timeline` - Timeline agrupado por fecha
- `GET /api/search?q={query}` - Buscar medios
- `GET /api/stats` - Estadísticas de uso

## 🐛 Solución de Problemas

### "Error de conexión a la base de datos"
- Verifica que MySQL esté ejecutándose en XAMPP
- Ejecuta `setup.php` nuevamente
- Revisa las credenciales en `.env`

### "Las imágenes no se muestran"
- Verifica que la carpeta `uploads` tenga permisos de escritura
- Revisa la consola del navegador para errores
- Asegúrate de que Apache esté ejecutándose

### "No se pueden subir archivos grandes"
Edita `php.ini` en XAMPP:
```ini
upload_max_filesize = 500M
post_max_size = 500M
max_execution_time = 300
```

### "Las miniaturas de video no se generan"
- Instala [FFmpeg](https://ffmpeg.org/download.html)
- Agrega FFmpeg al PATH de Windows
- Reinicia Apache

## 🎨 Personalización

### Cambiar Colores del Tema

Edita `css/styles.css`:
```css
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --accent-color: #f093fb;
}
```

### Cambiar Logo

Reemplaza el SVG en `index.html` dentro de `.logo`

## 📊 Base de Datos

### Tablas Principales

**media** - Almacena información de fotos y videos
- Metadatos EXIF completos
- Información de ubicación GPS
- Dimensiones y tamaño de archivo

**albums** - Colecciones de medios
- Nombre y descripción
- Imagen de portada

**album_media** - Relación entre álbumes y medios

**tags** - Etiquetas para organización

**media_tags** - Relación entre medios y etiquetas

## 🚀 Características Futuras

- [ ] Reconocimiento facial
- [ ] Búsqueda por contenido (AI)
- [ ] Edición básica de fotos
- [ ] Compartir álbumes
- [ ] Copias de seguridad automáticas
- [ ] Sincronización con la nube (opcional)

## 📝 Licencia

Este proyecto es de código abierto y está disponible para uso personal.

## 🤝 Contribuciones

¡Las contribuciones son bienvenidas! Si encuentras un bug o tienes una sugerencia, no dudes en crear un issue.

## 📧 Soporte

Si tienes problemas o preguntas:
1. Revisa la sección de Solución de Problemas
2. Verifica que XAMPP esté configurado correctamente
3. Asegúrate de haber ejecutado `setup.php`

---

**¡Disfruta de tu galería de fotos local! 📸✨**
