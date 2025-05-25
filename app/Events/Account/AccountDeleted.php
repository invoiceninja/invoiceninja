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

namespace App\Events\Account;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Class AccountDeleted.
 */
class AccountDeleted
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    /**
     * Create a new event instance.
     *
     * @param $user
     * @param $company
     * @param $event_vars
     */
    public function __construct(public string $account_key, public string $email, public string $ip)
    {
    }

    // /**
    //  * Get the channels the event should broadcast on.
    //  *
    //  * @return Channel|array
    //  */
    public function broadcastOn()
    {
        return [];
        //  return new PrivateChannel('channel-name');
    }
}
