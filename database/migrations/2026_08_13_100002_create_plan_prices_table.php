<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('interval'); // monthly | annual
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('amount'); // minor units (cents)
            $table->boolean('per_seat')->default(false);
            $table->string('provider_price_id')->nullable(); // e.g. Stripe price id
            $table->timestamps();

            $table->unique(['plan_id', 'interval', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};
