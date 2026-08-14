<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('url');
            $table->unsignedTinyInteger('score')->default(0);
            $table->json('checks')->nullable(); // [{key,label,status,detail}]
            $table->unsignedSmallInteger('issues_count')->default(0);
            $table->unsignedSmallInteger('fetched_status')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audits');
    }
};
