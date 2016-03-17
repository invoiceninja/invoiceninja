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
    
    protected function checkViewPermission($object, &$response = null){
        if(!$object->canView()){
            $response = response('Unauthorized.', 401);
            return false;
        }
        return true;
    }
    
    protected function checkEditPermission($object, &$response = null){
        if(!$object->canEdit()){
            $response = response('Unauthorized.', 401);
            return false;
        }
        return true;
    }
    
    protected function checkCreatePermission(&$response = null){
        if(!call_user_func(array($this->model, 'canCreate'))){
            $response = response('Unauthorized.', 401);
            return false;
        }
        return true;
    }
    
    protected function checkUpdatePermission($input, &$response = null){
        $creating = empty($input['public_id']) || $input['public_id'] == '-1';
        
        if($creating){
            return $this->checkCreatePermission($response);
        }
        else{
            $object = call_user_func(array($this->model, 'scope'), $input['public_id'])->firstOrFail();
            return $this->checkEditPermission($object, $response);
        }
    }
}
