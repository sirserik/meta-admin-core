<?php

namespace Meta\AdminCore\Concerns;

use Meta\AdminCore\Services\WebhookDispatcher;

/**
 * Auto-dispatch created / updated / deleted webhooks for a model.
 *
 *   class Article extends Model {
 *       use \Meta\AdminCore\Concerns\Webhookable;
 *   }
 *
 * Event names follow the convention `{table}.{action}` (e.g.
 * `articles.created`). Payload is the model's public attributes plus
 * `id`. Override `webhookPayload()` to customize what's sent.
 *
 * Opt-out for a particular save with `$model->withoutWebhook(fn () => …)`.
 */
trait Webhookable
{
    protected bool $skipWebhook = false;

    public static function bootWebhookable(): void
    {
        $emit = function ($action) {
            return function ($model) use ($action) {
                if ($model->skipWebhook) return;
                $name = $model->webhookEventName($action);
                app(WebhookDispatcher::class)->dispatch($name, $model->webhookPayload($action));
            };
        };

        static::created($emit('created'));
        static::updated($emit('updated'));
        static::deleted($emit('deleted'));
    }

    public function webhookEventName(string $action): string
    {
        $table = $this->getTable();
        return "{$table}.{$action}";
    }

    public function webhookPayload(string $action): array
    {
        return [
            'id'    => $this->getKey(),
            'data'  => $this->toArray(),
        ];
    }

    public function withoutWebhook(\Closure $fn): mixed
    {
        $prev = $this->skipWebhook;
        $this->skipWebhook = true;
        try { return $fn($this); } finally { $this->skipWebhook = $prev; }
    }
}
