<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('channel'); // linkedin|facebook|x|youtube|other
            $table->string('type')->nullable(); // thought_leadership|educational|case_study|testimonial|video|lead_gen
            $table->text('body');
            $table->string('media_url')->nullable();
            $table->foreignId('content_piece_id')->nullable()->constrained('content_pieces')->nullOnDelete();
            $table->string('status')->default('draft'); // draft|scheduled|published|failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('comments')->default(0);
            $table->unsignedBigInteger('shares')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_posts');
    }
};
