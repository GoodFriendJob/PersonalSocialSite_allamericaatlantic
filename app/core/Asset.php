<?php
/**
 * Builds URLs for local CSS/JS with a cache-busting version stamp.
 *
 * Without this, a browser keeps serving the copy it cached the first time.
 * After an edit or a deploy the markup and the script fall out of sync and the
 * page silently behaves like the old version — which looks exactly like a bug
 * in the new feature.
 *
 * The stamp is the file's modification time, so the URL changes only when the
 * file does, and unchanged assets stay cached.
 *
 *     <script src="<?= asset('assets/js/feed.js') ?>"></script>
 *     -> /PersonalSocialSite_allamericaatlantic/assets/js/feed.js?v=1784560012
 */

if (!function_exists('asset')) {

    function asset(string $path): string
    {
        static $base = null;

        if ($base === null) {
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            if ($base === '.' || $base === '/') {
                $base = '';
            }
        }

        $path = ltrim($path, '/');
        $full = dirname(__DIR__, 2) . '/' . $path;

        $version = is_file($full) ? filemtime($full) : null;

        return $base . '/' . $path . ($version ? '?v=' . $version : '');
    }
}
