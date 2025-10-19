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
        Schema::create('bookmarkable', function (Blueprint $table) {
            $table->foreignId('bookmark_id')->references('id')->on('bookmarks')->onDelete('cascade');

            $table->unsignedBigInteger('bookmarkable_id');
            $table->string('bookmarkable_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarkable');
    }
};
