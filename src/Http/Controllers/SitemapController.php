<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Meta\AdminCore\Facades\AdminCore;

/**
 * Public sitemap endpoint served at `/sitemap.xml`.
 *
 * Iterates every provider registered via `AdminCore::sitemapUrl()`,
 * collects `{loc, lastmod?, changefreq?, priority?}` rows, and renders
 * a standards-compliant Sitemap 0.9 XML document. Response is cached
 * for an hour by default — consumers busy-bust by changing the
 * `admin-core.sitemap.cache_key` or simply `php artisan cache:clear`.
 *
 * Mount via `routes/public.php` (already shipped by the provider).
 */
class SitemapController extends Controller
{
    public function index(): Response
    {
        $ttl = (int) config('admin-core.sitemap.ttl', 3600);
        $key = (string) config('admin-core.sitemap.cache_key', 'admin-core.sitemap.xml');

        $xml = $ttl > 0
            ? Cache::remember($key, $ttl, fn () => $this->render())
            : $this->render();

        return response($xml, 200, [
            'Content-Type'  => 'application/xml; charset=utf-8',
            'X-Robots-Tag'  => 'noindex',
        ]);
    }

    protected function render(): string
    {
        $rows = AdminCore::sitemap()->collect();

        $out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $out .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($rows as $row) {
            $loc        = htmlspecialchars((string) $row['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $lastmod    = isset($row['lastmod'])    ? htmlspecialchars((string) $row['lastmod'],    ENT_XML1 | ENT_QUOTES, 'UTF-8') : null;
            $changefreq = isset($row['changefreq']) ? htmlspecialchars((string) $row['changefreq'], ENT_XML1 | ENT_QUOTES, 'UTF-8') : null;
            $priority   = isset($row['priority'])   ? htmlspecialchars((string) $row['priority'],   ENT_XML1 | ENT_QUOTES, 'UTF-8') : null;

            $out .= "  <url>\n";
            $out .= "    <loc>{$loc}</loc>\n";
            if ($lastmod)    $out .= "    <lastmod>{$lastmod}</lastmod>\n";
            if ($changefreq) $out .= "    <changefreq>{$changefreq}</changefreq>\n";
            if ($priority)   $out .= "    <priority>{$priority}</priority>\n";
            $out .= "  </url>\n";
        }

        $out .= "</urlset>\n";
        return $out;
    }
}
