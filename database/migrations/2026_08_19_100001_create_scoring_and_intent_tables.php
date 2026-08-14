<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('behavioral'); // demographic|firmographic|behavioral|intent
            $table->string('attribute'); // lifecycle_stage|lead_source|title|email_opt_in|has_company|intent_score
            $table->string('operator')->default('equals'); // equals|contains|gte|is_true
            $table->string('value')->nullable();
            $table->integer('points')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('intent_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('type'); // page_visit|service_view|pricing_view|download|campaign_engagement|ad_engagement|crm_activity|demo_request
            $table->unsignedInteger('weight')->default(1);
            $table->string('url')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'contact_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intent_signals');
        Schema::dropIfExists('scoring_rules');
    }
};
