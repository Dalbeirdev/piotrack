<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record which driver produced each AI-visibility result.
 *
 * The same gap as keyword_rankings, and with a sharper edge: the fixture driver
 * derives "mentioned", position and share-of-answer from a hash of prompt and
 * brand, and returns invented competitor domains (competitor-msp.com,
 * rival-it.com) and an invented citation (wikipedia.org). Stored without
 * provenance those read as findings about a real market.
 *
 * Existing rows are backfilled with the driver configured at migration time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->string('provider', 40)->nullable()->after('engine');
        });

        DB::table('ai_visibility_checks')
            ->whereNull('provider')
            ->update(['provider' => (string) config('seo.ai_provider', 'fixture')]);
    }

    public function down(): void
    {
        Schema::table('ai_visibility_checks', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
