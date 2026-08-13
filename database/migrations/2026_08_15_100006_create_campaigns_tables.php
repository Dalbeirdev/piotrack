<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('name');
            $table->string('channel')->default('email'); // email|sms
            $table->string('type')->default('newsletter');
            $table->string('subject')->nullable();
            $table->string('preheader')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->text('body_html')->nullable();
            $table->text('body_text')->nullable();
            $table->foreignId('marketing_list_id')->nullable()->constrained('marketing_lists')->nullOnDelete();
            $table->string('status')->default('draft'); // draft|scheduled|sending|sent|failed
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedInteger('stat_recipients')->default(0);
            $table->unsignedInteger('stat_sent')->default(0);
            $table->unsignedInteger('stat_opened')->default(0);
            $table->unsignedInteger('stat_clicked')->default(0);
            $table->unsignedInteger('stat_bounced')->default(0);
            $table->unsignedInteger('stat_unsubscribed')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'status']);
        });

        Schema::create('campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('address'); // email or phone snapshot at send time
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending'); // pending|sent|failed|bounced
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'contact_id']);
            $table->index(['organization_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
    }
};
