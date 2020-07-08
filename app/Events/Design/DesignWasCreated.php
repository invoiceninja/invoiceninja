<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Events\Design;

use App\Models\Company;
use App\Models\Design;
use Illuminate\Queue\SerializesModels;

/**
 * Class DesignWasCreated.
 */
class DesignWasCreated
{
    use SerializesModels;

    /**
     * @var Design
     */
    public $design;

    public $company;

    public $event_vars;
    /**
     * Create a new event instance.
     *
     * @param Design $design
     */
    public function __construct(Design $design, Company $company, array $event_vars)
    {
        $this->design = $design;

        $this->company = $company;

        $this->event_vars = $event_vars;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
