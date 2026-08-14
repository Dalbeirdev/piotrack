<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('channel')->default('web');       // web|chat
            $table->string('status')->default('open');       // open|closed
            $table->text('summary')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('ai_request_id')->nullable()->constrained('ai_requests')->nullOnDelete();
            $table->string('role');                          // user|assistant
            $table->text('body');
            $table->timestamps();

            $table->index(['organization_id', 'ai_conversation_id']);
        });

        // AIVIS prompt library: the questions we monitor across AI engines.
        Schema::create('ai_prompts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('text');
            $table->string('category')->nullable();
            $table->string('service')->nullable();           // service-level visibility
            $table->string('city')->nullable();              // city-level visibility
            $table->string('vertical')->nullable();          // vertical-level visibility
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        // Link existing visibility checks to the library + capture recommendation.
        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->foreignId('ai_prompt_id')->nullable()->after('organization_id')
                ->constrained('ai_prompts')->nullOnDelete();
            $table->boolean('recommended')->default(false)->after('mentioned');
        });
    }

    public function down(): void
    {
        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_prompt_id');
            $table->dropColumn('recommended');
        });

        Schema::dropIfExists('ai_prompts');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
