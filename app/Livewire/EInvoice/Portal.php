<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\EInvoice;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class Portal extends Component
{
    public $email = '';
    public $password = '';

    public array $companies;

    public function mount()
    {

        $this->companies = auth()->user()->account->companies->map(function ($company) {
            return [
                'key' => $company->company_key,
                'city' => $company->settings->city,
                'country' => $company->country()->iso_3166_2,
                'county' => $company->settings->state,
                'line1' => $company->settings->address1,
                'line2' => $company->settings->address2,
                'name' => $company->settings->name,
                'vat_number' => $company->settings->vat_number,
                'zip' => $company->settings->postal_code,
                'legal_entity_id' => $company->legal_entity_id
            ];
        })->toArray();

    }

    public function login()
    {
        $credentials = ['email' => $this->email, 'password' => $this->password];

        if (Auth::attempt($credentials)) {
            session()->flash('message', 'Logged in successfully.');
    
            $this->companies = auth()->user()->account->companies->map(function ($c){
                return ['name' => $c->settings->name, 'company_key' => $c->company_key, 'legal_entity_id' => $c->legal_entity_id];
            })->toArray();

        } else {
            session()->flash('error', 'Invalid credentials.');
        }
    }

    public function logout()
    {
        Auth::logout();
        
        session()->flash('message', 'Logged out!');

    }

    public function render()
    {
        return view('livewire.e-invoice.portal');
    }
}
