<?php

namespace App\Http\ViewComposers\Components\Rotessa;

use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;
use Illuminate\View\Component;
use App\Models\ClientContact;
use Illuminate\Support\Arr;
use Illuminate\View\View;


// Contact Component
class ContactComponent extends Component
{

    public function __construct(ClientContact $contact) {
        $contact = collect($contact->client->contacts->firstWhere('is_primary', 1)->toArray())->merge([
            'home_phone' =>$contact->client->phone, 
            'custom_identifier' => $contact->client->number,
            'name' =>$contact->client->name,
            'id' => null
        ] )->all();
        
        $this->attributes = $this->newAttributeBag(Arr::only($contact, $this->fields) );
    }

    private $fields = [
        'name',
        'email',
        'home_phone',
        'phone',
        'custom_identifier',
        'customer_type' ,
        'id'
    ];

    private $defaults = [
        'customer_type' => "Business",
        'customer_identifier' => null,
        'id' => null
    ];

    public function render()
    {
        return render('gateways.rotessa.components.contact', array_merge($this->defaults, $this->attributes->getAttributes() ) );
    }
}
