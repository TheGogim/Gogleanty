<?php
/**
 * Controlador de Medios (Fotos, Videos, GIFs, Audios)
 * Con extracción COMPLETA de metadatos usando FFmpeg
 * Versión mejorada con soporte para AUDIOS
 */

class MediaController
{
    private $db;

    public function __construct()
    {
        $this->db = getDBConnection();
    }

    /**
     * Subir un nuevo archivo multimedia (incluye audios)
     */
    public function uploadMedia($file, $isHidden = 0, $deviceFileHash = null, $fallbackDate = null)
    {
        error_log("=== INICIO UPLOAD ===");

        if (!$file || !isset($file['tmp_name'])) {
            throw new Exception('No se recibió ningún archivo');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo: ' . $file['error']);
        }

        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('El archivo excede el tamaño máximo permitido');
        }

        // Determinar tipo de archivo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        error_log("MIME Type: $mime_type");

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        error_log("Extension: $extension");

        // Validar tipo de archivo (incluye audio)
        $file_type = $this->getFileType($mime_type, $extension);
        if (!$file_type) {
            throw new Exception('Tipo de archivo no permitido: ' . $mime_type);
        }
        error_log("File Type: $file_type");

        // Generar nombre único
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $subdir = $file_type === 'gif' ? 'gifs' : ($file_type === 'video' ? 'videos' : ($file_type === 'audio' ? 'audios' : 'images'));

        // Rutas relativas para la BD
        $relative_upload_path = APP_BASE_PATH . '/uploads/' . $subdir . '/' . $filename;

        // Ruta absoluta para guardar el archivo físicamente
        $absolute_target_dir = __DIR__ . '/../uploads/' . $subdir;
        if (!is_dir($absolute_target_dir)) {
            mkdir($absolute_target_dir, 0777, true);
            error_log("Directorio creado: $absolute_target_dir");
        }

        $absolute_upload_path = $absolute_target_dir . '/' . $filename;
        error_log("Upload Path (absoluta): $absolute_upload_path");
        error_log("Upload Path (relativa para BD): $relative_upload_path");

        // Mover archivo
        if (!move_uploaded_file($file['tmp_name'], $absolute_upload_path)) {
            throw new Exception('Error al guardar el archivo en: ' . $absolute_upload_path);
        }
        error_log("Archivo movido exitosamente");

        // Extraer metadatos (COMPLETOS, incluye audios)
        try {
            $metadata = $this->extractMetadata($absolute_upload_path, $file_type, $mime_type, $fallbackDate);
            error_log("Metadatos extraídos: " . json_encode($metadata));
        } catch (Exception $e) {
            error_log("Error extrayendo metadatos: " . $e->getMessage());
            // Continuar con metadatos básicos
            $fallbackTs = $fallbackDate ? strtotime($fallbackDate) : null;
            $metadata = [
                'file_size'     => filesize($absolute_upload_path),
                'width'         => null,
                'height'        => null,
                'duration'      => null,
                'date_taken'    => $fallbackTs
                    ? date('Y-m-d H:i:s', $fallbackTs)
                    : date('Y-m-d H:i:s', filemtime($absolute_upload_path)),
                'camera_make'   => null,
                'camera_model'  => null,
                'focal_length'  => null,
                'aperture'      => null,
                'iso'           => null,
                'exposure_time' => null,
                'gps_latitude'  => null,
                'gps_longitude' => null,
                'gps_altitude'  => null,
                'location_name' => null
            ];
        }

        // Generar miniatura
        $thumbnail_path = $this->generateThumbnail($absolute_upload_path, $file_type, $filename);
        $relative_thumbnail_path = $thumbnail_path ? APP_BASE_PATH . '/uploads/thumbnails/' . basename($thumbnail_path) : null;
        error_log("Thumbnail Path: " . ($thumbnail_path ?: 'No generado'));

