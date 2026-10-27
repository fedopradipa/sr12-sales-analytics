<?php

namespace App\Services;

use RuntimeException;

final class DesCalibrationResultService
{
    /**
     * @param array{
     *     updated:int,
     *     skipped:int,
     *     test_months:int,
     *     average_wape:float,
     *     skipped_products:list<array{
     *         produk_id:int,
     *         kode:string,
     *         nama:string,
     *         reason_code:string,
     *         reason:string
     *     }>
     * } $summary
     */
    public function remember(array $summary): bool
    {
        try {
            $payload = [
                'updated' => (int) (
                    $summary['updated'] ?? 0
                ),
                'skipped' => (int) (
                    $summary['skipped'] ?? 0
                ),
                'test_months' => (int) (
                    $summary['test_months'] ?? 0
                ),
                'average_wape' => (float) (
                    $summary['average_wape'] ?? 0
                ),
                'generated_at' => now('Asia/Jakarta')
                    ->toIso8601String(),
                'skipped_products' => $this->normalizeProducts(
                    $summary['skipped_products']
                        ?? []
                ),
            ];

            $directory = $this->directory();

            if (
                ! is_dir($directory)
                && ! mkdir(
                    $directory,
                    0775,
                    true
                )
                && ! is_dir($directory)
            ) {
                throw new RuntimeException(
                    'Direktori hasil penentuan parameter '
                    .'tidak dapat dibuat.'
                );
            }

            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

            if (! is_string($json)) {
                throw new RuntimeException(
                    'Hasil penentuan parameter '
                    .'tidak dapat diserialisasi.'
                );
            }

            $temporary = $directory
                .DIRECTORY_SEPARATOR
                .'latest.tmp';

            $written = file_put_contents(
                $temporary,
                $json.PHP_EOL,
                LOCK_EX
            );

            if ($written === false) {
                throw new RuntimeException(
                    'Hasil penentuan parameter '
                    .'tidak dapat disimpan.'
                );
            }

            if (! @rename(
                $temporary,
                $this->path()
            )) {
                @unlink($temporary);

                throw new RuntimeException(
                    'Hasil penentuan parameter '
                    .'tidak dapat difinalisasi.'
                );
            }

            return true;
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }
    }

    /**
     * @return array{
     *     updated:int,
     *     skipped:int,
     *     test_months:int,
     *     average_wape:float,
     *     generated_at:string,
     *     skipped_products:list<array{
     *         produk_id:int,
     *         kode:string,
     *         nama:string,
     *         reason_code:string,
     *         reason:string
     *     }>
     * }|null
     */
    public function latest(): ?array
    {
        $path = $this->path();

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if (! is_string($contents)) {
            return null;
        }

        $payload = json_decode(
            $contents,
            true
        );

        if (! is_array($payload)) {
            return null;
        }

        $testMonths = (int) (
            $payload['test_months'] ?? 0
        );

        $generatedAt = $payload['generated_at']
            ?? null;

        if (
            $testMonths < 1
            || ! is_string($generatedAt)
            || trim($generatedAt) === ''
        ) {
            return null;
        }

        $products = $this->normalizeProducts(
            $payload['skipped_products'] ?? []
        );

        return [
            'updated' => max(
                0,
                (int) ($payload['updated'] ?? 0)
            ),
            'skipped' => count($products),
            'test_months' => $testMonths,
            'average_wape' => max(
                0,
                (float) ($payload['average_wape'] ?? 0)
            ),
            'generated_at' => $generatedAt,
            'skipped_products' => $products,
        ];
    }

    /**
     * @return list<array{
     *     produk_id:int,
     *     kode:string,
     *     nama:string,
     *     reason_code:string,
     *     reason:string
     * }>
     */
    private function normalizeProducts(
        mixed $products
    ): array {
        if (! is_array($products)) {
            return [];
        }

        $normalized = [];

        foreach ($products as $product) {
            if (! is_array($product)) {
                continue;
            }

            $productId = (int) (
                $product['produk_id'] ?? 0
            );
            $kode = trim(
                (string) ($product['kode'] ?? '')
            );
            $nama = trim(
                (string) ($product['nama'] ?? '')
            );
            $reasonCode = trim(
                (string) (
                    $product['reason_code'] ?? ''
                )
            );
            $reason = trim(
                (string) ($product['reason'] ?? '')
            );

            if (
                $productId <= 0
                || $kode === ''
                || $nama === ''
                || $reasonCode === ''
                || $reason === ''
            ) {
                continue;
            }

            $normalized[] = [
                'produk_id' => $productId,
                'kode' => $kode,
                'nama' => $nama,
                'reason_code' => $reasonCode,
                'reason' => $reason,
            ];
        }

        return array_values($normalized);
    }

    private function directory(): string
    {
        if (app()->environment('testing')) {
            return storage_path(
                'framework/testing/des-calibration-results'
            );
        }

        return storage_path(
            'app/des-calibration-results'
        );
    }

    private function path(): string
    {
        return $this->directory()
            .DIRECTORY_SEPARATOR
            .'latest.json';
    }
}
