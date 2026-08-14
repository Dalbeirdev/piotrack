<?php

namespace App\Console\Commands;

use App\Models\AiRequest;
use App\Models\AuditLog;
use App\Models\Call;
use App\Models\IntentSignal;
use App\Models\Organization;
use App\Models\OutboundMessage;
use App\Models\RetentionPolicy;
use App\Support\CurrentOrganization;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies each tenant's retention rules (PRIV-004): records older than the
 * configured window are deleted. A tenant with no rule for a subject keeps that
 * data indefinitely — retention is opt-in, so nothing is silently destroyed.
 */
class PruneExpiredData extends Command
{
    protected $signature = 'privacy:prune-expired-data {--dry-run : Report what would be deleted without deleting}';

    protected $description = 'Delete records past their tenant retention window';

    public function handle(CurrentOrganization $current): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        Organization::query()->each(function (Organization $organization) use ($current, $dryRun, &$total) {
            $current->set($organization);

            foreach (RetentionPolicy::where('is_active', true)->get() as $policy) {
                $cutoff = now()->subDays($policy->retain_days);
                $query = $this->queryFor($policy->subject, $organization->id);

                if ($query === null) {
                    continue;
                }

                $query->where('created_at', '<', $cutoff);
                $count = (clone $query)->count();

                if ($count > 0 && ! $dryRun) {
                    $query->delete();
                }

                if ($count > 0) {
                    $this->components->info(sprintf(
                        '%s: %s %d %s older than %d days',
                        $organization->name, $dryRun ? 'would delete' : 'deleted',
                        $count, $policy->subject, $policy->retain_days,
                    ));
                    $total += $count;
                }
            }
        });

        $current->forget();

        $this->components->info(($dryRun ? 'Would delete ' : 'Deleted ').$total.' record(s).');

        return self::SUCCESS;
    }

    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>|null
     */
    private function queryFor(string $subject, int $organizationId): ?Builder
    {
        return match ($subject) {
            // audit_logs is not tenant-scoped by the global scope, so it is
            // filtered explicitly.
            'audit_logs' => AuditLog::query()->where('organization_id', $organizationId),
            'ai_requests' => AiRequest::query(),
            'outbound_messages' => OutboundMessage::query(),
            'intent_signals' => IntentSignal::query(),
            'calls' => Call::query(),
            default => null,
        };
    }
}
