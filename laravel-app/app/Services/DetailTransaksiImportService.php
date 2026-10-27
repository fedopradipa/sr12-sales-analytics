<?php

namespace App\Services;

use App\Models\DetailTransaksi;
use App\Models\Produk;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stringable;
use Throwable;

class DetailTransaksiImportService
{
    private const MAX_IMPORT_ROWS = 100000;

    private const INSERT_BATCH_SIZE = 1000;

    private const MAX_VISIBLE_ERRORS = 50;

    private const MAX_QTY = 65535;

    private const MAX_UNSIGNED_INTEGER = 4294967295;

    private const DATE_FORMATS = [
        'd/m/Y', 'd/m/Y H:i', 'd/m/Y H:i:s',
        'Y-m-d', 'Y-m-d H:i', 'Y-m-d H:i:s',
    ];

    public function import(
        UploadedFile $file,
        int $userId
    ): array {
        $stageTable = $this->makeStageTableName();
        $this->createStageTable($stageTable);
        $state = $this->newImportState($stageTable);

        try {
            $this->streamCsv(
                $file,
                function (
                    int $rowNumber,
                    array $row
                ) use (&$state): void {
                    $this->processRow(
                        $rowNumber,
                        $row,
                        $state
                    );
                }
            );

            $this->flushPendingRows($state);
            $this->validateImportState($state);
            $this->ensureNoExistingTransactions(
                $stageTable
            );
            $this->insertValidatedRows(
                $stageTable,
                $userId
            );

            return $this->buildSummary(
                $file,
                $state['summary']
            );
        } finally {
            $this->dropStageTable($stageTable);
        }
    }

    private function processRow(
        int $rowNumber,
        array $rawRow,
        array &$state
    ): void {
        if (
            $rowNumber === 1
            || $this->isEmptyRow($rawRow)
        ) {
            return;
        }

        if (
            ++$state['row_count']
            > self::MAX_IMPORT_ROWS
        ) {
            throw ValidationException::withMessages([
                'file_transaksi' => [
                    'Jumlah data maksimal '
                    .number_format(
                        self::MAX_IMPORT_ROWS,
                        0,
                        ',',
                        '.'
                    )
                    .' baris dalam satu proses import.',
                ],
            ]);
        }

        $row = $this->parseRow(
            $rowNumber,
            $rawRow,
            $state['errors']
        );

        if ($row === null) {
            $this->throwWhenErrorLimitReached(
                $state['errors']
            );

            return;
        }

        $product = $state['products'][
            $row['kode_produk']
        ] ?? null;

        if ($product === null) {
            $this->addError(
                $state['errors'],
                "Baris {$rowNumber}: Kode Produk "
                ."\"{$row['kode_produk']}\" tidak "
                .'ditemukan pada master Produk.'
            );

            $this->throwWhenErrorLimitReached(
                $state['errors']
            );

            return;
        }

        if ($product['deleted_at'] !== null) {
            $this->addError(
                $state['errors'],
                "Baris {$rowNumber}: Produk dengan kode "
                ."\"{$row['kode_produk']}\" sudah dihapus."
            );

            $this->throwWhenErrorLimitReached(
                $state['errors']
            );

            return;
        }

        $row['produk_id'] = $product['id'];

        $key = $this->compositeKey(
            $row['tanggal'],
            $row['faktur'],
            $row['produk_id']
        );

        if (isset($state['seen'][$key])) {
            $this->addError(
                $state['errors'],
                "Baris {$rowNumber}: Transaksi duplikat "
                ."dengan baris {$state['seen'][$key]}."
            );

            $this->throwWhenErrorLimitReached(
                $state['errors']
            );

            return;
        }

        $state['seen'][$key] = $rowNumber;
        $state['buffer'][] = $row;

        if (
            count($state['buffer'])
            >= self::INSERT_BATCH_SIZE
        ) {
            $this->flushPendingRows($state);
        }
    }

