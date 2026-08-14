<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('content_type')->default('article'); // article|service_page|case_study|whitepaper|ebook|guide|checklist|pillar|video|podcast|webinar|interview
            $table->string('format')->nullable(); // written|video|audio|download
            $table->string('funnel_stage')->nullable(); // tof|mof|bof
            $table->string('status')->default('idea'); // idea|draft|in_review|approved|published|archived
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('target_keyword')->nullable();
            $table->string('url')->nullable();
            $table->string('cta')->nullable();
            $table->foreignId('pillar_id')->nullable(); // self-reference (content cluster)
            $table->json('tags')->nullable();
            $table->boolean('is_lead_magnet')->default(false);
            $table->unsignedTinyInteger('optimization_score')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'content_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pieces');
    }
};
