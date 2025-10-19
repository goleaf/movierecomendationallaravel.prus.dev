<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rec_clicks', function (Blueprint $table): void {
            $table->id();
            $table->string('device_id');
            $table->foreignId('movie_id')->constrained()->cascadeOnDelete();
            $table->string('placement', 32);
            $table->string('variant', 1);
            $table->unsignedTinyInteger('position')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['placement', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rec_clicks');
    }
};
