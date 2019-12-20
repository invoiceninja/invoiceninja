<?php
/**
 * Quote Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Quote Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Quote;

use App\Models\Quote;
use App\Models\Payment;
use App\Models\PaymentTerm;
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

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Quote $quote, $settings)
    {

        $this->quote = $quote;

        $this->settings = $settings;
    }

    /**
     * Execute the job.
     *
     * 
     * @return void
     */
    public function handle()
    {
        //return early
        if($this->quote->number != '')
            return $this->quote;

        switch ($this->settings->quote_number_applied) {
            case 'when_saved':
                $this->quote->number = $this->getNextQuoteNumber($this->quote->client);
                break;
            case 'when_sent':
                if($this->quote->status_id == Quote::STATUS_SENT)
                    $this->quote->number = $this->getNextQuoteNumber($this->quote->client);
                break;
            
            default:
                # code...
                break;
        }
   
        $this->quote->save();
            
        return $this->quote;

    }


}
