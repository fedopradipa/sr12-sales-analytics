<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilDes extends Model
{
    use HasFactory;

    protected $table = 'hasil_des';

    public $timestamps = false;

    protected $fillable = [
        'proses_id',
        'produk_id',
        'target',
        'prediksi',
        'stok_pred',
        'stok_min_pred',
        'stok_pred_at',
        'estimasi',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'proses_id' => 'integer',
            'produk_id' => 'integer',
            'target' => 'date',
            'prediksi' => 'decimal:4',
            'stok_pred' => 'integer',
            'stok_min_pred' => 'integer',
            'stok_pred_at' => 'datetime',
            'estimasi' => 'integer',
        ];
    }

    public function prosesDes(): BelongsTo
    {
        return $this->belongsTo(
            ProsesDes::class,
            'proses_id'
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
