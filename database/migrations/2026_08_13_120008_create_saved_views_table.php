<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('resource'); // contacts|companies|leads|deals
            $table->string('name');
            $table->json('filters')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'user_id', 'resource']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_views');
    }
};
