<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('lifecycle_stage')->default('subscriber')->after('campaign');
            $table->unsignedInteger('lead_score')->default(0)->after('lifecycle_stage');
            $table->boolean('email_opt_in')->default(true)->after('lead_score');
            $table->boolean('sms_opt_in')->default(false)->after('email_opt_in');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('lifecycle_stage')->default('lead')->after('status');
            $table->unsignedInteger('lead_score')->default(0)->after('lifecycle_stage');
            $table->string('segment')->nullable()->after('lead_score');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['lifecycle_stage', 'lead_score', 'email_opt_in', 'sms_opt_in']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['lifecycle_stage', 'lead_score', 'segment']);
        });
    }
};
