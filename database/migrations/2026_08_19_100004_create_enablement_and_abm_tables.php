<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('type')->default('one_pager'); // deck|one_pager|battlecard|script|email_template|roi_calculator|proof|persona
            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('content')->nullable();
            $table->string('url')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'type']);
        });

        Schema::create('sales_plays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('steps')->nullable();
            $table->string('target_segment')->nullable();
            $table->timestamps();

            $table->index('organization_id');
        });

        Schema::create('target_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->unsignedTinyInteger('tier')->default(3); // 1|2|3
            $table->string('status')->default('targeted'); // targeted|engaged|opportunity|won|lost
            $table->integer('account_score')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['organization_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('target_accounts');
        Schema::dropIfExists('sales_plays');
        Schema::dropIfExists('sales_assets');
    }
};
