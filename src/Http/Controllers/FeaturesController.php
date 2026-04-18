<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Facades\AdminCore;

class FeaturesController extends Controller
{
    public function index(): Response
    {
        $modules = AdminCore::getFeatures();
        $rows = [];
        foreach ($modules as $m) {
            $rows[] = [
                'name'              => $m->name(),
                'label'             => $m->label(),
                'description'       => $m->description(),
                'icon'              => $m->icon(),
                'enabled'           => AdminCore::enabled($m->name()),
                'available'         => $m->available(),
                'unavailableReason' => $m->unavailableReason(),
            ];
        }
        // Sort: available + enabled first, then available, then unavailable
        usort($rows, fn ($a, $b) => ($b['available'] <=> $a['available']) ?: ($b['enabled'] <=> $a['enabled']) ?: strcmp($a['label'], $b['label']));

        return Inertia::render('Features/Index', [
            'title'    => 'Фичи',
            'features' => $rows,
        ]);
    }

    public function toggle(Request $request, string $feature): RedirectResponse
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        AdminCore::setEnabled($feature, (bool) $data['enabled']);

        // Clear caches so routes/navigation re-resolve with new flags.
        Artisan::call('route:clear');
        Artisan::call('config:clear');

        $label = ($data['enabled'] ? 'Включена' : 'Выключена');
        return back()->with('success', "{$label} фича «{$feature}». Обнови страницу чтобы увидеть изменения в сайдбаре.");
    }
}
