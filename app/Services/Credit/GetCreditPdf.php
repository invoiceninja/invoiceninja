<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Credit;

use App\Jobs\Entity\CreateRawPdf;
use App\Models\CreditInvitation;
use App\Services\AbstractService;

class GetCreditPdf extends AbstractService
{
    public function __construct(public CreditInvitation $invitation)
    {
    }

    public function run()
    {

        return (new CreateRawPdf($this->invitation))->handle();

    }
}
