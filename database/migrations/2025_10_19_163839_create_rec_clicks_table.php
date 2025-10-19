<?php

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
        Schema::create('rec_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('movie_id')->constrained('movies')->cascadeOnDelete();
            $table->string('device_id', 64);
            $table->string('placement', 32);
            $table->string('variant', 8);
            $table->timestamp('clicked_at')->useCurrent();
            $table->timestamps();

            $table->index(['movie_id', 'created_at']);
            $table->index(['variant', 'created_at']);
            $table->index(['placement', 'created_at']);
            $table->index(['clicked_at']);
            $table->index(['device_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rec_clicks');
    }
};
