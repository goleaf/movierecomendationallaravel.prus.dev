<?php

declare(strict_types=1);

use App\Models\Movie;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('movie_cast')) {
            Schema::create('movie_cast', function (Blueprint $table): void {
                $table->id();
                $table->foreignIdFor(Movie::class)->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('character')->nullable();
                $table->unsignedSmallInteger('order_column')->default(0);
                $table->timestamps();

                $table->index(['movie_id', 'order_column']);
            });
        }

        if (! Schema::hasTable('movie_posters')) {
            Schema::create('movie_posters', function (Blueprint $table): void {
                $table->id();
                $table->foreignIdFor(Movie::class)->constrained()->cascadeOnDelete();
                $table->string('url');
                $table->unsignedSmallInteger('priority')->default(0);
                $table->timestamps();

                $table->index(['movie_id', 'priority']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movie_posters');
        Schema::dropIfExists('movie_cast');
    }
};
