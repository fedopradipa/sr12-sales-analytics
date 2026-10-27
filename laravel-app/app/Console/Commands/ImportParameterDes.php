<?php

namespace App\Console\Commands;

use App\Models\ParameterDes;
use App\Models\Produk;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileObject;
use Throwable;

final class ImportParameterDes extends Command
{
    protected $signature = 'des:import-parameter
                            {file : Lokasi file CSV parameter DES}
                            {--dry-run : Validasi file tanpa menyimpan ke database}';

    protected $description =
        'Mengimpor alpha final setiap produk dari CSV ke parameter_des';

    public function handle(): int
    {
        try {
            $path = $this->resolvePath(
                (string) $this->argument('file')
            );

            $records = $this->readRecords($path);

            $productsByCode = $this->resolveProducts(
                $records
            );

            $summary = $this->prepareImport(
                $records,
                $productsByCode
            );

            if (! $this->option('dry-run')) {
                $this->persist(
                    $summary['rows']
                );
            }

            $this->displaySummary(
                path: $path,
                total: count($records),
                inserted: $summary['inserted'],
                updated: $summary['updated'],
                unchanged: $summary['unchanged'],
                dryRun: (bool) $this->option('dry-run')
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error(
                $exception->getMessage()
            );

            return self::FAILURE;
        }
    }

    private function resolvePath(
        string $input
    ): string {
        $input = trim($input);

        if ($input === '') {
            throw new RuntimeException(
                'Lokasi file CSV tidak boleh kosong.'
            );
        }

        $candidates = [
            $input,
            base_path($input),
        ];

        foreach ($candidates as $candidate) {
            if (
                is_file($candidate)
                && is_readable($candidate)
            ) {
                $resolved = realpath($candidate);

                return $resolved !== false
                    ? $resolved
                    : $candidate;
            }
        }

        throw new RuntimeException(
            'File CSV tidak ditemukan atau tidak dapat dibaca: '
            .$input
        );
    }

    /**
     * @return list<array{
     *     kode_produk:string,
     *     alpha:string,
     *     line:int
     * }>
     */
    private function readRecords(
        string $path
    ): array {
        $delimiter = $this->detectDelimiter(
            $path
        );

        $file = new SplFileObject(
            $path,
            'r'
        );

        $file->setCsvControl($delimiter);

        $file->setFlags(
            SplFileObject::READ_CSV
            | SplFileObject::DROP_NEW_LINE
        );

        $header = null;
        $records = [];
        $seenCodes = [];

        foreach ($file as $lineIndex => $row) {
            $lineNumber = $lineIndex + 1;

            if (
                $row === false
                || $row === [null]
                || $this->rowIsEmpty($row)
            ) {
                continue;
            }

            if ($header === null) {
                $header = array_map(
                    fn ($value): string => $this->normalizeHeader(
                        (string) $value
                    ),
                    $row
                );

                $this->validateHeader($header);

                continue;
            }

            if (count($row) !== count($header)) {
                throw new RuntimeException(
                    sprintf(
                        'Jumlah kolom pada baris %d tidak sesuai header.',
                        $lineNumber
                    )
                );
            }

            $data = array_combine(
                $header,
                $row
            );

            if ($data === false) {
                throw new RuntimeException(
                    sprintf(
                        'Baris %d tidak dapat dibaca.',
                        $lineNumber
                    )
                );
            }

            $code = strtoupper(
                trim(
                    (string) $data['kode_produk']
                )
            );

            if ($code === '') {
                throw new RuntimeException(
                    sprintf(
                        'kode_produk pada baris %d tidak boleh kosong.',
                        $lineNumber
                    )
                );
            }

            if (isset($seenCodes[$code])) {
                throw new RuntimeException(
                    sprintf(
                        'Kode produk %s duplikat pada baris %d dan %d.',
                        $code,
                        $seenCodes[$code],
                        $lineNumber
                    )
                );
            }

            $seenCodes[$code] = $lineNumber;

            $records[] = [
                'kode_produk' => $code,
                'alpha' => $this->normalizeAlpha(
                    $data['alpha'],
                    $lineNumber
                ),
                'line' => $lineNumber,
            ];
        }

        if ($header === null) {
            throw new RuntimeException(
                'File CSV kosong atau tidak memiliki header.'
            );
        }

        if ($records === []) {
            throw new RuntimeException(
                'File CSV tidak memiliki data parameter DES.'
            );
        }

        return $records;
    }

    private function detectDelimiter(
        string $path
    ): string {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException(
                'File CSV tidak dapat dibuka.'
            );
        }

        try {
            while (
                ($line = fgets($handle)) !== false
            ) {
                if (trim($line) === '') {
                    continue;
                }

                return substr_count($line, ';')
                    > substr_count($line, ',')
                        ? ';'
                        : ',';
            }
        } finally {
            fclose($handle);
        }

        throw new RuntimeException(
            'File CSV kosong.'
        );
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsEmpty(
        array $row
    ): bool {
        foreach ($row as $value) {
            if (
                $value !== null
                && trim((string) $value) !== ''
            ) {
                return false;
            }
        }

        return true;
    }

    private function normalizeHeader(
        string $value
    ): string {
        $value = preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            trim($value)
        ) ?? trim($value);

        $value = strtolower($value);

        return str_replace(
            [' ', '-'],
            '_',
            $value
        );
    }

