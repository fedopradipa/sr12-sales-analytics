<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hasil_des', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('proses_id')
                ->constrained('proses_des')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();

            $table->foreignId('produk_id')
                ->constrained('produk')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            $table->date('target');
            $table->decimal('prediksi', 20, 4);

            $table->unsignedInteger('stok_pred')->nullable();
            $table->unsignedInteger('stok_min_pred')->nullable();
            $table->timestamp('stok_pred_at')->nullable();
            $table->unsignedInteger('estimasi')->nullable();
            $table->string('status', 20);

            $table->unique(
                ['proses_id', 'produk_id'],
                'hasil_des_proses_produk_unique'
            );

            $table->index(
                'produk_id',
                'hasil_des_produk_id_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_des');
    }
};
