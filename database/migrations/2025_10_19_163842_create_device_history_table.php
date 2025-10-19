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
        if (Schema::hasTable('device_history')) {
            return;
        }

        Schema::create('device_history', function (Blueprint $table): void {
            $table->id();
            $table->string('device_id', 64);
            $table->string('page', 64);
            $table->foreignId('movie_id')->nullable()->constrained('movies')->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->index(['viewed_at']);
            $table->index(['device_id', 'viewed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_history');
    }
};
