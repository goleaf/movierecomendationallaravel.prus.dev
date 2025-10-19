<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ingestion_runs')) {
            return;
        }

        Schema::create('ingestion_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('sources', 191);
            $table->string('external_id', 191);
            $table->date('ingested_on');
            $table->string('last_etag', 191)->nullable();
            $table->timestampTz('last_modified')->nullable();
            $table->timestamps();

            $table->unique(['sources', 'external_id', 'ingested_on'], 'ingestion_runs_unique_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ingestion_runs');
    }
};
