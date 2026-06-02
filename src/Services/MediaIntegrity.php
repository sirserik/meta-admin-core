<?php

namespace Meta\AdminCore\Services;

/**
 * Detects media files that are silently corrupt — typically an HTML error
 * page saved with an image/PDF extension (a classic crawl/migration artifact).
 * Such files serve with HTTP 200 + an image MIME (by extension), so curl looks
 * fine, but browsers can't decode them → broken images. Catch them by content,
 * not by HTTP status.
 *
 * Pure helpers (no IO) so they're trivially testable; the
 * `admin-core:media-check` command does the scanning/repair around them.
 */
class MediaIntegrity
{
    /** Extensions whose bytes MUST be binary — if they start with markup, they're corrupt. */
    public const BINARY_IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'pdf', 'ico', 'avif'];

    /** True if the leading bytes look like an HTML/XML document (masquerading file). */
    public static function looksLikeMarkup(string $head): bool
    {
        $h = ltrim($head);
        // BOM tolerance
        $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;

        return (bool) preg_match('/^<(!doctype|html|\?xml|head|body|!--)/i', $h);
    }

    /** Should a file with this extension be binary (so markup content = corruption)? */
    public static function isBinaryImageExt(string $ext): bool
    {
        return in_array(strtolower(ltrim($ext, '.')), self::BINARY_IMAGE_EXTS, true);
    }

    /**
     * Verdict for one file given its extension and first bytes.
     * Returns true when the file is a binary-image type but its content is markup.
     */
    public static function isCorrupt(string $ext, string $head): bool
    {
        return self::isBinaryImageExt($ext) && self::looksLikeMarkup($head);
    }
}