        // Guardar en BD
        $stmt = $this->db->prepare("
            INSERT INTO media (
                filename, original_filename, file_path, thumbnail_path,
                file_type, mime_type, file_size, device_file_hash, width, height, duration,
                date_taken, camera_make, camera_model, focal_length,
                aperture, iso, exposure_time, gps_latitude, gps_longitude,
                gps_altitude, location_name, is_hidden
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "ssssssisiiisssssssdddsi",
            $filename,
            $file['name'],
            $relative_upload_path,
            $relative_thumbnail_path,
            $file_type,
            $mime_type,
            $metadata['file_size'],
            $deviceFileHash,
            $metadata['width'],
            $metadata['height'],
            $metadata['duration'],
            $metadata['date_taken'],
            $metadata['camera_make'],
            $metadata['camera_model'],
            $metadata['focal_length'],
            $metadata['aperture'],
            $metadata['iso'],
            $metadata['exposure_time'],
            $metadata['gps_latitude'],
            $metadata['gps_longitude'],
            $metadata['gps_altitude'],
            $metadata['location_name'],
            $isHidden
        );

        if (!$stmt->execute()) {
            throw new Exception('Error al guardar en la base de datos: ' . $stmt->error);
        }

        $media_id = $stmt->insert_id;
        $stmt->close();

        error_log("=== UPLOAD EXITOSO (ID: $media_id) ===");

        return [
            'success' => true,
            'message' => 'Archivo subido exitosamente',
            'media_id' => $media_id,
            'data' => $this->getMedia($media_id)['data']
        ];
    }

    public function getMedia($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM media WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Medio no encontrado');
        }

        $media = $result->fetch_assoc();

        $base = 'api/serve.php?id=' . $media['id'];
        $media['file_url'] = $base;
        $media['thumbnail_url'] = $media['thumbnail_path'] ? $base . '&thumb=1' : null;

        $stmt->close();

