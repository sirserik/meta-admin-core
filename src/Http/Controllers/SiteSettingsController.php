<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Models\PageBlock;

/**
 * Tabbed settings page that writes to four specific PageBlock rows:
 *   - header / social_media_links  → Social network links
 *   - rector / settings            → Rector contact info
 *   - header / secondary_logo      → Second logo near the wordmark
 *   - settings / main_menu_config  → Main navigation enabled/order
 *
 * Default menu keys and social networks come from config so each
 * consumer site can tailor them without forking the controller.
 */
class SiteSettingsController extends Controller
{
    protected function defaultMenu(): array
    {
        return (array) config('admin-core.site_settings.menu', [
            'home'          => ['enabled' => true, 'order' => 1,  'label' => 'Главная'],
            'about'         => ['enabled' => true, 'order' => 2,  'label' => 'О нас'],
            'admissions'    => ['enabled' => true, 'order' => 3,  'label' => 'Поступление'],
            'students'      => ['enabled' => true, 'order' => 4,  'label' => 'Студентам'],
            'schools'       => ['enabled' => true, 'order' => 5,  'label' => 'Школы'],
            'science'       => ['enabled' => true, 'order' => 6,  'label' => 'Наука'],
            'international' => ['enabled' => true, 'order' => 7,  'label' => 'Международное'],
            'news'          => ['enabled' => true, 'order' => 8,  'label' => 'Новости'],
            'careers'       => ['enabled' => true, 'order' => 9,  'label' => 'Карьера'],
        ]);
    }

    protected function socialNetworks(): array
    {
        return (array) config('admin-core.site_settings.socials', [
            'facebook'  => ['icon' => 'fab fa-facebook-f',     'label' => 'Facebook'],
            'instagram' => ['icon' => 'fab fa-instagram',      'label' => 'Instagram'],
            'youtube'   => ['icon' => 'fab fa-youtube',        'label' => 'YouTube'],
            'linkedin'  => ['icon' => 'fab fa-linkedin-in',    'label' => 'LinkedIn'],
            'telegram'  => ['icon' => 'fab fa-telegram-plane', 'label' => 'Telegram'],
            'tiktok'    => ['icon' => 'fab fa-tiktok',         'label' => 'TikTok'],
        ]);
    }

    protected function rectorDefaults(): array
    {
        return (array) config('admin-core.site_settings.rector_defaults', [
            'email'          => '',
            'phone'          => '',
            'reception_days' => '',
            'reception_time' => '',
        ]);
    }

    public function index(): Response
    {
        $nets = $this->socialNetworks();
        return Inertia::render('SiteSettings/Index', [
            'title'          => 'Общие настройки',
            'socialLinks'    => $this->loadSocialLinks(),
            'rector'         => $this->loadRector(),
            'secondaryLogo'  => $this->loadSecondaryLogo(),
            'menuConfig'     => $this->loadMenuConfig(),
            'socialNetworks' => collect($nets)
                ->map(fn ($info, $key) => array_merge($info, ['key' => $key]))
                ->values(),
        ]);
    }

    public function updateSocialMedia(Request $request): RedirectResponse
    {
        $nets = $this->socialNetworks();

        $rules = [];
        foreach (array_keys($nets) as $key) {
            $rules["{$key}_url"] = 'nullable|url:http,https|max:500';
        }
        $request->validate($rules);

        $links = [];
        foreach ($nets as $key => $info) {
            $links[$key] = [
                'url'     => $request->input("{$key}_url", ''),
                'enabled' => $request->boolean("{$key}_enabled"),
                'icon'    => $info['icon'],
            ];
        }

        $this->upsertBlock('header', 'social_media_links', [
            'block_type' => 'social-links',
            'title'      => 'Социальные сети',
            'is_active'  => true,
            'sort_order' => 1,
        ], $links);

        return back()->with('success', 'Соц-сети сохранены');
    }

