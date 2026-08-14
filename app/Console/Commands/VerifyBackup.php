<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Backup verification (BCK-004).
 *
 * This does NOT create backups — automated backups and point-in-time recovery
 * are provided by the managed database platform (Laravel Cloud / Postgres) and
 * are configured there, not here. What this command does is verify that a
 * database you have restored is actually usable: run it against the restored
 * instance and it checks connectivity, schema completeness, migration state and
 * that core tables hold data. A backup nobody has restored is a hope, not a
 * backup.
 */
class VerifyBackup extends Command
{
    protected $signature = 'backup:verify {--connection= : Connection to verify (defaults to the app default)}';

    protected $description = 'Verify a restored database is complete and usable';

    /** Tables whose absence means the restore is not usable. */
    private const CRITICAL_TABLES = [
        'users', 'organizations', 'organization_user', 'subscriptions', 'plans',
        'contacts', 'companies', 'deals', 'audit_logs', 'migrations',
    ];

    public function handle(): int
    {
        $connection = $this->option('connection') ?: config('database.default');
        $failures = [];

        $this->components->info("Verifying restore on connection [{$connection}].");

        try {
            DB::connection($connection)->select('select 1');
            $this->components->twoColumnDetail('Connectivity', '<fg=green>ok</>');
        } catch (Throwable $e) {
            $this->components->error('Cannot connect: '.$e->getMessage());

            return self::FAILURE;
        }

        foreach (self::CRITICAL_TABLES as $table) {
            $exists = Schema::connection($connection)->hasTable($table);
            $this->components->twoColumnDetail("Table {$table}", $exists ? '<fg=green>present</>' : '<fg=red>MISSING</>');

            if (! $exists) {
                $failures[] = "missing table {$table}";
            }
        }

        // A schema with no applied migrations is an empty shell, not a restore.
        if (Schema::connection($connection)->hasTable('migrations')) {
            $applied = DB::connection($connection)->table('migrations')->count();
            $this->components->twoColumnDetail('Applied migrations', (string) $applied);

            if ($applied === 0) {
                $failures[] = 'no migrations recorded';
            }
        }

        $organizations = Organization::on($connection)->count();
        $users = User::on($connection)->count();
        $this->components->twoColumnDetail('Organizations', (string) $organizations);
        $this->components->twoColumnDetail('Users', (string) $users);

        if ($users === 0) {
            $failures[] = 'no users present — the restore looks empty';
        }

        if ($failures !== []) {
            $this->components->error('Restore verification FAILED: '.implode('; ', $failures));

            return self::FAILURE;
        }

        $this->components->info('Restore verified: schema complete and core data present.');

        return self::SUCCESS;
    }
}
