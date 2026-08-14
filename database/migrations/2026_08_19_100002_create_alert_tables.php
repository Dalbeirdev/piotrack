<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger'); // score_threshold|high_intent|meeting_request|repeat_visit|bottom_funnel
            $table->integer('threshold')->default(0);
            $table->string('channel')->default('in_app'); // in_app|email
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'trigger', 'is_active']);
        });

        Schema::create('sales_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('type');
            $table->string('message');
            $table->boolean('is_read')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_alerts');
        Schema::dropIfExists('alert_rules');
    }
};
