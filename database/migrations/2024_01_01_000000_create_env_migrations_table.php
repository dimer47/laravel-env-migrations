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
        Schema::create(config('env-migrations.table', 'env_migrations'), function (Blueprint $table) {
            $table->id();
            $table->string('migration')->unique();
            $table->integer('batch');
            $table->json('changes')->nullable()->comment('Historique des modifications effectuées');
            $table->timestamp('executed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(config('env-migrations.table', 'env_migrations'));
    }
};
