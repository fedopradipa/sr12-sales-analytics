<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'detail_kmeans',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('kmeans_id')
                    ->constrained('hasil_kmeans')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();

                $table->foreignId('produk_id')
                    ->constrained('produk')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->unsignedSmallInteger('recency');
                $table->unsignedSmallInteger('frekuensi');
                $table->unsignedInteger('qty');
                $table->unsignedBigInteger('omzet');
                $table->unsignedTinyInteger('cluster');

                $table->unique(
                    [
                        'kmeans_id',
                        'produk_id',
                    ],
                    'detail_kmeans_hasil_produk_unique'
                );

                $table->index(
                    [
                        'kmeans_id',
                        'cluster',
                    ],
                    'detail_kmeans_hasil_cluster_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_kmeans');
    }
};
