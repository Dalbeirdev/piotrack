<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_visibility_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('prompt');
            $table->string('engine')->default('chatgpt'); // chatgpt|gemini|perplexity|copilot|ai_overview
            $table->string('brand');
            $table->boolean('mentioned')->default(false);
            $table->unsignedSmallInteger('position')->nullable();
            $table->json('cited_sources')->nullable();
            $table->json('competitors')->nullable();
            $table->unsignedTinyInteger('share_of_answer')->nullable(); // percentage
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'engine', 'checked_at']);
        });

        Schema::create('structured_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('url')->nullable();
            $table->string('schema_type'); // Organization|LocalBusiness|Service|FAQPage|Article|Person|Review
            $table->text('jsonld');
            $table->boolean('is_applied')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'schema_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structured_data');
        Schema::dropIfExists('ai_visibility_checks');
    }
};
