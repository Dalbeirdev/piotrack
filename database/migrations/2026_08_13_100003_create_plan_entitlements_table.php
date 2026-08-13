<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entitlements bind a plan to what it grants: boolean feature access or a
     * numeric limit (int_value null = unlimited). Resolved live by the
     * Entitlements service (ENTL-001).
     */
    public function up(): void
    {
        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->string('key');
            $table->string('kind'); // feature | limit
            $table->boolean('bool_value')->nullable();
            $table->integer('int_value')->nullable(); // null = unlimited for limits
            $table->timestamps();

            $table->unique(['plan_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_entitlements');
    }
};
