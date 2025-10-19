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
        Schema::table('users', function (Blueprint $table): void {
            $table->string('cep', 9)
                ->nullable()
                ->after('password');
            $table->string('street')
                ->nullable()
                ->after('cep');
            $table->string('neighborhood')
                ->nullable()
                ->after('street');
            $table->string('city')
                ->nullable()
                ->after('neighborhood');
            $table->string('state', 2)
                ->nullable()
                ->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'cep',
                'street',
                'neighborhood',
                'city',
                'state',
            ]);
        });
    }
};
