<?php

/**
 * Entity Ninja (https://entityninja.com).
 *
 * @link https://github.com/entityninja/entityninja source repository
 *
 * @copyright Copyright (c) 2022. Entity Ninja LLC (https://entityninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Entity;

use Illuminate\Bus\Batchable;
use App\Jobs\Entity\CreateRawPdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateBatchablePdf implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    private $batch_key;

    private $invitation;

    /**
     * @param $invitation
     */
    public function __construct($invitation, $batch_key)
    {
        $this->invitation = $invitation;
        $this->batch_key = $batch_key;
    }

    public function handle()
    {
        \App\Libraries\MultiDB::setDb($this->invitation->company->db);

        $pdf = (new CreateRawPdf($this->invitation))->handle();

        Cache::put($this->batch_key, $pdf);
    }

    public function failed($e)
    {
        nlog("CreateBatchablePdf failed: {$e->getMessage()}");
    }
}
