<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->string('channel'); // email|sms
            $table->string('address');
            $table->string('reason')->default('unsubscribe'); // unsubscribe|optout|bounce|complaint
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'channel', 'address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppressions');
    }
};
