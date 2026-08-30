<?php
/**
 * Controlador de Compartir Álbumes
 * Maneja la creación, eliminación y acceso a álbumes compartidos
 */

require_once __DIR__ . '/AuthController.php';

class ShareController
{
    private $db;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    /**
     * Crear enlace de compartir para un álbum
     */
    public function createShare($albumId, $expiresInDays = null, $allowUpload = false)
    {
        // Verificar que el álbum existe
        $stmt = $this->db->prepare("SELECT id, name FROM albums WHERE id = ?");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Álbum no encontrado');
        }

        $album = $result->fetch_assoc();

        // Verificar si ya existe un enlace compartido
        $stmt = $this->db->prepare("SELECT share_token, allow_upload FROM album_shares WHERE album_id = ?");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            // Ya existe, devolver el token existente
            $share = $result->fetch_assoc();

            // Si cambio el allow_upload, actualizarlo
            if ($allowUpload !== null && $share['allow_upload'] != $allowUpload) {
                $upt = $this->db->prepare("UPDATE album_shares SET allow_upload = ? WHERE album_id = ?");
                $val = $allowUpload ? 1 : 0;
                $upt->bind_param("ii", $val, $albumId);
                $upt->execute();
            }

            return [
                'success' => true,
                'message' => 'Enlace de compartir ya existe',
                'share_token' => $share['share_token'],
                'share_url' => $this->getShareUrl($share['share_token']),
                'album_name' => $album['name'],
                'allow_upload' => $allowUpload !== null ? $allowUpload : (bool) $share['allow_upload']
            ];
        }

        // Generar token único
        $shareToken = $this->generateUniqueToken();

        // Calcular fecha de expiración si se especificó
        $expiresAt = null;
        if ($expiresInDays !== null && $expiresInDays > 0) {
            $expiresAt = date('Y-m-d H:i:s', strtotime("+$expiresInDays days"));
        }

        // Insertar en BD
        // Insertar en BD
        $stmt = $this->db->prepare("
            INSERT INTO album_shares (album_id, share_token, expires_at, allow_upload) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("issi", $albumId, $shareToken, $expiresAt, $allowUploadVal);
        $stmt->execute();

        return [
            'success' => true,
            'message' => 'Enlace de compartir creado',
            'share_token' => $shareToken,
            'share_url' => $this->getShareUrl($shareToken),
            'album_name' => $album['name'],
            'expires_at' => $expiresAt,
            'allow_upload' => $allowUpload
        ];
    }

    /**
     * Actualizar configuración de compartir (Permisos y Emails)
     */
    public function updateShareConfig($albumId, $allowUpload, $emails = [])
    {
        // 1. Verificar si existe el share
        $stmt = $this->db->prepare("SELECT id FROM album_shares WHERE album_id = ?");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            throw new Exception("El álbum no está compartido");
        }

        $share = $res->fetch_assoc();
        $shareId = $share['id'];

        // 2. Actualizar flag allow_upload
        $stmt = $this->db->prepare("UPDATE album_shares SET allow_upload = ? WHERE id = ?");
        $val = $allowUpload ? 1 : 0;
        $stmt->bind_param("ii", $val, $shareId);
        $stmt->execute();

        // 3. Actualizar lista de emails
        // Primero borramos los existentes (estrategia simple: reemplazar todo)
        $stmt = $this->db->prepare("DELETE FROM share_access WHERE share_id = ?");
        $stmt->bind_param("i", $shareId);
        $stmt->execute();

        // Insertar los nuevos
        if ($allowUpload && !empty($emails)) {
            $stmt = $this->db->prepare("INSERT INTO share_access (share_id, user_email) VALUES (?, ?)");
            foreach ($emails as $email) {
                $email = trim($email);
                if (!empty($email)) {
                    $stmt->bind_param("is", $shareId, $email);
                    $stmt->execute();
                }
            }
        }

        return [
            'success' => true,
            'message' => 'Configuración actualizada',
            'allow_upload' => $allowUpload,
            'emails_count' => count($emails)
        ];
    }

    /**
     * Obtener lista de emails permitidos
     */
    public function getShareAccess($albumId)
    {
        $stmt = $this->db->prepare("
            SELECT sa.user_email 
            FROM share_access sa
            JOIN album_shares s ON sa.share_id = s.id
            WHERE s.album_id = ?
        ");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $result = $stmt->get_result();

        $emails = [];
        while ($row = $result->fetch_assoc()) {
            $emails[] = $row['user_email'];
        }

        return $emails;
    }

    /**
     * Obtener álbum compartido por token
     */
    public function getSharedAlbum($shareToken, $password = null)
    {
        // Buscar el share
        $stmt = $this->db->prepare("
            SELECT 
                s.id as share_id,
                s.album_id,
                s.expires_at,
                s.views,
                s.allow_upload,
                a.name as album_name,
                a.description,
                a.cover_media_id,
                a.created_at
            FROM album_shares s
            INNER JOIN albums a ON s.album_id = a.id
            WHERE s.share_token = ?
        ");
        $stmt->bind_param("s", $shareToken);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Enlace no encontrado o expirado');
        }

        $share = $result->fetch_assoc();

        if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
            throw new Exception('Este enlace ha expirado');
        }

        // --- VERIFICACIÓN DE PRIVACIDAD ---
        // Verificar si el álbum es privado
        $stmt = $this->db->prepare("SELECT type, password_hash FROM albums WHERE id = ?");
        $stmt->bind_param("i", $share['album_id']);
        $stmt->execute();
        $albumQuery = $stmt->get_result();
        $albumParams = $albumQuery->num_rows > 0 ? $albumQuery->fetch_assoc() : null;
        $stmt->close();

        if ($albumParams && $albumParams['type'] === 'private') {
            $password = $password ?? $_REQUEST['password'] ?? $_SERVER['HTTP_X_ALBUM_PASSWORD'] ?? null;
            $isPasswordValid = false;

            if ($password && !empty($albumParams['password_hash'])) {
                $isPasswordValid = password_verify($password, $albumParams['password_hash']);
            }

            if (!$isPasswordValid) {
                // Si la contraseña es inválida o no se envió, devolvemos estado "locked"
                // pero con información básica del álbum para mostrar la pantalla de bloqueo
                return [
                    'success' => true, // La petición API fue exitosa, pero el contenido está bloqueado
                    'locked' => true,
                    'album' => [
                        'id' => $share['album_id'],
                        'name' => 'Álbum Protegido',
                        'description' => '',
                        'created_at' => null,
                        'media_count' => 0
                    ],
                    'message' => 'Contraseña requerida'
                ];
            }
        }
        // ----------------------------------

        // --- PERMISOS DE SUBIDA (COLLABORATION) ---
        $canUpload = false;
        $currentUserEmail = null;

        // Asegurar que la sesión esté iniciada y obtener usuario
        $auth = new AuthController();
        if ($auth->isAuthenticated()) {
            $user = $auth->getCurrentUser();
            if ($user) {
                // Obtener email completo (getCurrentUser devuelve id, username, created_at, pero necesitamos email)
                // AuthController::getCurrentUser no devuelve email por defecto en su select actual...
                // Espera, revisemos AuthController::getCurrentUser.
                // Devuelve: SELECT id, username, created_at FROM user WHERE id = ?
                // ¡Falta el email!

                // Vamos a obtener el email directamente aquí ya que tenemos el ID
                $uStmt = $this->db->prepare("SELECT email FROM user WHERE id = ?");
                $uStmt->bind_param("i", $user['id']);
                $uStmt->execute();
                $uRes = $uStmt->get_result();
                if ($uRes->num_rows > 0) {
                    $currentUserEmail = $uRes->fetch_assoc()['email'];
                }
            }
        }

        if ($share['allow_upload'] == 1 && $currentUserEmail) {
            // Verificar si el email está en la lista de acceso
            $aStmt = $this->db->prepare("SELECT id FROM share_access WHERE share_id = ? AND user_email = ?");
            $aStmt->bind_param("is", $share['share_id'], $currentUserEmail);
            $aStmt->execute();
            if ($aStmt->get_result()->num_rows > 0) {
                $canUpload = true;
            }
        }
        // ------------------------------------------

        // Incrementar contador de vistas
        $this->incrementViews($share['share_id']);

        // Obtener medios del álbum
        $stmt = $this->db->prepare("
            SELECT 
                m.id,
                m.filename,
                m.file_path,
                m.thumbnail_path,
                m.file_type,
                m.width,
                m.height,
                m.duration,
                m.date_taken,
                m.custom_name
            FROM album_media am
            INNER JOIN media m ON am.media_id = m.id
            WHERE am.album_id = ?
            ORDER BY am.added_at DESC
        ");
        $stmt->bind_param("i", $share['album_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $media = [];
        while ($row = $result->fetch_assoc()) {
            $base = 'api/serve.php?id=' . $row['id'] . '&token=' . $shareToken;
            $row['file_url'] = $base;
            $row['thumbnail_url'] = $row['thumbnail_path'] ? $base . '&thumb=1' : null;
            $media[] = $row;
        }

        return [
            'success' => true,
            'album' => [
                'id' => $share['album_id'],
                'name' => $share['album_name'],
                'description' => $share['description'],
                'created_at' => $share['created_at'],
                'media_count' => count($media),
                'allow_upload' => (bool) $share['allow_upload']
            ],
            'media' => $media,
            'views' => $share['views'] + 1,
            'permissions' => [
                'can_upload' => $canUpload,
                'user_email' => $currentUserEmail
            ]
        ];
    }

    /**
     * Eliminar enlace compartido
     */
    public function deleteShare($albumId)
    {
        $stmt = $this->db->prepare("DELETE FROM album_shares WHERE album_id = ?");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            throw new Exception('No existe enlace compartido para este álbum');
        }

        return [
            'success' => true,
            'message' => 'Enlace compartido eliminado'
        ];
    }

    /**
     * Obtener información de share de un álbum
     */
    public function getAlbumShare($albumId)
    {
        $stmt = $this->db->prepare("
            SELECT id, share_token, created_at, expires_at, views, allow_upload
            FROM album_shares 
            WHERE album_id = ?
        ");
        $stmt->bind_param("i", $albumId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'success' => true,
                'shared' => false
            ];
        }

        $share = $result->fetch_assoc();

        // Obtener emails de acceso
        $emails = $this->getShareAccess($albumId);

        return [
            'success' => true,
            'shared' => true,
            'share_token' => $share['share_token'],
            'share_url' => $this->getShareUrl($share['share_token']),
            'created_at' => $share['created_at'],
            'expires_at' => $share['expires_at'],
            'views' => $share['views'],
            'allow_upload' => (bool) $share['allow_upload'],
            'access_emails' => $emails
        ];
    }

    /**
     * Generar token único
     */
    private function generateUniqueToken()
    {
        do {
            // Generar token aleatorio de 32 caracteres
            $token = bin2hex(random_bytes(32));

            // Verificar que no existe
            $stmt = $this->db->prepare("SELECT id FROM album_shares WHERE share_token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
        } while ($result->num_rows > 0);

        return $token;
    }

    /**
     * Incrementar contador de vistas
     */
    private function incrementViews($shareId)
    {
        $stmt = $this->db->prepare("
            UPDATE album_shares 
            SET views = views + 1, last_viewed_at = NOW() 
            WHERE id = ?
        ");
        $stmt->bind_param("i", $shareId);
        $stmt->execute();
    }

    /**
     * Generar URL completa de compartir
     */
    private function getShareUrl($token)
    {
        // Obtener la URL base
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $basePath = APP_BASE_PATH;
        return "$protocol://$host$basePath/share.html?token=$token";
    }

    /**
     * Limpiar enlaces expirados
     */
    public function cleanupExpiredShares()
    {
        $stmt = $this->db->prepare("
            DELETE FROM album_shares 
            WHERE expires_at IS NOT NULL AND expires_at < NOW()
        ");
        $stmt->execute();

        return [
            'success' => true,
            'deleted' => $stmt->affected_rows
        ];
    }
}

?>