    /**
     * @param  list<string>  $header
     */
    private function validateHeader(
        array $header
    ): void {
        if (
            count($header)
            !== count(array_unique($header))
        ) {
            throw new RuntimeException(
                'Header CSV mengandung nama kolom duplikat.'
            );
        }

        $required = [
            'kode_produk',
            'alpha',
        ];

        $missing = array_values(
            array_diff(
                $required,
                $header
            )
        );

        if ($missing !== []) {
            throw new RuntimeException(
                'Header CSV wajib memiliki kolom: '
                .implode(', ', $required)
                .'. Kolom yang tidak ditemukan: '
                .implode(', ', $missing)
            );
        }
    }

    private function normalizeAlpha(
        mixed $rawValue,
        int $lineNumber
    ): string {
        $value = str_replace(
            ' ',
            '',
            trim((string) $rawValue)
        );

        if (
            str_contains($value, ',')
            && ! str_contains($value, '.')
        ) {
            $value = str_replace(
                ',',
                '.',
                $value
            );
        }

        if (
            $value === ''
            || ! is_numeric($value)
        ) {
            throw new RuntimeException(
                sprintf(
                    'Alpha pada baris %d harus berupa angka.',
                    $lineNumber
                )
            );
        }

        $alpha = (float) $value;

        if (
            ! is_finite($alpha)
            || $alpha < 0.01
            || $alpha > 0.99
        ) {
            throw new RuntimeException(
                sprintf(
                    'Alpha pada baris %d harus berada pada rentang 0.01 sampai 0.99.',
                    $lineNumber
                )
            );
        }

        if (
            abs(
                ($alpha * 100)
                - round($alpha * 100)
            ) > 0.0000001
        ) {
            throw new RuntimeException(
                sprintf(
                    'Alpha pada baris %d maksimal memiliki dua angka desimal.',
                    $lineNumber
                )
            );
        }

        return number_format(
            $alpha,
            2,
            '.',
            ''
        );
    }

    /**
     * @param list<array{
     *     kode_produk:string,
     *     alpha:string,
     *     line:int
     * }> $records
     * @return Collection<string, Produk>
     */
    private function resolveProducts(
        array $records
    ): Collection {
        $codes = collect($records)
            ->pluck('kode_produk')
            ->values();

        $products = Produk::query()
            ->whereIn('kode', $codes)
            ->get([
                'id',
                'kode',
            ])
            ->keyBy(
                fn (Produk $product): string => strtoupper(
                    trim($product->kode)
                )
            );

        $unknownCodes = collect($records)
            ->reject(
                fn (array $record): bool => $products->has(
                    $record['kode_produk']
                )
            )
            ->pluck('kode_produk')
            ->unique()
            ->values();

        if ($unknownCodes->isNotEmpty()) {
            throw new RuntimeException(
                "Kode produk berikut tidak ditemukan atau sudah tidak aktif:\n- "
                .$unknownCodes->join("\n- ")
            );
        }

        return $products;
    }

    /**
     * @param list<array{
     *     kode_produk:string,
     *     alpha:string,
     *     line:int
     * }> $records
     * @param  Collection<string, Produk>  $productsByCode
     * @return array{
     *     rows:list<array<string, mixed>>,
     *     inserted:int,
     *     updated:int,
     *     unchanged:int
     * }
     */
    private function prepareImport(
        array $records,
        Collection $productsByCode
    ): array {
        $productIds = $productsByCode
            ->pluck('id')
            ->map(
                fn ($id): int => (int) $id
            )
            ->values();

        $existingByProduct = ParameterDes::query()
            ->whereIn(
                'produk_id',
                $productIds
            )
            ->get()
            ->keyBy('produk_id');

        $now = now();
        $rows = [];
        $inserted = 0;
        $updated = 0;
        $unchanged = 0;

        foreach ($records as $record) {
            $product = $productsByCode->get(
                $record['kode_produk']
            );

            if (! $product instanceof Produk) {
                throw new RuntimeException(
                    'Produk gagal dipetakan: '
                    .$record['kode_produk']
                );
            }

            $productId = (int) $product->id;
            $existing = $existingByProduct->get(
                $productId
            );

            if (! $existing instanceof ParameterDes) {
                $inserted++;
            } elseif (
                number_format(
                    (float) $existing->alpha,
                    2,
                    '.',
                    ''
                ) === $record['alpha']
            ) {
                $unchanged++;

                continue;
            } else {
                $updated++;
            }

            $rows[] = [
                'produk_id' => $productId,
                'alpha' => $record['alpha'],
                'created_at' => $existing?->created_at
                    ?? $now,
                'updated_at' => $now,
            ];
        }

        return [
            'rows' => $rows,
            'inserted' => $inserted,
            'updated' => $updated,
            'unchanged' => $unchanged,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function persist(
        array $rows
    ): void {
        if ($rows === []) {
            return;
        }

        DB::transaction(
            function () use ($rows): void {
                ParameterDes::query()->upsert(
                    $rows,
                    ['produk_id'],
                    [
                        'alpha',
                        'updated_at',
                    ]
                );
            },
            3
        );
    }

    private function displaySummary(
        string $path,
        int $total,
        int $inserted,
        int $updated,
        int $unchanged,
        bool $dryRun
    ): void {
        $this->newLine();

        $this->line(
            'File: '.$path
        );

        $this->table(
            [
                'Keterangan',
                'Jumlah',
            ],
            [
                [
                    'Baris valid',
                    $total,
                ],
                [
                    'Parameter baru',
                    $inserted,
                ],
                [
                    'Parameter diperbarui',
                    $updated,
                ],
                [
                    'Tidak berubah',
                    $unchanged,
                ],
            ]
        );

        if ($dryRun) {
            $this->components->info(
                'Validasi berhasil. Dry run tidak mengubah database.'
            );

            return;
        }

        $this->components->info(
            'Parameter DES berhasil diimpor.'
        );
    }
}
