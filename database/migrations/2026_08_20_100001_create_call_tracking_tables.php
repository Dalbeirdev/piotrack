<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_tracking_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('phone_number');
            $table->string('label')->nullable();
            $table->string('source')->nullable();   // channel that renders this number
            $table->string('campaign')->nullable();
            $table->string('provider')->default('fixture');
            $table->string('provider_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('call_tracking_number_id')->nullable()->constrained('call_tracking_numbers')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_number')->nullable();
            $table->string('to_number')->nullable();
            $table->string('direction')->default('inbound'); // inbound|outbound
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->string('status')->default('completed'); // completed|missed|voicemail
            $table->string('source')->nullable();
            $table->string('campaign')->nullable();
            $table->unsignedTinyInteger('score')->default(0); // 0-100 lead quality
            $table->boolean('is_qualified')->default(false);
            $table->boolean('converted')->default(false);
            $table->string('recording_url')->nullable();   // provider-only (Planned)
            $table->text('transcript')->nullable();        // provider-only (Planned)
            $table->text('summary')->nullable();           // provider-only (Planned)
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'occurred_at']);
            $table->index(['organization_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
        Schema::dropIfExists('call_tracking_numbers');
    }
};
