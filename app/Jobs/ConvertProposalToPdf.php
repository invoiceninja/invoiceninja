<?php

namespace App\Jobs;

use App\Jobs\Job;
use App\Libraries\CurlUtils;

class ConvertProposalToPdf extends Job
{
    public function __construct($proposal)
    {
        $this->proposal = $proposal;
    }

    public function handle()
    {
        $proposal = $this->proposal;
        $url = $proposal->getHeadlessLink();

        $filename = sprintf('%s/storage/app/%s.pdf', base_path(), strtolower(str_random(RANDOM_KEY_LENGTH)));
        $pdf = CurlUtils::renderPDF($url, $filename);

        return $pdf;
    }
}
