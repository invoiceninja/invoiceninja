<?php

namespace App\Jobs;

use App\Libraries\CurlUtils;
use Utils;

class ConvertProposalToPdf extends Job
{
    public function __construct($proposal)
    {
        $this->proposal = $proposal;
    }

    public function handle()
    {
        if (! env('PHANTOMJS_CLOUD_KEY') && ! env('PHANTOMJS_BIN_PATH')) {
            return false;
        }

        if (Utils::isTravis()) {
            return false;
        }

        $proposal = $this->proposal;
        $link = $proposal->getLink(true, true);
        $phantomjsSecret = env('PHANTOMJS_SECRET');
        $phantomjsLink = sprintf('%s?phantomjs=true&phantomjs_secret=%s', $link, $phantomjsSecret);
        $filename = sprintf('%s/storage/app/%s.pdf', base_path(), strtolower(str_random(RANDOM_KEY_LENGTH)));

        try {
            $pdf = CurlUtils::renderPDF($phantomjsLink, $filename);

            if (! $pdf && ($key = env('PHANTOMJS_CLOUD_KEY'))) {
                $url = "http://api.phantomjscloud.com/api/browser/v2/{$key}/?request=%7Burl:%22{$link}?phantomjs=true%26phantomjs_secret={$phantomjsSecret}%22,renderType:%22pdf%22%7D";
                $pdf = CurlUtils::get($url);
            }
        } catch (\Exception $exception) {
            Utils::logError("PhantomJS - Failed to load {$phantomjsLink}: {$exception->getMessage()}");
            return false;
        }

        if (! $pdf || strlen($pdf) < 200) {
            Utils::logError("PhantomJS - Invalid response {$phantomjsLink}: {$pdf}");
            return false;
        }

        return $pdf;
    }
}
