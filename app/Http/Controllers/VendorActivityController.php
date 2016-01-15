<?php namespace App\Http\Controllers;
// vendor
use Auth;
use DB;
use Datatable;
use Utils;
use View;
use App\Models\Vendor;
use App\Models\VendorActivity;
use App\Services\VendorActivityService;

class VendorActivityController extends BaseController
{
    protected $activityService;

    public function __construct(VendorActivityService $activityService)
    {
        parent::__construct();

        $this->activityService = $activityService;
    }

    public function getDatatable($vendorPublicId)
    {
        return $this->activityService->getDatatable($vendorPublicId);
    }
}
