<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->default('static'); // static|dynamic
            $table->json('criteria')->nullable(); // dynamic list rules
            $table->unsignedInteger('member_count')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });

        Schema::create('list_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('marketing_list_id')->constrained('marketing_lists')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->timestamp('added_at')->nullable();
            $table->timestamps();

            $table->unique(['marketing_list_id', 'contact_id']);
            $table->index(['organization_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('list_memberships');
        Schema::dropIfExists('marketing_lists');
    }
};
