<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Util;

use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RefreshPdfs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $company;

    public function __construct(Company $company)
    {
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        InvoiceInvitation::where('company_id', $this->company->id)->cursor()->each(function ($invitation) {
            nlog("generating invoice pdf for {$invitation->invoice_id}");
            CreateEntityPdf::dispatch($invitation);
        });

        QuoteInvitation::where('company_id', $this->company->id)->cursor()->each(function ($invitation) {
            nlog("generating quote pdf for {$invitation->quote_id}");
            CreateEntityPdf::dispatch($invitation);
        });

        CreditInvitation::where('company_id', $this->company->id)->cursor()->each(function ($invitation) {
            nlog("generating credit pdf for {$invitation->credit_id}");
            CreateEntityPdf::dispatch($invitation);
        });
    }
}
