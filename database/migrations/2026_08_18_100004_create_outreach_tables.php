<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outreach_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('digital_pr'); // digital_pr|link_building
            $table->text('goal')->nullable();
            $table->string('status')->default('active'); // active|paused|completed
            $table->timestamps();

            $table->index(['organization_id', 'type', 'status']);
        });

        Schema::create('outreach_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('outreach_campaign_id')->constrained('outreach_campaigns')->cascadeOnDelete();
            $table->string('name'); // publication or site
            $table->string('domain')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('status')->default('identified'); // identified|contacted|replied|won|lost
            $table->string('placement_url')->nullable(); // coverage or acquired backlink
            $table->unsignedTinyInteger('domain_authority')->nullable();
            $table->string('anchor_text')->nullable();
            $table->string('link_type')->nullable(); // dofollow|nofollow
            $table->timestamps();

            $table->index(['organization_id', 'outreach_campaign_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_prospects');
        Schema::dropIfExists('outreach_campaigns');
    }
};
