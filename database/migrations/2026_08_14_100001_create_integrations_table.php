<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('provider');
            $table->string('name');
            $table->string('status')->default('disconnected'); // connected|disconnected|error
            $table->text('credentials')->nullable(); // encrypted
            $table->json('scopes')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
