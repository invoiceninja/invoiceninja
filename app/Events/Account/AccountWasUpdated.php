<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Account;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Account;
use App\Models\BaseModel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use App\Utils\Traits\Invoice\Broadcasting\DefaultResourceBroadcast;

/**
 * Class AccountWasUpdated.
 */
class AccountWasUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;
    use DefaultResourceBroadcast;


    /**
     * Create a new event instance.
     *
     * @param $account
     */
    public function __construct(public Account $account)
    {
    }

    public function broadcastModel(): BaseModel
    {
        return $this->account;
    }

    public function broadcastOn(): array
    {
nlog("in broadcast on");
        return $this->account->companies->map(function($company){
            return new PrivateChannel("company-{$company->company_key}");
        })->toArray();
        
    }

    public function broadcastIncludes(): array
    {
        return [];
    }
}
