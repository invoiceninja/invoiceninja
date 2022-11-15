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

use App\Libraries\MultiDB;
use App\Models\Quote;
use App\Repositories\BaseRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class QuoteCheckExpired implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! config('ninja.db.multi_db_enabled'))
            return $this->checkForExpiredQuotes();

        foreach (MultiDB::$dbs as $db) {
            
            MultiDB::setDB($db);

            $this->checkForExpiredQuotes();

        }

    }

    private function checkForExpiredQuotes()
    {
        Quote::query()
             ->where('status_id', Quote::STATUS_SENT)
             ->where('is_deleted', false)
             ->whereNull('deleted_at')
             ->whereNotNull('due_date')
             ->whereHas('client', function ($query) {
                    $query->where('is_deleted', 0)
                           ->where('deleted_at', null);
                })
                ->whereHas('company', function ($query) {
                    $query->where('is_disabled', 0);
                })
             // ->where('due_date', '<='. now()->toDateTimeString())
             ->whereBetween('due_date', [now()->subDay(), now()])
             ->cursor()
             ->each(function ($quote){

             });
    }

}
