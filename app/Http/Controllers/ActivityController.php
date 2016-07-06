<?php namespace App\Http\Controllers;

use Auth;
use DB;
use Datatable;
use Utils;
use View;
use App\Services\ActivityService;

class ActivityController extends BaseController
{
    protected $activityService;

    public function __construct(ActivityService $activityService)
    {
        $this->activityService = $activityService;
    }

    public function getDatatable($clientPublicId)
    {
        return $this->activityService->getDatatable($clientPublicId);
    }
}
