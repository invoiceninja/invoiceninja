<?php namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesCommands;

class BaseController extends Controller
{
    use DispatchesCommands;

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

    public function __construct()
    {
        $this->beforeFilter('csrf', array('on' => array('post', 'delete', 'put')));
    }
}
