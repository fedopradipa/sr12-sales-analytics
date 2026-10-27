<?php

namespace App\Services;

use App\Models\ProsesDes;
use DateTimeInterface;
use Illuminate\Support\Facades\File;
use JsonException;
use UnexpectedValueException;

final class DesForecastScopeService
{
    private const VERSION = 1;

    /**
     * @return array{
     *     mode:'cluster'|'single',
     *     produk_id:int|null
     * }|null
     */
    public function resolve(
        ProsesDes $process
    ): ?array {
        $path = $this->path($process);

        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $payload = json_decode(
                $contents,
                true,
                flags: JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return null;
        }

        if (! is_array($payload)) {
            return null;
        }

        if (
            (int) ($payload['version'] ?? 0)
            !== self::VERSION
            || ($payload['process'] ?? null)
            !== $this->fingerprint($process)
        ) {
            return null;
        }

        $mode = $payload['mode'] ?? null;

        if ($mode === 'cluster') {
            return [
                'mode' => 'cluster',
                'produk_id' => null,
            ];
        }

        $productId = (int) (
            $payload['produk_id'] ?? 0
        );

        if (
            $mode !== 'single'
            || $productId < 1
        ) {
            return null;
        }

        return [
            'mode' => 'single',
            'produk_id' => $productId,
        ];
    }

    public function remember(
        ProsesDes $process,
        ?int $produkId
    ): void {
        $directory = $this->directory();

        File::ensureDirectoryExists(
            $directory
        );

        $payload = [
            'version' => self::VERSION,
            'mode' => $produkId === null
                ? 'cluster'
                : 'single',
            'produk_id' => $produkId,
            'process' => $this->fingerprint(
                $process
            ),
        ];

        try {
            $json = json_encode(
                $payload,
                JSON_PRETTY_PRINT
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            throw new UnexpectedValueException(
                'Metadata cakupan peramalan tidak dapat diserialisasi.',
                previous: $exception
            );
        }

        $written = file_put_contents(
            $this->path($process),
            $json.PHP_EOL,
            LOCK_EX
        );

        if ($written === false) {
            throw new UnexpectedValueException(
                'Metadata cakupan peramalan tidak dapat disimpan.'
            );
        }
    }

    private function directory(): string
    {
        return app()->environment(
            'testing'
        )
            ? storage_path(
                'framework/testing/des-forecast-scopes'
            )
            : storage_path(
                'app/des-forecast-scopes'
            );
    }

    private function path(
        ProsesDes $process
    ): string {
        return $this->directory()
            .DIRECTORY_SEPARATOR
            .((int) $process->id)
            .'.json';
    }

    /**
     * @return array<string, int|string|null>
     */
    private function fingerprint(
        ProsesDes $process
    ): array {
        return [
            'id' => (int) $process->id,
            'user_id' => (int)
                $process->user_id,
            'kmeans_id' => (int)
                $process->kmeans_id,
            'cluster' => (int)
                $process->cluster,
            'data_awal' => $this->dateValue(
                $process->data_awal
            ),
            'data_akhir' => $this->dateValue(
                $process->data_akhir
            ),
            'target' => $this->dateValue(
                $process->target
            ),
            'created_at' => $this->dateTimeValue(
                $process->created_at
            ),
        ];
    }

    private function dateValue(
        mixed $value
    ): ?string {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $text = trim((string) $value);

        return $text === ''
            ? null
            : substr($text, 0, 10);
    }

    private function dateTimeValue(
        mixed $value
    ): ?string {
        if ($value instanceof DateTimeInterface) {
            return $value->format(
                'Y-m-d H:i:s'
            );
        }

        $text = trim((string) $value);

        return $text === ''
            ? null
            : substr($text, 0, 19);
    }
}
