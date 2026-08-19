<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Record which driver produced each SERP position.
 *
 * Without it a position from the fixture driver - a hash of keyword, domain and
 * engine - is stored identically to one from a real SERP lookup. Nothing in the
 * row, the audit log or the UI distinguished them, so a customer could read a
 * hash as a ranking. It also means that once a real provider is connected, the
 * fixture history stays indistinguishable from real history for ever.
 *
 * Existing rows are backfilled with the driver configured at migration time,
 * which for a default install is the fixture driver that produced them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('keyword_rankings', function (Blueprint $table) {
            $table->string('provider', 40)->nullable()->after('engine');
        });

        DB::table('keyword_rankings')
            ->whereNull('provider')
            ->update(['provider' => (string) config('seo.rank_provider', 'fixture')]);
    }

    public function down(): void
    {
        Schema::table('keyword_rankings', function (Blueprint $table) {
            $table->dropColumn('provider');
        });
    }
};