    public function updateRector(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'          => 'required|email',
            'phone'          => 'nullable|string|max:50',
            'reception_days' => 'nullable|string|max:100',
            'reception_time' => 'nullable|string|max:50',
        ]);

        $this->upsertBlock('rector', 'settings', [
            'block_type' => 'settings',
            'title'      => 'Настройки ректора',
            'is_active'  => true,
            'sort_order' => 99,
        ], $data);

        return back()->with('success', 'Контакты ректора сохранены');
    }

    public function updateSecondaryLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'image' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:5120',
            'url'   => 'nullable|url:http,https|max:500',
        ]);

        $block = PageBlock::getBlock('header', 'secondary_logo');
        $data  = $block?->data ?? [];

        if ($request->hasFile('image')) {
            if (!empty($data['image']) && Storage::disk('public')->exists($data['image'])) {
                Storage::disk('public')->delete($data['image']);
            }
            $data['image'] = $request->file('image')->store('blocks/logos', 'public');
        } elseif ($request->boolean('remove_image') && !empty($data['image'])) {
            Storage::disk('public')->delete($data['image']);
            $data['image'] = null;
        }

        $data['url'] = $request->input('url', '');

        $this->upsertBlock('header', 'secondary_logo', [
            'block_type' => 'image',
            'title'      => 'Второй логотип',
            'is_active'  => $request->boolean('enabled'),
            'sort_order' => 0,
        ], $data);

        return back()->with('success', 'Настройки логотипа сохранены');
    }

    public function updateMenu(Request $request): RedirectResponse
    {
        $allowedKeys = array_keys($this->defaultMenu());
        $request->validate([
            'menu'         => 'nullable|array',
            'menu.*'       => 'array',
            'menu.*.order' => 'nullable|integer|min:0|max:100',
        ]);

        $config = [];
        foreach ((array) $request->input('menu', []) as $key => $item) {
            if (!in_array($key, $allowedKeys, true) || !is_array($item)) continue;
            $config[$key] = [
                'enabled' => !empty($item['enabled']),
                'order'   => (int) ($item['order'] ?? 0),
            ];
        }

        $this->upsertBlock('settings', 'main_menu_config', [
            'block_type' => 'menu_settings',
            'title'      => 'Настройки меню',
            'is_active'  => true,
        ], $config);

        return back()->with('success', 'Настройки меню сохранены');
    }

    // -----------------------------------------------------------------

    protected function upsertBlock(string $page, string $key, array $attrs, array $data): PageBlock
    {
        $block = PageBlock::updateOrCreate(
            ['page_name' => $page, 'block_key' => $key],
            $attrs,
        );
        $block->data = $data;
        $block->save();
        return $block;
    }

    protected function loadSocialLinks(): array
    {
        $block  = PageBlock::getBlock('header', 'social_media_links');
        $stored = $block?->data ?? [];
        $out = [];
        foreach ($this->socialNetworks() as $key => $info) {
            $out[$key] = [
                'url'     => $stored[$key]['url']     ?? '',
                'enabled' => $stored[$key]['enabled'] ?? false,
                'icon'    => $info['icon'],
                'label'   => $info['label'],
            ];
        }
        return $out;
    }

    protected function loadRector(): array
    {
        $block = PageBlock::getBlock('rector', 'settings');
        $d     = $block?->data ?? [];
        $defaults = $this->rectorDefaults();
        return [
            'email'          => $d['email']          ?? $defaults['email'],
            'phone'          => $d['phone']          ?? $defaults['phone'],
            'reception_days' => $d['reception_days'] ?? $defaults['reception_days'],
            'reception_time' => $d['reception_time'] ?? $defaults['reception_time'],
        ];
    }

    protected function loadSecondaryLogo(): array
    {
        $block = PageBlock::getBlock('header', 'secondary_logo');
        $d     = $block?->data ?? [];
        return [
            'image'     => $d['image'] ?? null,
            'image_url' => !empty($d['image']) ? media_url($d['image']) : null,
            'url'       => $d['url']   ?? '',
            'enabled'   => (bool) ($block?->is_active ?? false),
        ];
    }

    protected function loadMenuConfig(): array
    {
        $block  = PageBlock::getBlock('settings', 'main_menu_config');
        $stored = $block?->data ?? [];
        $out = [];
        foreach ($this->defaultMenu() as $key => $def) {
            $out[] = [
                'key'     => $key,
                'label'   => $def['label'],
                'enabled' => $stored[$key]['enabled'] ?? $def['enabled'],
                'order'   => $stored[$key]['order']   ?? $def['order'],
            ];
        }
        usort($out, fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $out;
    }
}
