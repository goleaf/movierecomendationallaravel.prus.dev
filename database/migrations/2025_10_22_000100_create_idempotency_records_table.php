<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_records', function (Blueprint $table): void {
            $table->id();
            $table->string('source');
            $table->string('external_id');
            $table->date('date_key');
            $table->string('last_etag')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['source', 'external_id', 'date_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_records');
    }
};
