<?php namespace app\Listeners;
// vendor
use App\Events\VendorWasCreated;
use App\Events\VendorWasDeleted;
use App\Events\VendorWasArchived;
use App\Events\VendorWasRestored;
use App\Ninja\Repositories\ActivityRepository;
use App\Ninja\Repositories\VendorActivityRepository;

class VendorActivityListener
{
    protected $activityRepo;

    public function __construct(VendorActivityRepository $activityRepo)
    {
        $this->activityRepo = $activityRepo;
    }

    // Vendors
    public function createdVendor(VendorWasCreated $event)
    {
        $this->activityRepo->create(
            $event->vendor,
            ACTIVITY_TYPE_CREATE_VENDOR
        );
    }

    public function deletedVendor(VendorWasDeleted $event)
    {
        $this->activityRepo->create(
            $event->vendor,
            ACTIVITY_TYPE_DELETE_CLIENT
        );
    }

    public function archivedVendor(VendorWasArchived $event)
    {
        if ($event->client->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->vendor,
            ACTIVITY_TYPE_ARCHIVE_CLIENT
        );
    }

    public function restoredVendor(VendorWasRestored $event)
    {
        $this->activityRepo->create(
            $event->vendor,
            ACTIVITY_TYPE_RESTORE_CLIENT
        );
    }
}
