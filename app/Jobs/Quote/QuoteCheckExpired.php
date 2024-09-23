<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Quote;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\QuoteExpiredObject;
use App\Models\Quote;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QuoteCheckExpired implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UserNotifies;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! config('ninja.db.multi_db_enabled')) {
            Quote::query()
                 ->where('quotes.status_id', Quote::STATUS_SENT)
                 ->where('quotes.is_deleted', false)
                 ->whereNull('quotes.deleted_at')
                 ->whereNotNull('quotes.due_date')
                 ->whereHas('client', function ($query) {
                     $query->where('is_deleted', 0)
                            ->where('deleted_at', null);
                 })
                    ->whereHas('company', function ($query) {
                        $query->where('is_disabled', 0);
                    })

                 ->whereBetween('quotes.due_date', [now()->subDay()->startOfDay(), now()->startOfDay()->subSecond()])
                 ->cursor()
                 ->each(function ($quote) {
                     $this->queueExpiredQuoteNotification($quote);
                 });
        } else {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                Quote::query()
                    ->where('quotes.status_id', Quote::STATUS_SENT)
                    ->where('quotes.is_deleted', false)
                    ->whereNull('quotes.deleted_at')
                    ->whereNotNull('quotes.due_date')
                    ->whereHas('client', function ($query) {
                        $query->where('is_deleted', 0)
                               ->where('deleted_at', null);
                    })
                       ->whereHas('company', function ($query) {
                           $query->where('is_disabled', 0);
                       })

                    ->whereBetween('quotes.due_date', [now()->subDay()->startOfDay(), now()->startOfDay()->subSecond()])
                    ->cursor()
                    ->each(function ($quote) {
                        $this->queueExpiredQuoteNotification($quote);
                    });
            }
        }
    }

    private function checkForExpiredQuotes()
    {
    }

    private function queueExpiredQuoteNotification(Quote $quote)
    {
        $nmo = new NinjaMailerObject();
        $nmo->company = $quote->company;
        $nmo->settings = $quote->company->settings;

        /* We loop through each user and determine whether they need to be notified */
        foreach ($quote->company->company_users as $company_user) {
            /* The User */
            $user = $company_user->user;

            if (! $user) {
                continue;
            }

            $nmo->mailable = new NinjaMailer((new QuoteExpiredObject($quote, $quote->company, $company_user->portalType()))->build());

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($quote->invitations()->first(), $company_user, 'quote', ['all_notifications', 'quote_expired', 'quote_expired_all', 'quote_expired_user']);

            /* If one of the methods is email then we fire the EntitySentMailer */
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);
            }
        }
    }
}
