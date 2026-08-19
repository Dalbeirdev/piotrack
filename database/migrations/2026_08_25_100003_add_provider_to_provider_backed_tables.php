<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finish the provenance sweep started for keyword_rankings and
 * ai_visibility_checks: record which driver produced provider-derived rows.
 *
 * Three tables still stored fabricated data with nothing to distinguish it:
 *
 *   ad_metrics    FixtureAdProvider derives impressions, clicks, spend,
 *                 conversions and revenue from a CRC32 of campaign and date.
 *                 The advertising dashboard reports ROAS from those figures.
 *
 *   reviews       FixtureReviewProvider invents an author name, a rating and a
 *                 body ("Reliable managed IT support with responsive service").
 *                 ReputationService::import stores them under the requested
 *                 source, so a generated string is filed as a Google review.
 *                 reviews.source is the platform, not the driver.
 *
 *   social_posts  FixtureSocialProvider invents impressions, likes, comments
 *                 and shares. Its external_id at least self-labels as
 *                 "fixture-...", but the engagement numbers do not.
 *
 * Existing rows are backfilled with the driver configured at migration time,
 * which on a default install is the fixture driver that produced them.
 */
return new class extends Migration
{
    /** @var array<string, string> table => config key holding the driver name */
    private const TABLES = [
        'ad_metrics' => 'advertising.driver',
        'reviews' => 'content.review_provider',
        'social_posts' => 'content.social_provider',
    ];

    public function up(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('provider', 40)->nullable();
            });
        }

        foreach (self::TABLES as $table => $configKey) {
            DB::table($table)->whereNull('provider')
                ->update(['provider' => (string) config($configKey, 'fixture')]);
        }
    }

    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('provider');
            });
        }
    }
};