    private function flushPendingRows(
        array &$state
    ): void {
        if ($state['buffer'] === []) {
            return;
        }

        $this->flushStageBuffer(
            $state['stage_table'],
            $state['buffer'],
            $state['summary']
        );

        $state['buffer'] = [];
    }

    private function validateImportState(
        array $state
    ): void {
        if ($state['row_count'] === 0) {
            throw ValidationException::withMessages([
                'file_transaksi' => [
                    'File tidak memiliki data transaksi '
                    .'untuk diimport.',
                ],
            ]);
        }

        if ($state['errors'] !== []) {
            $this->throwImportErrors(
                $state['errors']
            );
        }
    }

    private function newImportState(
        string $stageTable
    ): array {
        return [
            'stage_table' => $stageTable,
            'products' => $this->getProductsByCode(),
            'seen' => [],
            'errors' => [],
            'buffer' => [],
            'summary' => [
                'jumlah_baris' => 0,
                'faktur' => [],
                'produk' => [],
                'awal' => null,
                'akhir' => null,
                'total_penjualan' => 0,
            ],
            'row_count' => 0,
        ];
    }

    private function streamCsv(
        UploadedFile $file,
        callable $consumer
    ): void {
        $path = $file->getRealPath();

        if ($path === false) {
            throw ValidationException::withMessages([
                'file_transaksi' => [
                    'Lokasi file CSV tidak tersedia.',
                ],
            ]);
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file_transaksi' => [
                    'File CSV tidak dapat dibuka.',
                ],
            ]);
        }

        try {
            $firstLine = fgets($handle);

            if ($firstLine === false) {
                return;
            }

            $delimiter = $this->detectDelimiter(
                $firstLine
            );

            rewind($handle);
            $rowNumber = 1;

            while (
                ($row = fgetcsv(
                    $handle,
                    0,
                    $delimiter,
                    '"',
                    ''
                )) !== false
            ) {
                if ($rowNumber === 1) {
                    $row[0] = $this->stripUtf8Bom(
                        $row[0] ?? null
                    );
                }

                $this->assertUtf8Row(
                    $rowNumber,
                    $row
                );

                $consumer(
                    $rowNumber,
                    $row
                );

                $rowNumber++;
            }
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'file_transaksi' => [
                    'File CSV tidak dapat dibaca. '
                    .'Gunakan template CSV yang '
                    .'disediakan sistem.',
                ],
            ]);
        } finally {
            fclose($handle);
        }
    }

    private function detectDelimiter(
        string $firstLine
    ): string {
        $commaCount = substr_count(
            $firstLine,
            ','
        );

        $semicolonCount = substr_count(
            $firstLine,
            ';'
        );

        if (
            $commaCount < 5
            && $semicolonCount < 5
        ) {
            throw ValidationException::withMessages([
                'file_transaksi' => [
                    'Format CSV tidak sesuai template. '
                    .'Gunakan pemisah koma (,) atau '
                    .'titik koma (;).',
                ],
            ]);
        }

        return $semicolonCount > $commaCount
            ? ';'
            : ',';
    }

    private function stripUtf8Bom(
        mixed $value
    ): mixed {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace(
            '/^\xEF\xBB\xBF/',
            '',
            $value
        ) ?? $value;
    }

    private function assertUtf8Row(
        int $rowNumber,
        array $row
    ): void {
        foreach ($row as $value) {
            if (
                is_string($value)
                && ! mb_check_encoding(
                    $value,
                    'UTF-8'
                )
            ) {
                throw ValidationException::withMessages([
                    'file_transaksi' => [
                        "Baris {$rowNumber}: File harus "
                        .'menggunakan encoding UTF-8.',
                    ],
                ]);
            }
        }
    }

    private function getProductsByCode(): array
    {
        return Produk::query()
            ->withTrashed()
            ->get([
                'id',
                'kode',
                'deleted_at',
            ])
            ->mapWithKeys(
                static fn (
                    Produk $produk
                ): array => [
                    $produk->kode => [
                        'id' => (int)
                            $produk->getKey(),
                        'deleted_at' => $produk->deleted_at,
                    ],
                ]
            )
            ->all();
    }

    private function parseRow(
        int $rowNumber,
        array $rawRow,
        array &$errors
    ): ?array {
        if ($this->hasExtraValues($rawRow)) {
            $this->addError(
                $errors,
                "Baris {$rowNumber}: Data memiliki "
                .'lebih dari enam kolom.'
            );

            return null;
        }

        [
            $fakturValue,
            $tanggalValue,
            $kodeValue,
            $hargaValue,
            $qtyValue,
            $subtotalValue,
        ] = $this->getFirstSixCells($rawRow);

        $rowErrors = [];

        $tanggal = $this->parseDate(
            $tanggalValue
        );

        if ($tanggal === null) {
            $rowErrors[] =
                'Tanggal wajib menggunakan format '
                .'DD/MM/YYYY, misalnya 05/02/2022.';
        }

        $faktur = $this->parseRequiredString(
            $fakturValue,
            'No Faktur',
            $rowErrors
        );

        $kodeProduk =
            $this->parseRequiredString(
                $kodeValue,
                'Kode Produk',
                $rowErrors
            );

        $qty = $this->parseUnsignedInteger(
            $qtyValue,
            self::MAX_QTY
        );

        if ($qty === null || $qty < 1) {
            $rowErrors[] =
                'Qty harus berupa bilangan bulat '
                .'lebih dari nol.';
        }

        $harga = $this->parseUnsignedInteger(
            $hargaValue,
            self::MAX_UNSIGNED_INTEGER
        );

        if ($harga === null) {
            $rowErrors[] =
                'Harga Jual harus berupa bilangan '
                .'bulat tanpa Rp atau pemisah ribuan.';
        }

        $subtotal =
            $this->parseUnsignedInteger(
                $subtotalValue,
                self::MAX_UNSIGNED_INTEGER
            );

        if ($subtotal === null) {
            $rowErrors[] =
                'Nilai Penjualan harus berupa bilangan '
                .'bulat tanpa Rp atau pemisah ribuan.';
        }

        if ($rowErrors !== []) {
            foreach ($rowErrors as $message) {
                $this->addError(
                    $errors,
                    "Baris {$rowNumber}: {$message}"
                );
            }

            return null;
        }

        return [
            'row_number' => $rowNumber,
            'tanggal' => $tanggal,
            'faktur' => $faktur,
            'kode_produk' => $kodeProduk,
            'qty' => $qty,
            'harga' => $harga,
            'subtotal' => $subtotal,
        ];
    }

    private function parseRequiredString(
        mixed $value,
        string $label,
        array &$errors
    ): ?string {
        $parsed = $this->parseStrictString(
            $value
        );

        if ($parsed === null) {
            $errors[] =
                "{$label} wajib diisi tanpa spasi "
                .'di awal atau akhir.';
        } elseif (mb_strlen($parsed) > 20) {
            $errors[] =
                "{$label} maksimal 20 karakter.";
        }

        return $parsed;
    }

    private function flushStageBuffer(
        string $stageTable,
        array $rows,
        array &$summary
    ): void {
        $insertRows = [];

        foreach ($rows as $row) {
            $insertRows[] = [
                'row_number' => $row['row_number'],
                'produk_id' => $row['produk_id'],
                'tanggal' => $row['tanggal'],
                'faktur' => $row['faktur'],
                'qty' => $row['qty'],
                'harga' => $row['harga'],
                'subtotal' => $row['subtotal'],
            ];

            $this->updateSummary(
                $summary,
                $row
            );
        }

        try {
            DB::table($stageTable)
                ->insert($insertRows);
        } catch (QueryException $exception) {
            if (
                $this->isIntegrityViolation(
                    $exception
                )
            ) {
                throw ValidationException::withMessages([
                    'file_transaksi' => [
                        'Import dibatalkan karena '
                        .'terdapat transaksi duplikat '
                        .'di dalam file.',
                    ],
                ]);
            }

            throw $exception;
        }
    }

    private function ensureNoExistingTransactions(
        string $stageTable
    ): void {
        $query = DB::table(
            $stageTable.' as incoming'
        )->join(
            'detail_transaksi as existing',
            function ($join): void {
                $join
                    ->on(
                        'existing.faktur',
                        '=',
                        'incoming.faktur'
                    )
                    ->on(
                        'existing.produk_id',
                        '=',
                        'incoming.produk_id'
                    );
            }
        );

        if (
            DB::connection()
                ->getDriverName()
            === 'sqlite'
        ) {
            $query->whereRaw(
                'date(existing.tanggal) '
                .'= date(incoming.tanggal)'
            );
        } else {
            $query->whereColumn(
                'existing.tanggal',
                'incoming.tanggal'
            );
        }

        $duplicate = $query
            ->orderBy('incoming.row_number')
            ->first([
                'incoming.row_number',
                'incoming.tanggal',
                'incoming.faktur',
                'incoming.produk_id',
            ]);

        if ($duplicate === null) {
            return;
        }

        throw ValidationException::withMessages([
            'file_transaksi' => [
                'Baris '
                .$duplicate->row_number
                .': Transaksi sudah tersimpan untuk '
                .'tanggal '
                .$duplicate->tanggal
                .', faktur '
                .$duplicate->faktur
                .', dan produk ID '
                .$duplicate->produk_id
                .'.',
            ],
        ]);
    }

    private function insertValidatedRows(
        string $stageTable,
        int $userId
    ): void {
        $createdAt = now();

        try {
            DB::transaction(
                function () use (
                    $stageTable,
                    $userId,
                    $createdAt
                ): void {
                    $lastRowNumber = 0;

                    while (true) {
                        $rows = DB::table(
                            $stageTable
                        )
                            ->where(
                                'row_number',
                                '>',
                                $lastRowNumber
                            )
                            ->orderBy(
                                'row_number'
                            )
                            ->limit(
                                self::INSERT_BATCH_SIZE
                            )
                            ->get();

                        if ($rows->isEmpty()) {
                            break;
                        }

                        $databaseRows = $rows
                            ->map(
                                static fn (
                                    $row
                                ): array => [
                                    'user_id' => $userId,
                                    'produk_id' => (int)
                                        $row->produk_id,
                                    'tanggal' => (string)
                                        $row->tanggal,
                                    'faktur' => (string)
                                        $row->faktur,
                                    'qty' => (int)
                                        $row->qty,
                                    'harga' => (int)
                                        $row->harga,
                                    'subtotal' => (int)
                                        $row->subtotal,
                                    'created_at' => $createdAt,
                                ]
                            )
                            ->all();

                        DetailTransaksi::query()
                            ->insert(
                                $databaseRows
                            );

                        $lastRowNumber =
                            (int)
                            $rows
                                ->last()
                                ->row_number;
                    }
                },
                3
            );
        } catch (QueryException $exception) {
            if (
                $this->isIntegrityViolation(
                    $exception
                )
            ) {
                throw ValidationException::withMessages([
                    'file_transaksi' => [
                        'Import dibatalkan karena '
                        .'terdapat transaksi duplikat.',
                    ],
                ]);
            }

            throw $exception;
        }
    }

    private function makeStageTableName(): string
    {
        return 'tmp_detail_transaksi_'
            .bin2hex(random_bytes(8));
    }

    private function createStageTable(
        string $table
    ): void {
        $driver = DB::connection()
            ->getDriverName();

        if (
            ! in_array(
                $driver,
                ['mysql', 'mariadb'],
                true
            )
        ) {
            DB::statement(<<<SQL
CREATE TEMP TABLE "{$table}" (
    "row_number" INTEGER NOT NULL PRIMARY KEY,
    "produk_id" INTEGER NOT NULL,
    "tanggal" TEXT NOT NULL,
    "faktur" TEXT NOT NULL,
    "qty" INTEGER NOT NULL,
    "harga" INTEGER NOT NULL,
    "subtotal" INTEGER NOT NULL,
    UNIQUE ("tanggal", "faktur", "produk_id")
)
SQL);

            return;
        }

        $connection = DB::connection();

        $charset = (string) (
            $connection->getConfig('charset')
            ?? 'utf8mb4'
        );

        $collation = (string) (
            $connection->getConfig('collation')
            ?? 'utf8mb4_unicode_ci'
        );

        if (
            preg_match(
                '/^[A-Za-z0-9_]+$/',
                $charset
            ) !== 1
            || preg_match(
                '/^[A-Za-z0-9_]+$/',
                $collation
            ) !== 1
        ) {
            throw new \RuntimeException(
                'Konfigurasi charset atau '
                .'collation database tidak valid.'
            );
        }

        DB::statement(<<<SQL
CREATE TEMPORARY TABLE `{$table}` (
    `row_number` INT UNSIGNED NOT NULL PRIMARY KEY,
    `produk_id` BIGINT UNSIGNED NOT NULL,
    `tanggal` DATE NOT NULL,
    `faktur` VARCHAR(20) NOT NULL,
    `qty` INT UNSIGNED NOT NULL,
    `harga` BIGINT UNSIGNED NOT NULL,
    `subtotal` BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY `{$table}_unique` (`tanggal`, `faktur`, `produk_id`)
) ENGINE=InnoDB DEFAULT CHARACTER SET {$charset} COLLATE {$collation}
SQL);
    }

    private function dropStageTable(
        string $table
    ): void {
        if (
            in_array(
                DB::connection()
                    ->getDriverName(),
                ['mysql', 'mariadb'],
                true
            )
        ) {
            DB::statement(
                'DROP TEMPORARY TABLE '
                ."IF EXISTS `{$table}`"
            );

            return;
        }

        DB::statement(
            "DROP TABLE IF EXISTS \"{$table}\""
        );
    }

    private function getFirstSixCells(
        array $row
    ): array {
        return array_pad(
            array_slice(
                array_values($row),
                0,
                6
            ),
            6,
            null
        );
    }

    private function hasExtraValues(
        array $row
    ): bool {
        foreach (
            array_slice(
                array_values($row),
                6
            ) as $cell
        ) {
            if (! $this->isEmptyValue($cell)) {
                return true;
            }
        }

        return false;
    }

    private function isEmptyRow(
        array $row
    ): bool {
        foreach ($row as $cell) {
            if (! $this->isEmptyValue($cell)) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyValue(
        mixed $value
    ): bool {
        return $value === null
            || (
                is_string($value)
                && trim($value) === ''
            );
    }

    private function parseStrictString(
        mixed $value
    ): ?string {
        if (
            ! is_string($value)
            && ! is_int($value)
            && ! $value instanceof Stringable
        ) {
            return null;
        }

        $value = (string) $value;

        return $value !== ''
            && $value === trim($value)
            ? $value
            : null;
    }

    private function parseDate(
        mixed $value
    ): ?string {
        if (
            $value instanceof DateTimeInterface
        ) {
            return $value->format('Y-m-d');
        }

        if (
            ! is_string($value)
            || $value === ''
            || $value !== trim($value)
        ) {
            return null;
        }

        foreach (
            self::DATE_FORMATS as $format
        ) {
            $date =
                DateTimeImmutable::createFromFormat(
                    '!'.$format,
                    $value
                );

            $errors =
                DateTimeImmutable::getLastErrors();

            $validErrors =
                ! is_array($errors)
                || (
                    $errors['warning_count'] === 0
                    && $errors['error_count'] === 0
                );

            if (
                $date
                && $validErrors
                && $date->format($format)
                    === $value
            ) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function parseUnsignedInteger(
        mixed $value,
        int $maximum
    ): ?int {
        if (is_int($value)) {
            return $value >= 0
                && $value <= $maximum
                ? $value
                : null;
        }

        if (is_float($value)) {
            return is_finite($value)
                && floor($value) === $value
                && $value >= 0
                && $value <= $maximum
                ? (int) $value
                : null;
        }

        if (
            ! is_string($value)
            || $value === ''
            || $value !== trim($value)
            || ! preg_match(
                '/^\d+$/',
                $value
            )
        ) {
            return null;
        }

        $normalized =
            ltrim($value, '0');

        if ($normalized === '') {
            return 0;
        }

        $max = (string) $maximum;

        if (
            strlen($normalized)
                > strlen($max)
            || (
                strlen($normalized)
                    === strlen($max)
                && strcmp(
                    $normalized,
                    $max
                ) > 0
            )
        ) {
            return null;
        }

        return (int) $normalized;
    }

    private function compositeKey(
        string $tanggal,
        string $faktur,
        int $produkId
    ): string {
        return implode(
            '|',
            [
                $tanggal,
                mb_strtolower($faktur),
                $produkId,
            ]
        );
    }

    private function isIntegrityViolation(
        QueryException $exception
    ): bool {
        return (string) $exception->getCode()
                === '23000'
            || str_contains(
                strtolower(
                    $exception->getMessage()
                ),
                'unique constraint'
            );
    }

    private function addError(
        array &$errors,
        string $message
    ): void {
        if (
            count($errors)
            < self::MAX_VISIBLE_ERRORS
        ) {
            $errors[] = $message;
        }
    }

    private function throwWhenErrorLimitReached(
        array $errors
    ): void {
        if (
            count($errors)
            < self::MAX_VISIBLE_ERRORS
        ) {
            return;
        }

        $errors[] =
            'Batas 50 kesalahan telah tercapai. '
            .'Perbaiki data yang ditampilkan, '
            .'lalu import ulang.';

        $this->throwImportErrors($errors);
    }

    private function throwImportErrors(
        array $errors
    ): never {
        throw ValidationException::withMessages([
            'file_transaksi' => array_slice(
                $errors,
                0,
                self::MAX_VISIBLE_ERRORS + 1
            ),
        ]);
    }

    private function updateSummary(
        array &$summary,
        array $row
    ): void {
        $tanggal = (string) $row['tanggal'];

        $summary['jumlah_baris']++;

        $summary['faktur'][
            $tanggal
            .'|'
            .mb_strtolower(
                (string) $row['faktur']
            )
        ] = true;

        $summary['produk'][
            (int) $row['produk_id']
        ] = true;

        $summary['awal'] =
            $summary['awal'] === null
            || $tanggal < $summary['awal']
            ? $tanggal
            : $summary['awal'];

        $summary['akhir'] =
            $summary['akhir'] === null
            || $tanggal > $summary['akhir']
            ? $tanggal
            : $summary['akhir'];

        $summary['total_penjualan'] +=
            (int) $row['subtotal'];
    }

    private function buildSummary(
        UploadedFile $file,
        array $summary
    ): array {
        return [
            'nama_file' => $file->getClientOriginalName(),
            'jumlah_baris' => (int) $summary['jumlah_baris'],
            'jumlah_faktur' => count($summary['faktur']),
            'jumlah_produk' => count($summary['produk']),
            'awal' => (string) $summary['awal'],
            'akhir' => (string) $summary['akhir'],
            'total_penjualan' => (int) $summary['total_penjualan'],
        ];
    }
}
