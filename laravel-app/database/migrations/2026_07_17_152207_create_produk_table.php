<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'produk',
            function (Blueprint $table): void {
                $table->id();

                $table->string('kode', 20)->unique();
                $table->string('nama', 60);

                $table->unsignedInteger('stok')
                    ->default(0);

                $table->unsignedInteger('stok_min')
                    ->default(0);

                $table->timestamp('stok_at')
                    ->nullable();

                $table->timestamps();
                $table->softDeletes();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('produk');
    }
};
