<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Services\ThemeService;

class ThemeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Theme/Index', [
            'title'  => 'Тема сайта',
            'tokens' => ThemeService::getTokens(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $tokens = $request->input('tokens', []);
        if (!is_array($tokens)) {
            return back()->with('error', 'Некорректные данные');
        }
        ThemeService::saveOverrides($tokens);
        return back()->with('success', 'Тема обновлена');
    }

    public function reset(): RedirectResponse
    {
        ThemeService::saveOverrides([]);
        return back()->with('success', 'Тема сброшена');
    }
}
