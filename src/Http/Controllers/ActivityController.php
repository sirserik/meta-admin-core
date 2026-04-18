<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\ActivityLog;

class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->only(['user_id', 'action', 'search']);

        $query = ActivityLog::query()
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if (!empty($filters['user_id'])) $query->where('user_id', $filters['user_id']);
        if (!empty($filters['action']))  $query->where('action', $filters['action']);
        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('description', 'like', "%{$term}%")
                  ->orWhere('model_type', 'like', "%{$term}%");
            });
        }

        $paginator = $query->paginate(30)->withQueryString();
        $paginator->getCollection()->transform(fn (ActivityLog $l) => [
            'id'          => $l->id,
            'user'        => $l->user ? ['id' => $l->user->id, 'name' => $l->user->name] : null,
            'action'      => $l->action,
            'model_type'  => $l->model_type ? class_basename($l->model_type) : null,
            'model_id'    => $l->model_id,
            'description' => $this->humanizeDescription($l->description),
            'ip_address'  => $l->ip_address,
            'created_at'  => optional($l->created_at)->format('d.m.Y H:i:s'),
        ]);

        return Inertia::render('Activity/Index', [
            'title'   => 'Журнал действий',
            'items'   => $paginator,
            'actions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => $filters,
        ]);
    }

    /**
     * Unwrap JSON-encoded multi-locale titles from activity descriptions.
     *
     * When a consumer's ActivityLog::log() helper auto-generates the
     * description from `$model->title` and that field happens to store
     * `{"ru":"...","kk":"..."}` (multi-locale JSON), the raw JSON leaks
     * into the log. Detect that pattern and replace with the ru value.
     */
    protected function humanizeDescription(?string $raw): ?string
    {
        if ($raw === null || $raw === '') return $raw;

        return preg_replace_callback(
            '/«(\{.+?\})»/u',
            function ($matches) {
                $decoded = json_decode($matches[1], true);
                if (!is_array($decoded)) return $matches[0];
                $value = $decoded['ru'] ?? $decoded['kk'] ?? $decoded['en'] ?? null;
                if ($value === null || $value === '') return $matches[0];
                return '«' . trim((string) $value) . '»';
            },
            $raw,
        );
    }
}
