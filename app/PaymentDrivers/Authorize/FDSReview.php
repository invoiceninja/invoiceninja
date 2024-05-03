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

namespace App\PaymentDrivers\Authorize;

use App\Models\PaymentHash;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/*Multi Mailer implemented*/

class FDSReview implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    
    public $tries = 1; //number of retries
    public $deleteWhenMissingModels = true;

    public function __construct(private string $transaction_reference, private PaymentHash $payment_hash, private string $db)
    {
    }

    public function handle()
    {
    }
}
