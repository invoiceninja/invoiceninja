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

namespace App\Jobs\Ninja;

use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Models\ClientGatewayToken;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class CheckACHStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        //multiDB environment, need to
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            nlog("Checking ACH status");

            ClientGatewayToken::query()
            ->where('created_at', '>', now()->subMonths(2))
            ->where('gateway_type_id', 2)
            ->whereHas('gateway', function ($q) {
                $q->whereIn('gateway_key', ['d14dd26a37cecc30fdd65700bfb55b23','d14dd26a47cecc30fdd65700bfb67b34']);
            })
            ->whereJsonContains('meta', ['state' => 'unauthorized'])
            ->cursor()
            ->each(function ($token) {

                try {
                    $stripe = $token->gateway->driver($token->client)->init();
                    $pm =  $stripe->getStripePaymentMethod($token->token);

                    if($pm) {

                        $meta = $token->meta;
                        $meta->state = 'authorized';
                        $token->meta = $meta;
                        $token->save();

                    }

                } catch (\Exception $e) {
                }

            });
        }
    }
}