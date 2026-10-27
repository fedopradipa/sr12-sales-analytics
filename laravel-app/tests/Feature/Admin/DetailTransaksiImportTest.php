<?php

use App\Models\DetailTransaksi;
use App\Models\Permission;
use App\Models\Produk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * @return array<int, string>
 */
function transactionHeaders(): array
{
    return [
        'No Faktur',
        'Tanggal',
        'Kode Produk',
        'Harga Jual',
        'Qty',
        'Nilai Penjualan',
    ];
}

function createTransactionUser(
    bool $withPermission = true,
    string $role = 'operator'
): User {
    $user = User::query()->create([
        'name' => 'Pengguna '.Str::random(8),
        'email' => Str::uuid().'@example.test',
        'password' => 'password',
        'role' => $role,
    ]);

    if ($withPermission) {
        $permission = Permission::query()->firstOrCreate(
            [
                'code' => 'transaksi',
            ],
            [
                'name' => 'Kelola Transaksi',
            ]
        );

        $user->permissions()->syncWithoutDetaching([
            $permission->id,
        ]);
    }

    return $user;
}

function createTransactionProduct(
    string $code = 'SR173-02'
): Produk {
    return Produk::query()->create([
        'kode' => $code,
        'nama' => 'Produk '.Str::random(8),
    ]);
}

/**
 * @param  array<int, array<int, mixed>>  $rows
 */
function createTransactionCsv(
    array $rows,
    string $originalName = 'transaksi.csv',
    string $delimiter = ','
): UploadedFile {
    $csvPath = tempnam(
        sys_get_temp_dir(),
        'trx_import_'
    );

    if ($csvPath === false) {
        throw new RuntimeException(
            'Gagal membuat file CSV sementara.'
        );
    }

    $handle = fopen(
        $csvPath,
        'wb'
    );

    if ($handle === false) {
        throw new RuntimeException(
            'Gagal membuka file CSV sementara.'
        );
    }

    foreach ($rows as $row) {
        fputcsv(
            $handle,
            $row,
            $delimiter,
            '"',
            ''
        );
    }

    fclose($handle);

    $GLOBALS['detail_transaction_test_files'][] =
        $csvPath;

    return new UploadedFile(
        path: $csvPath,
        originalName: $originalName,
        mimeType: 'text/csv',
        error: null,
        test: true
    );
}

/**
 * @return array<int, string>
 */
function transactionImportErrorMessages(): array
{
    $errors = session('errors');

    if ($errors === null) {
        return [];
    }

    return $errors->get('file_transaksi');
}

afterEach(function (): void {
    foreach (
        $GLOBALS['detail_transaction_test_files'] ?? [] as $path
    ) {
        if (is_string($path) && is_file($path)) {
            unlink($path);
        }
    }

    $GLOBALS['detail_transaction_test_files'] = [];
});

it('mengarahkan guest ke halaman login', function (): void {
    $this
        ->get(route('admin.transaksi.import.create'))
        ->assertRedirect(route('login'));
});

it('menolak operator tanpa permission transaksi', function (): void {
    $user = createTransactionUser(
        withPermission: false
    );

    $this
        ->actingAs($user)
        ->get(route('admin.transaksi.import.create'))
        ->assertForbidden();
});

it('mengizinkan operator dengan permission membuka halaman import', function (): void {
    $user = createTransactionUser();

    $this
        ->actingAs($user)
        ->get(route('admin.transaksi.import.create'))
        ->assertOk()
        ->assertSee('Import Data Transaksi')
        ->assertSee('Download Template')
        ->assertSee('template CSV')
        ->assertDontSee('Baris pertama berisi header');
});

it('menolak file selain csv', function (): void {
    $user = createTransactionUser();

    $file = UploadedFile::fake()->create(
        'transaksi.xlsx',
        10,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors('file_transaksi');

    $this->assertDatabaseCount(
        'detail_transaksi',
        0
    );
});

it('mengunduh template csv dengan enam header dan tanpa data contoh', function (): void {
    $user = createTransactionUser();

    $response = $this
        ->actingAs($user)
        ->get(route('admin.transaksi.template'));

    $response
        ->assertOk()
        ->assertDownload(
            'template-import-transaksi.csv'
        );

    $content = $response
        ->streamedContent();

    $content = preg_replace(
        '/^\xEF\xBB\xBF/',
        '',
        $content
    ) ?? $content;

    $lines = preg_split(
        '/\r\n|\n|\r/',
        trim($content)
    );

    expect($lines)
        ->toHaveCount(1);

    expect(
        str_getcsv(
            $lines[0],
            ';',
            '"',
            ''
        )
    )->toBe(
        transactionHeaders()
    );
});

it('mengimport file template csv', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000001',
            '01/07/2026',
            $product->kode,
            50000,
            2,
            100000,
        ],
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(
            route('admin.transaksi.index')
        )
        ->assertSessionHas(
            'success',
            'Data transaksi berhasil diimport.'
        );

    $this->assertDatabaseHas(
        'detail_transaksi',
        [
            'user_id' => $user->id,
            'produk_id' => $product->id,
            'tanggal' => '2026-07-01',
            'faktur' => 'INV00000001',
            'qty' => 2,
            'harga' => 50000,
            'subtotal' => 100000,
        ]
    );
});

