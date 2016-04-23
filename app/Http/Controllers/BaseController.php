<?php namespace App\Http\Controllers;

use App\Http\Middleware\PermissionsRequired;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Auth;

class BaseController extends Controller
{
    use DispatchesJobs;
    
    protected $model = 'App\Models\EntityModel';

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
    
    protected function authorizeUpdate($input){
        $creating = empty($input['public_id']) || $input['public_id'] == '-1';
        
        if($creating){
            $this->authorize('create', $this->model);
        }
        else{
            $object = call_user_func(array($this->model, 'scope'), $input['public_id'])->firstOrFail();
            $this->authorize('edit', $object);
        }
    }
}
