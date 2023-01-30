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

use App\Models\Company;
use App\Models\Design;
use Illuminate\Queue\SerializesModels;

/**
 * Class DesignWasUpdated.
 */
class DesignWasUpdated
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
     * @param Company $company
     * @param array $event_vars
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
     * @return PrivateChannel
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
