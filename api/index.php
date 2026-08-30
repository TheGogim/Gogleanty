<?php
/**
 * API Principal - Enrutador de endpoints
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/MediaController.php';
require_once __DIR__ . '/AlbumController.php';

require_once __DIR__ . '/middleware.php';

// Proteger todas las rutas del API (excepto auth)
$request_uri = $_SERVER['REQUEST_URI'];
if (strpos($request_uri, '/api/auth.php') === false && strpos($request_uri, '/api/mobile_auth.php') === false) {
    requireAuthMobile();
}

// Obtener el método HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Obtener la ruta solicitada
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = dirname($_SERVER['SCRIPT_NAME']);
$path = str_replace($script_name, '', $request_uri);
$path = trim(parse_url($path, PHP_URL_PATH), '/');
$path = str_replace('api/', '', $path);

// Dividir la ruta en segmentos
$segments = explode('/', $path);
$endpoint = $segments[0] ?? '';

// Función para enviar respuesta JSON
function sendResponse($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

// Función para manejar errores
function sendError($message, $status = 400)
{
    sendResponse(['error' => $message, 'success' => false], $status);
}

try {
    // Inicializar controladores
    $mediaController = new MediaController();
    $albumController = new AlbumController();

    // Enrutamiento
    switch ($endpoint) {
        case 'media':
            switch ($method) {
                case 'GET':
                    if (isset($segments[1]) && is_numeric($segments[1])) {
                        $result = $mediaController->getMedia($segments[1]);
                    } elseif (isset($segments[1]) && $segments[1] === 'hashes') {
                        // GET media/hashes → lista todos los device_file_hash
                        // Usada por la app móvil para detectar qué ya está subido
                        $result = $mediaController->getAllHashes();
                    } else {
                        $page = $_GET['page'] ?? 1;
                        $limit = $_GET['limit'] ?? ITEMS_PER_PAGE;
                        $type = $_GET['type'] ?? null;
                        $favorite = $_GET['favorite'] ?? null;
                        $year = $_GET['year'] ?? null;
                        $month = $_GET['month'] ?? null;
                        $result = $mediaController->getAllMedia($page, $limit, $type, $favorite, $year, $month);
                    }
                    sendResponse($result);
                    break;

                case 'POST':
                    if (isset($segments[1]) && $segments[1] === 'bulk-delete') {
                        $data = json_decode(file_get_contents('php://input'), true);
                        if (!isset($data['ids']) || !is_array($data['ids'])) {
                            sendError('Se requiere un array de IDs en "ids"', 400);
                        }
                        $result = $mediaController->deleteMediaBulk($data['ids']);
                        sendResponse($result);
                    } else {
                        // Campos opcionales enviados por la app móvil
                        $deviceFileHash = isset($_POST['device_file_hash']) && strlen($_POST['device_file_hash']) === 32
                            ? $_POST['device_file_hash']
                            : null;
                        $fallbackDate = isset($_POST['fallback_date']) && strtotime($_POST['fallback_date'])
                            ? $_POST['fallback_date']
                            : null;
                        $result = $mediaController->uploadMedia($_FILES['file'] ?? null, 0, $deviceFileHash, $fallbackDate);
                        sendResponse($result, 201);
                    }
                    break;

                case 'PUT':
                    if (isset($segments[1]) && is_numeric($segments[1])) {
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = $mediaController->updateMedia($segments[1], $data);
                        sendResponse($result);
                    } else {
                        sendError('ID de medio no especificado', 400);
                    }
                    break;

                case 'DELETE':
                    if (isset($segments[1]) && is_numeric($segments[1])) {
                        $result = $mediaController->deleteMedia($segments[1]);
                        sendResponse($result);
                    } else {
                        // POST/DELETE bulk is safer via POST, checking special segment
                        if (isset($segments[1]) && $segments[1] === 'bulk-delete') {
                            $data = json_decode(file_get_contents('php://input'), true);
                            if (!isset($data['ids']) || !is_array($data['ids'])) {
                                sendError('Se requiere un array de IDs en "ids"', 400);
                            }
                            $result = $mediaController->deleteMediaBulk($data['ids']);
                            sendResponse($result);
                        } else {
                            sendError('ID de medio no especificado', 400);
                        }
                    }
                    break;

                default:
                    sendError('Método no permitido', 405);
            }
            break;

        case 'albums':
            switch ($method) {
                case 'GET':
                    if (isset($segments[1]) && is_numeric($segments[1])) {
                        if (isset($segments[2]) && $segments[2] === 'media') {
                            // GET albums/{id}/media
                            $result = $albumController->getAlbumMedia($segments[1]);
                        } else {
                            // GET albums/{id}
                            $result = $albumController->getAlbum($segments[1]);
                        }
                    } else {
                        // GET albums
                        $result = $albumController->getAllAlbums();
                    }
                    sendResponse($result);
                    break;

                case 'POST':
                    if (isset($segments[1]) && is_numeric($segments[1])) {
                        if (isset($segments[2]) && $segments[2] === 'media') {
                            // POST albums/{id}/media
                            $data = json_decode(file_get_contents('php://input'), true);
                            if (!isset($data['media_id'])) {
                                sendError('media_id es requerido', 400);
                            }
                            $result = $albumController->addMediaToAlbum($segments[1], $data['media_id']);
                            sendResponse($result, 201);
                        } elseif (isset($segments[2]) && $segments[2] === 'add-bulk') {
                            // POST albums/{id}/add-bulk
                            $data = json_decode(file_get_contents('php://input'), true);
                            if (!isset($data['media_ids']) || !is_array($data['media_ids'])) {
                                sendError('media_ids es requerido y debe ser un array', 400);
                            }
                            $result = $albumController->addMediaBulk($segments[1], $data['media_ids']);
                            sendResponse($result);
                        }
                    } else {
                        // POST albums
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = $albumController->createAlbum($data);
                        sendResponse($result, 201);
                    }
                    break;

                case 'PUT':
                    if (isset($segments[1]) && is_numeric($segments[1])) {
                        $data = json_decode(file_get_contents('php://input'), true);
                        $result = $albumController->updateAlbum($segments[1], $data);
                        sendResponse($result);
                    } else {
                        sendError('ID de álbum no especificado', 400);
                    }
                    break;

                case 'DELETE':
                    if (isset($segments[1]) && is_numeric($segments[1])) {
                        if (isset($segments[2]) && $segments[2] === 'media' && isset($segments[3]) && is_numeric($segments[3])) {
                            // DELETE albums/{id}/media/{media_id}
                            $result = $albumController->removeMediaFromAlbum($segments[1], $segments[3]);
                        } else {
                            // DELETE albums/{id}
                            $result = $albumController->deleteAlbum($segments[1]);
                        }
                        sendResponse($result);
                    } else {
                        sendError('ID de álbum no especificado', 400);
                    }
                    break;

                default:
                    sendError('Método no permitido', 405);
            }
            break;

        case 'timeline':
            $result = $mediaController->getTimeline();
            sendResponse($result);
            break;

        case 'search':
            $query = $_GET['q'] ?? '';
            $result = $mediaController->searchMedia($query);
            sendResponse($result);
            break;

        case 'stats':
            $result = $mediaController->getStats();
            sendResponse($result);
            break;

        default:
            sendError('Endpoint no encontrado', 404);
    }

} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}