        return ['success' => true, 'data' => $media];
    }

    public function getAllHashes()
    {
        // Solo necesitamos recuperar los hashes que no están vacíos
        $stmt = $this->db->prepare("SELECT device_file_hash FROM media WHERE device_file_hash IS NOT NULL AND device_file_hash != ''");
        if (!$stmt) {
             throw new Exception('Error preparando la consulta de hashes');
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $hashes = [];
        while ($row = $result->fetch_assoc()) {
            $hashes[] = $row['device_file_hash'];
        }
        $stmt->close();

        return ['success' => true, 'data' => $hashes];
    }

    public function getAllMedia($page = 1, $limit = ITEMS_PER_PAGE, $type = null, $favorite = null, $year = null, $month = null)
    {
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];
        $types = "";

        if ($type) {
            $where[] = "file_type = ?";
            $params[] = $type;
            $types .= "s";
        }

        if ($favorite !== null) {
            $where[] = "is_favorite = ?";
            $params[] = $favorite ? 1 : 0;
            $types .= "i";
        }

        if ($year) {
            $where[] = "YEAR(date_taken) = ?";
            $params[] = $year;
            $types .= "i";
        }

        if ($month) {
            $where[] = "MONTH(date_taken) = ?";
            $params[] = $month;
            $types .= "i";
        }

        $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT m.* 
                FROM media m
                WHERE NOT EXISTS (
                    SELECT 1 FROM album_media am 
                    JOIN albums a ON am.album_id = a.id 
                    WHERE am.media_id = m.id AND a.type = 'private'
                )
                " . ($where_clause ? " AND " . str_replace("WHERE ", "", $where_clause) : "") . "
                ORDER BY m.date_taken DESC, m.date_uploaded DESC 
                LIMIT ? OFFSET ?";

        // Ajustar count_sql también para la paginación correcta
        $count_sql = "SELECT COUNT(*) as total 
                      FROM media m
                      WHERE NOT EXISTS (
                          SELECT 1 FROM album_media am 
                          JOIN albums a ON am.album_id = a.id 
                          WHERE am.media_id = m.id AND a.type = 'private'
                      )
                      " . ($where_clause ? " AND " . str_replace("WHERE ", "", $where_clause) : "");

        $stmt = $this->db->prepare($count_sql);
        if (!empty($params)) {
            // Params se usan para los filtros adicionales (type, favorite, year, month)
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $total = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $this->db->prepare($sql);
        $types .= "ii";
        $params[] = $limit;
        $params[] = $offset;
        $stmt->bind_param($types, ...$params);
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

        return [
            'success' => true,
            'data' => $media,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ];
    }

    public function getTimeline()
    {
        $sql = "SELECT 
                    DATE(date_taken) as date,
                    COUNT(*) as count,
                    GROUP_CONCAT(id) as media_ids
                FROM media 
                WHERE date_taken IS NOT NULL
                GROUP BY DATE(date_taken)
                ORDER BY date_taken DESC";

        $result = $this->db->query($sql);
        $timeline = [];

        while ($row = $result->fetch_assoc()) {
            $media_ids = explode(',', $row['media_ids']);
            $media_items = [];

            foreach (array_slice($media_ids, 0, 4) as $id) {
                // getMedia ya devuelve las URLs seguras
                $media_data = $this->getMedia($id);
                $media_items[] = $media_data['data'];
            }

            $timeline[] = [
                'date' => $row['date'],
                'count' => $row['count'],
                'preview_items' => $media_items
            ];
        }

        return ['success' => true, 'data' => $timeline];
    }

    public function searchMedia($query)
    {
        $search = "%$query%";
        $stmt = $this->db->prepare("
            SELECT * FROM media 
            WHERE original_filename LIKE ? 
               OR description LIKE ?
               OR location_name LIKE ?
               OR custom_name LIKE ?
            ORDER BY date_taken DESC, id DESC
            LIMIT 100
        ");
        $stmt->bind_param("ssss", $search, $search, $search, $search);
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

    public function updateMedia($id, $data)
    {
        // Obtener el tipo de archivo
        $media = $this->getMedia($id);
        $file_type = $media['data']['file_type'];

        $allowed_fields = ['description', 'is_favorite', 'location_name'];

        // Solo permitir editar custom_name para audios
        if ($file_type === 'audio' && isset($data['custom_name'])) {
            $allowed_fields[] = 'custom_name';
        }

        $updates = [];
        $params = [];
        $types = "";

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                $params[] = $data[$field];
                $types .= is_bool($data[$field]) ? "i" : "s";
            }
        }

        if (empty($updates)) {
            throw new Exception('No hay campos para actualizar');
        }

        $sql = "UPDATE media SET " . implode(", ", $updates) . " WHERE id = ?";
        $params[] = $id;
        $types .= "i";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Medio actualizado'];
    }

    public function deleteMedia($id)
    {
        $media = $this->getMedia($id);

        $file_path = __DIR__ . '/..' . str_replace(APP_BASE_PATH, '', $media['data']['file_path']);
        $thumbnail_path = $media['data']['thumbnail_path'] ? __DIR__ . '/..' . str_replace(APP_BASE_PATH, '', $media['data']['thumbnail_path']) : null;

        $stmt = $this->db->prepare("DELETE FROM media WHERE id = ?");
        $stmt->bind_param("i", $id);

        if (!$stmt->execute()) {
            // Check for foreign key constraint error (MySQL 1451)
            // If the item is in use in album_media (albums), we delete the relation first
            if ($this->db->errno === 1451) {
                // Delete from album_media
                $stmt2 = $this->db->prepare("DELETE FROM album_media WHERE media_id = ?");
                $stmt2->bind_param("i", $id);
                $stmt2->execute();
                $stmt2->close();

                // Retry deleting media
                if (!$stmt->execute()) {
                    throw new Exception('Error al eliminar de la base de datos (después de limpiar relaciones): ' . $stmt->error);
                }
            } else {
                throw new Exception('Error al eliminar de la base de datos: ' . $this->db->error);
            }
        }
        $stmt->close();

        @unlink($file_path);
        if ($thumbnail_path) {
            @unlink($thumbnail_path);
        }

        return ['success' => true, 'message' => 'Medio eliminado'];
    }

    public function deleteMediaBulk($ids)
    {
        if (!is_array($ids) || empty($ids)) {
            throw new Exception('No se proporcionaron IDs para eliminar');
        }

        $success_count = 0;
        $failed_count = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                $this->deleteMedia($id);
                $success_count++;
            } catch (Exception $e) {
                $failed_count++;
                $errors[] = "ID $id: " . $e->getMessage();
            }
        }

        return [
            'success' => true,
            'message' => "Eliminación masiva completada: $success_count eliminados, $failed_count fallidos",
            'stats' => [
                'deleted' => $success_count,
                'failed' => $failed_count,
                'errors' => $errors
            ]
        ];
    }

    public function getStats()
    {
        $stats = [];

        $result = $this->db->query("SELECT COUNT(*) as total FROM media");
        $stats['total_media'] = $result->fetch_assoc()['total'];

        $result = $this->db->query("SELECT file_type, COUNT(*) as count FROM media GROUP BY file_type");
        $stats['by_type'] = [];
        while ($row = $result->fetch_assoc()) {
            $stats['by_type'][$row['file_type']] = $row['count'];
        }

        $result = $this->db->query("SELECT SUM(file_size) as total_size FROM media");
        $stats['total_size'] = $result->fetch_assoc()['total_size'] ?? 0;

        $result = $this->db->query("SELECT COUNT(*) as count FROM media WHERE is_favorite = 1");
        $stats['favorites'] = $result->fetch_assoc()['count'];

        return ['success' => true, 'data' => $stats];
    }



    /**
     * Determinar tipo de archivo (incluye AUDIO)
     */
    private function getFileType($mime_type, $extension)
    {
        // Audios
        $audio_extensions = ['mp3', 'wav', 'ogg', 'm4a', 'aac', 'flac', 'wma', 'opus'];
        if (strpos($mime_type, 'audio/') !== false || in_array($extension, $audio_extensions)) {
            return 'audio';
        }

        // GIFs
        if (strpos($mime_type, 'image/gif') !== false || $extension === 'gif') {
            return 'gif';
        }

        // Imágenes
        if (strpos($mime_type, 'image/') !== false) {
            return 'image';
        }

        // Videos
        if (strpos($mime_type, 'video/') !== false) {
            return 'video';
        }

        return null;
    }

    /**
     * Extraer TODOS los metadatos posibles
     * - Fotos: EXIF completo
     * - Videos: FFmpeg completo
     * - GIFs: FFmpeg (tratados como videos)
     * - Audios: FFmpeg (duración) + fecha actual
     */
    private function extractMetadata($file_path, $file_type, $mime_type, $fallbackDate = null)
    {
        $metadata = [
            'file_size' => filesize($file_path),
            'width' => null,
            'height' => null,
            'duration' => null,
            'date_taken' => null,
            'camera_make' => null,
            'camera_model' => null,
            'focal_length' => null,
            'aperture' => null,
            'iso' => null,
            'exposure_time' => null,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'gps_altitude' => null,
            'location_name' => null
        ];

        if ($file_type === 'image') {
            // FOTOS: Extraer con EXIF
            $image_info = @getimagesize($file_path);
            if ($image_info) {
                $metadata['width'] = $image_info[0];
                $metadata['height'] = $image_info[1];
            }

            if (function_exists('exif_read_data') && in_array($mime_type, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'])) {
                $exif = @exif_read_data($file_path);
                if ($exif) {
                    // Fecha de captura
                    if (isset($exif['DateTimeOriginal'])) {
                        $metadata['date_taken'] = date('Y-m-d H:i:s', strtotime($exif['DateTimeOriginal']));
                    } elseif (isset($exif['DateTime'])) {
                        $metadata['date_taken'] = date('Y-m-d H:i:s', strtotime($exif['DateTime']));
                    }

                    // Cámara
                    $metadata['camera_make'] = $exif['Make'] ?? null;
                    $metadata['camera_model'] = $exif['Model'] ?? null;

                    // Configuración
                    if (isset($exif['FocalLength'])) {
                        $metadata['focal_length'] = $this->formatFraction($exif['FocalLength']) . 'mm';
                    }
                    if (isset($exif['FNumber'])) {
                        $metadata['aperture'] = 'f/' . $this->formatFraction($exif['FNumber']);
                    }
                    if (isset($exif['ISOSpeedRatings'])) {
                        $metadata['iso'] = 'ISO ' . $exif['ISOSpeedRatings'];
                    }
                    if (isset($exif['ExposureTime'])) {
                        $metadata['exposure_time'] = $this->formatFraction($exif['ExposureTime']) . 's';
                    }

                    // GPS
                    if (isset($exif['GPSLatitude']) && isset($exif['GPSLongitude'])) {
                        $metadata['gps_latitude'] = $this->getGps($exif['GPSLatitude'], $exif['GPSLatitudeRef']);
                        $metadata['gps_longitude'] = $this->getGps($exif['GPSLongitude'], $exif['GPSLongitudeRef']);
                    }
                    if (isset($exif['GPSAltitude'])) {
                        $metadata['gps_altitude'] = $this->formatFraction($exif['GPSAltitude']);
                    }
                }
            }

            // Si EXIF falló o no dio fecha, intentar con FFmpeg (bueno para PNGs y metadata rara)
            if (empty($metadata['date_taken'])) {
                $ffmpeg_metadata = $this->extractFFmpegMetadata($file_path);
                if ($ffmpeg_metadata) {
                    // Solo sobrescribir si encontramos algo y no teníamos valores
                    foreach ($ffmpeg_metadata as $key => $value) {
                        if (empty($metadata[$key]) && !empty($value)) {
                            $metadata[$key] = $value;
                        }
                    }
                }
            }

        } elseif ($file_type === 'gif' || $file_type === 'video') {
            // GIFs y VIDEOS: Extraer con FFmpeg
            // Esto permite que los GIFs también tengan metadatos cronológicos si están disponibles
            $ffmpeg_metadata = $this->extractFFmpegMetadata($file_path);

            if ($ffmpeg_metadata) {
                $metadata = array_merge($metadata, $ffmpeg_metadata);
                error_log("Metadatos FFmpeg extraídos para $file_type");
            } else {
                // Fallback: fecha del archivo
                $metadata['date_taken'] = date('Y-m-d H:i:s', filemtime($file_path));
                error_log("FFmpeg no disponible o falló para $file_type, usando fecha de archivo");
            }
        } elseif ($file_type === 'audio') {
            // AUDIOS: usar fallback_date si viene de la app, si no la fecha actual
            $metadata['date_taken'] = $fallbackDate
                ? date('Y-m-d H:i:s', strtotime($fallbackDate))
                : date('Y-m-d H:i:s');

            // Si se desea mantener la duración en el futuro, se podría descomentar:
            /*
            $ffmpeg_metadata = $this->extractFFmpegMetadata($file_path);
            if ($ffmpeg_metadata && isset($ffmpeg_metadata['duration'])) {
                $metadata['duration'] = $ffmpeg_metadata['duration'];
            }
            */
        }

        // Si no hay fecha: usar fallback_date (enviado por la app si el archivo no tenía EXIF)
        // o en último caso la fecha de modificación del archivo en el servidor
        if (!$metadata['date_taken']) {
            if ($fallbackDate) {
                $ts = strtotime($fallbackDate);
                $metadata['date_taken'] = $ts ? date('Y-m-d H:i:s', $ts) : date('Y-m-d H:i:s', filemtime($file_path));
            } else {
                $metadata['date_taken'] = date('Y-m-d H:i:s', filemtime($file_path));
            }
        }

        return $metadata;
    }

    /**
     * Extraer metadatos con FFmpeg (para videos, GIFs y audios)
     */
    private function extractFFmpegMetadata($file_path)
    {
        $ffprobe_path = $this->findFFprobe();

        if (!$ffprobe_path) {
            error_log("FFprobe no encontrado");
            return null;
        }

        $command = '"' . $ffprobe_path . '" -v quiet -print_format json -show_format -show_streams "' . $file_path . '" 2>&1';
        $output = shell_exec($command);

        if (!$output) {
            return null;
        }

        $data = json_decode($output, true);
        if (!$data) {
            return null;
        }

        $metadata = [
            'width' => null,
            'height' => null,
            'duration' => null,
            'date_taken' => null,
            'camera_make' => null,
            'camera_model' => null,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'gps_altitude' => null,
            'location_name' => null
        ];

        $extra_info = [];

        // Stream de video
        if (isset($data['streams'])) {
            foreach ($data['streams'] as $stream) {
                if ($stream['codec_type'] === 'video') {
                    $metadata['width'] = $stream['width'] ?? null;
                    $metadata['height'] = $stream['height'] ?? null;

                    // FPS
                    if (isset($stream['r_frame_rate'])) {
                        $fps_parts = explode('/', $stream['r_frame_rate']);
                        if (count($fps_parts) == 2 && $fps_parts[1] != 0) {
                            $fps = round($fps_parts[0] / $fps_parts[1], 2);
                            $extra_info[] = $fps . ' fps';
                        }
                    }

                    // Códec
                    if (isset($stream['codec_name'])) {
                        $extra_info[] = 'Códec: ' . strtoupper($stream['codec_name']);
                    }

                    // Rotation
                    if (isset($stream['tags']['rotate']) && $stream['tags']['rotate'] != 0) {
                        $extra_info[] = 'Rotación: ' . $stream['tags']['rotate'] . '°';
                    }

                    // Aspect Ratio
                    if (isset($stream['display_aspect_ratio'])) {
                        $extra_info[] = $stream['display_aspect_ratio'];
                    }

                    // GPS del stream
                    if (isset($stream['tags']['location'])) {
                        if (preg_match('/([+-]?\d+\.\d+)([+-]\d+\.\d+)/', $stream['tags']['location'], $matches)) {
                            $metadata['gps_latitude'] = floatval($matches[1]);
                            $metadata['gps_longitude'] = floatval($matches[2]);
                        }
                    }

                    break;
                }
            }
        }

        // Formato
        if (isset($data['format'])) {
            $format = $data['format'];

            // Duración
            if (isset($format['duration'])) {
                $metadata['duration'] = round($format['duration'], 2);
            }

            // Bitrate
            if (isset($format['bit_rate'])) {
                $bitrate_mbps = round($format['bit_rate'] / 1000000, 1);
                $extra_info[] = $bitrate_mbps . ' Mbps';
            }

            // Tags
            if (isset($format['tags'])) {
                $tags = $format['tags'];

                // Fecha de creación
                if (isset($tags['creation_time'])) {
                    $metadata['date_taken'] = date('Y-m-d H:i:s', strtotime($tags['creation_time']));
                } elseif (isset($tags['date'])) {
                    $metadata['date_taken'] = date('Y-m-d H:i:s', strtotime($tags['date']));
                }

                // Dispositivo
                if (isset($tags['make'])) {
                    $metadata['camera_make'] = $tags['make'];
                }
                if (isset($tags['model'])) {
                    $metadata['camera_model'] = $tags['model'];
                }

                // GPS (formato Apple QuickTime)
                if (isset($tags['com.apple.quicktime.location.ISO6709'])) {
                    if (preg_match('/([+-]\d+\.\d+)([+-]\d+\.\d+)([+-]\d+\.\d+)?/', $tags['com.apple.quicktime.location.ISO6709'], $matches)) {
                        $metadata['gps_latitude'] = floatval($matches[1]);
                        $metadata['gps_longitude'] = floatval($matches[2]);
                        if (isset($matches[3])) {
                            $metadata['gps_altitude'] = floatval($matches[3]);
                        }
                    }
                }

                // GPS (formato estándar)
                if (isset($tags['location']) && !$metadata['gps_latitude']) {
                    if (preg_match('/([+-]?\d+\.\d+)([+-]\d+\.\d+)/', $tags['location'], $matches)) {
                        $metadata['gps_latitude'] = floatval($matches[1]);
                        $metadata['gps_longitude'] = floatval($matches[2]);
                    }
                }
            }
        }

        // Guardar info técnica en location_name si no hay GPS
        if (!empty($extra_info) && !$metadata['gps_latitude']) {
            $metadata['location_name'] = implode(' | ', $extra_info);
        }

        return $metadata;
    }

    /**
     * Generar miniatura (incluye audios con icono de música)
     */
    private function generateThumbnail($file_path, $file_type, $filename)
    {
        $thumbnails_dir = __DIR__ . '/../uploads/thumbnails';
        if (!is_dir($thumbnails_dir)) {
            mkdir($thumbnails_dir, 0777, true);
        }

        $thumbnail_filename = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
        $thumbnail_path = $thumbnails_dir . '/' . $thumbnail_filename;

        if ($file_type === 'image') {
            // Miniaturas de imágenes con GD
            if (!function_exists('imagecreatefromjpeg')) {
                return null;
            }

            $image_info = @getimagesize($file_path);
            if (!$image_info) {
                return null;
            }

            $mime_type = $image_info['mime'];
            $source = null;

            switch ($mime_type) {
                case 'image/jpeg':
                case 'image/jpg':
                    $source = @imagecreatefromjpeg($file_path);
                    break;
                case 'image/png':
                    $source = @imagecreatefrompng($file_path);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $source = @imagecreatefromwebp($file_path);
                    }
                    break;
            }

            if (!$source) {
                return null;
            }

            $width = imagesx($source);
            $height = imagesy($source);

            $ratio = min(THUMBNAIL_WIDTH / $width, THUMBNAIL_HEIGHT / $height);
            $new_width = round($width * $ratio);
            $new_height = round($height * $ratio);

            $thumbnail = imagecreatetruecolor($new_width, $new_height);
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);

            imagejpeg($thumbnail, $thumbnail_path, THUMBNAIL_QUALITY);
            imagedestroy($source);
            imagedestroy($thumbnail);

            return $thumbnail_path;
        } elseif ($file_type === 'gif') {
            // Miniaturas de GIFs con FFmpeg
            $ffmpeg_path = $this->findFFmpeg();
            if (!$ffmpeg_path) {
                return null;
            }

            $command = '"' . $ffmpeg_path . '" -i "' . $file_path . '" -vf "scale=' . THUMBNAIL_WIDTH . ':' . THUMBNAIL_HEIGHT . ':force_original_aspect_ratio=decrease" -vframes 1 "' . $thumbnail_path . '" 2>&1';
            $output = shell_exec($command);

            if (file_exists($thumbnail_path) && filesize($thumbnail_path) > 0) {
                return $thumbnail_path;
            }

            return null;
        } elseif ($file_type === 'video') {
            // Miniaturas de videos con FFmpeg
            $ffmpeg_path = $this->findFFmpeg();
            if (!$ffmpeg_path) {
                return null;
            }

            $command = '"' . $ffmpeg_path . '" -i "' . $file_path . '" -ss 00:00:01 -vf "scale=' . THUMBNAIL_WIDTH . ':' . THUMBNAIL_HEIGHT . ':force_original_aspect_ratio=decrease" -vframes 1 "' . $thumbnail_path . '" 2>&1';
            $output = shell_exec($command);

            if (file_exists($thumbnail_path) && filesize($thumbnail_path) > 0) {
                return $thumbnail_path;
            }

            return null;
        } elseif ($file_type === 'audio') {
            // Miniaturas de audio: icono de música
            $thumbnail = imagecreatetruecolor(THUMBNAIL_WIDTH, THUMBNAIL_HEIGHT);

            // Gradiente de fondo
            for ($i = 0; $i < THUMBNAIL_HEIGHT; $i++) {
                $r = 102 + ($i / THUMBNAIL_HEIGHT) * (118 - 102);
                $g = 126 + ($i / THUMBNAIL_HEIGHT) * (75 - 126);
                $b = 234 + ($i / THUMBNAIL_HEIGHT) * (162 - 234);
                $color = imagecolorallocate($thumbnail, $r, $g, $b);
                imageline($thumbnail, 0, $i, THUMBNAIL_WIDTH, $i, $color);
            }

            // Icono de música (nota musical)
            $white = imagecolorallocate($thumbnail, 255, 255, 255);
            imagesetthickness($thumbnail, 8);

            $center_x = THUMBNAIL_WIDTH / 2;
            $center_y = THUMBNAIL_HEIGHT / 2;

            // Dibujar nota musical
            imageellipse($thumbnail, $center_x - 20, $center_y + 20, 30, 30, $white);
            imageline($thumbnail, $center_x - 5, $center_y + 5, $center_x - 5, $center_y - 40, $white);
            imagearc($thumbnail, $center_x + 10, $center_y - 35, 30, 20, 180, 0, $white);

            imagejpeg($thumbnail, $thumbnail_path, THUMBNAIL_QUALITY);
            imagedestroy($thumbnail);

            return $thumbnail_path;
        }

        return null;
    }

    private function findFFmpeg()
    {
        $possible_paths = [
            'ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg'
        ];

        foreach ($possible_paths as $path) {
            $test = shell_exec('"' . $path . '" -version 2>&1');
            if ($test && strpos($test, 'ffmpeg version') !== false) {
                return $path;
            }
        }

        return null;
    }

    private function findFFprobe()
    {
        $possible_paths = [
            'ffprobe',
            'C:\\ffmpeg\\bin\\ffprobe.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffprobe.exe',
            '/usr/bin/ffprobe',
            '/usr/local/bin/ffprobe'
        ];

        foreach ($possible_paths as $path) {
            $test = shell_exec('"' . $path . '" -version 2>&1');
            if ($test && strpos($test, 'ffprobe version') !== false) {
                return $path;
            }
        }

        return null;
    }

    private function formatFraction($fraction)
    {
        if (is_string($fraction) && strpos($fraction, '/') !== false) {
            $parts = explode('/', $fraction);
            if (count($parts) == 2 && $parts[1] != 0) {
                return round($parts[0] / $parts[1], 2);
            }
        }
        return $fraction;
    }

    private function getGps($exifCoord, $hemi)
    {
        $degrees = count($exifCoord) > 0 ? $this->formatFraction($exifCoord[0]) : 0;
        $minutes = count($exifCoord) > 1 ? $this->formatFraction($exifCoord[1]) : 0;
        $seconds = count($exifCoord) > 2 ? $this->formatFraction($exifCoord[2]) : 0;

        $flip = ($hemi == 'W' or $hemi == 'S') ? -1 : 1;

        return $flip * ($degrees + $minutes / 60 + $seconds / 3600);
    }
}