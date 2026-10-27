<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'parameter_des',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('produk_id')
                    ->unique()
                    ->constrained('produk')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->decimal('alpha', 3, 2);
                $table->unsignedSmallInteger('jumlah_test')->nullable();
                $table->decimal('mae_train', 20, 4)->nullable();
                $table->decimal('wape_train', 10, 4)->nullable();
                $table->decimal('mae_test', 20, 4)->nullable();
                $table->decimal('wape_test', 10, 4)->nullable();
                $table->date('data_awal')->nullable();
                $table->date('data_akhir')->nullable();

                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_des');
    }
};
