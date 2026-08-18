<?php

declare(strict_types=1);

use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config()->set('app.demo_seed_allowed', true);
});

it('seeds a demo organization with data across the modules', function () {
    $this->seed(DemoSeeder::class);

    expect(DB::table('organizations')->count())->toBeGreaterThan(0)
        ->and(DB::table('companies')->count())->toBeGreaterThan(0)
        ->and(DB::table('contacts')->count())->toBeGreaterThan(0)
        ->and(DB::table('deals')->count())->toBeGreaterThan(0);
});

/**
 * Column length limits declared in the migrations, as table => [column => limit].
 *
 * These are read from the migration source rather than from the database because
 * sqlite is typeless: it stores the declared type as a bare "varchar" and discards
 * the length, so schema introspection cannot report it locally.
 *
 * @return array<string, array<string, int>>
 */
function declaredStringLimits(): array
{
    $limits = [];

    foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
        $table = null;

        foreach (file($file) ?: [] as $line) {
            if (preg_match('/Schema::(?:create|table)\(\s*[\'"]([a-z0-9_]+)[\'"]/i', $line, $m)) {
                $table = $m[1];

                continue;
            }

            if ($table !== null && preg_match('/\$table->(?:string|char)\(\s*[\'"]([a-z0-9_]+)[\'"]\s*,\s*(\d+)\s*\)/i', $line, $m)) {
                $limits[$table][$m[1]] = (int) $m[2];
            }
        }
    }

    return $limits;
}

// sqlite ignores VARCHAR length limits; MySQL and PostgreSQL enforce them and abort the
// insert. That asymmetry let the demo dataset write the country name "Canada" into
// companies.country, a 2-char ISO-3166 column: it seeded cleanly in development and blew
// up only on a real MariaDB deployment, part-way through an install. This checks every
// length-constrained column in the schema, so the next over-long seed value fails here
// rather than on someone's server.
it('keeps every seeded value inside its declared column length', function () {
    $this->seed(DemoSeeder::class);

    $grammar = DB::connection()->getQueryGrammar();
    $limits = declaredStringLimits();
    $checked = 0;
    $violations = [];

    expect($limits)->not->toBeEmpty('parsed no column limits from the migrations');

    foreach ($limits as $table => $columns) {
        foreach ($columns as $column => $limit) {
            $over = DB::table($table)
                ->whereNotNull($column)
                ->whereRaw('LENGTH('.$grammar->wrap($column).') > ?', [$limit])
                ->value($column);

            $checked++;

            if ($over !== null) {
                $violations[] = sprintf(
                    '%s.%s holds %d chars but the column allows %d: "%s"',
                    $table, $column, mb_strlen((string) $over), $limit, $over
                );
            }
        }
    }

    expect($violations)->toBe([])
        ->and($checked)->toBeGreaterThan(0);
});
