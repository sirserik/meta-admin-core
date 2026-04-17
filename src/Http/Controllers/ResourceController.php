<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Facades\AdminCore;

/**
 * Generic Inertia CRUD controller driven by resource configuration registered
 * via AdminCore::resource(). Adding a new resource is literally one call in a
 * ServiceProvider — no extra controller required for basic CRUD.
 *
 * Every route is dispatched through this single controller; it reads the
 * resource name from the URL segment and pulls the config from AdminCore.
 */
class ResourceController extends Controller
{
    protected const LOCALES = ['ru', 'kk', 'en'];

    public function index(Request $request, string $resource): Response
    {
        $config = $this->config($resource);
        $model = $config['model'];

        $filters = $request->only(['search']);
        $query = $model::query();

        foreach ($config['order_by'] as $col => $dir) {
            $query->orderBy($col, $dir);
        }
        if (!empty($filters['search'])) {
            $this->applySearch($query, $filters['search'], $config);
        }

        $paginator = $query->paginate($config['per_page'])->withQueryString();
        $paginator->getCollection()->transform(fn (Model $m) => $this->presentRow($m, $config));

        return Inertia::render($config['page'] . '/Index', [
            'title'    => $config['label'],
            'items'    => $paginator,
            'resource' => $config['name'],
            'filters'  => $filters,
            'fields'   => $config['fields'],
        ]);
    }

    public function create(string $resource): Response
    {
        $config = $this->config($resource);

        return Inertia::render($config['page'] . '/Form', [
            'title'    => 'Новый: ' . $config['label'],
            'item'     => null,
            'fields'   => $config['fields'],
            'locales'  => self::LOCALES,
            'resource' => $config['name'],
            'is_edit'  => false,
        ]);
    }

    public function edit(string $resource, string $id): Response
    {
        $config = $this->config($resource);
        $m = $this->findModel($config, $id);

        return Inertia::render($config['page'] . '/Form', [
            'title'    => 'Редактирование',
            'item'     => $this->presentForm($m, $config),
            'fields'   => $config['fields'],
            'locales'  => self::LOCALES,
            'resource' => $config['name'],
            'is_edit'  => true,
        ]);
    }

    public function store(Request $request, string $resource): RedirectResponse
    {
        $config = $this->config($resource);
        $data = $this->validated($request, $config);

        /** @var Model $m */
        $m = new $config['model']();
        $this->fill($m, $data, $request, $config);
        $m->save();
        $this->saveTranslations($m, $data, $config);

        return redirect()
            ->route('admin.resource.index', $resource)
            ->with('success', $config['label'] . ' создан(а)');
    }

    public function update(Request $request, string $resource, string $id): RedirectResponse
    {
        $config = $this->config($resource);
        $m = $this->findModel($config, $id);
        $data = $this->validated($request, $config, $m);

        $this->fill($m, $data, $request, $config, $m);
        $m->save();
        $this->saveTranslations($m, $data, $config);

        return redirect()
            ->route('admin.resource.index', $resource)
            ->with('success', 'Сохранено');
    }

    public function destroy(string $resource, string $id): RedirectResponse
    {
        $config = $this->config($resource);
        $m = $this->findModel($config, $id);

        if ($config['image_field'] && $m->{$config['image_field']}) {
            Storage::disk('public')->delete($m->{$config['image_field']});
        }
        $m->delete();

        return redirect()
            ->route('admin.resource.index', $resource)
            ->with('success', 'Удалено');
    }

    public function togglePublish(string $resource, string $id): RedirectResponse
    {
        $config = $this->config($resource);
        $m = $this->findModel($config, $id);
        $table = $m->getTable();

        if (Schema::hasColumn($table, 'status')) {
            $m->update(['status' => $m->status === 'published' ? 'draft' : 'published']);
        } elseif (Schema::hasColumn($table, 'is_published')) {
            $m->update(['is_published' => !$m->is_published]);
        } elseif (Schema::hasColumn($table, 'is_active')) {
            $m->update(['is_active' => !$m->is_active]);
        }

        return back()->with('success', 'Статус изменён');
    }

    // ---------------------------------------------------------------

    protected function config(string $resource): array
    {
        $c = AdminCore::getResource($resource);
        abort_unless($c, 404, "Resource [{$resource}] not registered");
        return $c;
    }

    protected function findModel(array $config, string $id): Model
    {
        $modelClass = $config['model'];
        $key = $config['route_key'] ?? $modelClass::make()->getRouteKeyName();
        return $modelClass::where($key, $id)->firstOrFail();
    }

    protected function applySearch($query, string $term, array $config): void
    {
        $translatable = $config['translatable'];
        $titleField = in_array('title', $translatable, true) ? 'title'
            : (in_array('name', $translatable, true) ? 'name' : null);

        if ($titleField) {
            $query->whereHas('translations', function ($q) use ($term, $titleField) {
                $q->where('field', $titleField)->where('value', 'like', "%{$term}%");
            });
        } elseif (Schema::hasColumn(($config['model'])::make()->getTable(), 'name')) {
            $query->where('name', 'like', "%{$term}%");
        } elseif (Schema::hasColumn(($config['model'])::make()->getTable(), 'title')) {
            $query->where('title', 'like', "%{$term}%");
        }
    }

