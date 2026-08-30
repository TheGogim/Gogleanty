<?php
/**
 * API de Compartir Álbumes
 * Endpoints públicos (sin autenticación para vista) y privados (con auth para crear/eliminar)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ShareController.php';
require_once __DIR__ . '/middleware.php';

$shareController = new ShareController();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            // Requiere autenticación
            requireAuth();

            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $albumId = $data['album_id'] ?? null;
            $expiresInDays = $data['expires_in_days'] ?? null;
            $allowUpload = $data['allow_upload'] ?? false; // Nuevo parámetro

            if (!$albumId) {
                throw new Exception('album_id es requerido');
            }

            $result = $shareController->createShare($albumId, $expiresInDays, $allowUpload);
            echo json_encode($result);
            break;

        case 'update_config':
            // Requiere autenticación
            requireAuth();

            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $albumId = $data['album_id'] ?? null;
            $allowUpload = $data['allow_upload'] ?? false;
            $emails = $data['emails'] ?? [];

            if (!$albumId) {
                throw new Exception('album_id es requerido');
            }

            $result = $shareController->updateShareConfig($albumId, $allowUpload, $emails);
            echo json_encode($result);
            break;

        case 'get':
            // Público - no requiere autenticación
            if ($method !== 'GET') {
                throw new Exception('Método no permitido');
            }

            $token = $_GET['token'] ?? '';

            if (empty($token)) {
                throw new Exception('Token es requerido');
            }

            $result = $shareController->getSharedAlbum($token);
            echo json_encode($result);
            break;

        case 'delete':
            // Requiere autenticación
            requireAuth();

            if ($method !== 'DELETE' && $method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $albumId = $data['album_id'] ?? null;

            if (!$albumId) {
                throw new Exception('album_id es requerido');
            }

            $result = $shareController->deleteShare($albumId);
            echo json_encode($result);
            break;

        case 'info':
            // Requiere autenticación
            requireAuth();

            if ($method !== 'GET') {
                throw new Exception('Método no permitido');
            }

            $albumId = $_GET['album_id'] ?? null;

            if (!$albumId) {
                throw new Exception('album_id es requerido');
            }

            $result = $shareController->getAlbumShare($albumId);
            echo json_encode($result);
            break;

        case 'cleanup':
            // Requiere autenticación
            requireAuth();

            $result = $shareController->cleanupExpiredShares();
            echo json_encode($result);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Endpoint no encontrado'
            ]);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>