<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\Webhook;
use Meta\AdminCore\Services\WebhookDispatcher;

/**
 * Outbound webhooks — admin CRUD + manual test-fire.
 *
 * Events are auto-discovered from every model that uses the
 * Webhookable trait. Consumers contribute additional events via
 * `AdminCore::webhookEvent($name, $description)` (see facade/service).
 */
class WebhooksController extends Controller
{
    public function index(): Response
    {
        $rows = Webhook::query()
            ->latest('id')
            ->get()
            ->map(fn (Webhook $h) => [
                'id'            => $h->id,
                'label'         => $h->label,
                'url'           => $h->url,
                'events'        => $h->events ?? [],
                'is_active'     => (bool) $h->is_active,
                'last_fired_at' => optional($h->last_fired_at)->format('d.m.Y H:i'),
                'has_secret'    => !empty($h->secret),
            ]);

        return Inertia::render('Webhooks/Index', [
            'title'         => 'Webhooks',
            'webhooks'      => $rows,
            'knownEvents'   => $this->knownEvents(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Webhook::create($data);
        return back()->with('success', 'Webhook создан');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $hook = Webhook::findOrFail($id);
        $data = $this->validated($request);
        // Empty secret input leaves the stored secret untouched —
        // avoids "editing the label wiped my HMAC key" footguns.
        if (empty($data['secret'])) unset($data['secret']);
        $hook->update($data);
        return back()->with('success', 'Webhook сохранён');
    }

    public function destroy(int $id): RedirectResponse
    {
        Webhook::findOrFail($id)->delete();
        return back()->with('success', 'Webhook удалён');
    }

    public function test(int $id): RedirectResponse
    {
        $hook = Webhook::findOrFail($id);
        app(WebhookDispatcher::class)->dispatch('admin.webhook_test', [
            'hook_id' => $hook->id,
            'label'   => $hook->label,
            'message' => 'Тестовое событие с /admin/webhooks',
        ]);
        return back()->with('success', "Тест отправлен на {$hook->url}");
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'label'     => 'required|string|max:150',
            'url'       => 'required|url|max:500',
            'events'    => 'required|array|min:1',
            'events.*'  => 'string|max:100',
            'secret'    => 'nullable|string|max:120',
            'is_active' => 'nullable|boolean',
        ]);
    }

    /**
     * Best-effort list of event names the admin can subscribe to.
     * Inspects registered resources + the built-in PageBlock;
     * consumers contribute more via AdminCore::webhookEvent().
     */
    protected function knownEvents(): array
    {
        $actions = ['created', 'updated', 'deleted'];
        $out = [];
        foreach (\Meta\AdminCore\Facades\AdminCore::getResources() as $name => $cfg) {
            $table = isset($cfg['model']) && class_exists($cfg['model'])
                ? (new $cfg['model'])->getTable()
                : $name;
            foreach ($actions as $a) {
                $out[] = ['name' => "{$table}.{$a}", 'label' => ($cfg['label'] ?? $name) . " — {$a}"];
            }
        }
        // Built-in models that already use Webhookable.
        foreach (['page_blocks'] as $table) {
            foreach ($actions as $a) {
                $out[] = ['name' => "{$table}.{$a}", 'label' => "PageBlock — {$a}"];
            }
        }
        // Deduplicate by event name, preserve first label.
        $seen = [];
        $dedup = [];
        foreach ($out as $row) {
            if (isset($seen[$row['name']])) continue;
            $seen[$row['name']] = true;
            $dedup[] = $row;
        }
        return $dedup;
    }
}
