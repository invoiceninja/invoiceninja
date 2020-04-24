<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Quote;

use App\Mail\Quote\QuoteWasApproved;
use App\Models\Quote;
use App\Models\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QuoteWorkflowSettings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $quote;
    public $client;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Quote $quote, Client $client = null)
    {
        $this->quote = $quote;
        $this->client = $client ?? $quote->client;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->client->getSetting('auto_archive_quote')) {
            $this->quote->archive();
        }

        if ($this->client->getSetting('auto_email_quote')) {
           // Todo: Fetch the right client contact.
            Mail::to($this->client->contacts()->first()->email)
                ->send(new QuoteWasApproved());
        }
    }
}
