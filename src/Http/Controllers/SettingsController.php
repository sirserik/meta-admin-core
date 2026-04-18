<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Events\SettingUpdated;
use Meta\AdminCore\Models\Setting;

class SettingsController extends Controller
{
    protected const LOCALES = ['ru', 'kk', 'en'];

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'group']);
        $query = Setting::query()->orderBy('group')->orderBy('key');

        if (!empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('key', 'like', "%{$term}%")
                  ->orWhere('description', 'like', "%{$term}%");
            });
        }
        if (!empty($filters['group'])) {
            $query->where('group', $filters['group']);
        }

        $grouped = $query->get()
            ->groupBy('group')
            ->map(fn ($items) => $items->map(fn (Setting $s) => $this->presentSetting($s))->values())
            ->toArray();

        return Inertia::render('Settings/Index', [
            'title'     => 'Настройки сайта',
            'groups'    => $grouped,
            'allGroups' => Setting::getGroups(),
            'filters'   => $filters,
            'locales'   => self::LOCALES,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $setting = Setting::findOrFail($id);

        $data = $request->validate([
            'value.ru' => 'nullable|string',
            'value.kk' => 'nullable|string',
            'value.en' => 'nullable|string',
        ]);

        $ru = $data['value']['ru'] ?? '';
        $new = [
            'ru' => $ru,
            'kk' => $data['value']['kk'] ?? $ru,
            'en' => $data['value']['en'] ?? $ru,
        ];

        $old = is_array($setting->value) ? $setting->value : [];
        $setting->update(['value' => $new]);

        // Consumer apps can hook in without overriding the controller.
        event(new SettingUpdated($setting->fresh(), $old, $new));

        return back()->with('success', 'Настройка обновлена');
    }

    protected function presentSetting(Setting $s): array
    {
        $value = is_array($s->value)
            ? $s->value
            : ['ru' => (string) $s->value, 'kk' => '', 'en' => ''];

        return [
            'id'          => $s->id,
            'key'         => $s->key,
            'group'       => $s->group,
            'type'        => $s->type ?? 'text',
            'description' => $s->description,
            'value'       => [
                'ru' => $value['ru'] ?? '',
                'kk' => $value['kk'] ?? '',
                'en' => $value['en'] ?? '',
            ],
        ];
    }
}
