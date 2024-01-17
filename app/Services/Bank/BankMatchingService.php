<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Bank;

use App\Libraries\MultiDB;
use App\Models\BankTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class BankMatchingService implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public $company_id, public $db)
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
        return [(new WithoutOverlapping($this->company_id))];
    }
}
