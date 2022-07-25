<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Events\Design;

use App\Models\Company;
use App\Models\Design;
use Illuminate\Queue\SerializesModels;

/**
 * Class DesignWasRestored.
 */
class DesignWasRestored
{
    use SerializesModels;

    /**
     * @var Design
     */
    public $design;

    public $company;

    public $event_vars;

    public $fromDeleted;

    /**
     * Create a new event instance.
     *
     * @param Design $design
     * @param Company $company
     * @param array $event_vars
     */
    public function __construct(Design $design, $fromDeleted, Company $company, array $event_vars)
    {
        $this->design = $design;

        $this->fromDeleted = $fromDeleted;

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
