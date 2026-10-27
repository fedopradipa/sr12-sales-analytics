<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailKmeans extends Model
{
    use HasFactory;

    protected $table = 'detail_kmeans';

    public $timestamps = false;

    protected $fillable = [
        'kmeans_id',
        'produk_id',
        'recency',
        'frekuensi',
        'qty',
        'omzet',
        'cluster',
    ];

    protected function casts(): array
    {
        return [
            'kmeans_id' => 'integer',
            'produk_id' => 'integer',
            'recency' => 'integer',
            'frekuensi' => 'integer',
            'qty' => 'integer',
            'omzet' => 'integer',
            'cluster' => 'integer',
        ];
    }

    public function hasilKmeans(): BelongsTo
    {
        return $this->belongsTo(
            HasilKmeans::class,
            'kmeans_id'
        );
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(
            Produk::class,
            'produk_id'
        )->withTrashed();
    }
}
