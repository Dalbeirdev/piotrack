<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('key');
            $table->unsignedInteger('version')->default(1);
            $table->string('description')->nullable();
            $table->text('system')->nullable();
            $table->text('template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'key', 'version']);
            $table->index(['organization_id', 'key', 'is_active']);
        });

        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ai_prompt_template_id')->nullable()->constrained('ai_prompt_templates')->nullOnDelete();
            $table->string('feature');                       // cost attribution per feature
            $table->string('provider');
            $table->string('model');
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedBigInteger('estimated_cost')->default(0); // minor units
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->string('status')->default('succeeded');  // succeeded|failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'feature']);
            $table->index(['organization_id', 'created_at']);
        });

        Schema::create('ai_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type');                          // send_email|update_crm|book_meeting|...
            $table->string('summary');
            $table->json('payload');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('status')->default('pending');    // pending|confirmed|rejected|executed
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_actions');
        Schema::dropIfExists('ai_requests');
        Schema::dropIfExists('ai_prompt_templates');
    }
};
