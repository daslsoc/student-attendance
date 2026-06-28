<?php

namespace App\Console\Commands;

use App\Exceptions\RegistrationApiException;
use App\Services\RegistrationSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pulls allocations from student-registration and enrols students. Wire this to
 * cron on the shared host to run every few minutes (see docs/integration.md for
 * the exact cron line).
 */
class RegistrationSync extends Command
{
    protected $signature = 'registration:sync {--dry-run : Report what would change without writing anything}';

    protected $description = 'Sync student allocations from the registration app and enrol them.';

    public function handle(RegistrationSyncService $sync): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $sync->sync($dryRun);
        } catch (RegistrationApiException $e) {
            Log::error('Registration sync failed', ['error' => $e->getMessage()]);
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("DRY RUN — nothing written. Received {$result['received']}, would enrol {$result['enrolled']}, would move {$result['moved']}.");
        } else {
            $this->info("Received {$result['received']}, newly enrolled {$result['enrolled']}, moved {$result['moved']}.");
        }

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        return self::SUCCESS;
    }
}
