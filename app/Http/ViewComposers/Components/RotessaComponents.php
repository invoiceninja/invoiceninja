<?php

namespace App\Http\ViewComposers\Components;

use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;
use Illuminate\View\Component;
use Illuminate\Support\Arr;
use Illuminate\View\View;

// Contact Component
class ContactComponent extends Component
{

    public array $contact;

    public function __construct(array $contact) {
        $this->contact = $contact;
        $this->attributes = $this->newAttributeBag(Arr::only($this->contact, $this->fields) );
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
        return $this->view('rotessa::components.contact', $this->attributes->getAttributes(), $this->defaults );
    }
}

// Address Component
class AddressComponent extends Component
{
    private $fields = [
        'address_1',
        'address_2',
        'city',
        'postal_code',
        'province_code',
        'country'
    ];

    private $defaults = [
        'country' => 'US'
    ];

    public array $address;

    public function __construct(array $address) {
        $this->address = $address;
        if(strlen($this->address['state']) > 2 ) {
            $this->address['state'] = $this->address['country'] == 'US' ? array_search($this->address['state'], USStates::$states) : CAProvinces::getAbbreviation($this->address['state']); 
        }

        $this->attributes = $this->newAttributeBag(
            Arr::only(Arr::mapWithKeys($this->address, function ($item, $key) {
                return in_array($key, ['address1','address2','state'])?[ (['address1'=>'address_1','address2'=>'address_2','state'=>'province_code'])[$key] => $item ] :[ $key => $item ];
             }),
        $this->fields) );
    }

    
    public function render()
    {

        return $this->view('rotessa::components.address', $this->attributes->getAttributes(), $this->defaults );
    }
}

// AmericanBankInfo Component
class AccountComponent extends Component
{
    private $fields = [
        'bank_account_type',
        'routing_number',
        'institution_number',
        'transit_number',
        'bank_name',
        'country',
        'account_number'
    ];

    private $defaults =  [
        'bank_account_type' => null,
        'routing_number' => null,
        'institution_number' => null,
        'transit_number' => null,
        'bank_name' => ' ',
        'account_number' => null,
        'country' => 'US',
        "authorization_type" => 'Online'
    ];

    public array $account;

    public function __construct(array $account) {
        $this->account = $account;
        $this->attributes = $this->newAttributeBag(Arr::only($this->account, $this->fields) );
    }
    
    public function render()
    {
        return $this->view('rotessa::components.account', $this->attributes->getAttributes(), $this->defaults );
    }
}
