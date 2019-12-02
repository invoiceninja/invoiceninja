<?php


namespace App\Services\Migration\Steps;


class ClientsStepService
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
        $this->mapClients();
    }

    public function getSuccessful()
    {
        return $this->successful;
    }

    public function onSuccess()
    {
        return '/migration/steps/clients';
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function onFailure()
    {
        return '/migration/steps/settings';
    }


    private function mapClients()
    {
        $clients = auth()->user()->account->clients;

        // ..
    }
}