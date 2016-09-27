<?php namespace App\Http\Controllers;

use Utils;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class BaseController extends Controller
{
    use DispatchesJobs, AuthorizesRequests;

    protected $entityType;

    /**
     * Setup the layout used by the controller.
     *
     * @return void
     */
    protected function setupLayout()
    {
        if (! is_null($this->layout)) {
            $this->layout = View::make($this->layout);
        }
    }

    protected function returnBulk($entityType, $action, $ids)
    {
        if ( ! is_array($ids)) {
            $ids = [$ids];
        }
        
        $isDatatable = filter_var(request()->datatable, FILTER_VALIDATE_BOOLEAN);
        $entityTypes = Utils::pluralizeEntityType($entityType);

        if ($action == 'restore' && count($ids) == 1) {
            return redirect("{$entityTypes}/" . $ids[0]);
        } elseif ($isDatatable || ($action == 'archive' || $action == 'delete')) {
            return redirect("{$entityTypes}");
        } elseif (count($ids)) {
            return redirect("{$entityTypes}/" . $ids[0]);
        } else {
            return redirect("{$entityTypes}");
        }
    }
}
