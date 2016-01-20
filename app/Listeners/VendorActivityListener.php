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
            ACTIVITY_TYPE_DELETE_VENDOR
        );
    }

    public function archivedVendor(VendorWasArchived $event)
    {
        if ($event->vendor->is_deleted) {
            return;
        }

        $this->activityRepo->create(
            $event->vendor,
            ACTIVITY_TYPE_ARCHIVE_VENDOR
        );
    }

    public function restoredVendor(VendorWasRestored $event)
    {
        $this->activityRepo->create(
            $event->vendor,
            ACTIVITY_TYPE_RESTORE_VENDOR
        );
    }
}
