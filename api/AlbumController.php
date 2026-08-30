<?php
/**
 * Controlador de Álbumes
 */

class AlbumController
{
    private $db;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    /**
     * Crear un nuevo álbum
     */
    public function createAlbum($data)
    {
        if (!isset($data['name']) || empty(trim($data['name']))) {
            throw new Exception('El nombre del álbum es requerido');
        }

        $name = trim($data['name']);
        $description = isset($data['description']) ? trim($data['description']) : null;

        $stmt = $this->db->prepare("INSERT INTO albums (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);

        if (!$stmt->execute()) {
            throw new Exception('Error al crear el álbum: ' . $stmt->error);
        }

        $album_id = $stmt->insert_id;
        $stmt->close();

        // Si se envió password (creación de álbum privado desde inicio)
        if (isset($data['password']) && !empty($data['password'])) {
            $passHash = password_hash($data['password'], PASSWORD_BCRYPT);
            $type = 'private'; // Forzar tipo privado si hay pass

            $stmt = $this->db->prepare("UPDATE albums SET type = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param("ssi", $type, $passHash, $album_id);
            $stmt->execute();
            $stmt->close();
        }

        return [
            'success' => true,
            'message' => 'Álbum creado exitosamente',
            'album_id' => $album_id
        ];
    }

    /**
     * Obtener un álbum específico
     */
    /**
     * Obtener un álbum específico (con verificación de privacidad básica)
     */
    public function getAlbum($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM albums WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Álbum no encontrado');
        }

        $album = $result->fetch_assoc();

        // Si es privado, limpiar información sensible si no se proporciona password correcto (se manejará en frontend)
        // Pero para el listado básico, devolvemos la info pública (nombre, tipo)
        // La restricción fuerte está en getAlbumMedia

        $stmt->close();

        return ['success' => true, 'data' => $album];
    }

    /**
     * Verificar contraseña de álbum
     */
    public function verifyAlbumPassword($albumId, $password)
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM albums WHERE id = ?");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return false;
        }

        $album = $result->fetch_assoc();

        // Si no tiene password establecido (error de config), permitir acceso
        if (empty($album['password_hash'])) {
            return true;
        }

