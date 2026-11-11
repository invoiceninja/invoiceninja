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

use App\Jobs\Entity\CreateRawPdf;
use App\Services\PdfMaker\BatchPdfService;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateBatchablePdf implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use SerializesModels;

    /**
     * @param $invitation
     */
    public function __construct(private mixed $invitation, private string $batch_key) {}

    public function handle(BatchPdfService $batch_pdf_service)
    {
        \App\Libraries\MultiDB::setDb($this->invitation->company->db);

        $pdf = (new CreateRawPdf($this->invitation))->handle();

        $batch_pdf_service->cachePdf($this->batch_key, $pdf);
    }

    public function failed($e)
    {
        nlog("CreateBatchablePdf failed: {$e->getMessage()}");
    }
}
