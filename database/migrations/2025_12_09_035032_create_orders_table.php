<?php

use App\Enums\SideType;
use App\Enums\StatusType;
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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('symbol');
            $table->enum('side', SideType::cases());

            $table->decimal('price', 20, 8);
            $table->decimal('amount', 20, 8);
            $table->decimal('locked_amount', 20, 8)->default(0);
            $table->unsignedTinyInteger('status')->default(StatusType::Open->value)->index();

            $table->timestamps();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->index(['symbol', 'status', 'side', 'price']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
