<?php
/**
 * Middleware de Autenticación
 * Protege las rutas que requieren login
 */

require_once __DIR__ . '/AuthController.php';

/**
 * Verificar autenticación
 * Redirige a login si no está autenticado
 */
function requireAuth()
{
    $auth = new AuthController();

    if (!$auth->isAuthenticated()) {
        // Si es una petición AJAX, devolver JSON
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'No autenticado',
                'redirect' => '/Gogleanty/login.html'
            ]);
            exit;
        }

        // Redirigir a login
        header('Location: /Gogleanty/login.html');
        exit;
    }

    return $auth;
}

/**
 * Verificar si es administrador
 */
function requireAdmin()
{
    $auth = requireAuth();
    $user = $auth->getCurrentUser();

    if ($user['role'] !== 'admin') {
        // Si es AJAX return JSON
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Acceso denegado: Se requieren permisos de administrador'
            ]);
            exit;
        }

        // Si no es AJAX, mostrar error o redirigir
        http_response_code(403);
        die('Acceso denegado. <a href="/Gogleanty/login.html">Volver</a>');
    }

    return $auth;
}

/**
 * Autenticación mixta: Bearer token (app móvil) o sesión PHP (web)
 * Úsala en lugar de requireAdmin() en index.php para que la app pueda acceder.
 * La web sigue funcionando exactamente igual que antes.
 */
function requireAuthMobile()
{
    // ── 1. Intentar Bearer token (petición desde la app móvil) ─────────────
    $authHeader = '';
    if (function_exists('getallheaders')) {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        $authHeader = $headers['authorization'] ?? '';
    }
    // Fallback para servidores que no populan getallheaders()
    if (empty($authHeader) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    }

    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        $token = trim($matches[1]);
        $db    = getDBConnection();

        $stmt = $db->prepare("
            SELECT s.user_id, u.username, u.email, u.role
            FROM sessions s
            JOIN user u ON s.user_id = u.id
            WHERE s.id = ? AND s.expires_at > NOW() AND s.is_mobile = 1
        ");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Token inválido o expirado. Vuelve a iniciar sesión en la app.'
            ]);
            exit;
        }

        // Inyectar en $_SESSION para que el resto del código funcione igual
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id']       = $row['user_id'];
        $_SESSION['user_role']     = $row['role'];

        // Retornar instancia compatible (sin rellamada a BD de sesión)
        return new AuthController();
    }

    // ── 2. Sin Bearer → flujo normal de sesión PHP (web) ──────────────────
    $auth = new AuthController();

    if (!$auth->isAuthenticated()) {
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode([
                'success'  => false,
                'message'  => 'No autenticado',
                'redirect' => '/Gogleanty/login.html'
            ]);
            exit;
        }

        header('Location: /Gogleanty/login.html');
        exit;
    }

    return $auth;
}

/**
 * Verificar token CSRF (para formularios)
 */
function verifyCsrfToken()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

        if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
            http_response_code(403);
            die(json_encode([
                'success' => false,
                'message' => 'Token CSRF inválido'
            ]));
        }
    }
}

/**
 * Generar token CSRF
 */
function generateCsrfToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Sanitizar entrada
 */
function sanitizeInput($data)
{
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar longitud de contraseña
 */
function validatePassword($password)
{
    if (strlen($password) < 8) {
        return ['valid' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres'];
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return ['valid' => false, 'message' => 'La contraseña debe contener al menos una mayúscula'];
    }

    if (!preg_match('/[a-z]/', $password)) {
        return ['valid' => false, 'message' => 'La contraseña debe contener al menos una minúscula'];
    }

    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'La contraseña debe contener al menos un número'];
    }

    return ['valid' => true];
}
?>