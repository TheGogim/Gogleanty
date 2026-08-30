# 📸 GOGLEANTY - Resumen del Proyecto

## 🎯 ¿Qué es Gogleanty?

Una **copia exacta de Google Fotos** que funciona 100% en tu computadora local Windows.
No necesita internet, tus fotos nunca salen de tu PC.

---

## ✨ Características Implementadas

### 📷 Gestión de Medios
- ✅ Subida de fotos (JPG, PNG, GIF, WebP, HEIC)
- ✅ Subida de videos (MP4, MOV, AVI, MKV, WebM)
- ✅ GIFs animados
- ✅ Miniaturas automáticas
- ✅ Previsualización de todo tipo de archivo

### 📊 Metadatos EXIF
- ✅ Fecha y hora de captura
- ✅ Marca y modelo de cámara
- ✅ Configuración de la foto (ISO, apertura, velocidad)
- ✅ Distancia focal
- ✅ Ubicación GPS (latitud, longitud, altitud)
- ✅ Dimensiones (ancho × alto)
- ✅ Tamaño de archivo

### 🎨 Interfaz de Usuario
- ✅ Diseño moderno similar a Google Fotos
- ✅ Timeline organizado por fechas
- ✅ Visor de fotos en pantalla completa
- ✅ Navegación con teclado (flechas, ESC)
- ✅ Panel de información lateral
- ✅ Búsqueda en tiempo real
- ✅ Drag & Drop para subir archivos
- ✅ Diseño responsivo (desktop y móvil)

### 🗂️ Organización
- ✅ Favoritos
- ✅ Álbumes
- ✅ Filtros por tipo (fotos, videos)
- ✅ Agrupación automática por fecha

### 🔧 Backend
- ✅ API REST completa en PHP
- ✅ Base de datos MySQL
- ✅ Extracción automática de metadatos
- ✅ Generación de miniaturas
- ✅ Manejo de archivos grandes (hasta 500MB)

---

## 📁 Estructura del Proyecto

```
Gogleanty/
│
├── 📄 index.html                  # Aplicación principal
├── 📄 bienvenida.html            # Página de bienvenida
├── 📄 setup.php                  # Instalador automático ⭐
├── 📄 check-db.php               # Verificador de BD ⭐
├── 📄 .htaccess                  # Configuración Apache
├── 📄 .env                       # Variables de entorno (se genera)
│
├── 📁 api/
│   ├── config.php                # Configuración y conexión DB
│   ├── index.php                 # Enrutador API REST
│   ├── MediaController.php       # Controlador de medios
│   └── AlbumController.php       # Controlador de álbumes
│
├── 📁 css/
│   └── styles.css                # Estilos completos
│
├── 📁 js/
│   └── app.js                    # Lógica de la aplicación
│
├── 📁 uploads/ (se crea automáticamente)
│   ├── images/                   # Fotos
│   ├── videos/                   # Videos
│   ├── gifs/                     # GIFs
│   └── thumbnails/               # Miniaturas
│
└── 📁 docs/
    ├── README.md                 # Documentación completa
    ├── INICIO-RAPIDO.md          # Guía rápida
    └── php-config-example.ini    # Configuración PHP
```

---

## 🚀 Instalación (3 Pasos)

### 1️⃣ Preparar XAMPP
```
1. Abre XAMPP Control Panel
2. Start → Apache
3. Start → MySQL
```

### 2️⃣ Ejecutar Setup
```
Navega a: http://localhost/Gogleanty/setup.php
```

### 3️⃣ ¡Usar!
```
Abre: http://localhost/Gogleanty
```

---

## 🗄️ Base de Datos

### Tablas Creadas Automáticamente

**media** - Almacena fotos y videos
- id, filename, original_filename, file_path, thumbnail_path
- file_type, mime_type, file_size, width, height, duration
- date_taken, date_uploaded
- camera_make, camera_model, focal_length, aperture, iso, exposure_time
- gps_latitude, gps_longitude, gps_altitude, location_name
- description, is_favorite

**albums** - Colecciones de medios
- id, name, description, cover_media_id
- created_at, updated_at

**album_media** - Relación álbumes-medios
- album_id, media_id, added_at

**tags** - Etiquetas
- id, name, created_at

**media_tags** - Relación medios-etiquetas
- media_id, tag_id

---

## 🔌 API Endpoints

### Medios
```
GET    /api/media              # Listar todos
GET    /api/media/{id}         # Ver uno
POST   /api/media              # Subir nuevo
PUT    /api/media/{id}         # Actualizar
DELETE /api/media/{id}         # Eliminar
```

### Álbumes
```
GET    /api/albums             # Listar todos
GET    /api/albums/{id}        # Ver uno
POST   /api/albums             # Crear nuevo
PUT    /api/albums/{id}        # Actualizar
DELETE /api/albums/{id}        # Eliminar
```

### Otros
```
GET    /api/timeline           # Timeline por fechas
GET    /api/search?q={query}  # Buscar
GET    /api/stats              # Estadísticas
```

