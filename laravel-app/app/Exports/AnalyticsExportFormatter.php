<?php

namespace App\Exports;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AnalyticsExportFormatter
{
    private const CLUSTER_LABELS = [
        1 => 'Pergerakan Tinggi',
        2 => 'Pergerakan Sedang',
        3 => 'Pergerakan Rendah',
    ];

    public static function clusterLabel(int $cluster): string
    {
        return self::CLUSTER_LABELS[$cluster]
            ?? 'Tidak diketahui';
    }

    public static function processTime(
        ?CarbonInterface $value
    ): string {
        if ($value === null) {
            return '-';
        }

        return $value
            ->copy()
            ->setTimezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('d M Y, H.i')
            .' WIB';
    }

    public static function dateRange(
        ?CarbonInterface $start,
        ?CarbonInterface $end
    ): string {
        if ($start === null || $end === null) {
            return '-';
        }

        $start = $start->copy()->locale('id');
        $end = $end->copy()->locale('id');

        if ($start->year === $end->year) {
            return sprintf(
                '%s s.d. %s',
                $start->translatedFormat('d M'),
                $end->translatedFormat('d M Y')
            );
        }

        return sprintf(
            '%s s.d. %s',
            $start->translatedFormat('d M Y'),
            $end->translatedFormat('d M Y')
        );
    }

    /**
     * @param  Collection<int, mixed>  $forecastDetails
     */
    public static function predictionPeriod(
        Collection $forecastDetails
    ): string {
        $details = $forecastDetails
            ->sortBy('periode')
            ->values();

        $first = $details->first();
        $last = $details->last();

        if ($first === null || $last === null) {
            return '-';
        }

        $firstLabel = $first->periode
            ->locale('id')
            ->translatedFormat('M Y');

        if ($details->count() === 1) {
            return $firstLabel;
        }

        return sprintf(
            '%s s.d. %s',
            $firstLabel,
            $last->periode
                ->locale('id')
                ->translatedFormat('M Y')
        );
    }
}
