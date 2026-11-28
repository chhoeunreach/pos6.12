<?php

namespace Modules\LoanManagement\Console\Commands;

use Illuminate\Console\Command;

/**
 * Backward-compatible alias delegating to Modules\Social (`social:flush-views`).
 */
class FlushLoanFeedViews extends Command
{
    protected $signature = 'loan-feed:flush-views
        {--recompute-all : Also recompute hot_score for every published post, not only flushed ones}
        {--limit=2000 : Maximum number of posts to process in one run}';

    protected $description = 'Alias for social:flush-views';

    public function handle(): int
    {
        return $this->call('social:flush-views', [
            '--recompute-all' => (bool)$this->option('recompute-all'),
            '--limit' => $this->option('limit'),
        ]);
    }
}