---

## 🎨 Tecnologías Utilizadas

### Frontend
- **HTML5** - Estructura semántica
- **CSS3** - Estilos modernos con gradientes y animaciones
- **JavaScript (Vanilla)** - Sin frameworks, puro JS
- **Fetch API** - Comunicación con backend

### Backend
- **PHP 7.4+** - Lenguaje del servidor
- **MySQL** - Base de datos
- **Apache** - Servidor web (vía XAMPP)

### Características Técnicas
- **REST API** - Arquitectura moderna
- **EXIF Reading** - Extracción de metadatos
- **GD/Imagick** - Procesamiento de imágenes
- **Responsive Design** - Mobile-first
- **Drag & Drop API** - Subida intuitiva

---

## 🎯 Archivos Clave para el Usuario

### ⭐ DEBES EJECUTAR PRIMERO:
1. **setup.php** - Crea la BD y configura todo
2. **check-db.php** - Verifica que todo esté bien

### 📖 DOCUMENTACIÓN:
1. **README.md** - Documentación completa
2. **INICIO-RAPIDO.md** - Guía rápida de 3 pasos
3. **bienvenida.html** - Página de bienvenida interactiva

### ⚙️ CONFIGURACIÓN:
1. **.env** - Variables de entorno (se genera automáticamente)
2. **php-config-example.ini** - Configuración PHP recomendada

---

## 🔐 Seguridad y Privacidad

✅ **100% Local** - Tus fotos nunca salen de tu PC
✅ **Sin Internet** - No requiere conexión
✅ **Sin Cuentas** - No hay logins ni registros
✅ **Control Total** - Tú decides qué hacer con tus datos
✅ **Sin Tracking** - Cero seguimiento o analytics

---

## 📊 Capacidades

- **Archivos soportados**: Ilimitados (depende de tu disco)
- **Tamaño máximo por archivo**: 500MB (configurable)
- **Formatos de imagen**: JPG, PNG, GIF, WebP, HEIC
- **Formatos de video**: MP4, MOV, AVI, MKV, WebM, M4V
- **Metadatos EXIF**: Completos (cámara, GPS, configuración)
- **Miniaturas**: Generación automática
- **Búsqueda**: En tiempo real
- **Organización**: Álbumes ilimitados

---

## 💡 Características Destacadas

### 🎨 Diseño Premium
- Gradientes vibrantes
- Animaciones suaves
- Glassmorphism
- Sombras modernas
- Tipografía Inter

### ⚡ Rendimiento
- Carga lazy de imágenes
- Miniaturas optimizadas
- Paginación eficiente
- Cache de navegador

### 🔧 Facilidad de Uso
- Instalación automática
- Configuración cero
- Drag & Drop
- Búsqueda instantánea
- Navegación con teclado

---

## 🎯 Diferencias con Google Fotos

| Característica | Google Fotos | Gogleanty |
|----------------|--------------|-----------|
| **Ubicación** | Nube | Local |
| **Privacidad** | Datos en Google | 100% privado |
| **Internet** | Requerido | No necesario |
| **Costo** | Gratis/Pago | Gratis |
| **Almacenamiento** | Limitado | Tu disco duro |
| **Velocidad** | Depende de internet | Instantáneo |
| **Control** | Limitado | Total |
| **Metadatos** | Básicos | Completos (EXIF) |

---

## 🚀 Próximas Mejoras Posibles

- [ ] Reconocimiento facial
- [ ] Búsqueda por contenido (AI)
- [ ] Edición básica de fotos
- [ ] Compartir álbumes (red local)
- [ ] Backup automático
- [ ] Importación desde Google Photos
- [ ] Detección de duplicados
- [ ] Mapa de ubicaciones

---

## 📞 Soporte

### Problemas Comunes

**"No se puede conectar"**
→ Verifica que Apache y MySQL estén activos en XAMPP

**"Error 404"**
→ Asegúrate de que la carpeta esté en C:\xampp\htdocs\Gogleanty

**"Error de BD"**
→ Ejecuta setup.php nuevamente

**"No se suben archivos"**
→ Verifica permisos de la carpeta uploads

### Verificación Rápida
```
http://localhost/Gogleanty/check-db.php
```

---

## ✅ Checklist de Instalación

- [ ] XAMPP instalado
- [ ] Apache iniciado (verde)
- [ ] MySQL iniciado (verde)
- [ ] Carpeta en C:\xampp\htdocs\Gogleanty
- [ ] Ejecutado setup.php
- [ ] Verificado con check-db.php
- [ ] Abierto http://localhost/Gogleanty
- [ ] Subida primera foto de prueba

---

## 🎉 ¡Todo Listo!

Tu copia local de Google Fotos está lista para usar.

**Disfruta de tu galería privada y local! 📸✨**

---

**Versión:** 1.0  
**Fecha:** 2024  
**Autor:** Creado para uso local y personal  
**Licencia:** Uso libre personal
