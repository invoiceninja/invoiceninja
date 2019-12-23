<?php


namespace App\Services\Migration\Steps;

class OptionStepService
{
    private $request;

    private $response;

    private $successful;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function start()
    {
        $this->setOption($this->request->version); 
    }

    public function getSuccessful()
    {
        return $this->successful;
    }

    public function onSuccess()
    {
        return '/migration/steps/login';
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function onFailure()
    {
        return '/migration/steps/options';
    }

    protected function setOption($option)
    {
        session()->put('migration_option', $option); 

        if(session('migration_option')) {
            return $this->successful = true; 
        }
        
        $this->successful = false;

        // TODO: Put nice "selected" message in the $response.
    }
}