<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rec_clicks')) {
            return;
        }

        Schema::create('rec_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('placement', 32);
            $table->string('variant', 8);
            $table->timestamps();

            $table->index(['created_at', 'variant']);
            $table->index(['placement', 'created_at']);
            $table->index(['movie_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rec_clicks');
    }
};
