<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Design;

use App\Models\Company;
use App\Models\Design;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Queue\SerializesModels;

/**
 * Class DesignWasDeleted.
 */
class DesignWasDeleted
{
    use SerializesModels;

    public function __construct(public Design $design, public Company $company, public array $event_vars)
    {
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return PrivateChannel|array
    */
    public function broadcastOn()
    {
        return [];
    }
}
