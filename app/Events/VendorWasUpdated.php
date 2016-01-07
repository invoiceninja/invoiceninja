<?php namespace App\Events;
// vendor
use App\Events\Event;
use Illuminate\Queue\SerializesModels;

class VendorWasUpdated extends Event
{
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
