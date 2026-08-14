<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('landing_page'); // landing_page|cta|form|copy|headline|offer|layout|ux|multivariate
            $table->text('hypothesis')->nullable();
            $table->string('primary_metric')->default('conversion_rate');
            $table->string('status')->default('draft'); // draft|running|completed
            $table->foreignId('winning_variant_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('experiment_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('experiment_id')->constrained('experiments')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_control')->default(false);
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'experiment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_variants');
        Schema::dropIfExists('experiments');
    }
};
