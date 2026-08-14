<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_tracked')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_tracked']);
        });

        Schema::create('growth_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->unsignedTinyInteger('overall');       // 0-100
            $table->json('breakdown');                    // sub-score map
            $table->json('recommendations')->nullable();  // prioritized actions
            $table->date('computed_on');                  // one snapshot per org per day
            $table->timestamps();

            $table->unique(['organization_id', 'computed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('growth_scores');
        Schema::dropIfExists('competitors');
    }
};
