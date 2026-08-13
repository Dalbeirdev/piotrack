<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('funnel_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('funnel_id')->constrained('funnels')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position');
            $table->string('category')->default('tof'); // tof|mof|bof|post
            $table->string('lifecycle_stage')->nullable(); // maps a funnel stage to a contact lifecycle_stage
            $table->timestamps();

            $table->index(['funnel_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funnel_stages');
        Schema::dropIfExists('funnels');
    }
};
