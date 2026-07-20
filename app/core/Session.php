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
    /** True once the session has been read into $_SESSION this request. */
    private static $loaded = false;

    /** Applies the cookie flags. Must run before session_start(). */
    private static function configureCookie(): void
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $https,   // only send over TLS when the site is on TLS
            'httponly' => true,     // not readable from JavaScript
            'samesite' => 'Lax',    // blocks cross-site POSTs carrying the cookie
        ]);
    }

    /**
     * Opens the session for writing and HOLDS the lock until the request ends.
     * Only for requests that actually modify $_SESSION — login and logout.
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::configureCookie();
        session_start();
        self::$loaded = true;
    }

    /**
     * Reads the session, then immediately releases the lock.
     *
     * PHP's file session handler holds an exclusive lock on the session file
     * for the whole request. app.php fires six API calls at once, and every
     * one of them starts a session, so they queue behind each other instead of
     * running concurrently — measurably about 2x the wall-clock time locally,
     * and far worse in production where each request also opens its own
     * connection to a remote database.
     *
     * Closing the session immediately drops that lock. $_SESSION stays
     * populated and readable for the rest of the request; it just will not
     * persist writes, which is exactly right for endpoints that only need to
     * know who is logged in. Anything that writes must call start() again,
     * which re-acquires the lock — see login() and logout().
     */
    public static function startReadOnly(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Started read-write elsewhere; release the lock but keep the data.
            session_write_close();
            self::$loaded = true;
            return;
        }

        if (self::$loaded) {
            return; // already read and closed this request
        }

        self::configureCookie();
        session_start();
        session_write_close();
        self::$loaded = true;
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
