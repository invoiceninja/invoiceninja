<?php namespace App\Http\Controllers;
// vendor
use Auth;
use DB;
use Datatable;
use Utils;
use View;
use App\Models\Expense;
use App\Models\ExpenseActivity;
use App\Services\ExpenseActivityService;

class ExpenseActivityController extends BaseController
{
    protected $activityService;

    public function __construct(ExpenseActivityService $activityService)
    {
        parent::__construct();

        $this->activityService = $activityService;
    }

    public function getDatatable($vendorPublicId)
    {
        return $this->activityService->getDatatable($vendorPublicId);
    }
}
