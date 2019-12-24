<?php


namespace App\Services\Migration\Steps;

use App\Models\User;


class ClientsStepService
{
    private $request;
    private $response;
    private $successful;
    private $clients; 

    public function __construct($request)
    {
        $this->request = $request;
       
        $this->clients = [
            'clients' => []
        ];

        $this->successful = false;
    }

    public function start()
    {
        $this->mapClients();
        $this->insertClients(); 
    }

    public function getSuccessful()
    {
        return $this->successful;
    }

    public function onSuccess()
    {
        return '/migration/steps/invoices';
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function onFailure()
    {
        return '/migration/steps/clients';
    }

    private function mapClients()
    {
        $user = User::find(auth()->user()->id)
            ->with('account.contacts')
            ->first();

        foreach ($user->account->clients as $client) {

            $object = [
                'name' => $client->name,
                'address1' => $client->address1,
                'address2' => $client->address2,
                'city' => $client->city,
                'state' => $client->state,
                'postal_code' => $client->postal_code,
                'country_id' => $client->country_id,
                'phone' => $client->work_phone,
                'private_notes' => $client->private_notes,
                'balance' => $client->balance,
                'paid_to_date' => $client->paid_to_date,
                'last_login' => $client->last_login,
                'website' => $client->website,
                'industry_id' => $client->industry_id,
                'is_deleted' => $client->is_deleted,
                'custom_value1' => $client->custom_value1,
                'custom_value2' => $client->custom_value2,
                'vat_number' => $client->vat_number,
                'id_number' => $client->id_number,
                'shipping_address1' => $client->shipping_address1,
                'shipping_address2' => $client->shipping_address2,
                'shipping_city' => $client->shipping_city,
                'shipping_state' => $client->shipping_state,
                'shipping_postal_code' => $client->shipping_postal_code,
                'shipping_country_id' => $client->shipping_country_id,
                'settings' => [
                    'size_id' => $client->size_id,
                    'industry_id' => $client->industry_id,
                ]
            ];

            foreach($client->contacts as $contact) {
                $object['contacts'][] = [
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'email' => $contact->email,
                    'is_primary' => $contact->is_primary,
                    'phone' => $contact->phone,
                    'contact_key' => $contact->contact_key,
                ]; 
            }

            $this->clients['clients'][] = $object;
        }
    }

    private function insertClients()
    {  
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-API-SECRET' => session('X_API_SECRET'),
            'X-API-TOKEN' => session('X_API_TOKEN'),
        ];

        $response = \Unirest\Request::post(
            session('SELF_HOSTED_URL') . '/api/v1/clients/bulk/import',
            $headers,
            json_encode($this->clients)
        );

        if($response->code == 200) {
            $this->successful = true; 
        }

        $this->response = [
            'code' => $response->code,
            'type' => $this->successful ? 'single' : 'array',
            'content' => $this->successful ? 'Client\'s migrated succesfully.' : ($response->body->message) ?? null,
            'errors' => $this->successful ? [] : $response->body->errors,
        ];
    }
}