it('mencatat user yang melakukan import', function (): void {
    $uploader = createTransactionUser();
    $otherUser = createTransactionUser();
    $product = createTransactionProduct();

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000003',
            '03/07/2026',
            $product->kode,
            80000,
            1,
            80000,
        ],
    ]);

    $this
        ->actingAs($uploader)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas(
        'detail_transaksi',
        [
            'user_id' => $uploader->id,
            'faktur' => 'INV00000003',
        ]
    );

    $this->assertDatabaseMissing(
        'detail_transaksi',
        [
            'user_id' => $otherUser->id,
            'faktur' => 'INV00000003',
        ]
    );
});

it('mengimport csv dengan pemisah titik koma', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    $file = createTransactionCsv(
        [
            transactionHeaders(),
            [
                'INV00000012',
                '12/07/2026',
                $product->kode,
                50000,
                1,
                45000,
            ],
        ],
        'transaksi.csv',
        ';'
    );

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas(
        'detail_transaksi',
        [
            'faktur' => 'INV00000012',
            'subtotal' => 45000,
        ]
    );
});

it('menolak kode produk yang tidak sama persis', function (): void {
    $user = createTransactionUser();

    createTransactionProduct(
        code: 'SR173-02'
    );

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000004',
            '04/07/2026',
            'SR17302',
            50000,
            1,
            50000,
        ],
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors(
            'file_transaksi'
        );

    expect(
        implode(
            ' ',
            transactionImportErrorMessages()
        )
    )->toContain(
        'Kode Produk "SR17302" tidak ditemukan'
    );

    $this->assertDatabaseCount(
        'detail_transaksi',
        0
    );
});

it('menolak produk yang sudah dihapus', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    $product->delete();

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000005',
            '05/07/2026',
            $product->kode,
            50000,
            1,
            50000,
        ],
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors(
            'file_transaksi'
        );

    expect(
        implode(
            ' ',
            transactionImportErrorMessages()
        )
    )->toContain(
        'sudah dihapus'
    );

    $this->assertDatabaseCount(
        'detail_transaksi',
        0
    );
});

it('menyimpan nilai penjualan dari file tanpa memaksa qty dikali harga jual', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000006',
            '06/07/2026',
            $product->kode,
            50000,
            2,
            90000,
        ],
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasNoErrors()
        ->assertRedirect(
            route('admin.transaksi.index')
        );

    $this->assertDatabaseHas(
        'detail_transaksi',
        [
            'faktur' => 'INV00000006',
            'qty' => 2,
            'harga' => 50000,
            'subtotal' => 90000,
        ]
    );
});

it('menolak transaksi duplikat di dalam satu file', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    $duplicateRow = [
        'INV00000007',
        '07/07/2026',
        $product->kode,
        50000,
        1,
        50000,
    ];

    $file = createTransactionCsv([
        transactionHeaders(),
        $duplicateRow,
        $duplicateRow,
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors(
            'file_transaksi'
        );

    expect(
        implode(
            ' ',
            transactionImportErrorMessages()
        )
    )->toContain(
        'Transaksi duplikat dengan baris'
    );

    $this->assertDatabaseCount(
        'detail_transaksi',
        0
    );
});

it('menolak transaksi yang sudah tersimpan di database', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    DetailTransaksi::query()->create([
        'user_id' => $user->id,
        'produk_id' => $product->id,
        'tanggal' => '2026-07-08',
        'faktur' => 'INV00000008',
        'qty' => 1,
        'harga' => 50000,
        'subtotal' => 50000,
    ]);

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000008',
            '08/07/2026',
            $product->kode,
            50000,
            1,
            50000,
        ],
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors(
            'file_transaksi'
        );

    expect(
        implode(
            ' ',
            transactionImportErrorMessages()
        )
    )->toContain(
        'Transaksi sudah tersimpan'
    );

    $this->assertDatabaseCount(
        'detail_transaksi',
        1
    );
});

it('membatalkan seluruh file ketika satu baris tidak valid', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000009',
            '09/07/2026',
            $product->kode,
            50000,
            1,
            50000,
        ],
        [
            'INV00000010',
            '10/07/2026',
            $product->kode,
            50000,
            0,
            75000,
        ],
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors(
            'file_transaksi'
        );

    $this->assertDatabaseCount(
        'detail_transaksi',
        0
    );
});

it('menolak baris yang memiliki lebih dari enam kolom', function (): void {
    $user = createTransactionUser();
    $product = createTransactionProduct();

    $file = createTransactionCsv([
        transactionHeaders(),
        [
            'INV00000011',
            '11/07/2026',
            $product->kode,
            50000,
            1,
            50000,
            'KOLOM TAMBAHAN',
        ],
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors(
            'file_transaksi'
        );

    expect(
        implode(
            ' ',
            transactionImportErrorMessages()
        )
    )->toContain(
        'lebih dari enam kolom'
    );

    $this->assertDatabaseCount(
        'detail_transaksi',
        0
    );
});

it('menolak file yang hanya berisi header', function (): void {
    $user = createTransactionUser();

    $file = createTransactionCsv([
        transactionHeaders(),
    ]);

    $this
        ->actingAs($user)
        ->post(
            route('admin.transaksi.import.store'),
            [
                'file_transaksi' => $file,
            ]
        )
        ->assertSessionHasErrors(
            'file_transaksi'
        );

    expect(
        implode(
            ' ',
            transactionImportErrorMessages()
        )
    )->toContain(
        'tidak memiliki data transaksi'
    );

    $this->assertDatabaseCount(
        'detail_transaksi',
        0
    );
});
