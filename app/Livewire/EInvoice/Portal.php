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
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class Portal extends Component
{
    public $email = '';
    public $password = '';

    public array $companies;

    private string $api_url = '';

    public function mount()
    {

        $this->api_url = config('ninja.hosted_ninja_url');
        
        $this->getCompanies();

    }

    private function getCompanies(): self
    {
                
        $this->companies = auth()->guard('user')->check() ? auth()->guard('user')->user()->account->companies->map(function ($company) {
            return [
                'key' => $company->company_key,
                'city' => $company->settings->city,
                'country' => $company->country()->iso_3166_2,
                'county' => $company->settings->state,
                'line1' => $company->settings->address1,
                'line2' => $company->settings->address2,
                'party_name' => $company->settings->name,
                'vat_number' => $company->settings->vat_number,
                'zip' => $company->settings->postal_code,
                'legal_entity_id' => $company->legal_entity_id,
                'tax_registered' => (bool) strlen($company->settings->vat_number ?? '') > 2,
                'tenant_id' => $company->company_key,
                'classification' => strlen($company->settings->classification ?? '') > 2 ? $company->settings->classification : 'business',
            ];
        })->toArray() : [];

        return $this;

    }

    public function login()
    {
        $credentials = ['email' => $this->email, 'password' => $this->password];

        if (Auth::attempt($credentials)) {
            session()->flash('message', 'Logged in successfully.');
    
            App::setLocale(auth()->guard('user')->user()->account->companies->first()->getLocale());

            $this->getCOmpanies();


        } else {
            session()->flash('error', 'Invalid credentials.');
        }
    }

    public function logout()
    {
        Auth::logout();
        
        session()->flash('message', 'Logged out!');

    }

    public function register(string $company_key)
    {

        $register_company = [            
            'acts_as_receiver' => true,
            'acts_as_sender' => true,
            'advertisements' => ['invoice']
        ];

        foreach($this->companies as $company)
        {
            if($company['key'] == $company_key)
                $register_company = array_merge($company, $register_company);
        }

        $r = Http::withHeaders($this->getHeaders())
                    ->post("{$this->api_url}/api/einvoice/createLegalEntity", $register_company);

        if($r->successful())
        {

            nlog($r->body());
            $response = $r->json();
            
            $_company = auth()->guard('user')->user()->account->companies()->where('company_key', $company_key)->first();
            $_company->legal_entity_id = $response['id'];
            $_company->save();

            $this->getCompanies();
            
            return;
        }

        if($r->failed())
            nlog($r->getBody()->getContents());

        $error = json_decode($r->getBody()->getContents(),true);

        session()->flash('error', $error['message']);

    }

    private function getHeaders()
    {
        return [
            'X-API-SELF-HOST-TOKEN' => config('ninja.license_key'),
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type" => "application/json",
        ];
    }

    public function render()
    {
        return view('livewire.e-invoice.portal');
    }
}
