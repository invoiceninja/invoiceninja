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

namespace App\Jobs\Mailgun;

use App\Helpers\IngresMail\Transformer\MailgunInboundWebhookTransformer;
use App\Libraries\MultiDB;
use App\Services\IngresEmail\IngresEmailEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class ProcessMailgunInboundWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(private array $request)
    {
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        if (!array_key_exists('To', $this->request) || !array_key_exists('attachments', $this->request) || !array_key_exists('timestamp', $this->request) || !array_key_exists('Subject', $this->request) || !(array_key_exists('body-html', $this->request) || array_key_exists('body-plain', $this->request)))
            throw new \Exception('invalid body');

        // match company
        $company = MultiDB::findAndSetDbByExpenseMailbox($this->request["To"]);
        if (!$company) {
            Log::info('unknown Expense Mailbox occured while handling an inbound email from mailgun: ' . $this->request["To"]);
            return;
        }

        // prepare
        $ingresMail = (new MailgunInboundWebhookTransformer())->transform($this->request);
        Log::info(json_encode($ingresMail));

        // perform
        (new IngresEmailEngine($ingresMail))->handle();
    }
}
