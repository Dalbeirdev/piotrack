<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('integration_id')->constrained('integrations')->cascadeOnDelete();
            $table->string('status')->default('running'); // running|success|failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('records')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'integration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
    }
};
