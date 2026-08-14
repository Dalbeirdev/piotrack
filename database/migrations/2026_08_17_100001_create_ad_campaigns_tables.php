<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('platform'); // google_search|microsoft|linkedin|meta|youtube
            $table->string('name');
            $table->string('type')->nullable(); // keyword|brand|competitor|service|location|vertical|high_intent
            $table->string('objective')->default('leads'); // leads|awareness|traffic|conversions
            $table->string('status')->default('draft'); // draft|active|paused|ended
            $table->unsignedBigInteger('daily_budget')->default(0); // minor units
            $table->unsignedBigInteger('total_budget')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('targeting')->nullable();
            $table->string('external_id')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'platform', 'status']);
        });

        Schema::create('ad_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->string('bid_strategy')->nullable(); // manual_cpc|maximize_conversions|target_cpa
            $table->unsignedBigInteger('bid_amount')->nullable();
            $table->json('targeting')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'ad_campaign_id']);
        });

        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ad_group_id')->constrained('ad_groups')->cascadeOnDelete();
            $table->string('name');
            $table->string('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('cta')->nullable();
            $table->string('destination_url')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['organization_id', 'ad_group_id']);
        });

        Schema::create('ad_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ad_group_id')->constrained('ad_groups')->cascadeOnDelete();
            $table->string('phrase');
            $table->string('match_type')->default('broad'); // broad|phrase|exact
            $table->boolean('is_negative')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'ad_group_id', 'is_negative']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_keywords');
        Schema::dropIfExists('ads');
        Schema::dropIfExists('ad_groups');
        Schema::dropIfExists('ad_campaigns');
    }
};
