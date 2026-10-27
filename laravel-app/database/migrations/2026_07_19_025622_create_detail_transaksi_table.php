<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'detail_transaksi',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->foreignId('produk_id')
                    ->constrained('produk')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->date('tanggal');
                $table->string('faktur', 20);

                $table->unsignedSmallInteger('qty');
                $table->unsignedInteger('harga');
                $table->unsignedInteger('subtotal');

                $table->timestamp('created_at')->useCurrent();

                $table->unique(
                    [
                        'tanggal',
                        'faktur',
                        'produk_id',
                    ],
                    'trx_tanggal_faktur_produk_unique'
                );

                $table->index(
                    'faktur',
                    'trx_faktur_index'
                );

                $table->index(
                    [
                        'produk_id',
                        'tanggal',
                    ],
                    'trx_produk_tanggal_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('detail_transaksi');
    }
};
