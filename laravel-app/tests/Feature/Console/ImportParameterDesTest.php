<?php

use App\Models\ParameterDes;
use App\Models\Produk;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    File::ensureDirectoryExists(
        storage_path(
            'framework/testing/parameter-des'
        )
    );
});

afterEach(function (): void {
    File::delete(
        File::glob(
            storage_path(
                'framework/testing/parameter-des/*.csv'
            )
        )
    );
});

function parameterDesCommandProducts(): array
{
    $first = Produk::query()->create([
        'kode' => 'SR011',
        'nama' => 'Produk SR011',
    ]);

    $second = Produk::query()->create([
        'kode' => 'SR015',
        'nama' => 'Produk SR015',
    ]);

    return compact(
        'first',
        'second'
    );
}

function writeParameterDesCsv(
    string $name,
    string $contents
): string {
    $path = storage_path(
        'framework/testing/parameter-des/'
        .$name
    );

    file_put_contents(
        $path,
        $contents
    );

    return $path;
}

it('mengimpor parameter alpha berdasarkan kode produk', function (): void {
    $products = parameterDesCommandProducts();

    $path = writeParameterDesCsv(
        'valid.csv',
        "kode_produk,alpha\n"
        ."SR011,0.25\n"
        ."SR015,0.38\n"
    );

    $exitCode = Artisan::call(
        'des:import-parameter',
        ['file' => $path]
    );

    expect($exitCode)
        ->toBe(SymfonyCommand::SUCCESS)
        ->and(Artisan::output())
        ->toContain(
            'Parameter DES berhasil diimpor.'
        );

    $this->assertDatabaseHas(
        'parameter_des',
        [
            'produk_id' => $products['first']->id,
            'alpha' => 0.25,
        ]
    );

    $this->assertDatabaseHas(
        'parameter_des',
        [
            'produk_id' => $products['second']->id,
            'alpha' => 0.38,
        ]
    );

    $this->assertDatabaseCount(
        'parameter_des',
        2
    );
});

it('memperbarui alpha produk yang sudah memiliki parameter', function (): void {
    $products = parameterDesCommandProducts();

    ParameterDes::query()->create([
        'produk_id' => $products['first']->id,
        'alpha' => 0.25,
    ]);

    $path = writeParameterDesCsv(
        'update.csv',
        "kode_produk,alpha\n"
        ."SR011,0.40\n"
    );

    $exitCode = Artisan::call(
        'des:import-parameter',
        ['file' => $path]
    );

    expect($exitCode)
        ->toBe(SymfonyCommand::SUCCESS);

    $this->assertDatabaseCount(
        'parameter_des',
        1
    );

    $this->assertDatabaseHas(
        'parameter_des',
        [
            'produk_id' => $products['first']->id,
            'alpha' => 0.40,
        ]
    );
});

it('menolak seluruh import ketika kode produk tidak ditemukan', function (): void {
    parameterDesCommandProducts();

    $path = writeParameterDesCsv(
        'unknown.csv',
        "kode_produk,alpha\n"
        ."SR011,0.25\n"
        ."TIDAKADA,0.38\n"
    );

    $exitCode = Artisan::call(
        'des:import-parameter',
        ['file' => $path]
    );

    expect($exitCode)
        ->toBe(SymfonyCommand::FAILURE)
        ->and(Artisan::output())
        ->toContain(
            'TIDAKADA'
        );

    $this->assertDatabaseCount(
        'parameter_des',
        0
    );
});

it('menolak alpha di luar rentang yang diizinkan', function (): void {
    parameterDesCommandProducts();

    $path = writeParameterDesCsv(
        'invalid-alpha.csv',
        "kode_produk,alpha\n"
        ."SR011,1.00\n"
    );

    $exitCode = Artisan::call(
        'des:import-parameter',
        ['file' => $path]
    );

    expect($exitCode)
        ->toBe(SymfonyCommand::FAILURE)
        ->and(Artisan::output())
        ->toContain(
            'rentang 0.01 sampai 0.99'
        );

    $this->assertDatabaseCount(
        'parameter_des',
        0
    );
});

it('menolak kode produk duplikat dalam satu file', function (): void {
    parameterDesCommandProducts();

    $path = writeParameterDesCsv(
        'duplicate.csv',
        "kode_produk,alpha\n"
        ."SR011,0.25\n"
        ."SR011,0.30\n"
    );

    $exitCode = Artisan::call(
        'des:import-parameter',
        ['file' => $path]
    );

    expect($exitCode)
        ->toBe(SymfonyCommand::FAILURE)
        ->and(Artisan::output())
        ->toContain(
            'duplikat'
        );

    $this->assertDatabaseCount(
        'parameter_des',
        0
    );
});

it('dry run memvalidasi tanpa menyimpan parameter', function (): void {
    parameterDesCommandProducts();

    $path = writeParameterDesCsv(
        'dry-run.csv',
        "kode_produk,alpha\n"
        ."SR011,0.25\n"
        ."SR015,0.38\n"
    );

    $exitCode = Artisan::call(
        'des:import-parameter',
        [
            'file' => $path,
            '--dry-run' => true,
        ]
    );

    expect($exitCode)
        ->toBe(SymfonyCommand::SUCCESS)
        ->and(Artisan::output())
        ->toContain(
            'Dry run tidak mengubah database.'
        );

    $this->assertDatabaseCount(
        'parameter_des',
        0
    );
});
