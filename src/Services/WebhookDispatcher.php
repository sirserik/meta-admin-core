<?php

namespace Meta\AdminCore\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Meta\AdminCore\Models\Webhook;

/**
 * Fire-and-forget webhook dispatcher.
 *
 * Given an event name and payload, looks up every active webhook that
 * listens to the event and POSTs the JSON body. Signs the body with
 * HMAC-SHA256 when the webhook row has a `secret` — consumers verify
 * via the `X-AdminCore-Signature` header (scheme: `sha256=…`).
 *
 * Errors are swallowed into the log; webhooks must not crash the
 * admin UI when the receiver is down. For at-least-once delivery
 * consumers can queue the dispatch via Laravel's default job pipe.
 */
class WebhookDispatcher
{
    public function dispatch(string $event, array $payload): void
    {
        $hooks = Webhook::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $w) => $w->listensTo($event));

        if ($hooks->isEmpty()) return;

        $body = [
            'event'      => $event,
            'delivered_at' => Carbon::now()->toIso8601String(),
            'payload'    => $payload,
        ];
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($hooks as $hook) {
            try {
                $headers = [
                    'Content-Type'        => 'application/json',
                    'X-AdminCore-Event'   => $event,
                    'X-AdminCore-Hook-Id' => (string) $hook->id,
                ];

                if (!empty($hook->secret)) {
                    $sig = hash_hmac('sha256', $json, (string) $hook->secret);
                    $headers['X-AdminCore-Signature'] = "sha256={$sig}";
                }

                Http::withHeaders($headers)
                    ->timeout(10)
                    ->withBody($json, 'application/json')
                    ->post((string) $hook->url);

                $hook->forceFill(['last_fired_at' => Carbon::now()])->save();
            } catch (\Throwable $e) {
                Log::warning('admin-core webhook failed', [
                    'event' => $event,
                    'hook'  => $hook->id,
                    'url'   => $hook->url,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
