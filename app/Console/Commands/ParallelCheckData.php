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

namespace App\Console\Commands;

use App;
use App\Jobs\Ninja\CheckCompanyData;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\Contact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Utils\Ninja;
use DB;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Mail;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class CheckData.
 */
class ParallelCheckData extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ninja:pcheck-data';

    /**
     * @var string
     */
    protected $description = 'Check company data in parallel';

    protected $log = '';

    protected $isValid = true;

    public function handle()
    {
        $hash = Str::random(32);

        Company::cursor()->each(function ($company) use ($hash) {
            CheckCompanyData::dispatch($company, $hash)->onQueue('checkdata');
        });
    }
}
