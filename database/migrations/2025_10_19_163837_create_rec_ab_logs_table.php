<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('rec_ab_logs')) {
            return;
        }

        Schema::create('rec_ab_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('device_id', 64);
            $table->string('placement', 32);
            $table->string('variant', 8);
            $table->foreignId('movie_id')->nullable()->constrained('movies')->cascadeOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['variant', 'created_at']);
            $table->index(['placement', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rec_ab_logs');
    }
};
