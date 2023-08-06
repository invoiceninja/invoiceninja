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

namespace App\Events\Design;

use App\Models\Design;
use App\Models\Company;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * Class DesignWasRestored.
 */
class DesignWasRestored
{
    use SerializesModels;

    public function __construct(public Design $design, public bool $fromDeleted, public Company $company, public array $event_vars)
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
