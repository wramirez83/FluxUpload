<?php

namespace Medusa\FluxUpload\Commands;

use Illuminate\Console\Command;
use Medusa\FluxUpload\Services\SessionService;

class CleanExpiredSessionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fluxupload:clean {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired FluxUpload sessions';

    /**
     * Execute the console command.
     */
    public function handle(SessionService $sessionService): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('Dry run mode - no sessions will be deleted');
        }

        $count = $sessionService->cleanExpiredSessions($dryRun);

        if ($count > 0) {
            if ($dryRun) {
                $this->info("Would clean {$count} expired session(s)");
            } else {
                $this->info("Cleaned {$count} expired session(s)");
            }
        } else {
            $this->info('No expired sessions found');
        }

        return Command::SUCCESS;
    }
}

