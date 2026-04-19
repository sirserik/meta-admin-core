<?php

namespace Meta\AdminCore\Support;

/**
 * Collects URL providers contributed by consumer sites and renders
 * them as a sitemap index. A "provider" is just a callable that
 * yields `{loc, lastmod?, changefreq?, priority?}` rows — no class
 * hierarchy, no schema. Consumers decide where the URLs come from:
 * a Page collection, an Article model, or a plain static array.
 *
 *   AdminCore::sitemapUrl(function () {
 *       return [
 *           ['loc' => url('/'), 'priority' => '1.0'],
 *           ['loc' => url('/about'), 'priority' => '0.8'],
 *       ];
 *   });
 *
 *   AdminCore::sitemapUrl(function () {
 *       return \App\Models\Page::where('status', 'published')
 *           ->get()
 *           ->map(fn ($p) => [
 *               'loc'     => url($p->slug),
 *               'lastmod' => $p->updated_at->toIso8601String(),
 *           ]);
 *   });
 */
class SitemapRegistry
{
    /** @var array<int, callable> */
    protected array $providers = [];

    public function register(callable $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return array<int, array<string, string>> */
    public function collect(): array
    {
        $out = [];
        foreach ($this->providers as $fn) {
            $rows = $fn();
            if ($rows === null) continue;
            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['loc'])) continue;
                $out[] = $row;
            }
        }
        return $out;
    }
}
