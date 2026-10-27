<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'hasil_kmeans',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->date('awal');
                $table->date('akhir');
                $table->unsignedSmallInteger('iterasi');

                $table->timestamp('created_at')->useCurrent();

                $table->index(
                    [
                        'awal',
                        'akhir',
                    ],
                    'kmeans_periode_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('hasil_kmeans');
    }
};
