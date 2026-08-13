<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('fields'); // [{name,label,type,required}]
            $table->json('settings')->nullable(); // {redirect_url, success_message, double_optin}
            $table->foreignId('target_list_id')->nullable()->constrained('marketing_lists')->nullOnDelete();
            $table->string('lifecycle_stage')->default('lead');
            $table->string('status')->default('draft'); // draft|published
            $table->unsignedInteger('submission_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->json('payload');
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'form_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
