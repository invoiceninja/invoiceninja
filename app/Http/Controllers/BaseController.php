<?php namespace App\Http\Controllers;

use App\Http\Middleware\PermissionsRequired;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
    
    protected function authorizeCreate() {
        $this->authorize('create', $this->entityType);
    }
    
    protected function authorizeUpdate($input){
        $creating = empty($input['public_id']) || $input['public_id'] == '-1';
        
        if($creating){
            $this->authorize('create', $this->entityType);
        }
        else{
            $className = Utils::getEntityName($this->entityType);
            
            $object = call_user_func(array("App\\Models\\{$className}", 'scope'), $input['public_id'])->firstOrFail();
            $this->authorize('edit', $object);
        }
    }
}
