# 28. Тестирование

Как писать тесты на консьюмер-приложение, которое использует
`meta/admin-core`.

## Setup

```php
// tests/TestCase.php

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Миграции пакетных таблиц накатываются через RefreshDatabase
    }
}
```

## Тест CRUD ресурса

```php
use Tests\TestCase;
use App\Models\User;
use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArticleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_auth(): void
    {
        $this->get('/admin/articles')->assertRedirect('/login');
    }

    public function test_admin_can_list_articles(): void
    {
        $admin = User::factory()->create();
        Article::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get('/admin/articles')
            ->assertStatus(200)
            ->assertInertia(fn ($p) => $p->component('Resource/Index')
                ->has('items.data', 3),
            );
    }

    public function test_admin_can_create_article(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post('/admin/articles', [
                'title'       => ['ru' => 'Hello', 'kk' => '', 'en' => ''],
                'content'     => ['ru' => '<p>…</p>', 'kk' => '', 'en' => ''],
                'slug'        => 'hello',
                'is_published'=> true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('articles', ['slug' => 'hello']);
    }
}
```

## Тест PageBlock

```php
use Meta\AdminCore\Models\PageBlock;

class PageBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_scope_honours_publish_at(): void
    {
        $future = now()->addHour();
        PageBlock::factory()->create([
            'status'     => 'draft',
            'publish_at' => $future,
            'is_active'  => true,
        ]);

        $this->assertEmpty(PageBlock::published()->get());
    }

    public function test_due_publish_finds_ready_drafts(): void
    {
        PageBlock::factory()->create([
            'status'     => 'draft',
            'publish_at' => now()->subHour(),
        ]);

        $this->assertCount(1, PageBlock::duePublish()->get());
    }
}
```

## Тест Revisionable

```php
class ArticleRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_creates_revision(): void
    {
        $a = Article::factory()->create(['title' => 'Hello']);
        $this->assertCount(0, $a->revisions);

        $a->update(['title' => 'World']);
        $a->refresh();

        $this->assertCount(1, $a->revisions);
        $this->assertEquals('Hello', $a->revisions->first()->data['title']);
    }

    public function test_restore_revision(): void
    {
        $a = Article::factory()->create(['title' => 'v1']);
        $a->update(['title' => 'v2']);
        $a->update(['title' => 'v3']);

        $rev = $a->revisions->last();  // snapshot перед v2 → должен содержать v1
        $a->restoreRevision($rev->id);
        $a->refresh();

        $this->assertEquals('v1', $a->title);
    }

    public function test_without_revision_skips(): void
    {
        $a = Article::factory()->create();
        $a->withoutRevision(fn () => $a->update(['title' => 'silent']));

        $this->assertCount(0, $a->revisions);
    }
}
```

## Тест Publishable + apply-schedule

```php
class ApplyScheduleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticker_publishes_due_drafts(): void
    {
        $b = PageBlock::factory()->create([
            'status'     => 'draft',
            'publish_at' => now()->subMinute(),
        ]);

        $this->artisan('admin-core:apply-schedule')->assertSuccessful();

        $this->assertEquals('published', $b->fresh()->status);
    }

    public function test_dry_run_does_not_write(): void
    {
        $b = PageBlock::factory()->create([
            'status'     => 'draft',
            'publish_at' => now()->subMinute(),
        ]);

        $this->artisan('admin-core:apply-schedule', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertEquals('draft', $b->fresh()->status);
    }
}
```

## Тест Webhooks

```php
use Illuminate\Support\Facades\Http;
use Meta\AdminCore\Models\Webhook;
use Meta\AdminCore\Models\PageBlock;

class WebhookDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_updating_fires_webhook(): void
    {
        Http::fake();

        Webhook::create([
            'label'     => 'Test',
            'url'       => 'https://example.com/hook',
            'events'    => ['page_blocks.updated'],
            'is_active' => true,
        ]);

        $block = PageBlock::factory()->create();
        $block->update(['title' => 'Updated']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://example.com/hook'
                && $request['event'] === 'page_blocks.updated';
        });
    }
}
```

## Тест Taxable

```php
class ArticleTaxableTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_terms(): void
    {
        $a = Article::factory()->create();
        $a->syncTerms('tag', ['science', 'opinion']);

        $this->assertEquals(['opinion','science'], $a->terms()->pluck('slug')->sort()->values()->all());
    }

    public function test_with_term_scope(): void
    {
        $a = Article::factory()->create();
        $a->syncTerms('tag', ['science']);

        $b = Article::factory()->create();
        $b->syncTerms('tag', ['opinion']);

        $this->assertEquals(
            [$a->id],
            Article::withTerm('tag', 'science')->pluck('id')->all(),
        );
    }
}
```

## Тест Content API

```php
class ContentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_articles(): void
    {
        Article::factory()->count(3)->create(['status' => 'published']);
        Article::factory()->create(['status' => 'draft']);

        $response = $this->getJson('/api/content/articles');

        $response->assertOk()
                 ->assertJsonCount(3, 'data');
    }

    public function test_locale_query(): void
    {
        $a = Article::factory()->create(['status' => 'published']);
        $a->saveTranslations('en', ['title' => 'English']);

        $this->getJson('/api/content/articles?locale=en')
             ->assertOk()
             ->assertJsonPath('data.0.title', 'English');
    }
}
```

## Factory для PageBlock

```php
// database/factories/PageBlockFactory.php
namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Meta\AdminCore\Models\PageBlock;

class PageBlockFactory extends Factory
{
    protected $model = PageBlock::class;

    public function definition(): array
    {
        return [
            'page_name'  => 'home',
            'block_key'  => $this->faker->slug(),
            'block_type' => 'content',
            'title'      => $this->faker->sentence(3),
            'content'    => '<p>' . $this->faker->paragraph() . '</p>',
            'is_active'  => true,
            'status'     => 'published',
            'sort_order' => 0,
        ];
    }

    public function draft(): self
    {
        return $this->state(['status' => 'draft']);
    }
}
```

## Browser-тесты (Dusk)

Для end-to-end — можно использовать Dusk. Админка Inertia-based, нужны
корректные селекторы:

```php
class DuskAdminTest extends \Laravel\Dusk\Browser
{
    public function test_create_block()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs(User::find(1))
                    ->visit('/admin/blocks/create')
                    ->select('@page-select', 'home')
                    ->click('@type-picker-hero')
                    ->type('@title-ru', 'Тестовый блок')
                    ->press('Создать')
                    ->waitForText('Блок создан');
        });
    }
}
```

Добавляй `dusk="page-select"` атрибуты в Vue для стабильных селекторов.

## Mocking внешних API

- Webhooks: `Http::fake()` / `Http::assertSent()`.
- Image uploads: `Storage::fake('public')`.
- Mail notifications: `Mail::fake()` / `Mail::assertSent()`.

## Покрытие пакета

Пакетные юнит-тесты лежат в `tests/` самого `meta-admin-core`.
Запускать: `vendor/bin/phpunit`. Покрытие сейчас частичное — фокус на
критичной логике (Publishable scopes, Revisionable restore,
WebhookDispatcher HMAC).

## Следующее

→ [29. Траблшутинг](./29-troubleshooting.md)
