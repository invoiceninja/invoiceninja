<?php

namespace App\Http\ViewComposers\Components\Rotessa;

use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;
use Illuminate\View\Component;
use App\Models\ClientContact;
use Illuminate\Support\Arr;
use Illuminate\View\View;

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
        'bank_name' => null,
        'account_number' => null,
        'country' => 'US',
        "authorization_type" => 'Online'
    ];

    public function __construct(public array $account) {
        $this->attributes = $this->newAttributeBag(Arr::only($this->account, $this->fields) );
    }
    
    public function render()
    {

        return render('gateways.rotessa.components.account', $this->attributes->getAttributes() + $this->defaults);
    }
}
