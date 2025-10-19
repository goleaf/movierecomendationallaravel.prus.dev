<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_history', function (Blueprint $table): void {
            $table->id();
            $table->string('device_id', 64);
            $table->foreignId('movie_id')->nullable()->constrained('movies')->nullOnDelete();
            $table->string('placement', 32)->nullable();
            $table->string('variant', 8)->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->index('viewed_at');
            $table->index(['device_id', 'viewed_at']);
            $table->index(['movie_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_history');
    }
};
