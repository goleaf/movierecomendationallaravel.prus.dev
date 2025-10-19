<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rec_trending_rollups')) {
            return;
        }

        Schema::create('rec_trending_rollups', function (Blueprint $table): void {
            $table->date('captured_on');
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->unsignedInteger('clicks');
            $table->timestamps();

            $table->primary(['captured_on', 'movie_id']);
            $table->index('captured_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rec_trending_rollups');
    }
};
