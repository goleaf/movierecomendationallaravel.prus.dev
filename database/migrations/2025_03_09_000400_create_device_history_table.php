<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_history')) {
            return;
        }

        Schema::create('device_history', function (Blueprint $table): void {
            $table->id();
            $table->string('device_id');
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->string('placement', 32)->nullable();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->index('viewed_at');
            $table->index('placement');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_history');
    }
};
