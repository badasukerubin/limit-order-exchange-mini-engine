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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();

            $table->string('symbol');
            $table->decimal('amount', 20, 8)->default(0);
            $table->decimal('locked_amount', 20, 8)->default(0);

            $table->timestamps();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unique(['user_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
