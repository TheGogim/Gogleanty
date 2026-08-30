<?php
/**
 * API de autenticación para la App Móvil
 * Usa tokens Bearer persistentes (90 días) en vez de cookies de sesión PHP.
 * La web sigue usando su sistema de sesiones sin cambios.
 *
 * Endpoints:
 *   POST ?action=login   → { username, password } → { token, user }
 *   GET  ?action=check   → Header: Authorization: Bearer <token> → { valid, user }
 *   POST ?action=logout  → Header: Authorization: Bearer <token> → { success }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config.php';

// ── Constantes exclusivas para tokens móviles ──────────────────────────────
define('MOBILE_TOKEN_LIFETIME', 90 * 24 * 3600); // 90 días en segundos
define('MAX_LOGIN_ATTEMPTS_MOBILE', 5);
define('LOCKOUT_TIME_MOBILE', 900); // 15 minutos

// ── Helpers ────────────────────────────────────────────────────────────────

function getClientIP(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }
    return filter_var(trim($ip), FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

function getBearerToken(): ?string
{
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = getallheaders();
    }
    // Normalizar claves (algunos servidores las pasan en minúsculas)
    $normalized = array_change_key_case($headers, CASE_LOWER);
    $authHeader = $normalized['authorization'] ?? '';

    if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

function isLockedOutMobile(mysqli $db, string $ip): bool
{
    $cutoff = date('Y-m-d H:i:s', time() - LOCKOUT_TIME_MOBILE);
    $stmt = $db->prepare("
        SELECT COUNT(*) AS attempts
        FROM login_attempts
        WHERE ip_address = ? AND attempted_at > ? AND success = 0
    ");
    $stmt->bind_param("ss", $ip, $cutoff);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int)$row['attempts'] >= MAX_LOGIN_ATTEMPTS_MOBILE;
}

function recordLoginAttemptMobile(mysqli $db, string $ip, bool $success): void
{
    $stmt = $db->prepare("INSERT INTO login_attempts (ip_address, success) VALUES (?, ?)");
    $s = $success ? 1 : 0;
    $stmt->bind_param("si", $ip, $s);
    $stmt->execute();
    $stmt->close();

    // Limpiar intentos viejos
    $cutoff = date('Y-m-d H:i:s', time() - LOCKOUT_TIME_MOBILE);
    $db->query("DELETE FROM login_attempts WHERE attempted_at < '$cutoff'");
}

function sendJson(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Accion solicitada ──────────────────────────────────────────────────────
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

try {
    $db = getDBConnection();

    switch ($action) {

        // ── LOGIN ──────────────────────────────────────────────────────────
        case 'login':
            if ($method !== 'POST') {
                sendJson(['success' => false, 'message' => 'Método no permitido'], 405);
            }

            $body = json_decode(file_get_contents('php://input'), true) ?? [];
            $username = trim($body['username'] ?? '');
            $password  = $body['password']  ?? '';

            if (empty($username) || empty($password)) {
                sendJson(['success' => false, 'message' => 'Usuario y contraseña son requeridos'], 400);
            }

            $ip = getClientIP();

            if (isLockedOutMobile($db, $ip)) {
                sendJson([
                    'success' => false,
                    'message' => 'Demasiados intentos fallidos. Espera 15 minutos.',
                    'locked'  => true
                ], 429);
            }

            // Buscar usuario
            $stmt = $db->prepare("
                SELECT id, username, email, password_hash, role
                FROM user
                WHERE username = ? OR email = ?
                LIMIT 1
            ");
            $stmt->bind_param("ss", $username, $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                recordLoginAttemptMobile($db, $ip, false);
                sendJson(['success' => false, 'message' => 'Usuario o contraseña incorrectos'], 401);
            }

            recordLoginAttemptMobile($db, $ip, true);

            // Generar token
            $token     = bin2hex(random_bytes(32));
            $expiresAt = date('Y-m-d H:i:s', time() + MOBILE_TOKEN_LIFETIME);
            $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Android App', 0, 255);
            $isMobile  = 1;

            $stmt = $db->prepare("
                INSERT INTO sessions (id, user_id, expires_at, ip_address, user_agent, is_mobile)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sisssi", $token, $user['id'], $expiresAt, $ip, $userAgent, $isMobile);
            $stmt->execute();
            $stmt->close();

            sendJson([
                'success'    => true,
                'message'    => 'Login exitoso',
                'token'      => $token,
                'expires_at' => $expiresAt,
                'user' => [
                    'id'       => $user['id'],
                    'username' => $user['username'],
                    'email'    => $user['email'],
                    'role'     => $user['role'],
                ]
            ]);
            break;

        // ── CHECK TOKEN ────────────────────────────────────────────────────
        case 'check':
            $token = getBearerToken();

            if (!$token) {
                sendJson(['success' => false, 'valid' => false, 'message' => 'Token no proporcionado'], 401);
            }

            $stmt = $db->prepare("
                SELECT s.user_id, s.expires_at, u.username, u.email, u.role
                FROM sessions s
                JOIN user u ON s.user_id = u.id
                WHERE s.id = ? AND s.expires_at > NOW() AND s.is_mobile = 1
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$row) {
                sendJson(['success' => false, 'valid' => false, 'message' => 'Token inválido o expirado'], 401);
            }

            sendJson([
                'success' => true,
                'valid'   => true,
                'user' => [
                    'id'         => $row['user_id'],
                    'username'   => $row['username'],
                    'email'      => $row['email'],
                    'role'       => $row['role'],
                    'expires_at' => $row['expires_at'],
                ]
            ]);
            break;

        // ── LOGOUT ─────────────────────────────────────────────────────────
        case 'logout':
            if ($method !== 'POST') {
                sendJson(['success' => false, 'message' => 'Método no permitido'], 405);
            }

            $token = getBearerToken();

            if (!$token) {
                sendJson(['success' => false, 'message' => 'Token no proporcionado'], 400);
            }

            $stmt = $db->prepare("DELETE FROM sessions WHERE id = ? AND is_mobile = 1");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();

            sendJson([
                'success' => true,
                'message' => $affected > 0 ? 'Sesión cerrada' : 'Token no encontrado (ya expirado)'
            ]);
            break;

        default:
            sendJson(['success' => false, 'message' => 'Acción no válida. Usa: login, check, logout'], 404);
    }

} catch (Exception $e) {
    error_log("mobile_auth.php error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Error interno del servidor'], 500);
}
?>
