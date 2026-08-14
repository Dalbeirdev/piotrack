<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('seo_location_id')->constrained('seo_locations')->cascadeOnDelete();
            $table->string('source'); // directory name (e.g. Yelp, BBB)
            $table->string('listed_name')->nullable();
            $table->string('listed_address')->nullable();
            $table->string('listed_phone')->nullable();
            $table->string('url')->nullable();
            $table->string('status')->default('consistent'); // consistent|inconsistent|missing
            $table->json('mismatches')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'seo_location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('citations');
        Schema::dropIfExists('seo_locations');
    }
};
