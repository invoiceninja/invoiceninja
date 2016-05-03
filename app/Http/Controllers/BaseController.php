<?php namespace App\Http\Controllers;

use App\Http\Middleware\PermissionsRequired;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Input;
use Auth;
use Utils;

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
}
