<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('source')->default('google'); // google|clutch|g2|facebook|manual
            $table->string('author_name')->nullable();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('body')->nullable();
            $table->string('url')->nullable();
            $table->string('sentiment')->default('neutral'); // positive|neutral|negative
            $table->boolean('responded')->default(false);
            $table->text('response')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'source']);
        });

        Schema::create('review_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('channel')->default('email'); // email|sms
            $table->string('status')->default('pending'); // pending|sent|completed
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('authority_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type')->default('award'); // award|certification|logo|mention|proof
            $table->string('name');
            $table->string('issuer')->nullable();
            $table->string('url')->nullable();
            $table->string('image_url')->nullable();
            $table->date('achieved_on')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authority_assets');
        Schema::dropIfExists('review_requests');
        Schema::dropIfExists('reviews');
    }
};
