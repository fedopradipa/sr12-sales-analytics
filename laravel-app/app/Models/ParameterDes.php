<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParameterDes extends Model
{
    use HasFactory;

    protected $table = 'parameter_des';

    protected $fillable = [
        'produk_id',
        'alpha',
        'jumlah_test',
        'mae_train',
        'wape_train',
        'mae_test',
        'wape_test',
        'data_awal',
        'data_akhir',
    ];

    protected function casts(): array
    {
        return [
            'produk_id' => 'integer',
            'alpha' => 'decimal:2',
            'jumlah_test' => 'integer',
            'mae_train' => 'decimal:4',
            'wape_train' => 'decimal:4',
            'mae_test' => 'decimal:4',
            'wape_test' => 'decimal:4',
            'data_awal' => 'date',
            'data_akhir' => 'date',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function produk(): BelongsTo
    {
        return $this->belongsTo(
            Produk::class,
            'produk_id'
        )->withTrashed();
    }
}
