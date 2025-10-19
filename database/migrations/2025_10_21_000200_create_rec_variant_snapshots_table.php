<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rec_variant_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('variant', 8);
            $table->date('captured_on');
            $table->unsignedBigInteger('item_count')->default(0);
            $table->decimal('pop_total', 18, 6)->default(0.0);
            $table->decimal('recent_total', 18, 6)->default(0.0);
            $table->decimal('pref_total', 18, 6)->default(0.0);
            $table->decimal('score_total', 18, 6)->default(0.0);
            $table->decimal('weight_pop_total', 18, 6)->default(0.0);
            $table->decimal('weight_recent_total', 18, 6)->default(0.0);
            $table->decimal('weight_pref_total', 18, 6)->default(0.0);
            $table->timestamps();

            $table->unique(['captured_on', 'variant']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rec_variant_snapshots');
    }
};
