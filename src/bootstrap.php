<?php
// Loaded first by every page in public/: environment, config, database,
// session and auth helpers.
define('APP_ROOT', dirname(__DIR__));
define('SRC_PATH', __DIR__);
define('PUBLIC_PATH', APP_ROOT . '/public');

require_once SRC_PATH . '/env.php';
require_once SRC_PATH . '/config.php';
require_once SRC_PATH . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

require_once SRC_PATH . '/auth.php';

/**
 * Build a URL to a file inside public/, e.g. url('auth/login.php').
 * Works whether the site is served from public/ directly or through the
 * project-root .htaccess, and from pages in any subfolder.
 */
function url(string $path = ''): string {
    static $base = null;
    if ($base === null) {
        $scriptDir = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? '')) ?: '';
        $public    = realpath(PUBLIC_PATH) ?: PUBLIC_PATH;
        $rel       = trim(str_replace('\\', '/', substr($scriptDir, strlen($public))), '/');
        $depth     = $rel === '' ? 0 : substr_count($rel, '/') + 1;

        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        for ($i = 0; $i < $depth; $i++) {
            $base = str_replace('\\', '/', dirname($base));
        }
        $base = rtrim($base, '/');
    }
    return $base . '/' . ltrim($path, '/');
}

function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

_normalize_session_user($mysqli);
