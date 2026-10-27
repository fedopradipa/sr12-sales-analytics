<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produk extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'produk';

    protected $fillable = [
        'kode',
        'nama',
        'stok',
        'stok_min',
        'stok_at',
    ];

    protected function casts(): array
    {
        return [
            'stok' => 'integer',
            'stok_min' => 'integer',
            'stok_at' => 'datetime',
        ];
    }

    public function detailTransaksi(): HasMany
    {
        return $this->hasMany(
            DetailTransaksi::class,
            'produk_id'
        );
    }

    public function parameterDes(): HasOne
    {
        return $this->hasOne(
            ParameterDes::class,
            'produk_id'
        );
    }

    public function hasilDes(): HasMany
    {
        return $this->hasMany(
            HasilDes::class,
            'produk_id'
        );
    }
}
