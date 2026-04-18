<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\MenuItem;

class MenuController extends Controller
{
    protected const LOCALES = ['ru', 'kk', 'en'];

    public function index(): Response
    {
        $items = MenuItem::query()->orderBy('menu_order')->get();
        $tree  = $items->groupBy('parent_id')->map(fn ($g) => $g->values());

        $format = function (MenuItem $m) use (&$tree, &$format) {
            $children = ($tree[$m->id] ?? collect())->map(fn ($c) => $format($c))->all();
            return [
                'id'           => $m->id,
                'parent_id'    => $m->parent_id,
                'menu_order'   => $m->menu_order,
                'slug'         => $m->slug,
                'icon'         => $m->icon,
                'is_published' => (bool) $m->is_published,
                'content_type' => $m->content_type,
                'content_id'   => $m->content_id,
                'title'        => $this->localized($m, 'title'),
                'url'          => $this->localized($m, 'url'),
                'children'     => $children,
            ];
        };

        $roots = ($tree[null] ?? ($tree->get('') ?? collect()))
            ->map(fn ($m) => $format($m))
            ->all();

        return Inertia::render('Menu/Index', [
            'title'   => 'Меню сайта',
            'items'   => $roots,
            'flat'    => $items->map(fn (MenuItem $m) => [
                'id'        => $m->id,
                'title'     => $m->translate('title', 'ru') ?? '',
                'parent_id' => $m->parent_id,
            ])->values(),
            'locales' => self::LOCALES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $item = MenuItem::create([
            'parent_id'    => $data['parent_id'] ?: null,
            'content_type' => $data['content_type'] ?? 'link',
            'content_id'   => $data['content_id']   ?? null,
            'slug'         => $data['slug'] ?: null,
            'icon'         => $data['icon'] ?: null,
            'is_published' => $request->boolean('is_published', true),
            'menu_order'   => (int) MenuItem::where('parent_id', $data['parent_id'] ?: null)->max('menu_order') + 1,
        ]);
        $this->persistTranslations($item, $data);

        return back()->with('success', 'Пункт меню создан');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $menu = MenuItem::findOrFail($id);
        $data = $this->validated($request, $menu);

        $menu->update([
            'parent_id'    => $data['parent_id'] ?: null,
            'content_type' => $data['content_type'] ?? $menu->content_type,
            'content_id'   => $data['content_id']   ?? $menu->content_id,
            'slug'         => $data['slug'] ?: null,
            'icon'         => $data['icon'] ?: null,
            'is_published' => $request->boolean('is_published'),
        ]);
        $this->persistTranslations($menu, $data);

        return back()->with('success', 'Пункт обновлён');
    }

    public function destroy(int $id): RedirectResponse
    {
        $menu = MenuItem::findOrFail($id);
        $menu->children()->update(['parent_id' => null]);
        $menu->delete();
        return back()->with('success', 'Пункт удалён');
    }

    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'              => 'required|array',
            'items.*.id'         => 'required|integer|exists:menu_items,id',
            'items.*.parent_id'  => 'nullable|integer|exists:menu_items,id',
            'items.*.menu_order' => 'required|integer',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $row) {
                MenuItem::where('id', $row['id'])->update([
                    'parent_id'  => $row['parent_id'] ?: null,
                    'menu_order' => $row['menu_order'],
                ]);
            }
        });

        return response()->json(['ok' => true]);
    }

    // -----------------------------------------------------------------

    protected function validated(Request $request, ?MenuItem $existing = null): array
    {
        $rules = [
            'parent_id'    => 'nullable|integer|exists:menu_items,id',
            'content_type' => 'nullable|string|max:50',
            'content_id'   => 'nullable|integer',
            'slug'         => 'nullable|string|max:255',
            'icon'         => 'nullable|string|max:100',
            'is_published' => 'nullable|boolean',
        ];
        foreach (self::LOCALES as $locale) {
            $rules["title.{$locale}"] = ($locale === 'ru') ? 'required|string|max:255' : 'nullable|string|max:255';
            $rules["url.{$locale}"]   = 'nullable|string|max:500';
        }
        return $request->validate($rules);
    }

    protected function persistTranslations(MenuItem $item, array $data): void
    {
        foreach (self::LOCALES as $locale) {
            $payload = [];
            foreach (['title', 'url'] as $field) {
                $value = $data[$field][$locale] ?? '';
                if ($value !== '') $payload[$field] = $value;
            }
            if ($payload) $item->saveTranslations($locale, $payload);
        }
    }

    protected function localized(MenuItem $m, string $field): array
    {
        $out = [];
        foreach (self::LOCALES as $locale) {
            $out[$locale] = $m->translate($field, $locale) ?? '';
        }
        return $out;
    }
}
