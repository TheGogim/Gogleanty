<?php
/**
 * API de Autenticación
 * Endpoints: /api/auth/login, /api/auth/logout, /api/auth/check
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/AuthController.php';

$auth = new AuthController();
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['action'] ?? '';

try {
    switch ($path) {
        case 'login':
            if ($method !== 'POST') {
                throw new Exception('Método no permitido');
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $username = $data['username'] ?? '';
            $password = $data['password'] ?? '';

            if (empty($username) || empty($password)) {
                throw new Exception('Usuario y contraseña son requeridos');
            }

            $result = $auth->login($username, $password);
            echo json_encode($result);
            break;

        case 'logout':
            $result = $auth->logout();
            echo json_encode($result);
            break;

        case 'check':
            $isAuth = $auth->isAuthenticated();
            $user = $isAuth ? $auth->getCurrentUser() : null;

            echo json_encode([
                'success' => true,
                'authenticated' => $isAuth,
                'user' => $user
            ]);
            break;

        case 'user':
            if (!$auth->isAuthenticated()) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'No autenticado'
                ]);
                break;
            }

            $user = $auth->getCurrentUser();
            echo json_encode([
                'success' => true,
                'user' => $user
            ]);
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