<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Services\CacheService;

class CacheController extends Controller
{
    public function index(): Response
    {
        $groups = CacheService::groups();
        return Inertia::render('Cache/Index', [
            'title'  => 'Кэш',
            'stats'  => CacheService::getStats(),
            'groups' => collect($groups)->map(fn ($info, $key) => [
                'key'         => $key,
                'label'       => $info['label'],
                'description' => $info['description'] ?? null,
            ])->values(),
        ]);
    }

    public function flush(Request $request): RedirectResponse
    {
        $group  = $request->input('group');
        $groups = CacheService::groups();

        if ($group === 'all') {
            CacheService::flushAll();
            return back()->with('success', 'Весь кэш очищен');
        }

        if ($group && array_key_exists($group, $groups)) {
            $count = CacheService::flush($group);
            $label = $groups[$group]['label'];
            return back()->with('success', "Кэш «{$label}» очищен ({$count} ключей)");
        }

        return back()->with('error', 'Неизвестная группа кэша');
    }
}
