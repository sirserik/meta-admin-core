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

    public function index(Request $request, string $resource = ''): Response
    {
        $resource = $this->resolveResource($request, $resource);
        $config = $this->config($resource);
        $model = $config['model'];

        // Build allowed-filter list: 'search' plus any resource-declared filters.
        $filterKeys = array_merge(['search'], array_keys($config['filters'] ?? []));
        $filters = $request->only($filterKeys);
        $query = $model::query();

        foreach ($config['order_by'] as $col => $dir) {
            $query->orderBy($col, $dir);
        }
        if (!empty($filters['search'])) {
            $this->applySearch($query, $filters['search'], $config);
        }
        // Apply declared column filters (?field=value — exact match by default).
        foreach (($config['filters'] ?? []) as $field => $cfg) {
            $value = $filters[$field] ?? null;
            if ($value === null || $value === '') continue;
            $type = is_array($cfg) ? ($cfg['type'] ?? 'exact') : 'exact';
            $column = is_array($cfg) ? ($cfg['column'] ?? $field) : $field;
            match ($type) {
                'like'  => $query->where($column, 'like', '%' . $value . '%'),
                'in'    => $query->whereIn($column, (array) $value),
                default => $query->where($column, $value),
            };
        }

        $paginator = $query->paginate($config['per_page'])->withQueryString();
        $paginator->getCollection()->transform(fn (Model $m) => $this->presentRow($m, $config));

        return Inertia::render($config['page'] . '/Index', [
            'title'       => $config['label'],
            'items'       => $paginator,
            'resource'    => $config['name'],
            'filters'     => $filters,
            'fields'      => $config['fields'],
            'attributes'  => $this->resolveAttributeOptions($config['attributes']),
        ]);
    }

    public function create(Request $request, string $resource = ''): Response
    {
        $resource = $this->resolveResource($request, $resource);
        $config = $this->config($resource);

        return Inertia::render($config['page'] . '/Form', [
            'title'       => 'Новый: ' . $config['label'],
            'item'        => null,
            'fields'      => $config['fields'],
            'attributes'  => $this->resolveAttributeOptions($config['attributes']),
            'actions'     => $this->resolveActions($config['actions'], null),
            'locales'     => self::LOCALES,
            'resource'    => $config['name'],
            'image_field' => $config['image_field'],
            'is_edit'     => false,
        ]);
    }

    public function edit(Request $request, string $id): Response
    {
        $resource = $this->resolveResource($request);
        $config = $this->config($resource);
        $m = $this->findModel($config, $id);

        return Inertia::render($config['page'] . '/Form', [
            'title'       => 'Редактирование',
            'item'        => $this->presentForm($m, $config),
            'fields'      => $config['fields'],
            'attributes'  => $this->resolveAttributeOptions($config['attributes']),
            'actions'     => $this->resolveActions($config['actions'], $m),
            'locales'     => self::LOCALES,
            'resource'    => $config['name'],
            'image_field' => $config['image_field'],
            'is_edit'     => true,
        ]);
    }

    /**
     * Resolve closure-based `options` on select attributes at request time.
     * Enables dynamic FK selects:
     *   ['type' => 'select', 'options' => fn () => School::pluck('name','id')
     *       ->map(fn($l,$v) => ['value'=>$v, 'label'=>$l])->values()->all()]
     */
    protected function resolveAttributeOptions(array $attributes): array
    {
        return array_map(function ($a) {
            if (($a['type'] ?? '') === 'select' && isset($a['options']) && is_callable($a['options'])) {
                $a['options'] = call_user_func($a['options']);
            }
            return $a;
        }, $attributes);
    }

    /**
     * Resolve action `url` closures (which may depend on the current
     * model) into plain strings before passing to Vue.
     *
     * Each action entry: ['label', 'icon', 'url', 'description', 'primary'].
     * Actions whose closure-url throws or returns null are dropped.
     */
    protected function resolveActions(array $actions, ?Model $item): array
    {
        $out = [];
        foreach ($actions as $a) {
            $url = $a['url'] ?? null;
            if (is_callable($url)) {
                try {
                    $url = $item ? $url($item) : null;
                } catch (\Throwable) {
                    $url = null;
                }
            }
            if (!$url) continue; // skip actions that have no URL yet (e.g. create screen)
            $out[] = [
                'label'       => $a['label'] ?? '',
                'icon'        => $a['icon'] ?? 'fa-link',
                'url'         => $url,
                'description' => $a['description'] ?? null,
                'primary'     => (bool) ($a['primary'] ?? false),
            ];
        }
        return $out;
    }

    public function store(Request $request, string $resource = ''): RedirectResponse
    {
        $resource = $this->resolveResource($request, $resource);
        $config = $this->config($resource);
        $data = $this->validated($request, $config);

        /** @var Model $m */
        $m = new $config['model']();
        $this->fill($m, $data, $request, $config);
        $m->save();
        $this->saveTranslations($m, $data, $config);

        return redirect($this->resourceIndexUrl($resource))
            ->with('success', $config['label'] . ' создан(а)');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $resource = $this->resolveResource($request);
        $config = $this->config($resource);
        $m = $this->findModel($config, $id);
        $data = $this->validated($request, $config, $m);

        $this->fill($m, $data, $request, $config, $m);
        $m->save();
        $this->saveTranslations($m, $data, $config);

        return redirect($this->resourceIndexUrl($resource))
            ->with('success', 'Сохранено');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $resource = $this->resolveResource($request);
        $config = $this->config($resource);
        $m = $this->findModel($config, $id);

        if ($config['image_field'] && $m->{$config['image_field']}) {
            Storage::disk('public')->delete($m->{$config['image_field']});
        }
        $m->delete();

        return redirect($this->resourceIndexUrl($resource))
            ->with('success', 'Удалено');
    }

    protected function resourceIndexUrl(string $resource): string
    {
        return '/' . config('admin-core.prefix', 'admin') . '/' . $resource;
    }

    public function togglePublish(Request $request, string $id): RedirectResponse
    {
        $resource = $this->resolveResource($request);
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

    /**
     * Resolve the resource name from the current route. We can't rely on
     * Laravel's positional DI because routes registered from
     * routes/admin.php use `->defaults('resource', $name)` plus a URL
     * placeholder `{id}` — positional binding would swap them.
     *
     * Read directly from `$request->route()->parameter('resource')` (which
     * covers both URL params and `->defaults()`). Fallback to the
     * controller-arg value if it was provided.
     */
    protected function resolveResource(Request $request, string $fallback = ''): string
    {
        $route = $request->route();
        if ($route) {
            $name = $route->parameter('resource');
            if (is_string($name) && $name !== '') {
                return $name;
            }
        }
        return $fallback;
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

        // Typed rules for attributes (SimpleField in the sidebar).
        $table = (new $config['model']())->getTable();
        foreach ($config['attributes'] as $a) {
            $rule = $this->ruleForAttribute($a);
            // 'unique' => true  adds unique:{table},{name}[,ignoreId]
            if (!empty($a['unique'])) {
                $ignoreId = $existing?->id;
                $rule .= '|unique:' . $table . ',' . $a['name'] . ($ignoreId ? ',' . $ignoreId : '');
            }
            $rules[$a['name']] = $rule;
        }

        foreach ($config['translatable'] as $field) {
            foreach (self::LOCALES as $locale) {
                $isRequired = $this->fieldIsRequired($field, $config) && $locale === 'ru';
                $rules["{$field}.{$locale}"] = ($isRequired ? 'required' : 'nullable') . '|string';
            }
        }

        return $request->validate($rules);
    }

    protected function ruleForAttribute(array $a): string
    {
        $base = $a['required'] ?? false ? 'required' : 'nullable';
        $type = $a['type'] ?? 'text';
        return match ($type) {
            'email'     => "{$base}|email|max:255",
            'url'       => "{$base}|url|max:2000",
            'number'    => "{$base}|numeric",
            'date'      => "{$base}|date",
            'datetime-local' => "{$base}|date",
            'boolean'   => "nullable|boolean",
            'icon'      => "{$base}|string|max:100",
            'select'    => isset($a['options']) && $a['options']
                ? "{$base}|in:" . implode(',', array_column(is_callable($a['options']) ? call_user_func($a['options']) : $a['options'], 'value'))
                : "{$base}|string",
            default     => "{$base}|string|max:" . ($a['max'] ?? 500),
        };
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

        // Fill typed attributes
        foreach ($config['attributes'] as $a) {
            $name = $a['name'];
            if (($a['type'] ?? '') === 'boolean') {
                $m->{$name} = $request->boolean($name);
            } elseif (array_key_exists($name, $data)) {
                $m->{$name} = $data[$name] !== '' ? $data[$name] : null;
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
        $prefix = config('admin-core.prefix', 'admin');
        // Honour per-resource edit_url closure when set (lets resources
        // redirect row clicks to a related resource, e.g. Pages → Blocks).
        if (is_callable($config['edit_url'] ?? null)) {
            $customUrl = call_user_func($config['edit_url'], $m);
        } else {
            $customUrl = url("/{$prefix}/{$config['name']}/{$m->getRouteKey()}/edit");
        }
        $row = [
            'id'         => $m->id,
            '_route_key' => $m->getRouteKey(),
            'url'        => $customUrl,
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
        foreach ($config['attributes'] as $a) {
            if (Schema::hasColumn($m->getTable(), $a['name'])) {
                $row[$a['name']] = $m->{$a['name']};
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
        // `_route_key` is whatever getRouteKeyName returns for this model —
        // 'id' by default but models like News override to 'slug'. The Vue
        // form builds PUT/DELETE URLs from it, so update/destroy hit the
        // right findModel lookup.
        $out = [
            'id'          => $m->id,
            '_route_key'  => $m->getRouteKey(),
        ];

        foreach ($config['plain'] as $col) {
            if (Schema::hasColumn($m->getTable(), $col)) {
                $v = $m->{$col};
                if ($v instanceof \DateTimeInterface) $v = $v->format('Y-m-d\TH:i');
                $out[$col] = $v;
            }
        }
        foreach ($config['attributes'] as $a) {
            if (Schema::hasColumn($m->getTable(), $a['name'])) {
                $v = $m->{$a['name']};
                if ($v instanceof \DateTimeInterface) {
                    $v = $v->format(($a['type'] ?? '') === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i');
                }
                $out[$a['name']] = $v;
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
