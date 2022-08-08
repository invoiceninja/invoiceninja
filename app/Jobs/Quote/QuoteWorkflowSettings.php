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

namespace App\Jobs\Quote;

use App\Models\Client;
use App\Models\Quote;
use App\Repositories\BaseRepository;
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

    public $base_repository;

    /**
     * Create a new job instance.
     *
     * @param Quote $quote
     * @param Client|null $client
     */
    public function __construct(Quote $quote, Client $client = null)
    {
        $this->quote = $quote;
        $this->client = $client ?? $quote->client;
        $this->base_repository = new BaseRepository();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->client->getSetting('auto_email_quote')) {
            $this->quote->invitations->each(function ($invitation, $key) {
                $this->quote->service()->sendEmail($invitation->contact);
            });
        }

        // if ($this->client->getSetting('auto_archive_quote')) {
        //     $this->base_repository->archive($this->quote);
        // }
    }
}
