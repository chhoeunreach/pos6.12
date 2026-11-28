<?php

namespace Modules\LoanManagement\Jobs;

use Modules\Social\Jobs\TranscodeFeedVideoJob as SocialTranscodeFeedVideoJob;

/**
 * Backward-compatible alias delegating to Modules\Social\Jobs\TranscodeFeedVideoJob.
 */
class TranscodeFeedVideoJob extends SocialTranscodeFeedVideoJob
{
}
