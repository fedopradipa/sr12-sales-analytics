<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;

final class AuthService
{
    private const OPERATOR_ROUTES = [
        'produk' => 'admin.produk.index',
        'transaksi' => 'admin.transaksi.index',
        'kmeans' => 'admin.kmeans.index',
        'des' => 'admin.des.index',
        'kalibrasi' => 'admin.kalibrasi-parameter.index',
        'laporan.segmentasi' => 'admin.laporan.segmentasi.index',
        'laporan.prediksi' => 'admin.laporan.peramalan.index',
    ];

    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /** @param array<string, mixed> $credentials */
    public function login(
        array $credentials,
        bool $remember = false
    ): bool {
        return Auth::attempt(
            $credentials,
            $remember
        );
    }

    public function logout(): void
    {
        Auth::logout();
    }

    public function redirectPath(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if (
            $this->dashboardService
                ->canAccess($user)
        ) {
            return route('admin.dashboard');
        }

        foreach (
            self::OPERATOR_ROUTES as $permission => $route
        ) {
            if ($user->hasPermission($permission)) {
                return route($route);
            }
        }

        return null;
    }
}
