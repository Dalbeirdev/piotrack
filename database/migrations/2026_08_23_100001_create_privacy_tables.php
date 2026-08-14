<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who accepted which policy version, and when (PRIV-001). Not
        // tenant-scoped: acceptance belongs to the person, not their org.
        Schema::create('policy_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('policy');           // terms|privacy|dpa
            $table->string('version');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accepted_at');
            $table->timestamps();

            $table->unique(['user_id', 'policy', 'version']);
        });

        // Cookie preferences (PRIV-002). Keyed by user where known, otherwise by
        // an anonymous visitor token so a pre-login choice is still honoured.
        Schema::create('cookie_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('visitor_token')->nullable();
            $table->boolean('necessary')->default(true);   // always on
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['user_id']);
            $table->index(['visitor_token']);
        });

        // Per-organization retention rules (PRIV-004).
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('subject');          // audit_logs|ai_requests|outbound_messages|intent_signals|calls
            $table->unsignedInteger('retain_days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'subject']);
        });

        // Data export / erasure requests (PRIV-003), so a GDPR request is a
        // tracked, auditable object rather than an ad-hoc script run.
        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');             // export|delete_user|delete_organization
            $table->string('status')->default('pending'); // pending|completed|failed
            $table->string('file_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('retention_policies');
        Schema::dropIfExists('cookie_preferences');
        Schema::dropIfExists('policy_acceptances');
    }
};
