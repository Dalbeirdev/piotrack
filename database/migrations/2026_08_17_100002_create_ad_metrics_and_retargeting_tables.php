<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('spend')->default(0); // minor units
            $table->unsignedBigInteger('conversions')->default(0);
            $table->unsignedBigInteger('revenue')->default(0); // minor units
            $table->timestamps();

            $table->unique(['ad_campaign_id', 'date']);
            $table->index(['organization_id', 'ad_campaign_id', 'date']);
        });

        Schema::create('retargeting_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('source')->default('list'); // list|behavior|funnel_stage|all_contacts
            $table->foreignId('marketing_list_id')->nullable()->constrained('marketing_lists')->nullOnDelete();
            $table->json('rules')->nullable(); // e.g. {lifecycle_stage, min_lead_score}
            $table->json('platforms')->nullable(); // ['google','meta','linkedin']
            $table->boolean('exclude_converted')->default(true);
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamps();

            $table->index('organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retargeting_audiences');
        Schema::dropIfExists('ad_metrics');
    }
};
