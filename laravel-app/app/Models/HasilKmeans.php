<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HasilKmeans extends Model
{
    use HasFactory;

    protected $table = 'hasil_kmeans';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'awal',
        'akhir',
        'iterasi',
    ];

    protected function casts(): array
    {
        return [
            'awal' => 'date',
            'akhir' => 'date',
            'iterasi' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')
            ->withTrashed();
    }

    public function detail(): HasMany
    {
        return $this->hasMany(
            DetailKmeans::class,
            'kmeans_id'
        )
            ->orderBy('cluster')
            ->orderBy('recency')
            ->orderByDesc('omzet')
            ->orderByDesc('qty');
    }

    public function prosesDes(): HasMany
    {
        return $this->hasMany(
            ProsesDes::class,
            'kmeans_id'
        )->orderByDesc('created_at');
    }
}
