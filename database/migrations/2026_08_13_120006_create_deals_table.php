<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('pipeline_id')->constrained('pipelines');
            $table->foreignId('stage_id')->constrained('pipeline_stages');
            $table->string('name');
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            // Money in minor units (cents). Attribution chain feeds ATTR (Stage 11).
            $table->unsignedBigInteger('value')->default(0);
            $table->unsignedBigInteger('mrr')->default(0);
            $table->unsignedBigInteger('arr')->default(0);
            $table->unsignedInteger('contract_term_months')->nullable();
            $table->unsignedBigInteger('ltv')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('open'); // open|won|lost
            $table->string('lead_source')->nullable();
            $table->string('campaign')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('marketing_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_close_date')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'status']);
            $table->index(['organization_id', 'stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
