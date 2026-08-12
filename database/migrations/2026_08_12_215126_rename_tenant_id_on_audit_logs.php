<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align the audit trail's tenant key with the canonical name used by every
     * tenant-owned table (organization_id) and add the FK now that the
     * organizations table exists. Nullable: pre-tenant/platform events (login,
     * registration) have no organization.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->renameColumn('tenant_id', 'organization_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->index(['organization_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->renameColumn('organization_id', 'tenant_id');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'created_at']);
        });
    }
};
