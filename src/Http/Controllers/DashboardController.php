<?php

namespace Meta\AdminCore\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Meta\AdminCore\Facades\AdminCore;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard', [
            'title' => 'Dashboard',
            'stats' => AdminCore::dashboardStats(),
        ]);
    }
}
