<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Contracts\BlockCatalog;
use Meta\AdminCore\Models\PageBlock;
use Meta\AdminCore\Services\ImageService;

class PageBlockController extends Controller
{
    protected const LOCALES = ['ru', 'kk', 'en'];
    protected const TRANSLATABLE = ['title', 'subtitle', 'content'];

    public function __construct(
        protected ImageService $imageService,
        protected BlockCatalog $catalog,
    ) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['page', 'type', 'status', 'search']);

        // Newest first — both pages and blocks within a page bubble up
        // on recent edits. groupBy('page_name') preserves iteration
        // order, so whatever page has the most-recent block lands on top.
        $query = PageBlock::query()->orderByDesc('updated_at')->orderByDesc('id');

        if (!empty($filters['page']))   $query->where('page_name',  $filters['page']);
        if (!empty($filters['type']))   $query->where('block_type', $filters['type']);
        if (!empty($filters['status'])) $query->where('status',     $filters['status']);
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $like = "%{$term}%";

            // Match page slugs whose catalog label or group name contains
            // the term, so typing "документы" finds blocks on pages like
            // "Отчёты и прозрачность" via their human label too.
            $pageSlugs = [];
            foreach ($this->catalog->pagesGrouped() as $group => $pages) {
                foreach ($pages as $slug => $label) {
                    if (mb_stripos((string) $label, $term) !== false
                        || mb_stripos((string) $group, $term) !== false) {
                        $pageSlugs[] = $slug;
                    }
                }
            }

            $query->where(function ($q) use ($like, $pageSlugs) {
                $q->where('block_key',  'like', $like)
                  ->orWhere('page_name', 'like', $like)
                  ->orWhere('title',     'like', $like)
                  ->orWhere('subtitle',  'like', $like)
                  ->orWhere('content',   'like', $like)
                  ->orWhere('data',      'like', $like)
                  // Polymorphic translations table — match per-locale
                  // values (kk, en) for title/subtitle/content/etc.
                  ->orWhereIn('id', function ($sub) use ($like) {
                      $sub->select('translatable_id')
                          ->from('translations')
                          ->where('translatable_type', PageBlock::class)
                          ->where('value', 'like', $like);
                  });

                if (!empty($pageSlugs)) {
                    $q->orWhereIn('page_name', $pageSlugs);
                }
            });
        }

        $rows = $query->get()->map(fn (PageBlock $b) => $this->presentRow($b));

        return Inertia::render('Blocks/Index', [
            'title'    => 'Блоки страниц',
            'groups'   => $rows->groupBy('page_name'),
            'pages'    => $this->pagesForIndex(),
            'types'    => $this->blockTypesIndex(),
            'statuses' => $this->statusOptions(),
            'filters'  => $filters,
            'counts'   => [
                'total'     => PageBlock::count(),
                'active'    => PageBlock::where('is_active', true)->count(),
                'published' => PageBlock::where('status', 'published')->count(),
                'drafts'    => PageBlock::where('status', 'draft')->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Blocks/Form', [
            'title' => 'Новый блок',
            'item'  => [
                'page_name'  => $request->query('page', ''),
                'block_type' => 'content',
                'is_active'  => true,
                'status'     => 'draft',
                'sort_order' => 0,
                'title'      => ['ru' => '', 'kk' => '', 'en' => ''],
                'subtitle'   => ['ru' => '', 'kk' => '', 'en' => ''],
                'content'    => ['ru' => '', 'kk' => '', 'en' => ''],
                'data'       => '{}',
                'settings'   => '{}',
            ],
            'pagesGrouped'       => $this->catalog->pagesGrouped(),
            'typesByCategory'    => $this->catalog->blockTypesGrouped(),
            'typesFlat'          => $this->blockTypesIndex(),
            'schemas'            => $this->schemasMap(),
            'statuses'           => $this->statusOptions(),
            'locales'            => self::LOCALES,
            'isEdit'             => false,
            'existingKeysByPage' => $this->existingKeysByPage(),
        ]);
    }

    public function edit(int $id): Response
    {
        $block = PageBlock::findOrFail($id);
        return Inertia::render('Blocks/Form', [
            'title'              => 'Блок: ' . $block->block_key,
            'item'               => $this->presentForm($block),
            'pagesGrouped'       => $this->catalog->pagesGrouped(),
            'typesByCategory'    => $this->catalog->blockTypesGrouped(),
            'typesFlat'          => $this->blockTypesIndex(),
            'schemas'            => $this->schemasMap(),
            'statuses'           => $this->statusOptions(),
            'locales'            => self::LOCALES,
            'isEdit'             => true,
            'existingKeysByPage' => $this->existingKeysByPage(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        if (empty($data['block_key'])) {
            $data['block_key'] = $this->generateUniqueKey($data['page_name'], $data['block_type']);
        }

        $block = PageBlock::create([
            'page_name'  => $data['page_name'],
            'block_key'  => $data['block_key'],
            'block_type' => $data['block_type'],
            'is_active'  => $request->boolean('is_active', true),
            'status'     => $data['status'] ?? 'draft',
            'sort_order' => $data['sort_order'] ?? 0,
            'title'      => $data['title']['ru']    ?? null,
            'subtitle'   => $data['subtitle']['ru'] ?? null,
            'content'    => $data['content']['ru']  ?? null,
            'data'       => $this->decodeJson($data['data']     ?? null),
            'settings'   => $this->decodeJson($data['settings'] ?? null),
        ]);
        $this->persistTranslations($block, $data);

        return redirect()
            ->route('admin.blocks.index', ['page' => $block->page_name])
            ->with('success', 'Блок создан');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $block = PageBlock::findOrFail($id);
        $data = $this->validated($request, $block);

        $block->update([
            'page_name'  => $data['page_name'],
            'block_key'  => $data['block_key'],
            'block_type' => $data['block_type'],
            'is_active'  => $request->boolean('is_active'),
            'status'     => $data['status'] ?? $block->status,
            'sort_order' => $data['sort_order'] ?? $block->sort_order,
            'title'      => $data['title']['ru']    ?? $block->title,
            'subtitle'   => $data['subtitle']['ru'] ?? $block->subtitle,
            'content'    => $data['content']['ru']  ?? $block->content,
            'data'       => $this->decodeJson($data['data']     ?? null),
            'settings'   => $this->decodeJson($data['settings'] ?? null),
        ]);
        $this->persistTranslations($block, $data);

        // Back to the page-filtered block list so the user can see the
        // row in its context and keep editing other blocks of that page.
        return redirect()
            ->route('admin.blocks.index', ['page' => $block->page_name])
            ->with('success', 'Блок сохранён');
    }

    public function destroy(int $id): RedirectResponse
    {
        PageBlock::findOrFail($id)->delete();
        return redirect()
            ->route('admin.blocks.index')
            ->with('success', 'Блок удалён');
    }

    public function toggleActive(int $id): RedirectResponse
    {
        $block = PageBlock::findOrFail($id);
        $block->update(['is_active' => !$block->is_active]);
        return back()->with('success', $block->is_active ? 'Включён' : 'Скрыт');
    }

    public function publish(int $id): RedirectResponse
    {
        PageBlock::findOrFail($id)->publish();
        return back()->with('success', 'Опубликован');
    }

    public function unpublish(int $id): RedirectResponse
    {
        PageBlock::findOrFail($id)->unpublish();
        return back()->with('success', 'Снят с публикации');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.id'         => 'required|integer|exists:page_blocks,id',
            'items.*.sort_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $row) {
                PageBlock::where('id', $row['id'])->update(['sort_order' => $row['sort_order']]);
            }
        });

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------

    /**
     * Union of pages the consumer site declares + pages that actually
     * have rows in the DB. This way a site where someone manually
     * created a `/custom-page` block doesn't disappear from the filter.
     */
    protected function pagesForIndex(): array
    {
        $dbPages = PageBlock::query()->distinct()->orderBy('page_name')->pluck('page_name')->toArray();
        $known   = [];
        foreach ($this->catalog->pagesGrouped() as $pages) {
            foreach ($pages as $slug => $label) {
                $known[$slug] = ['slug' => $slug, 'label' => $label];
            }
        }
        foreach ($dbPages as $slug) {
            $known[$slug] ??= ['slug' => $slug, 'label' => $slug];
        }
        return array_values($known);
    }

    /**
     * Map of block_type → data schema. Passed to the Vue form so it
     * can render visual editors for known types and fall back to a
     * raw JSON textarea for the rest.
     */
    protected function schemasMap(): array
    {
        $out = [];
        foreach ($this->catalog->blockTypesFlat() as $type) {
            $schema = $this->catalog->blockSchema($type['key']);
            if ($schema) $out[$type['key']] = $schema;
        }
        return $out;
    }

    protected function blockTypesIndex(): array
    {
        $dbTypes = PageBlock::query()->distinct()->orderBy('block_type')->pluck('block_type')->toArray();
        $catalog = collect($this->catalog->blockTypesFlat())->keyBy('key');

        $out = [];
        foreach ($catalog as $info) {
            $out[] = $info;
        }
        foreach ($dbTypes as $key) {
            if (!$catalog->has($key)) {
                $out[] = [
                    'key'         => $key,
                    'label'       => $key,
                    'description' => 'Устаревший тип (нет в каталоге)',
                    'icon'        => 'fa-puzzle-piece',
                    'category'    => 'Устаревшие',
                    'preview'     => '?',
                ];
            }
        }
        return $out;
    }

    protected function existingKeysByPage(): array
    {
        return PageBlock::query()
            ->select('page_name', 'block_key')
            ->get()
            ->groupBy('page_name')
            ->map(fn ($rows) => $rows->pluck('block_key')->all())
            ->toArray();
    }

    protected function generateUniqueKey(string $pageName, string $blockType): string
    {
        $base = preg_replace('/[^a-z0-9_]/', '_', strtolower($blockType)) ?: 'block';
        $existing = PageBlock::where('page_name', $pageName)->pluck('block_key')->all();
        if (!in_array($base, $existing, true)) return $base;
        $i = 2;
        while (in_array($base . '_' . $i, $existing, true)) $i++;
        return $base . '_' . $i;
    }

    protected function validated(Request $request, ?PageBlock $existing = null): array
    {
        $rules = [
            'page_name'  => 'required|string|max:100',
            'block_key'  => 'nullable|string|max:100',
            'block_type' => 'required|string|max:100',
            'sort_order' => 'nullable|integer',
            'is_active'  => 'nullable|boolean',
            'status'     => 'nullable|in:draft,published,archived',
            'data'       => 'nullable|string',
            'settings'   => 'nullable|string',
        ];
        foreach (self::TRANSLATABLE as $field) {
            foreach (self::LOCALES as $locale) {
                $rules["{$field}.{$locale}"] = 'nullable|string';
            }
        }
        return $request->validate($rules);
    }

    protected function persistTranslations(PageBlock $block, array $data): void
    {
        foreach (self::LOCALES as $locale) {
            $payload = [];
            foreach (self::TRANSLATABLE as $field) {
                $value = $data[$field][$locale] ?? '';
                if ($value !== '') $payload[$field] = $value;
            }
            if ($payload) $block->saveTranslations($locale, $payload);
        }
    }

    protected function decodeJson(?string $raw): mixed
    {
        if ($raw === null || $raw === '') return null;
        $decoded = json_decode($raw, true);
        return $decoded !== null || $raw === 'null' ? $decoded : $raw;
    }

    protected function statusOptions(): array
    {
        return [
            ['value' => 'draft',     'label' => 'Черновик'],
            ['value' => 'published', 'label' => 'Опубликовано'],
            ['value' => 'archived',  'label' => 'В архиве'],
        ];
    }

    protected function presentRow(PageBlock $b): array
    {
        $typeInfo = $this->catalog->blockType($b->block_type);
        return [
            'id'         => $b->id,
            'page_name'  => $b->page_name,
            'page_label' => $this->catalog->pageLabel($b->page_name),
            'block_key'  => $b->block_key,
            'block_type' => $b->block_type,
            'type_label' => $typeInfo['label'] ?? $b->block_type,
            'type_icon'  => $typeInfo['icon']  ?? 'fa-puzzle-piece',
            'title'      => $b->getLocalizedField('title', 'ru'),
            'is_active'  => (bool) $b->is_active,
            'status'     => $b->status,
            'sort_order' => $b->sort_order,
        ];
    }

    protected function presentForm(PageBlock $b): array
    {
        $translations = [];
        foreach (self::TRANSLATABLE as $field) {
            $translations[$field] = [];
            foreach (self::LOCALES as $locale) {
                $translations[$field][$locale] = $b->translate($field, $locale)
                    ?? ($locale === 'ru' ? ($b->{$field} ?? '') : '');
            }
        }

        return array_merge($translations, [
            'id'         => $b->id,
            'page_name'  => $b->page_name,
            'block_key'  => $b->block_key,
            'block_type' => $b->block_type,
            'is_active'  => (bool) $b->is_active,
            'status'     => $b->status,
            'sort_order' => $b->sort_order,
            'data'       => $b->data     !== null ? json_encode($b->data,     JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}',
            'settings'   => $b->settings !== null ? json_encode($b->settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '{}',
            'created_at' => optional($b->created_at)->format('d.m.Y H:i'),
            'updated_at' => optional($b->updated_at)->format('d.m.Y H:i'),
        ]);
    }
}
