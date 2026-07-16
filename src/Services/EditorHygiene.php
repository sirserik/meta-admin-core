<?php

namespace Meta\AdminCore\Services;

/**
 * Pure HTML/content hygiene transforms for rich-editor fields — the kind of
 * mess that lands in any CMS via paste-from-Word/Google-Docs or base64
 * image paste. Used by the `admin-core:content-*` commands and reusable on
 * save (e.g. in a model mutator).
 *
 * cleanGoogleDocs() and paragraphsToLists() are pure (string → string).
 * extractBase64() takes a $persist callback so it stays free of Storage and
 * is easy to test.
 */
class EditorHygiene
{
    /**
     * Strip Google-Docs / Word cruft: docs-internal-guid spans, junk inline
     * styles (keep only meaningful ones), dir/lang/aria attrs, nested empty
     * spans, <li><p>…</p></li> unwrapping, empty <p>/&nbsp; collapse.
     */
    public static function cleanGoogleDocs(string $html): string
    {
        $h = $html;
        $h = preg_replace('/<span[^>]*id="docs-internal-guid-[^"]*"[^>]*>/i', '', $h);

        $h = preg_replace_callback('/\sstyle="([^"]*)"/i', function ($m) {
            $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
            $keep = [];
            foreach (array_filter(array_map('trim', explode(';', $raw))) as $d) {
                if (! str_contains($d, ':')) {
                    continue;
                }
                [$prop, $val] = array_map('trim', explode(':', $d, 2));
                $prop = strtolower($prop);
                if (! in_array($prop, ['color', 'background-color', 'text-align', 'font-weight', 'font-style', 'text-decoration'], true)) {
                    continue;
                }
                if ($prop === 'background-color' && preg_match('/^(transparent|#?fff(?:fff)?|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\))$/i', $val)) {
                    continue;
                }
                if ($prop === 'color' && preg_match('/^(#?000(?:000)?|rgb\(\s*0\s*,\s*0\s*,\s*0\s*\))$/i', $val)) {
                    continue;
                }
                if ($prop === 'font-weight' && (in_array((int) $val, [400, 300], true) || preg_match('/^normal$/i', $val))) {
                    continue;
                }
                if ($prop === 'font-style' && preg_match('/^normal$/i', $val)) {
                    continue;
                }
                if ($prop === 'text-decoration' && preg_match('/^none$/i', $val)) {
                    continue;
                }
                if ($prop === 'text-align' && preg_match('/^(justify|left)$/i', $val)) {
                    continue;
                }
                $keep[] = "{$prop}:{$val}";
            }

            return $keep ? ' style="' . implode(';', $keep) . '"' : '';
        }, $h);

        $h = preg_replace('/\s(dir|lang|role|aria-level)="[^"]*"/i', '', $h);
        $h = preg_replace('/\sid="docs-internal-guid-[^"]*"/i', '', $h);

        for ($i = 0; $i < 5; $i++) {
            $h = preg_replace('/<span>(.*?)<\/span>/is', '$1', $h);
            $h = preg_replace('/<span style="([^"]+)">\s*<span style="\1">(.*?)<\/span>\s*<\/span>/is', '<span style="$1">$2</span>', $h);
        }

        $h = preg_replace('/<li[^>]*>\s*<p[^>]*>(.*?)<\/p>\s*<\/li>/is', '<li>$1</li>', $h);
        $h = preg_replace('/<p[^>]*>\s*(?:<br\s*\/?>|\s|&nbsp;)*\s*<\/p>/i', '', $h);
        $h = preg_replace('/<p([^>]*)>\s*(?:&nbsp;\s*)+/i', '<p$1>', $h);
        $h = preg_replace('/(?:&nbsp;\s*)+<\/p>/i', '</p>', $h);
        $h = preg_replace('/(<\/[^>]+>)\s*<br\s*\/?>\s*(?=<[ph])/i', '$1', $h);
        $h = preg_replace('/<br\s*\/?>\s*<br\s*\/?>/i', '<br>', $h);
        $h = preg_replace('/<(\w+)>\s*<\/\1>/i', '', $h);
        $h = preg_replace('/<(\w+)[^>]*>\s*<\/\1>/i', '', $h);
        $h = preg_replace('/>\s+</', '><', $h);
        $h = preg_replace('/ {2,}/', ' ', $h);

        return trim($h);
    }

    /**
     * Narrow cleanup of rich-editor (TipTap/ProseMirror) round-trip artifacts,
     * safe to sweep over already-good content: empty paragraphs
     * (<p></p> / <p>&nbsp;</p> / <p><br></p>) and the <li><p>…</p></li>
     * wrapper the editor adds around list-item content. Unlike
     * cleanGoogleDocs() it never touches inline styles or spans.
     */
    public static function cleanEditorArtifacts(string $html): string
    {
        $h = $html;

        // <li><p>single paragraph</p></li> → <li>…</li>. The lookahead keeps
        // multi-paragraph list items intact (unwrapping those would splice
        // stray </p><p> into the middle of the <li>).
        $h = preg_replace(
            '/<li([^>]*)>\s*<p[^>]*>((?:(?!<p[\s>]|<\/p>).)*)<\/p>\s*<\/li>/is',
            '<li$1>$2</li>',
            $h
        );

        $h = preg_replace('/<p[^>]*>\s*(?:<br\s*\/?>|&nbsp;|\s)*<\/p>/i', '', $h);

        return trim($h);
    }

    /**
     * Turn runs of `<p>…;</p>` (≥ $min consecutive, semicolon-terminated —
     * the classic pasted bullet list) into a single <ul><li>…</li></ul>.
     */
    public static function paragraphsToLists(string $html, int $min = 2): string
    {
        $min = max(2, $min);
        $out = preg_replace_callback(
            '/((?:<p(?:\s[^>]*)?>[^<]*[;]\s*<\/p>\s*){' . $min . ',})/i',
            function ($m) {
                preg_match_all('/<p(?:\s[^>]*)?>([^<]*?)[;]\s*<\/p>/i', $m[1], $items);
                if (empty($items[1])) {
                    return $m[0];
                }
                $lis = array_map(fn ($t) => '<li>' . trim($t) . '</li>', $items[1]);

                return '<ul>' . implode('', $lis) . '</ul>';
            },
            $html
        );

        return $out ?? $html;
    }

    /**
     * Replace inline `data:image/...;base64,...` blobs with URLs. For each
     * blob, $persist(string $filename, string $bytes): string is called and
     * must return the public URL to substitute. Returns the rewritten text.
     */
    public static function extractBase64(string $text, callable $persist): string
    {
        $pattern = '/data:image\/([a-zA-Z0-9+.\-]+);base64,([A-Za-z0-9+\/=\s]+?)(?=["\'\)\s<])/';

        return preg_replace_callback($pattern, function ($m) use ($persist) {
            $ext = strtolower($m[1]);
            $ext = match ($ext) {
                'jpeg'    => 'jpg',
                'svg+xml' => 'svg',
                default   => $ext,
            };
            $bytes = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
            if ($bytes === false || strlen($bytes) < 8) {
                return $m[0];
            }
            $filename = sha1($bytes) . '.' . $ext;

            return (string) $persist($filename, $bytes);
        }, $text) ?? $text;
    }
}
