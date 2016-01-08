<?php namespace app\Listeners;

use App\Events\ExpenseWasCreated;
use App\Events\ExpenseWasDeleted;
use App\Events\ExpenseWasArchived;
use App\Events\ExpenseWasRestored;
use App\Ninja\Repositories\ActivityRepository;
use App\Ninja\Repositories\ExpenseActivityRepository;

class ExpenseActivityListener
{
    protected $activityRepo;

    public function __construct(ExpenseActivityRepository $activityRepo)
    {
        $this->activityRepo = $activityRepo;
    }

    // Expenses
    public function createdExpense(ExpenseWasCreated $event)
    {
        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_CREATE_EXPENSE
        );
    }

    public function deletedExpense(ExpenseWasDeleted $event)
    {
        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_DELETE_EXPENSE
        );
    }

    public function archivedExpense(ExpenseWasArchived $event)
    {
        /*
        if ($event->client->is_deleted) {
            return;
        }
        */
        
        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_ARCHIVE_EXPENSE
        );
    }

    public function restoredExpense(ExpenseWasRestored $event)
    {
        $this->activityRepo->create(
            $event->expense,
            ACTIVITY_TYPE_RESTORE_EXPENSE
        );
    }
}
