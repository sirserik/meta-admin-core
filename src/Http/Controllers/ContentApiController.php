<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Meta\AdminCore\Facades\AdminCore;
use Meta\AdminCore\Models\PageBlock;

/**
 * Read-only Content API. Exposes the CMS payload as JSON so frontend
 * apps (Next.js, mobile, static-site generators…) can decouple from
 * Blade rendering. Two endpoints:
 *
 *   GET /api/content/pages/{slug}
 *     → { page: {slug, …}, blocks: [{block_type, title, ..., data}] }
 *
 *   GET /api/content/{resource}            (any AdminCore resource)
 *   GET /api/content/{resource}/{id}
 *     → paginated list OR a single record, localized fields collapsed
 *       into the requested locale (?locale=ru|kk|en, defaults to
 *       config('app.locale')).
 *
 * Responses respect the `Accept-Language` header as a fallback for
 * `?locale=`. Published status is enforced — draft rows don't leak.
 *
 * No auth by default (public read). Consumers can wrap the route
 * group with their own middleware (`auth:sanctum`, `throttle`, …).
 */
class ContentApiController extends Controller
{
    public function pageBySlug(Request $request, string $slug): JsonResponse
    {
        $locale = $this->resolveLocale($request);

        $blocks = PageBlock::query()
            ->where('page_name', $slug)
            ->where('is_active', true)
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PageBlock $b) => $this->presentBlock($b, $locale))
            ->all();

        return response()->json([
            'page'   => ['slug' => $slug, 'locale' => $locale],
            'blocks' => $blocks,
        ]);
    }

    public function resourceList(Request $request, string $resource): JsonResponse
    {
        $config = AdminCore::getResource($resource);
        abort_unless($config && isset($config['model']), 404);

        $locale = $this->resolveLocale($request);
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = $config['model'];

        $perPage = (int) min(max((int) $request->input('per_page', 20), 1), 100);
        $query = $model::query();

        // Default to only published rows when the model has a status column.
        if (in_array('status', (new $model)->getFillable(), true)) {
            $query->where('status', 'published');
        }
        if (in_array('is_published', (new $model)->getFillable(), true)) {
            $query->where('is_published', true);
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'data' => collect($paginator->items())->map(
                fn ($row) => $this->presentModel($row, $config, $locale),
            ),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'locale'       => $locale,
            ],
        ]);
    }

    public function resourceShow(Request $request, string $resource, string $idOrSlug): JsonResponse
    {
        $config = AdminCore::getResource($resource);
        abort_unless($config && isset($config['model']), 404);

        $locale = $this->resolveLocale($request);
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = $config['model'];
        $query = $model::query();

        // Prefer slug lookup when the table has one — cleaner URLs.
        $record = in_array('slug', (new $model)->getFillable(), true)
            ? $query->where('slug', $idOrSlug)->orWhere('id', $idOrSlug)->first()
            : $query->where('id', $idOrSlug)->first();

        if (!$record) return response()->json(['message' => 'Not found'], 404);

        return response()->json([
            'data'   => $this->presentModel($record, $config, $locale),
            'locale' => $locale,
        ]);
    }

    /* ------------------------------------------------------------------ */

    protected function resolveLocale(Request $request): string
    {
        $configured = (array) config('admin-core.locales', ['ru', 'kk', 'en']);
        $default    = $configured[0] ?? 'ru';

        $candidate = $request->query('locale')
            ?: $request->header('Accept-Language')
            ?: config('app.locale')
            ?: $default;

        $candidate = strtolower(substr((string) $candidate, 0, 2));
        return in_array($candidate, $configured, true) ? $candidate : $default;
    }

    protected function presentBlock(PageBlock $b, string $locale): array
    {
        return [
            'id'         => $b->id,
            'block_type' => $b->block_type,
            'block_key'  => $b->block_key,
            'title'      => method_exists($b, 'translate') ? $b->translate('title',    $locale) : $b->title,
            'subtitle'   => method_exists($b, 'translate') ? $b->translate('subtitle', $locale) : $b->subtitle,
            'content'    => method_exists($b, 'translate') ? $b->translate('content',  $locale) : $b->content,
            'data'       => $b->data,
            'settings'   => $b->settings,
            'sort_order' => $b->sort_order,
        ];
    }

    protected function presentModel($record, array $config, string $locale): array
    {
        $attrs = $record->toArray();
        $fields = $config['translatable'] ?? [];

        // Replace translatable columns with the localized value.
        if (method_exists($record, 'translate')) {
            foreach ($fields as $f) {
                $val = $record->translate($f, $locale);
                if ($val !== null) $attrs[$f] = $val;
            }
        }
        return $attrs;
    }
}
