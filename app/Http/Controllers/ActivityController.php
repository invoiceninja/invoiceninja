<?php

namespace App\Http\Controllers;

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
