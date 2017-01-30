<?php

namespace App\Http\Requests;

class ClientRequest extends EntityRequest
{
    protected $entityType = ENTITY_CLIENT;

    public function entity()
    {
        $client = parent::entity();
        
        // eager load the contacts
        if ($client && ! $client->relationLoaded('contacts')) {
            $client->load('contacts');
        }
         
        return $client;
    }
}
