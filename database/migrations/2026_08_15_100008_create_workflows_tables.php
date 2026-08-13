<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('trigger_type'); // form_submission|lead_stage|deal_stage|email_engagement|list_added
            $table->json('trigger_config')->nullable();
            $table->string('status')->default('paused'); // active|paused
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'trigger_type', 'status']);
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('action_type'); // send_email|send_sms|assign|create_task|update_crm|change_score|change_lifecycle|notify|add_to_list|remove_from_list|schedule_follow_up
            $table->json('action_config')->nullable();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->timestamps();

            $table->index(['workflow_id', 'position']);
        });

        Schema::create('workflow_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->unsignedInteger('current_position')->default(0);
            $table->string('status')->default('active'); // active|completed|exited
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'next_run_at']);
            $table->index(['workflow_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_enrollments');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
    }
};
