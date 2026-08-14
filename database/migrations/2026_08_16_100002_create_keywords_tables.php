<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('phrase');
            $table->string('intent')->default('informational'); // informational|commercial|transactional|navigational
            $table->string('type')->nullable(); // service|industry|vertical|competitor|long_tail|local
            $table->unsignedInteger('search_volume')->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();
            $table->string('mapped_url')->nullable();
            $table->string('cluster')->nullable();
            $table->boolean('is_tracked')->default(true);
            $table->unsignedSmallInteger('current_position')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'phrase']);
            $table->index(['organization_id', 'cluster']);
        });

        Schema::create('keyword_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('keywords')->cascadeOnDelete();
            $table->string('engine')->default('google');
            $table->string('location')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('url')->nullable();
            $table->boolean('is_competitor')->default(false);
            $table->string('competitor_domain')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'keyword_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_rankings');
        Schema::dropIfExists('keywords');
    }
};
