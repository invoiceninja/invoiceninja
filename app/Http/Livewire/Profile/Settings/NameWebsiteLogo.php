<?php

namespace App\Http\Livewire\Profile\Settings;

use Livewire\Component;

class NameWebsiteLogo extends Component
{
    public $profile;

    public $name;

    public $vat_number;

    public $website;

    public $phone;

    public $saved;

    public $rules = [
        'name' => ['sometimes', 'min:3'],
        'vat_number' => ['sometimes'],
        'website' => ['sometimes'],
        'phone' => ['sometimes', 'string', 'max:255'],
    ];

    public function mount()
    {
        $this->fill([
            'profile' => auth()->guard('contact')->user()->client,
            'name' => auth()->guard('contact')->user()->client->present()->name,
            'vat_number' => auth()->guard('contact')->user()->client->present()->vat_number,
            'website' => auth()->guard('contact')->user()->client->present()->website,
            'phone' => auth()->guard('contact')->user()->client->present()->phone,
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.name-website-logo');
    }

    public function submit()
    {
        $data = $this->validate($this->rules);

        $this->profile->name = $data['name'];
        $this->profile->vat_number = $data['vat_number'];
        $this->profile->website = $data['website'];
        $this->profile->phone = $data['phone'];

        $this->profile->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}
