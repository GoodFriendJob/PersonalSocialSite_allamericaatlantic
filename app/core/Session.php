<?php
/**
 * Single place where the session cookie is configured and started.
 *
 * Every entry point (API front controller, page scripts) must call
 * Session::start() before touching $_SESSION, so the cookie flags stay
 * consistent across the app.
 */

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,   // only send over TLS when the site is on TLS
            'httponly' => true,     // not readable from JavaScript
            'samesite' => 'Lax',    // blocks cross-site POSTs carrying the cookie
        ]);

        session_start();
    }

    /** Current user id, or null when logged out. */
    public static function userId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /** Called on successful login. Rotates the id to prevent session fixation. */
    public static function login(int $userId): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }

        session_destroy();
    }
}
