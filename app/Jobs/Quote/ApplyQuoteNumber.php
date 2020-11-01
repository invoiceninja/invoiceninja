<?php
/**
 * Quote Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Quote Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Quote;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Quote;
use App\Repositories\QuoteRepository;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ApplyQuoteNumber implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, NumberFormatter, GeneratesCounter;

    private $quote;

    private $settings;

    private $company;

    /**
     * Create a new job instance.
     *
     * @param Quote $quote
     * @param $settings
     * @param Company $company
     */
    public function __construct(Quote $quote, $settings, Company $company)
    {
        $this->quote = $quote;

        $this->settings = $settings;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     *
     * @return Quote
     */
    public function handle()
    {
        MultiDB::setDB($this->company->db);

        //return early
        if ($this->quote->number != '') {
            return $this->quote;
        }

        switch ($this->settings->quote_number_applied) {
            case 'when_saved':
                $this->quote->number = $this->getNextQuoteNumber($this->quote->client);
                break;
            case 'when_sent':
                if ($this->quote->status_id == Quote::STATUS_SENT) {
                    $this->quote->number = $this->getNextQuoteNumber($this->quote->client);
                }
                break;

            default:
                // code...
                break;
        }

        $this->quote->save();

        return $this->quote;
    }
}
