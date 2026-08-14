<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Platform-scoped tables (Stage 13, ADMIN + SUPP help centre). These are
 * deliberately NOT tenant-scoped: they belong to the platform operator, not to
 * any single organization.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->boolean('is_kill_switch')->default(false); // overrides every other rule when on
            $table->json('rollout')->nullable();               // {organizations: [], percentage: 0-100}
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->string('audience')->default('all');   // all|plan code|platform
            $table->string('type')->default('announcement'); // announcement|release_note
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['audience', 'published_at']);
        });

        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('impersonator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('reason');                      // mandatory: why support needed access
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['impersonator_id', 'ended_at']);
        });

        Schema::create('kb_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('body');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_published']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('impersonation_sessions');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('feature_flags');
    }
};