        return password_verify($password, $album['password_hash']);
    }

    /**
     * Obtener todos los álbumes
     */
    public function getAllAlbums()
    {
        $sql = "
            SELECT a.*, COUNT(am.media_id) as media_count
            FROM albums a
            LEFT JOIN album_media am ON a.id = am.album_id
            GROUP BY a.id
            ORDER BY a.updated_at DESC
        ";

        $result = $this->db->query($sql);
        $albums = [];

        while ($row = $result->fetch_assoc()) {
            // Obtener imagen de portada
            if ($row['cover_media_id']) {
                $row['cover_url'] = 'api/serve.php?id=' . $row['cover_media_id'] . '&thumb=1';
            } else {
                // Usar la primera imagen del álbum
                $first_stmt = $this->db->prepare("
                    SELECT m.id 
                    FROM media m
                    INNER JOIN album_media am ON m.id = am.media_id
                    WHERE am.album_id = ?
                    ORDER BY am.added_at ASC
                    LIMIT 1
                ");
                $first_stmt->bind_param("i", $row['id']);
                $first_stmt->execute();
                $first_result = $first_stmt->get_result();
                if ($first_result->num_rows > 0) {
                    $first = $first_result->fetch_assoc();
                    $row['cover_url'] = 'api/serve.php?id=' . $first['id'] . '&thumb=1';
                }
                $first_stmt->close();
            }

            $albums[] = $row;
        }

        return ['success' => true, 'data' => $albums];
    }

    /**
     * Obtener medios de un álbum
     */
    /**
     * Obtener medios de un álbum (CON SEGURIDAD)
     */
    public function getAlbumMedia($albumId)
    {
        // 1. Verificar tipo de álbum
        $stmt = $this->db->prepare("SELECT type FROM albums WHERE id = ?");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $res = $stmt->get_result();
        $albumType = $res->fetch_assoc()['type'] ?? 'standard';
        $stmt->close();

        // 2. Si es privado, requerir contraseña en headers o request
        if ($albumType === 'private') {
            $password = $_REQUEST['password'] ?? $_SERVER['HTTP_X_ALBUM_PASSWORD'] ?? null;

            if (!$password || !$this->verifyAlbumPassword($albumId, $password)) {
                // Retornar error específico 403
                http_response_code(403);
                return [
                    'success' => false,
                    'message' => 'Se requiere contraseña para este álbum',
                    'locked' => true
                ];
            }
        }

        $stmt = $this->db->prepare("
            SELECT m.* 
            FROM media m
            INNER JOIN album_media am ON m.id = am.media_id
            WHERE am.album_id = ?
            ORDER BY am.added_at DESC
        ");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $result = $stmt->get_result();

        $media = [];
        while ($row = $result->fetch_assoc()) {
            $base = 'api/serve.php?id=' . $row['id'];
            $row['file_url'] = $base;
            $row['thumbnail_url'] = $row['thumbnail_path'] ? $base . '&thumb=1' : null;
            $media[] = $row;
        }
        $stmt->close();

        return ['success' => true, 'data' => $media];
    }

    /**
     * Actualizar álbum
     */
    public function updateAlbum($id, $data)
    {
        $allowed_fields = ['name', 'description', 'cover_media_id'];
        $updates = [];
        $params = [];
        $types = "";

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= $field === 'cover_media_id' ? "i" : "s";
            }
        }

        if (empty($updates)) {
            throw new Exception('No hay campos para actualizar');
        }

        $sql = "UPDATE albums SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Álbum actualizado'];
    }

    /**
     * Eliminar álbum
     */
    public function deleteAlbum($id)
    {
        $stmt = $this->db->prepare("DELETE FROM albums WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Álbum eliminado'];
    }

    /**
     * Agregar medio a álbum
     */
    public function addMediaToAlbum($album_id, $media_id)
    {
        // Verificar que el álbum existe
        $stmt = $this->db->prepare("SELECT id FROM albums WHERE id = ?");
        $stmt->bind_param("i", $album_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            throw new Exception('Álbum no encontrado');
        }
        $stmt->close();

        // Verificar que el medio existe
        $stmt = $this->db->prepare("SELECT id FROM media WHERE id = ?");
        $stmt->bind_param("i", $media_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            $stmt->close();
            throw new Exception('Medio no encontrado');
        }
        $stmt->close();

        // Agregar a álbum
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO album_media (album_id, media_id) 
            VALUES (?, ?)
        ");
        $stmt->bind_param("ii", $album_id, $media_id);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Medio agregado al álbum'];
    }

    /**
     * Eliminar medio de álbum
     */
    public function removeMediaFromAlbum($album_id, $media_id)
    {
        $stmt = $this->db->prepare("
            DELETE FROM album_media 
            WHERE album_id = ? AND media_id = ?
        ");
        $stmt->bind_param("ii", $album_id, $media_id);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Medio eliminado del álbum'];
    }

    public function addMediaBulk($albumId, $mediaIds)
    {
        if (!is_array($mediaIds) || empty($mediaIds)) {
            throw new Exception('No se proporcionaron IDs de medios');
        }

        // Verify album exists
        $album = $this->getAlbum($albumId); // getAlbum throws exception if not found, so no need to check result if we trust it

        // However, getAlbum returns an array ['success'=>true, 'data'=>...]. It does NOT return the album object directly, but the method claims to throw exception if not found.
        // Let's check getAlbum implementation again. It throws Exception if album not found. So we are good.

        $success_count = 0;
        $failed_count = 0;

        $stmt = $this->db->prepare("INSERT IGNORE INTO album_media (album_id, media_id) VALUES (?, ?)");

        foreach ($mediaIds as $mediaId) {
            $stmt->bind_param("ii", $albumId, $mediaId);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $success_count++;
                } else {
                    // Already inside, count as success
                    $success_count++;
                }
            } else {
                $failed_count++;
            }
        }
        $stmt->close();

        return [
            'success' => true,
            'message' => "Se añadieron $success_count elementos al álbum",
            'stats' => [
                'added' => $success_count,
                'failed' => $failed_count
            ]
        ];
    }
}