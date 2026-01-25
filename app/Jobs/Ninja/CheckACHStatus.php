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

use App\Libraries\MultiDB;
use App\Models\ClientGatewayToken;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckACHStatus implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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

                    if ($pm) {

                        $meta = $token->meta;
                        $meta->state = 'authorized';
                        $token->meta = $meta;
                        $token->save();

                    }

                } catch (\Exception $e) {
                }

            });

            Payment::where('status_id', 1)
            ->whereHas('company_gateway', function ($q) {
                $q->whereIn('gateway_key', ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23']);
            })
            ->cursor()
            ->each(function ($p) {

                try {
                    $stripe = $p->company_gateway->driver($p->client)->init();
                } catch (\Exception $e) {
                    return;
                }

                $pi = false;

                try {
                    if(str_starts_with($p->transaction_reference, 'pi_')){
                        $pi = $stripe->getPaymentIntent($p->transaction_reference);
                    }
                } catch (\Exception $e) {

                }

                if (!$pi) {

                    try {
                        $charge = \Stripe\Charge::retrieve($p->transaction_reference, $stripe->stripe_connect_auth);

                        if($charge &&$charge->status == 'failed'){
                            $p->service()->deletePayment();
                            $p->status_id = \App\Models\Payment::STATUS_FAILED;
                            $p->save();
                            return;
                        }
                        elseif($charge && $charge->status == 'succeeded'){
                            $p->status_id = Payment::STATUS_COMPLETED;
                            $p->saveQuietly();
                            return;
                        }

                    } catch (\Exception $e) {
                       
                    }

                }


                if ($pi && $pi->status == 'succeeded') {
                    $p->status_id = Payment::STATUS_COMPLETED;
                    $p->saveQuietly();
                    return;
                }
                
                if($pi && $pi->latest_charge){

                    $charge = \Stripe\Charge::retrieve($pi->latest_charge, $stripe->stripe_connect_auth);

                    if($charge &&$charge->status == 'failed'){
                        $p->service()->deletePayment();
                        $p->status_id = \App\Models\Payment::STATUS_FAILED;
                        $p->save();
                        return;
                    }
                    elseif($charge && $charge->status == 'succeeded'){
                        $p->status_id = \App\Models\Payment::STATUS_COMPLETED;
                        $p->saveQuietly();
                        return;
                    }
                }

                

                if ($pi) {
                    nlog("{$p->id} did not complete {$p->transaction_reference}");
                } else {
                    nlog("did not find a payment intent {$p->transaction_reference}");
                }

                

            });

            /**
             * Blockonomics payments that have been pending for over 3 days are deleted
             */
            Payment::where('status_id', 1)
                ->where('created_at', '<', now()->startOfDay()->subDays(3))
                ->whereHas('company_gateway', function ($q) {
                    $q->where('gateway_key', 'wbhf02us6owgo7p4nfjd0ymssdshks4d');
                })
                ->cursor()
                ->each(function ($p) {
                    $p->service()->deletePayment();
                    $p->status_id = \App\Models\Payment::STATUS_FAILED;
                    $p->save();
                });
            
        }
    }
}
