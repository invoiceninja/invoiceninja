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

namespace App\PaymentDrivers\Square;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Bus\Queueable;
use App\Models\CompanyGateway;
use App\Jobs\Util\SystemLogger;
use Illuminate\Queue\SerializesModels;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SquareWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Utilities;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    public CompanyGateway $company_gateway;

    public function __construct(public array $webhook_array, public string $company_key, public int $company_gateway_id)
    {
    }

    public function handle()
    {
        nlog("Square Webhook");

        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $this->company_gateway = CompanyGateway::withTrashed()->find($this->company_gateway_id);

        // if(!isset($this->webhook_array['type']))
        //     nlog("Checkout Webhook type not set");

        // match($this->webhook_array['type']){
        //     'payment_approved' => $this->paymentApproved(),
        // };

    }
}