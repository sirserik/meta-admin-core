# 13. Webhooks — trait `Webhookable` + `WebhookDispatcher`

Исходящие HTTP-callback'и на события CRUD. Внешний мир узнаёт об
изменениях в CMS.

## Use cases

- Rebuild статического фронтенда (Vercel, Netlify, Cloudflare Pages).
- Reindex поискового движка (Meilisearch, Algolia).
- Уведомления в Slack / Telegram / Discord.
- Репликация в mobile-app через push.
- Бэкап критичных событий в отдельную систему.

## Архитектура

```
Model::updating() → bootWebhookable() → WebhookDispatcher::dispatch()
                                              ↓
                                    Webhook::where(is_active)->get()
                                              ↓
                                    foreach matching event:
                                       POST URL (HMAC-подписан)
                                              ↓
                                    Webhook::last_fired_at = now()
```

## Подключение

### Trait на модель

```php
use Meta\AdminCore\Concerns\Webhookable;

class Article extends Model
{
    use Webhookable;
}
```

На каждый `created` / `updated` / `deleted` — в очередь.

### Opt-out одной операции

```php
$article->withoutWebhook(function () use ($article) {
    $article->update(['views_count' => 42]); // → webhook не шлётся
});
```

## Имя события

По умолчанию — `{table}.{action}`:

- `articles.created`
- `articles.updated`
- `articles.deleted`
- `page_blocks.updated`

Override для кастомного имени:

```php
class Article extends Model
{
    use Webhookable;

    public function webhookEventName(string $action): string
    {
        return "etec.article.{$action}";
    }
}
```

## Payload

По умолчанию:

```json
{
    "event": "articles.updated",
    "delivered_at": "2026-04-19T14:23:15+00:00",
    "payload": {
        "id": 42,
        "data": { /* весь model->toArray() */ }
    }
}
```

Override:

```php
public function webhookPayload(string $action): array
{
    return [
        'id'        => $this->id,
        'slug'      => $this->slug,
        'title'     => $this->translate('title', 'ru'),
        'published' => $this->published_at?->toIso8601String(),
    ];
}
```

## Настройка webhook'ов

Через админку `/admin/webhooks`:
- Название (для себя).
- URL.
- Список событий (checkbox'ы — сгруппированы по таблицам).
- HMAC-секрет (опционально).
- Активен / не активен.

Подробнее — [13. Webhooks (user docs)](../users/13-webhooks.md).

## HMAC-подпись

Если у webhook'а есть секрет, каждый запрос идёт с заголовком:

```
X-AdminCore-Signature: sha256=a5b2c…
```

Где подпись — HMAC-SHA256 от **тела запроса** (без изменений).

Пример проверки на Node.js:

```js
import crypto from 'node:crypto';

export function verifySignature(body, signature, secret) {
    const expected = 'sha256=' + crypto.createHmac('sha256', secret)
        .update(body)
        .digest('hex');
    return crypto.timingSafeEqual(
        Buffer.from(expected),
        Buffer.from(signature),
    );
}
```

На Python:

```python
import hmac, hashlib

def verify(body: bytes, signature: str, secret: str) -> bool:
    expected = 'sha256=' + hmac.new(
        secret.encode(), body, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature)
```

## Программный вызов dispatcher'а

Не всегда webhook'и привязаны к модели. Для ad-hoc событий:

```php
app(\Meta\AdminCore\Services\WebhookDispatcher::class)->dispatch(
    'custom.admission_deadline_extended',
    [
        'old_deadline' => '2026-07-01',
        'new_deadline' => '2026-07-15',
        'extended_by'  => auth()->user()->name,
    ],
);
```

Админка в списке событий увидит «`custom.admission_deadline_extended`» —
редактор может подписаться.

Но имя события не попадёт в стандартный фильтр UI (он генерится из
зарегистрированных ресурсов) — для кастомных событий нужно либо
добавить их вручную в `Webhook::events`, либо расширить контроллер
`WebhooksController::knownEvents()`.

## Retries / queuing

Пакет **не queue'ит** dispatch — вызывается синхронно в том же запросе,
что сохранил модель. Если внешний сервер недоступен:
- Ошибка логируется (Laravel Log).
- Сохранение модели **не откатывается** — webhook это best-effort.

Для at-least-once delivery оберни dispatch в job:

```php
class DispatchWebhooksJob implements ShouldQueue
{
    public function __construct(
        public string $event,
        public array $payload,
    ) {}

    public function handle(WebhookDispatcher $d): void
    {
        $d->dispatch($this->event, $this->payload);
    }
}
```

И override в trait:

```php
public static function bootWebhookable(): void
{
    $emit = fn ($action) => function ($model) use ($action) {
        if ($model->skipWebhook) return;
        DispatchWebhooksJob::dispatch(
            $model->webhookEventName($action),
            $model->webhookPayload($action),
        );
    };

    static::created($emit('created'));
    static::updated($emit('updated'));
    static::deleted($emit('deleted'));
}
```

## Что НЕ отправляется автоматически

- `saved` (он overkill — либо created либо updated).
- `saving` / `creating` / `updating` (pre-events).
- Soft-deletes `forceDeleted`, `restored` — не hook'нуты. Добавляй
  вручную через override.

## Тестирование получателя локально

Две опции:

### 1. `webhook.site`

Зарегистрируй https://webhook.site → получи уникальный URL → вставь
в webhook → нажми «Тест» в админке. Увидишь весь запрос.

### 2. ngrok

```bash
ngrok http 3000
# → даёт https://abcd.ngrok.io → направь webhook туда, у себя
```

## Performance

Pakage делает последовательный POST на каждый зарегистрированный
webhook. Если их 10 — после сохранения прилетит 10 синхронных HTTP-
запросов. Таймаут каждого — 10 сек.

Для сайтов с высоким RPS — queue'й через job (см. выше).

## Deleted event + security

При `deleted` payload содержит последнее состояние модели. Если модель
содержала чувствительные данные (пароли, токены) — отфильтруй их:

```php
public function webhookPayload(string $action): array
{
    return array_diff_key(
        parent::webhookPayload($action),
        array_flip(['password', 'api_token', 'remember_token']),
    );
}
```

## Следующее

→ [14. Content API](./14-content-api.md)
