<?php namespace App\Events;

use App\Events\Event;
use Illuminate\Queue\SerializesModels;

class VendorWasDeleted extends Event
{
    // vendor
    use SerializesModels;

    public $vendor;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($vendor)
    {
        $this->vendor = $vendor;
    }
}
