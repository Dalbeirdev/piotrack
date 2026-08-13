<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Metered usage per tenant, per key, per billing period (ENTL-005).
     * Live-derived usage (e.g. member count) is computed on read; this table
     * holds incrementing meters (emails, API calls, …) as their modules land.
     */
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamps();

            $table->unique(['organization_id', 'key', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
