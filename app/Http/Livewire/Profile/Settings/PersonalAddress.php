<?php

namespace App\Http\Livewire\Profile\Settings;

use Livewire\Component;

class PersonalAddress extends Component
{
    public $profile;

    public $address1;
    public $address2;
    public $city;
    public $state;
    public $postal_code;
    public $country_id;

    public $countries;

    public $saved;

    protected $rules = [
        'address1' => ['required'],
        'address2' => ['required'],
        'city' => ['required'],
        'state' => ['required'],
        'postal_code' => ['required'],
        'country_id' => ['required'],
    ];

    public function mount($countries)
    {
        $this->fill([
            'profile' => auth()->user('contact')->client,
            'address1' => auth()->user('contact')->client->address1,
            'address2' => auth()->user('contact')->client->address2,
            'city' => auth()->user('contact')->client->city,
            'state' => auth()->user('contact')->client->state,
            'postal_code' => auth()->user('contact')->client->postal_code,
            'country_id' => auth()->user('contact')->client->country_id,

            'countries' => $countries,
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.personal-address');
    }

    public function submit()
    {
        $data = $this->validate($this->rules);

        if ($data['country_id'] == 'none') {
            $data['country_id'] = null;
        }

        $this->profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}
