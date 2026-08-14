<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('strategy_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->string('status')->default('draft'); // draft|active|completed
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('strategy_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('strategy_plan_id')->nullable()->constrained('strategy_plans')->cascadeOnDelete();
            $table->string('type');   // assessment|audit|research|roadmap|initiative
            $table->string('title');
            $table->text('findings')->nullable();
            $table->text('recommendation')->nullable();
            $table->string('priority')->default('medium'); // low|medium|high
            $table->string('status')->default('open');     // open|in_progress|complete
            $table->date('due_on')->nullable();
            // Where a module already computes part of this work, name it so the
            // strategy record points at the real data instead of duplicating it.
            $table->string('source_module')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type', 'status']);
        });

        Schema::create('kpi_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('metric'); // leads|sqls|meetings|cpl|mrr|revenue|roi
            $table->unsignedBigInteger('target_value'); // minor units for money metrics
            $table->date('period_start');
            $table->date('period_end');
            $table->timestamps();

            $table->index(['organization_id', 'metric']);
        });

        Schema::create('brand_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained('organizations')->cascadeOnDelete();
            $table->text('positioning_statement')->nullable();
            $table->text('usp')->nullable();
            $table->text('value_proposition')->nullable();
            $table->json('differentiators')->nullable();
            $table->text('narrative')->nullable();
            $table->text('story')->nullable();
            $table->string('tone_of_voice')->nullable();
            $table->json('messaging_hierarchy')->nullable();
            $table->text('elevator_pitch')->nullable();
            $table->string('tagline')->nullable();
            $table->json('palette')->nullable();
            $table->json('typography')->nullable();
            $table->text('imagery_direction')->nullable();
            $table->string('guidelines_url')->nullable();
            $table->timestamps();
        });

        Schema::create('brand_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('type');  // logo|deck|one_pager|service_sheet|case_study_template|proposal_template|social_proof|testimonial|guidelines|presentation
            $table->string('title');
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });

        Schema::create('engagements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');  // consulting|training|masterclass|workshop|qbr|strategy_review|competitive_review|growth_planning
            $table->string('topic')->nullable(); // marketing|seo|sales|executive
            $table->string('title');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status')->default('scheduled'); // scheduled|completed|canceled
            $table->unsignedInteger('attendees')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type', 'status']);
        });

        Schema::create('performance_agreements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('model')->default('guarantee'); // guarantee|performance_pricing|pay_per_lead
            $table->unsignedInteger('lead_target')->default(0);
            $table->unsignedInteger('sql_target')->default(0);
            $table->unsignedInteger('meeting_target')->default(0);
            $table->json('quality_criteria')->nullable();  // what counts as a qualified lead
            $table->json('deliverables')->nullable();      // guaranteed deliverables
            $table->unsignedInteger('sla_days')->default(30);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('active');   // active|completed|breached
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('lead_replacements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('performance_agreement_id')->nullable()->constrained('performance_agreements')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('replacement_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('reason');
            $table->timestamp('replaced_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'performance_agreement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_replacements');
        Schema::dropIfExists('performance_agreements');
        Schema::dropIfExists('engagements');
        Schema::dropIfExists('brand_assets');
        Schema::dropIfExists('brand_profiles');
        Schema::dropIfExists('kpi_targets');
        Schema::dropIfExists('strategy_items');
        Schema::dropIfExists('strategy_plans');
    }
};
