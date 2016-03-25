<?php namespace App\Http\Controllers;

use Auth;
use DB;
use Datatable;
use Utils;
use View;
use App\Models\Client;
use App\Models\Activity;
use App\Services\ActivityService;

class ActivityController extends BaseController
{
    protected $activityService;

    public function __construct(ActivityService $activityService)
    {
        //parent::__construct();

        $this->activityService = $activityService;
    }

    public function getDatatable($clientPublicId)
    {
        return $this->activityService->getDatatable($clientPublicId);
    }
}
