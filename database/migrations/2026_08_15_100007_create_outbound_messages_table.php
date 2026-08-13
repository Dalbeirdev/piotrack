<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbound_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('channel')->default('email'); // email|sms
            $table->string('address');
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('token', 64)->unique();
            $table->string('status')->default('pending'); // pending|sent|failed
            $table->string('source')->default('automation'); // automation|manual
            $table->foreignId('workflow_id')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->string('error')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbound_messages');
    }
};
