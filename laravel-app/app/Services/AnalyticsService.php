<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use UnexpectedValueException;

final class AnalyticsService
{
    /** @return array{status:string,service:string} */
    public function health(): array
    {
        $response = $this->request()
            ->get($this->endpoint('/health'))
            ->throw();

        $payload = $response->json();

        if (
            ! is_array($payload)
            || ($payload['status'] ?? null) !== 'ok'
            || ($payload['service'] ?? null) !== 'sr12-analytics'
        ) {
            throw new UnexpectedValueException(
                'Respons health Analytics Service tidak sesuai kontrak.'
            );
        }

        return [
            'status' => 'ok',
            'service' => 'sr12-analytics',
        ];
    }

    /**
     * @param list<array{
     *     produk_id:int,
     *     recency:int,
     *     frekuensi:int,
     *     qty:int,
     *     omzet:int
     * }> $products
     * @return array{
     *     k:int,
     *     iterasi:int,
     *     results:list<array{
     *         produk_id:int,
     *         frekuensi:int,
     *         qty:int,
     *         omzet:int,
     *         cluster:int
     *     }>
     * }
     */
    public function analyzeKmeans(array $products): array
    {
        $normalizedProducts = $this->normalizeKmeansProducts($products);

        $response = $this->request()
            ->post(
                $this->endpoint('/api/kmeans/analyze'),
                ['products' => $normalizedProducts]
            )
            ->throw();

        return $this->validateKmeansResponse(
            $response->json(),
            $normalizedProducts
        );
    }

    private function request(): PendingRequest
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

        if (! is_string($baseUrl) || trim($baseUrl) === '') {
            throw new LogicException(
                'Konfigurasi URL Analytics Service belum ditentukan.'
            );
        }

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<int|string, mixed>  $products
     * @return list<array{
     *     produk_id:int,
     *     recency:int,
     *     frekuensi:int,
     *     qty:int,
     *     omzet:int
     * }>
     */
    private function normalizeKmeansProducts(array $products): array
    {
        if (! array_is_list($products)) {
            throw new InvalidArgumentException(
                'Data produk segmentasi harus berupa daftar berurutan.'
            );
        }

        if (count($products) < 3) {
            throw new InvalidArgumentException(
                'Data segmentasi minimal terdiri dari 3 produk.'
            );
        }

        $normalizedProducts = [];
        $productIds = [];

        foreach ($products as $index => $product) {
            if (! is_array($product)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Data produk segmentasi pada indeks %d tidak valid.',
                        $index
                    )
                );
            }

            $produkId = $this->integerField($product, 'produk_id', $index);
            $recency = $this->integerField($product, 'recency', $index);
            $frekuensi = $this->integerField($product, 'frekuensi', $index);
            $qty = $this->integerField($product, 'qty', $index);
            $omzet = $this->integerField($product, 'omzet', $index);

            if ($produkId <= 0) {
                throw new InvalidArgumentException(
                    sprintf(
                        'produk_id pada indeks %d harus lebih besar dari 0.',
                        $index
                    )
                );
            }

            if (
                $recency < 0
                || $frekuensi < 0
                || $qty < 0
                || $omzet < 0
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Atribut segmentasi pada indeks %d tidak boleh negatif.',
                        $index
                    )
                );
            }

            if (isset($productIds[$produkId])) {
                throw new InvalidArgumentException(
                    sprintf(
                        'produk_id %d terdapat lebih dari satu kali.',
                        $produkId
                    )
                );
            }

            $productIds[$produkId] = true;
            $normalizedProducts[] = [
                'produk_id' => $produkId,
                'recency' => $recency,
                'frekuensi' => $frekuensi,
                'qty' => $qty,
                'omzet' => $omzet,
            ];
        }

        return $normalizedProducts;
    }

    /** @param array<string, mixed> $product */
    private function integerField(
        array $product,
        string $field,
        int $index
    ): int {
        if (
            ! array_key_exists($field, $product)
            || ! is_int($product[$field])
        ) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s pada indeks %d harus berupa bilangan bulat.',
                    $field,
                    $index
                )
            );
        }

        return $product[$field];
    }

    /**
     * @param list<array{
     *     produk_id:int,
     *     recency:int,
     *     frekuensi:int,
     *     qty:int,
     *     omzet:int
     * }> $requestedProducts
     * @return array{
     *     k:int,
     *     iterasi:int,
     *     results:list<array{
     *         produk_id:int,
     *         frekuensi:int,
     *         qty:int,
     *         omzet:int,
     *         cluster:int
     *     }>
     * }
     */
    private function validateKmeansResponse(
        mixed $payload,
        array $requestedProducts
    ): array {
        if (! is_array($payload)) {
            throw $this->invalidKmeansResponse();
        }

        if (
            ($payload['k'] ?? null) !== 3
            || ! is_int($payload['iterasi'] ?? null)
            || $payload['iterasi'] < 1
            || ! is_array($payload['results'] ?? null)
            || ! array_is_list($payload['results'])
            || count($payload['results']) !== count($requestedProducts)
        ) {
            throw $this->invalidKmeansResponse();
        }

        $requestedByProductId = [];

        foreach ($requestedProducts as $product) {
            $requestedByProductId[$product['produk_id']] = $product;
        }

        $validatedResults = [];
        $receivedProductIds = [];

        foreach ($payload['results'] as $result) {
            if (! is_array($result)) {
                throw $this->invalidKmeansResponse();
            }

            foreach (
                [
                    'produk_id',
                    'recency',
                    'frekuensi',
                    'qty',
                    'omzet',
                    'cluster',
                ] as $field
            ) {
                if (
                    ! array_key_exists($field, $result)
                    || ! is_int($result[$field])
                ) {
                    throw $this->invalidKmeansResponse();
                }
            }

            $produkId = $result['produk_id'];

            if (
                ! isset($requestedByProductId[$produkId])
                || isset($receivedProductIds[$produkId])
            ) {
                throw $this->invalidKmeansResponse();
            }

            if ($result['cluster'] < 1 || $result['cluster'] > 3) {
                throw $this->invalidKmeansResponse();
            }

            $requestedProduct = $requestedByProductId[$produkId];

            if (
                $result['recency'] !== $requestedProduct['recency']
                || $result['frekuensi'] !== $requestedProduct['frekuensi']
                || $result['qty'] !== $requestedProduct['qty']
                || $result['omzet'] !== $requestedProduct['omzet']
            ) {
                throw $this->invalidKmeansResponse();
            }

            $receivedProductIds[$produkId] = true;
            $validatedResults[] = [
                'produk_id' => $produkId,
                'recency' => $result['recency'],
                'frekuensi' => $result['frekuensi'],
                'qty' => $result['qty'],
                'omzet' => $result['omzet'],
                'cluster' => $result['cluster'],
            ];
        }

        if (count($receivedProductIds) !== count($requestedByProductId)) {
            throw $this->invalidKmeansResponse();
        }

        return [
            'k' => 3,
            'iterasi' => $payload['iterasi'],
            'results' => $validatedResults,
        ];
    }

    private function invalidKmeansResponse(): UnexpectedValueException
    {
        return new UnexpectedValueException(
            'Respons layanan segmentasi tidak sesuai kontrak.'
        );
    }
}
