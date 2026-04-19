# 16. Forms API

`POST /api/forms/{slug}` — публичный эндпоинт, принимает заявки из HTML-
форм.

## Форма в админке

Редактор создаёт форму на `/admin/forms`. Каждая имеет:
- `slug` — идентификатор в URL.
- `fields[]` — схема полей `{name, type, label, required, options[], …}`.
- `notify_email` — email для уведомлений.
- `success_message` — текст после отправки.

Подробнее — [user doc 08. Формы](../users/08-forms.md).

## Эндпоинт

```
POST /api/forms/{slug}
Content-Type: application/json

{
    "name": "Иван",
    "email": "ivan@example.com",
    "message": "Здравствуйте"
}
```

Или `multipart/form-data` / `application/x-www-form-urlencoded` —
Laravel нормализует.

### Response — success

```
201 Created
{
    "ok": true,
    "id": 45,
    "message": "Спасибо! Заявка принята."
}
```

### Response — validation error

```
422 Unprocessable Entity
{
    "message": "The name field is required.",
    "errors": {
        "name":  ["The name field is required."],
        "email": ["The email must be a valid email."]
    }
}
```

### Response — форма не найдена

```
404 Not Found
{ "message": "Form not found" }
```

## Валидация

Правила генерируются из схемы полей:

- `required: true` → `required`
- type `email` → `email|max:255`
- type `url` → `url|max:500`
- type `tel` → `string|max:50`
- type `number` → `numeric`
- type `date` → `date`
- type `textarea` → `string|max:10000`
- type `select` / `radio` с `options[]` → `string|in:v1,v2,…`
- type `checkbox` → `boolean`
- unknown / text → `string|max:500`

## Что сохраняется

Каждая отправка — строка в `form_submissions`:

```
form_submissions
 - id
 - form_id          — связь с forms.id
 - data (json)      — весь payload
 - ip_address       — Request::ip()
 - user_agent       — 500 символов max
 - status           — 'new' (default)
 - created_at
```

## Интеграция с HTML-формой

### Нативный submit

```html
<form action="/api/forms/contact" method="POST">
    @csrf
    <input type="text" name="name" required>
    <input type="email" name="email" required>
    <textarea name="message"></textarea>
    <button type="submit">Отправить</button>
</form>
```

Но пакетный эндпоинт отвечает JSON — на нативном submit браузер
покажет JSON в виде текста. Нужен redirect или AJAX.

### AJAX submit

```html
<form id="contact-form">
    <input name="name" required>
    <input name="email" type="email" required>
    <textarea name="message"></textarea>
    <button>Отправить</button>
    <div class="error"></div>
    <div class="success"></div>
</form>

<script>
document.getElementById('contact-form').addEventListener('submit', async e => {
    e.preventDefault();
    const form = e.target;
    const data = Object.fromEntries(new FormData(form));

    const resp = await fetch('/api/forms/contact', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
        },
        body: JSON.stringify(data),
    });

    if (resp.ok) {
        const json = await resp.json();
        form.querySelector('.success').textContent = json.message;
        form.reset();
    } else if (resp.status === 422) {
        const json = await resp.json();
        form.querySelector('.error').textContent =
            Object.values(json.errors).flat().join(' ');
    }
});
</script>
```

## CSRF

По умолчанию роуты `/api/*` **исключены** из CSRF-middleware в
`bootstrap/app.php` consumer-приложения:

```php
$middleware->validateCsrfTokens(except: [
    'api/*',
    'api/leads/*',
]);
```

Если CSRF не исключён — на сайте нужно слать `X-CSRF-TOKEN` заголовок.

## Rate limiting

Сайт должен защитить эндпоинт throttle middleware:

```php
// routes/web.php
RateLimiter::for('forms', fn (Request $r) =>
    Limit::perMinute(5)->by($r->ip())
         ->response(fn () => response()->json(
             ['message' => 'Слишком много попыток'], 429,
         ))
);
```

И обернуть `/api/forms/*`:

```php
Route::middleware(['throttle:forms'])->post('/api/forms/{slug}', ...);
```

Пакет сам этого **не делает** — решает consumer.

## Уведомления

Если в форме указан `notify_email`, на этот адрес автоматом уходит
текстовое письмо:

```
Subject: [Название формы] Новая заявка

Новая заявка через форму «...»:

Array (
    [name] => Иван
    [email] => ivan@example.com
    [message] => Здравствуйте
)
```

Send через `Mail::raw()` — best-effort, ошибки логируются, но не
пробрасываются. То есть SMTP-misconfig не уронит форму.

## Custom email template

Перехвати `FormSubmission::created` event и шли красивое письмо сам:

```php
// App\Providers\EventServiceProvider
Event::listen(
    \Illuminate\Database\Events\ModelsEvent::class,  // или kaскад observer'ов
    function ($submission) {
        if ($submission instanceof \Meta\AdminCore\Models\FormSubmission) {
            \Mail::to($submission->form->notify_email)
                ->send(new \App\Mail\NewSubmission($submission));
        }
    }
);
```

Или через FormSubmission Observer:

```php
class FormSubmissionObserver
{
    public function created(FormSubmission $s): void
    {
        if (!$s->form?->notify_email) return;
        \Mail::to($s->form->notify_email)
            ->send(new \App\Mail\NewFormSubmission($s));
    }
}
```

## Програмная работа с формами

```php
use Meta\AdminCore\Models\Form;
use Meta\AdminCore\Models\FormSubmission;

// Создать форму программно
Form::create([
    'name'   => 'Запись на приёмную комиссию',
    'slug'   => 'admission',
    'fields' => [
        ['name'=>'name',  'type'=>'text',   'label'=>'ФИО',      'required'=>true],
        ['name'=>'phone', 'type'=>'tel',    'label'=>'Телефон',  'required'=>true],
        ['name'=>'program','type'=>'select','label'=>'Программа',
         'options'=>[
             ['value'=>'cs','label'=>'Computer Science'],
             ['value'=>'bus','label'=>'Business'],
         ]],
    ],
    'notify_email' => 'admission@etec.kz',
    'success_message' => 'Мы свяжемся с вами в течение дня',
    'is_active' => true,
]);

// Все заявки
FormSubmission::where('form_id', $form->id)
    ->where('status', 'new')
    ->latest()
    ->get();

// Пометить как обработанную
FormSubmission::find($id)->update(['status' => 'replied']);
```

## Экспорт в CSV

Контроллер экспорта:

```
GET /admin/forms/{id}/submissions/export
```

Стримит CSV с UTF-8 BOM (для Excel), колонки — `#, Когда, Статус, IP,
…поля формы…`.

## Следующее

→ [17. Интеграция с spatie/laravel-permission](./17-permissions.md)
