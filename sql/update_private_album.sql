-- Comandos para activar el Álbum Privado
-- Ejecuta estos comandos en tu consola MariaDB/MySQL

-- 1. Agregar columna para ocultar fotos
ALTER TABLE media ADD COLUMN is_hidden TINYINT(1) DEFAULT 0;

-- 2. Agregar tipo de álbum (para diferenciar el privado de los normales)
ALTER TABLE albums ADD COLUMN type ENUM('standard', 'private') DEFAULT 'standard';

-- 3. Crear la "Carpeta Privada" por defecto
-- Nota: Solo se creará si no existe.
INSERT INTO albums (name, description, type) 
SELECT 'Carpeta Privada', 'Archivos ocultos y protegidos', 'private' 
WHERE NOT EXISTS (SELECT 1 FROM albums WHERE type = 'private');
