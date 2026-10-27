<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DesController;
use App\Http\Controllers\Admin\DetailTransaksiController;
use App\Http\Controllers\Admin\KmeansController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\ProdukController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Middleware\AdminMiddleware;
use App\Services\Auth\AuthService;
use Illuminate\Support\Facades\Route;

Route::get('/', function (AuthService $authService) {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    $path = $authService->redirectPath(auth()->user());

    return $path === null
        ? redirect()->route('login')
        : redirect()->to($path);
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'index'])
        ->name('login');
    Route::post('/login', [LoginController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [LoginController::class, 'destroy'])
        ->name('logout');

    Route::name('admin.')->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::put(
            'produk/{produk}/stok',
            [ProdukController::class, 'updateStock']
        )
            ->middleware('permission:produk')
            ->name('produk.stok.update');

        Route::resource('produk', ProdukController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->middleware('permission:produk');

        Route::controller(DetailTransaksiController::class)
            ->prefix('transaksi')
            ->name('transaksi.')
            ->middleware('permission:transaksi')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/import', 'createImport')->name('import.create');

                Route::delete(
                    '/hapus-semua',
                    'destroyAll'
                )->name('destroy-all');

                Route::put(
                    '/{detailTransaksi}',
                    'update'
                )->name('update');

                Route::delete(
                    '/{detailTransaksi}',
                    'destroy'
                )->name('destroy');
                Route::post('/import', 'storeImport')->name('import.store');
                Route::get('/template', 'downloadTemplate')->name('template');
            });

        Route::controller(KmeansController::class)
            ->prefix('segmentasi')
            ->name('kmeans.')
            ->middleware('permission:kmeans')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::get('/{hasilKmeans}', 'show')->name('show');
            });

        Route::controller(DesController::class)
            ->prefix('peramalan')
            ->name('des.')
            ->middleware('permission:des')
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/proses/{prosesDes}', 'showProcess')
                    ->name('process.show');
            });

        Route::controller(DesController::class)
            ->prefix('kalibrasi-parameter')
            ->name('kalibrasi-parameter.')
            ->middleware('permission:kalibrasi')
            ->group(function (): void {
                Route::get('/', 'calibration')
                    ->name('index');
                Route::post('/', 'calibrate')
                    ->name('store');
            });

        Route::controller(LaporanController::class)
            ->prefix('laporan')
            ->name('laporan.')
            ->group(function (): void {
                Route::get('/', 'index')
                    ->name('index');

                Route::get('/segmentasi', 'segmentasi')
                    ->middleware(
                        'permission:laporan.segmentasi'
                    )
                    ->name('segmentasi.index');

                Route::get(
                    '/kmeans/{hasilKmeans}/pdf',
                    'downloadKmeans'
                )
                    ->middleware(
                        'permission:laporan.segmentasi'
                    )
                    ->name('kmeans.pdf');

                Route::get('/peramalan', 'peramalan')
                    ->middleware(
                        'permission:laporan.prediksi'
                    )
                    ->name('peramalan.index');

                Route::get(
                    '/des/batch/{prosesDes}/pdf',
                    'downloadDesBatch'
                )
                    ->middleware(
                        'permission:laporan.prediksi'
                    )
                    ->name('des.batch.pdf');

                Route::get(
                    '/des/{hasilDes}/pdf',
                    'downloadDes'
                )
                    ->middleware(
                        'permission:laporan.prediksi'
                    )
                    ->name('des.pdf');
            });

        Route::controller(UserController::class)
            ->prefix('admin/pengguna')
            ->name('users.')
            ->middleware(AdminMiddleware::class)
            ->group(function (): void {
                Route::get('/', 'index')->name('index');
                Route::get('/create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('/{user}/edit', 'edit')->name('edit');
                Route::put('/{user}', 'update')->name('update');
                Route::delete('/{user}', 'destroy')->name('destroy');
            });
    });
});
