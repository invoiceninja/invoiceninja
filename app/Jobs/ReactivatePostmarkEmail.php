<?php

namespace App\Jobs;

use App\Jobs\Job;
use Postmark\PostmarkClient;

class ReactivatePostmarkEmail extends Job
{
    public function __construct($bounceId)
    {
        $this->bounceId = $bounceId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! config('services.postmark')) {
            return false;
        }

        $postmark = new PostmarkClient(config('services.postmark'));
        $response = $postmark->activateBounce($this->bounceId);
    }
}
