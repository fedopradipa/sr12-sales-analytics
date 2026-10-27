<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProsesDes extends Model
{
    protected $table = 'proses_des';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'kmeans_id',
        'cluster',
        'data_awal',
        'data_akhir',
        'target',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'kmeans_id' => 'integer',
            'cluster' => 'integer',
            'data_awal' => 'date',
            'data_akhir' => 'date',
            'target' => 'date',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        )->withTrashed();
    }

    public function hasilKmeans(): BelongsTo
    {
        return $this->belongsTo(
            HasilKmeans::class,
            'kmeans_id'
        );
    }

    public function hasilDes(): HasMany
    {
        return $this->hasMany(
            HasilDes::class,
            'proses_id'
        );
    }
}
