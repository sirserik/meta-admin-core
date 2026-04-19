<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\TaxonomyTerm;

/**
 * Admin CRUD for taxonomy terms. Groups rows by `type`, lets editors
 * add/rename/delete terms inside each vocabulary. Slugs are
 * auto-generated from the label if left empty.
 *
 * Consumers hook terms to their resources via the Taxable trait on
 * the model and (optionally) a `terms` attribute on the resource form
 * that calls syncTerms() on save.
 */
class TaxonomyController extends Controller
{
    public function index(Request $request): Response
    {
        $type = (string) $request->query('type', 'tag');

        $types = TaxonomyTerm::query()
            ->select('type')
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values()
            ->all();

        // Make sure the view has at least one vocabulary to show on
        // a fresh install — "tag" is the expected baseline.
        if (!in_array('tag', $types, true)) $types = array_merge(['tag'], $types);

        $terms = TaxonomyTerm::query()
            ->where('type', $type)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'type', 'slug', 'label', 'sort_order', 'label_translations'])
            ->map(fn (TaxonomyTerm $t) => [
                'id'                 => $t->id,
                'type'               => $t->type,
                'slug'               => $t->slug,
                'label'              => $t->label,
                'sort_order'         => $t->sort_order,
                'label_translations' => $t->label_translations ?? [],
            ]);

        return Inertia::render('Taxonomies/Index', [
            'title' => 'Словари',
            'types' => $types,
            'activeType' => $type,
            'terms' => $terms,
            'locales' => (array) config('admin-core.locales', ['ru', 'kk', 'en']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        if (empty($data['slug'])) $data['slug'] = Str::slug($data['label']);

        TaxonomyTerm::create([
            'type'               => $data['type'],
            'slug'               => $data['slug'],
            'label'              => $data['label'],
            'sort_order'         => $data['sort_order'] ?? 0,
            'label_translations' => $data['label_translations'] ?? [],
        ]);

        return redirect()
            ->route('admin.taxonomies.index', ['type' => $data['type']])
            ->with('success', 'Термин создан');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $term = TaxonomyTerm::findOrFail($id);
        $data = $this->validated($request, $term);
        if (empty($data['slug'])) $data['slug'] = Str::slug($data['label']);

        $term->update([
            'type'               => $data['type'],
            'slug'               => $data['slug'],
            'label'              => $data['label'],
            'sort_order'         => $data['sort_order'] ?? $term->sort_order,
            'label_translations' => $data['label_translations'] ?? [],
        ]);

        return redirect()
            ->route('admin.taxonomies.index', ['type' => $term->type])
            ->with('success', 'Термин обновлён');
    }

    public function destroy(int $id): RedirectResponse
    {
        $term = TaxonomyTerm::findOrFail($id);
        $type = $term->type;
        $term->delete();

        return redirect()
            ->route('admin.taxonomies.index', ['type' => $type])
            ->with('success', 'Термин удалён');
    }

    protected function validated(Request $request, ?TaxonomyTerm $existing = null): array
    {
        $rules = [
            'type'                   => 'required|string|max:50',
            'slug'                   => 'nullable|string|max:120',
            'label'                  => 'required|string|max:255',
            'sort_order'             => 'nullable|integer',
            'label_translations'     => 'nullable|array',
            'label_translations.*'   => 'nullable|string|max:255',
        ];
        return $request->validate($rules);
    }
}
