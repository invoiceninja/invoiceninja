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

namespace App\Events\Account;

use App\Models\Company;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;

/**
 * Class StripeConnectFailure.
 */
class StripeConnectFailure
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Company $company, public string $db)
    {
    }

    public function broadcastOn()
    {
        return [];
    }
}
