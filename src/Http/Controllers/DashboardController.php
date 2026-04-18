<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Facades\AdminCore;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'title'         => 'Dashboard',
            'stats'         => AdminCore::dashboardStats(),
            'recent'        => $this->buildRecent(),
            'quickActions'  => AdminCore::getDashboardQuickActions(),
        ]);
    }

    /**
     * For every AdminCore::dashboardRecent() registration, fetch the last N
     * rows of that resource and render them into a lightweight shape the
     * Vue widget can consume.
     */
    protected function buildRecent(): array
    {
        $prefix = config('admin-core.prefix', 'admin');
        $out = [];

        foreach (AdminCore::getDashboardRecent() as $cfg) {
            $resource = AdminCore::getResource($cfg['resource']);
            if (!$resource) continue;

            /** @var class-string<Model> $modelClass */
            $modelClass = $resource['model'];
            $limit = (int) ($cfg['limit'] ?? 5);

            // Find a sensible timestamp column to sort by.
            $table = (new $modelClass)->getTable();
            $orderCol = Schema::hasColumn($table, 'created_at') ? 'created_at'
                : (Schema::hasColumn($table, 'id') ? 'id' : null);

            $query = $modelClass::query();
            if ($orderCol) $query->orderByDesc($orderCol);
            $items = $query->limit($limit)->get();

            $rows = $items->map(function (Model $m) use ($resource, $prefix) {
                $title = $this->rowTitle($m, $resource);
                return [
                    'id'    => $m->id,
                    'title' => $title,
                    'url'   => url("/{$prefix}/{$resource['name']}/{$m->getRouteKey()}/edit"),
                    'date'  => optional($m->created_at)->isoFormat('D MMM YYYY'),
                ];
            })->all();

            $out[] = [
                'resource' => $resource['name'],
                'label'    => $cfg['label'] ?? $resource['label'],
                'icon'     => $cfg['icon'] ?? $resource['icon'],
                'index_url'=> "/{$prefix}/{$resource['name']}",
                'items'    => $rows,
            ];
        }

        return $out;
    }

    protected function rowTitle(Model $m, array $resource): string
    {
        // Prefer translated title/name (ru), fall back to physical column
        foreach (['title', 'name'] as $f) {
            if (in_array($f, $resource['translatable'] ?? [], true) && method_exists($m, 'translate')) {
                $v = $m->translate($f, 'ru') ?? $m->{$f} ?? null;
                if ($v) return (string) $v;
            }
            if (Schema::hasColumn($m->getTable(), $f) && !empty($m->{$f})) {
                return (string) $m->{$f};
            }
        }
        return "#{$m->id}";
    }
}
