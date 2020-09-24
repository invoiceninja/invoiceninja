<?php

namespace App\Http\Livewire\Profile\Settings;

use Livewire\Component;

class ShippingAddress extends Component
{
    public $profile;

    public $shipping_address1;
    public $shipping_address2;
    public $shipping_city;
    public $shipping_state;
    public $shipping_postal_code;
    public $shipping_country_id;

    public $countries;

    public $saved;

    protected $rules = [
        'shipping_address1' => ['required'],
        'shipping_address2' => ['required'],
        'shipping_city' => ['required'],
        'shipping_state' => ['required'],
        'shipping_postal_code' => ['required'],
        'shipping_country_id' => ['required'],
    ];

    public function mount($countries)
    {
        $this->fill([
            'profile' => auth()->user('contact')->client,
            'shipping_address1' => auth()->user('contact')->client->shipping_address1,
            'shipping_address2' => auth()->user('contact')->client->shipping_address2,
            'shipping_city' => auth()->user('contact')->client->shipping_city,
            'shipping_state' => auth()->user('contact')->client->shipping_state,
            'shipping_postal_code' => auth()->user('contact')->client->shipping_postal_code,
            'shipping_country_id' => auth()->user('contact')->client->shipping_country_id,

            'countries' => $countries,
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.shipping-address');
    }

    public function submit()
    {
        $data = $this->validate($this->rules);

        $this->profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}
