<?php
/**
 * Single source of truth for where uploaded files live and what URL is
 * stored for them.
 *
 * The controllers used to each write into app/public/... and store a
 * root-absolute URL like "/uploads/stories/x.jpg". That only resolves if
 * app/public is the web root, which it never was — the site is served from
 * the project root, so every uploaded image 404'd.
 *
 * Files now go in <project root>/uploads/<kind>/ and the stored URL is
 * relative ("uploads/stories/x.jpg"), matching the convention that the
 * working profile pictures already used. The frontend prefixes it with the
 * app base path, so it resolves whether the site is installed at a domain
 * root or in a subdirectory.
 */

class Media
{
    /** Absolute path of the project root (two levels up from app/core). */
    public static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    /** Absolute directory for a media kind, created if missing. */
    public static function dir(string $kind): string
    {
        $dir = self::root() . '/uploads/' . $kind;

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    /** The relative URL to store in the database. */
    public static function url(string $kind, string $filename): string
    {
        return 'uploads/' . $kind . '/' . $filename;
    }

    /** Whether the GD extension is available for resizing. */
    public static function hasImageSupport(): bool
    {
        return function_exists('imagecreatetruecolor');
    }

    /**
     * Writes a downscaled copy of an image.
     *
     * If GD is not installed the original is copied instead of resized. That
     * costs bandwidth but keeps uploads working — previously a missing GD made
     * imagecreatefromjpeg() fatal, which failed the whole upload and lost the
     * post rather than just the thumbnail.
     */
    public static function thumbnail(string $sourcePath, string $destPath, int $maxWidth = 300): bool
    {
        $info = @getimagesize($sourcePath);
        if (!$info) {
            return false;
        }

        if (!self::hasImageSupport()) {
            error_log('Media::thumbnail — GD not available, storing full-size copy for ' . basename($destPath));
            return @copy($sourcePath, $destPath);
        }

        list($width, $height) = $info;
        if ($width < 1 || $height < 1) {
            return false;
        }

        // Never upscale a small image.
        $targetWidth  = min($maxWidth, $width);
        $targetHeight = (int)round($targetWidth * ($height / $width));

        switch ($info['mime']) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($sourcePath); break;
            case 'image/png':  $src = @imagecreatefrompng($sourcePath);  break;
            case 'image/gif':  $src = @imagecreatefromgif($sourcePath);  break;
            default:           return false;
        }

        if (!$src) {
            return false;
        }

        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        $ok = imagejpeg($thumb, $destPath, 80);

        imagedestroy($src);
        imagedestroy($thumb);

        return (bool)$ok;
    }

    /**
     * Grabs a poster frame from a video with ffmpeg.
     *
     * ffmpeg is usually absent on shared hosting, so a missing binary is a
     * no-op: the video still uploads, it just has no poster image.
     */
    public static function videoThumbnail(string $videoPath, string $thumbPath): bool
    {
        if (!function_exists('exec')) {
            return false;
        }

        $probe = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where ffmpeg' : 'command -v ffmpeg';
        @exec($probe . ' 2>&1', $probeOut, $probeStatus);

        if ($probeStatus !== 0) {
            error_log('Media::videoThumbnail — ffmpeg not available, skipping poster frame');
            return false;
        }

        $cmd = 'ffmpeg -y -i ' . escapeshellarg($videoPath)
             . ' -ss 00:00:01 -vframes 1 ' . escapeshellarg($thumbPath);

        @exec($cmd . ' 2>&1', $out, $status);

        return $status === 0 && is_file($thumbPath);
    }
}
