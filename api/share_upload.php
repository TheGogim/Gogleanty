<?php
/**
 * API de Subida para Usuarios Colaboradores
 * Permite subir archivos a un álbum compartido si se tiene permiso
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ShareController.php';
require_once __DIR__ . '/MediaController.php';
require_once __DIR__ . '/AlbumController.php';

// Iniciar sesión (para verificar usuario logueado)
// Nota: AuthController inicia la sesión en su constructor
require_once __DIR__ . '/AuthController.php';
$auth = new AuthController();

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $token = $_POST['token'] ?? '';

    if (empty($token)) {
        throw new Exception('Token es requerido');
    }

    // Verificar permisos del share
    $shareController = new ShareController();

    // Obtenemos los datos del share, incluyendo permisos para el usuario actual
    // getSharedAlbum verifica si hay sesión activa y si el email coincide
    $password = $_POST['password'] ?? null;
    $shareData = $shareController->getSharedAlbum($token, $password);

    if (!$shareData['permissions']['can_upload']) {
        throw new Exception('No tienes permiso para subir archivos a este álbum');
    }

    // Si llegamos aquí, el usuario está autorizado
    $albumId = $shareData['album']['id'];

    // Verificar tipo de álbum para is_hidden
    $albumController = new AlbumController();
    $albumInfo = $albumController->getAlbum($albumId);
    $isHidden = ($albumInfo['success'] && isset($albumInfo['data']['type']) && $albumInfo['data']['type'] === 'private') ? 1 : 0;

    // Procesar subida
    $mediaController = new MediaController();

    // Usamos el método existente uploadMedia con el flag isHidden
    $uploadResult = $mediaController->uploadMedia($_FILES['file'] ?? null, $isHidden); // Ajustado para recibir null si no hay archivo y que lance su propia excepción

    if ($uploadResult['success']) {
        // Añadir al álbum
        $albumController = new AlbumController();
        $albumController->addMediaToAlbum($albumId, $uploadResult['media_id']);

        echo json_encode([
            'success' => true,
            'message' => 'Archivo subido correctamente al álbum compartido',
            'media' => $uploadResult['data']
        ]);
    } else {
        throw new Exception('Error desconocido al subir archivo');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>