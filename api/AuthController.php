<?php
/**
 * Controlador de Autenticación
 * Maneja login, logout, sesiones y seguridad
 */

class AuthController
{
    private $db;
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_TIME = 900; // 15 minutos en segundos
    private const SESSION_LIFETIME = 86400; // 24 horas

    public function __construct()
    {
        $this->db = getDBConnection();
        $this->startSecureSession();
    }

    /**
     * Iniciar sesión segura
     */
    private function startSecureSession()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Configuración segura de sesiones
            ini_set('session.cookie_httponly', 1);
            ini_set('session.use_only_cookies', 1);
            ini_set('session.cookie_secure', 0); // Cambiar a 1 si usas HTTPS
            ini_set('session.cookie_samesite', 'Strict');

            session_name('GOGLEANTY_SESSION');
            session_start();

            // Regenerar ID de sesión periódicamente
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }

    /**
     * Login de usuario
     */
    public function login($usernameOrEmail, $password)
    {
        $ip = $this->getClientIP();

        // Verificar intentos de login
        if ($this->isLockedOut($ip)) {
            return [
                'success' => false,
                'message' => 'Demasiados intentos fallidos. Intenta de nuevo en 15 minutos.',
                'locked' => true
            ];
        }

        // Buscar usuario por username o email
        $stmt = $this->db->prepare("SELECT id, username, email, password_hash, role FROM user WHERE username = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $usernameOrEmail, $usernameOrEmail);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->recordLoginAttempt($ip, false);
            return [
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ];
        }

        $user = $result->fetch_assoc();

        // Verificar contraseña
        if (!password_verify($password, $user['password_hash'])) {
            $this->recordLoginAttempt($ip, false);
            return [
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ];
        }

        // Login exitoso
        $this->recordLoginAttempt($ip, true);
        $this->createSession($user['id']);

        return [
            'success' => true,
            'message' => 'Bienvenido a Gogleanty',
            'user' => [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ]
        ];
    }

    /**
     * Crear sesión de usuario
     */
    private function createSession($userId)
    {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::SESSION_LIFETIME);
        $ip = $this->getClientIP();
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Guardar en BD
        $stmt = $this->db->prepare("
            INSERT INTO sessions (id, user_id, expires_at, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sisss", $sessionId, $userId, $expiresAt, $ip, $userAgent);
        $stmt->execute();

        // Guardar en sesión PHP
        $_SESSION['user_id'] = $userId;
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();

        // Obtener rol del usuario
        $rStmt = $this->db->prepare("SELECT role FROM user WHERE id = ?");
        $rStmt->bind_param("i", $userId);
        $rStmt->execute();
        $rRes = $rStmt->get_result();
        if ($rRes->num_rows > 0) {
            $_SESSION['user_role'] = $rRes->fetch_assoc()['role'];
        } else {
            $_SESSION['user_role'] = 'visitor';
        }

        // Limpiar sesiones antiguas del usuario
        $this->cleanupOldSessions($userId);
    }

    /**
     * Verificar si el usuario está autenticado
     */
    public function isAuthenticated()
    {
        if (!isset($_SESSION['authenticated']) || !isset($_SESSION['session_id'])) {
            return false;
        }

        // Verificar sesión en BD
        $sessionId = $_SESSION['session_id'];
        $stmt = $this->db->prepare("
            SELECT user_id, expires_at 
            FROM sessions 
            WHERE id = ? AND expires_at > NOW()
        ");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $this->logout();
            return false;
        }

        return true;
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        if (isset($_SESSION['session_id'])) {
            // Eliminar de BD
            $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = ?");
            $stmt->bind_param("s", $_SESSION['session_id']);
            $stmt->execute();
        }

        // Limpiar sesión PHP
        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();

        return ['success' => true, 'message' => 'Sesión cerrada'];
    }

    /**
     * Verificar si la IP está bloqueada
     */
    private function isLockedOut($ip)
    {
        $lockoutTime = date('Y-m-d H:i:s', time() - self::LOCKOUT_TIME);

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as attempts 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND attempted_at > ? 
            AND success = FALSE
        ");
        $stmt->bind_param("ss", $ip, $lockoutTime);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result['attempts'] >= self::MAX_LOGIN_ATTEMPTS;
    }

    /**
     * Registrar intento de login
     */
    private function recordLoginAttempt($ip, $success)
    {
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, success) 
            VALUES (?, ?)
        ");
        $successInt = $success ? 1 : 0;
        $stmt->bind_param("si", $ip, $successInt);
        $stmt->execute();

        // Limpiar intentos antiguos
        $this->cleanupOldAttempts();
    }

    /**
     * Limpiar intentos antiguos
     */
    private function cleanupOldAttempts()
    {
        $cutoff = date('Y-m-d H:i:s', time() - self::LOCKOUT_TIME);
        $stmt = $this->db->prepare("DELETE FROM login_attempts WHERE attempted_at < ?");
        $stmt->bind_param("s", $cutoff);
        $stmt->execute();
    }

    /**
     * Limpiar sesiones antiguas del usuario
     * IMPORTANTE: Solo limpia sesiones WEB (is_mobile = 0).
     * Los tokens móviles tienen 90 días de vida y son gestionados por mobile_auth.php
     */
    private function cleanupOldSessions($userId)
    {
        // Mantener solo las 3 sesiones WEB más recientes (NO tocar las móviles)
        $stmt = $this->db->prepare("
            DELETE FROM sessions 
            WHERE user_id = ? 
            AND is_mobile = 0
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM sessions 
                    WHERE user_id = ? AND is_mobile = 0
                    ORDER BY created_at DESC 
                    LIMIT 3
                ) AS recent
            )
        ");
        $stmt->bind_param("ii", $userId, $userId);
        $stmt->execute();

        // Eliminar SOLO sesiones WEB expiradas (is_mobile = 0)
        // Las sesiones móviles (is_mobile = 1) se gestionan desde mobile_auth.php
        $this->db->query("DELETE FROM sessions WHERE expires_at < NOW() AND is_mobile = 0");
    }

    /**
     * Obtener IP del cliente
     */
    private function getClientIP()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Verificar proxies
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    /**
     * Obtener información del usuario actual
     */
    public function getCurrentUser()
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = $_SESSION['user_id'];
        $stmt = $this->db->prepare("SELECT id, username, email, role, created_at FROM user WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }
}
?>