<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'proses_des',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('user_id')
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->foreignId('kmeans_id')
                    ->constrained('hasil_kmeans')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();

                $table->unsignedTinyInteger('cluster');
                $table->date('data_awal');
                $table->date('data_akhir');
                $table->date('target');
                $table->timestamp('created_at')->useCurrent();

                $table->index(
                    [
                        'kmeans_id',
                        'cluster',
                        'target',
                    ],
                    'proses_des_sumber_target_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('proses_des');
    }
};
