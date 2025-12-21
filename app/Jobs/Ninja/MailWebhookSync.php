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

namespace App\Jobs\Ninja;

use App\Utils\Ninja;
use App\Models\Account;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Facades\LightLogs;
use App\DataMapper\Analytics\EmailCount;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class MailWebhookSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! Ninja::isHosted()) {
            return;
        }

        /** Add to the logs any email deliveries that have not been sync'd */
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            $this->scanSentEmails();
        }
    }

    private function scanSentEmails()
    {

        $query = \App\Models\InvoiceInvitation::whereNotNull('message_id')
        ->whereNull('email_status')
        ->whereHas('company', function ($q) {
            $q->where('settings->email_sending_method', 'default');
        });

        $this->runIterator($query);


        $query = \App\Models\QuoteInvitation::whereNotNull('message_id')
        ->whereNull('email_status')
        ->whereHas('company', function ($q) {
            $q->where('settings->email_sending_method', 'default');
        });

        $this->runIterator($query);


        $query = \App\Models\RecurringInvoiceInvitation::whereNotNull('message_id')
        ->whereNull('email_status')
        ->whereHas('company', function ($q) {
            $q->where('settings->email_sending_method', 'default');
        });

        $this->runIterator($query);


        $query = \App\Models\CreditInvitation::whereNotNull('message_id')
        ->whereNull('email_status')
        ->whereHas('company', function ($q) {
            $q->where('settings->email_sending_method', 'default');
        });

        $this->runIterator($query);


        $query = \App\Models\PurchaseOrderInvitation::whereNotNull('message_id')
        ->whereNull('email_status')
        ->whereHas('company', function ($q) {
            $q->where('settings->email_sending_method', 'default');
        });

        $this->runIterator($query);

    }

    private function runIterator($query)
    {
        $query->whereBetween('created_at', [now()->subHours(12), now()->subHour()])
        ->orderBy('id', 'desc')
        ->each(function ($invite) {

            $token = config('services.postmark.token');
            $postmark = new \Postmark\PostmarkClient($token);

            $messageDetail = false;

            try {
                $messageDetail = $postmark->getOutboundMessageDetails($invite->message_id);
            } catch (\Throwable $th) {
                $token = config('services.postmark-outlook.token');
                $postmark = new \Postmark\PostmarkClient($token);
                $messageDetail = $postmark->getOutboundMessageDetails($invite->message_id);
            }

            try {

                if (!$messageDetail) {
                    return true;
                }

                $data = [
                    'RecordType' => 'Delivery',
                    'ServerID' => 23,
                    'MessageStream' => 'outbound',
                    'MessageID' => $invite->message_id,
                    'Recipient' => collect($messageDetail->recipients)->first(),
                    'Tag' => $invite->company->company_key,
                    'DeliveredAt' => '2025-01-01T16:34:52Z',
                    'Metadata' => [

                    ]
                ];

                (new \App\Jobs\PostMark\ProcessPostmarkWebhook($data, $token))->handle();

                $invite->sent_date = now();
                $invite->save();

            } catch (\Throwable $th) {
                nlog("MailWebhookSync:: {$th->getMessage()}");
            }

        });

    }

    public function middleware()
    {
        return [(new WithoutOverlapping('mail-webhook-sync'))->dontRelease()];
    }

    public function failed($exception)
    {
        nlog("MailWebhookSync:: Exception:: => ".$exception->getMessage());
        config(['queue.failed.driver' => null]);
    }
}