    protected function validated(Request $request, array $config, ?Model $existing = null): array
    {
        $rules = [];

        if ($config['image_field']) {
            $rules[$config['image_field']]              = 'nullable|image|max:5120';
            $rules['remove_' . $config['image_field']]  = 'nullable|boolean';
        }

        foreach ($config['plain'] as $col) {
            $rules[$col] = 'nullable';
        }

        foreach ($config['translatable'] as $field) {
            foreach (self::LOCALES as $locale) {
                $isRequired = $this->fieldIsRequired($field, $config) && $locale === 'ru';
                $rules["{$field}.{$locale}"] = ($isRequired ? 'required' : 'nullable') . '|string';
            }
        }

        return $request->validate($rules);
    }

    protected function fieldIsRequired(string $field, array $config): bool
    {
        $def = collect($config['fields'])->firstWhere('name', $field);
        return !empty($def['required']);
    }

    protected function fill(Model $m, array $data, Request $request, array $config, ?Model $existing = null): void
    {
        foreach ($config['plain'] as $col) {
            if (in_array($col, ['is_active', 'is_published', 'is_featured'])) {
                $m->{$col} = $request->boolean($col);
            } elseif (array_key_exists($col, $data)) {
                $m->{$col} = $data[$col] !== '' ? $data[$col] : null;
            }
        }

        if (Schema::hasColumn($m->getTable(), 'slug') && empty($m->slug)) {
            $titleField = in_array('title', $config['translatable'], true) ? 'title' : 'name';
            $m->slug = Str::slug($data[$titleField]['ru'] ?? ('item-' . uniqid()));
        }

        if ($imageField = $config['image_field']) {
            if ($request->hasFile($imageField)) {
                $path = $request->file($imageField)->store($config['name'], 'public');
                if ($existing && $existing->{$imageField}) {
                    Storage::disk('public')->delete($existing->{$imageField});
                }
                $m->{$imageField} = $path;
            } elseif ($request->boolean('remove_' . $imageField) && $m->{$imageField}) {
                Storage::disk('public')->delete($m->{$imageField});
                $m->{$imageField} = null;
            }
        }

        // Mirror primary-locale translation into physical column if it exists.
        foreach ($config['translatable'] as $field) {
            if (Schema::hasColumn($m->getTable(), $field)) {
                $value = $data[$field]['ru'] ?? null;
                if ($value !== null && $value !== '') $m->{$field} = $value;
            }
        }
    }

    protected function saveTranslations(Model $m, array $data, array $config): void
    {
        if (!method_exists($m, 'saveTranslations')) return;

        foreach (self::LOCALES as $locale) {
            $payload = [];
            foreach ($config['translatable'] as $field) {
                $value = $data[$field][$locale] ?? '';
                if ($value !== '') $payload[$field] = $value;
            }
            if (!empty($payload)) $m->saveTranslations($locale, $payload);
        }
    }

    protected function presentRow(Model $m, array $config): array
    {
        $row = [
            'id'  => $m->id,
            'url' => route('admin.resource.edit', [$config['name'], $m->getRouteKey()]),
        ];
        // Title/name
        foreach (['title', 'name'] as $f) {
            if (in_array($f, $config['translatable'], true) && method_exists($m, 'translate')) {
                $row['title'] = $m->translate($f, 'ru') ?? $m->{$f} ?? '';
                break;
            }
            if (Schema::hasColumn($m->getTable(), $f)) {
                $row['title'] = $m->{$f} ?? '';
                break;
            }
        }
        foreach ($config['plain'] as $col) {
            if (Schema::hasColumn($m->getTable(), $col)) {
                $row[$col] = $m->{$col};
            }
        }
        if ($imageField = $config['image_field']) {
            $row['image']     = $m->{$imageField};
            $row['image_url'] = $m->{$imageField} ? $this->mediaUrl($m->{$imageField}) : null;
        }
        return $row;
    }

    protected function presentForm(Model $m, array $config): array
    {
        $out = ['id' => $m->id];

        foreach ($config['plain'] as $col) {
            if (Schema::hasColumn($m->getTable(), $col)) {
                $v = $m->{$col};
                if ($v instanceof \DateTimeInterface) $v = $v->format('Y-m-d\TH:i');
                $out[$col] = $v;
            }
        }

        if (Schema::hasColumn($m->getTable(), 'slug')) $out['slug'] = $m->slug;

        if ($imageField = $config['image_field']) {
            $out[$imageField]           = $m->{$imageField};
            $out[$imageField . '_url']  = $m->{$imageField} ? $this->mediaUrl($m->{$imageField}) : null;
        }

        foreach ($config['translatable'] as $field) {
            $out[$field] = [];
            foreach (self::LOCALES as $locale) {
                $out[$field][$locale] = method_exists($m, 'translate')
                    ? ($m->translate($field, $locale) ?? '')
                    : ($m->{$field} ?? '');
            }
        }

        return $out;
    }

    protected function mediaUrl(string $path): string
    {
        return function_exists('media_url') ? media_url($path) : Storage::disk('public')->url($path);
    }
}
