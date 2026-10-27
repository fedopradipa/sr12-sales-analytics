<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LogicException;
use UnexpectedValueException;

final class DesFastApiService
{
    /**
     * @param list<array{
     *     produk_id:int,
     *     alpha:float,
     *     series:list<array{periode:string,aktual:int}>
     * }> $products
     * @return list<array{
     *     produk_id:int,
     *     prediksi:float
     * }>
     */
    public function forecast(array $products): array
    {
        if ($products === [] || ! array_is_list($products)) {
            throw new LogicException(
                'Data produk prediksi harus berupa daftar yang tidak kosong.'
            );
        }

        $response = $this->client()
            ->post(
                $this->endpoint(
                    '/api/v1/des/forecast-batch'
                ),
                ['products' => $products]
            )
            ->throw();

        $payload = $response->json();

        if (
            ! is_array($payload)
            || ! is_array($payload['results'] ?? null)
            || ! array_is_list($payload['results'])
        ) {
            throw $this->invalidResponse();
        }

        $requestedById = collect($products)
            ->keyBy(
                fn (array $product): int => (int) $product['produk_id']
            );

        $validated = [];
        $receivedIds = [];

        foreach ($payload['results'] as $result) {
            if (! is_array($result)) {
                throw $this->invalidResponse();
            }

            if (
                array_diff(
                    array_keys($result),
                    ['produk_id', 'prediksi']
                ) !== []
            ) {
                throw $this->invalidResponse();
            }

            $produkId = $this->integerValue(
                $result,
                'produk_id'
            );

            if (
                ! $requestedById->has($produkId)
                || isset($receivedIds[$produkId])
            ) {
                throw $this->invalidResponse();
            }

            $prediction = $this->numericValue(
                $result,
                'prediksi'
            );

            if (
                ! is_finite($prediction)
                || $prediction < 0
            ) {
                throw $this->invalidResponse();
            }

            $receivedIds[$produkId] = true;

            $validated[] = [
                'produk_id' => $produkId,
                'prediksi' => $prediction,
            ];
        }

        if (
            count($receivedIds)
            !== $requestedById->count()
        ) {
            throw $this->invalidResponse();
        }

        return $validated;
    }

    /** @param array<string, mixed> $payload */
    private function integerValue(
        array $payload,
        string $field
    ): int {
        if (
            ! array_key_exists($field, $payload)
            || ! is_int($payload[$field])
        ) {
            throw $this->invalidResponse();
        }

        return $payload[$field];
    }

    /** @param array<string, mixed> $payload */
    private function numericValue(
        array $payload,
        string $field
    ): float {
        if (
            ! array_key_exists($field, $payload)
            || (
                ! is_int($payload[$field])
                && ! is_float($payload[$field])
            )
        ) {
            throw $this->invalidResponse();
        }

        return (float) $payload[$field];
    }

    /**
     * @param list<array{
     *     produk_id:int,
     *     series:list<array{periode:string,aktual:int}>
     * }> $products
     * @return list<array{
     *     produk_id:int,
     *     alpha:float,
     *     wape:float
     * }>
     */
    public function calibrate(
        array $products,
        int $testMonths
    ): array {
        if ($products === [] || ! array_is_list($products)) {
            throw new LogicException(
                'Data produk penentuan parameter harus berupa daftar '
                .'yang tidak kosong.'
            );
        }

        if ($testMonths < 1) {
            throw new LogicException(
                'Jumlah bulan test penentuan parameter tidak valid.'
            );
        }

        $response = $this->client()
            ->timeout(60)
            ->post(
                $this->endpoint('/api/v1/des/calibrate-batch'),
                [
                    'test_months' => $testMonths,
                    'products' => $products,
                ]
            )
            ->throw();

        $payload = $response->json();
        $results = is_array($payload)
            ? ($payload['results'] ?? null)
            : null;

        if (
            ! is_array($results)
            || ! array_is_list($results)
            || $results === []
        ) {
            throw $this->invalidResponse();
        }

        $normalized = [];
        $seenIds = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                throw $this->invalidResponse();
            }

            $required = [
                'produk_id',
                'alpha',
                'mae_train',
                'wape_train',
                'mae_test',
                'wape_test',
            ];
            foreach ($required as $field) {
                if (! is_numeric($result[$field] ?? null)) {
                    throw $this->invalidResponse();
                }
            }

            $productId = (int) $result['produk_id'];
            $alpha = (float) $result['alpha'];
            $maeTrain = (float) $result['mae_train'];
            $wapeTrain = (float) $result['wape_train'];
            $maeTest = (float) $result['mae_test'];
            $wapeTest = (float) $result['wape_test'];

            $metrics = [
                $maeTrain,
                $wapeTrain,
                $maeTest,
                $wapeTest,
            ];

            if (
                $productId <= 0
                || isset($seenIds[$productId])
                || ! is_finite($alpha)
                || $alpha < 0.01
                || $alpha > 0.99
                || collect($metrics)->contains(
                    fn (float $value): bool => ! is_finite($value) || $value < 0
                )
            ) {
                throw $this->invalidResponse();
            }

            $seenIds[$productId] = true;
            $normalized[] = [
                'produk_id' => $productId,
                'alpha' => $alpha,
                'mae_train' => $maeTrain,
                'wape_train' => $wapeTrain,
                'mae_test' => $maeTest,
                'wape_test' => $wapeTest,
            ];
        }

        return $normalized;
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->connectTimeout(
                (int) config(
                    'services.analytics.connect_timeout',
                    3
                )
            )
            ->timeout(
                (int) config(
                    'services.analytics.timeout',
                    10
                )
            );
    }

    private function endpoint(string $path): string
    {
        $baseUrl = config('services.analytics.url');

        if (
            ! is_string($baseUrl)
            || trim($baseUrl) === ''
        ) {
            throw new LogicException(
                'Konfigurasi URL Analytics Service belum ditentukan.'
            );
        }

        return rtrim($baseUrl, '/')
            .'/'
            .ltrim($path, '/');
    }

    private function invalidResponse(): UnexpectedValueException
    {
        return new UnexpectedValueException(
            'Respons layanan prediksi tidak sesuai kontrak.'
        );
    }
}
