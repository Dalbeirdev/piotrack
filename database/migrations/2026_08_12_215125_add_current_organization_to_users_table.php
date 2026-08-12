<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('current_organization_id')
                ->nullable()
                ->after('remember_token')
                ->constrained('organizations')
                ->nullOnDelete();

            // Global platform-staff role (nullable = ordinary customer user).
            // Only Super Admin's bypass is exercised until the platform admin
            // area (Stage 13).
            $table->string('platform_role')->nullable()->after('current_organization_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
            $table->dropColumn('platform_role');
        });
    }
};
