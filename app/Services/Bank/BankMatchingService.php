<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Bank;

use App\Libraries\MultiDB;
use App\Models\BankTransaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class BankMatchingService implements ShouldQueue
{
    use Dispatchable;
    use SerializesModels;

    public $tries = 1;

    public $timeout = 3600;

    public function __construct(public int $company_id, public string $db)
    {
    }

    public function handle(): void
    {
        MultiDB::setDb($this->db);

        BankTransaction::query()->where('company_id', $this->company_id)
           ->where('status_id', BankTransaction::STATUS_UNMATCHED)
           ->cursor()
           ->each(function ($bt) {
               (new BankService($bt))->processRules();
           });
    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->db."_".$this->company_id))->dontRelease()];
    }

    public function failed($exception = null)
    {

        if ($exception) {
            nlog("BANKMATCHINGSERVICE:: ". $exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }
}
