<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('requester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('open');     // open|pending|resolved|closed
            $table->string('priority')->default('normal'); // low|normal|high|urgent
            $table->string('category')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'priority']);
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            // Internal notes are never exposed through the client portal.
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'ticket_id']);
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('active');   // planning|active|on_hold|completed
            $table->string('health')->default('on_track'); // on_track|at_risk|off_track
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // The delivery-team roles from PROJ-001…008.
            $table->string('role'); // strategist|project_manager|seo|ppc|developer|designer|copywriter|automation
            $table->timestamps();

            $table->unique(['project_id', 'user_id', 'role']);
        });

        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('planned'); // planned|active|completed
            $table->timestamps();

            $table->index(['organization_id', 'project_id', 'status']);
        });

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained('sprints')->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo');      // todo|in_progress|review|done
            $table->string('priority')->default('normal');  // low|normal|high
            $table->date('due_on')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'project_id', 'status']);
        });

        Schema::create('deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('document'); // document|content|website|campaign|report|design
            $table->string('status')->default('in_progress'); // in_progress|submitted|delivered
            $table->string('approval_status')->default('not_required'); // not_required|pending|approved|rejected
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->date('due_on')->nullable();
            $table->timestamp('approved_at')->nullable();
            // Only explicitly client-visible deliverables reach the portal.
            $table->boolean('client_visible')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'project_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverables');
        Schema::dropIfExists('project_tasks');
        Schema::dropIfExists('sprints');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('tickets');
    }
};
