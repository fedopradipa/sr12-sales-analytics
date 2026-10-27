<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $this->dashboardService
                ->canAccess($user),
            403,
            'Anda tidak memiliki dashboard untuk hak akses yang diberikan.'
        );

        return view(
            'admin.dashboard.index',
            $this->dashboardService
                ->getDashboardData($user)
        );
    }
}
