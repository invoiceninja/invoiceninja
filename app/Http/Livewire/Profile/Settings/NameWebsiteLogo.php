<?php

namespace App\Http\Livewire\Profile\Settings;

use Livewire\Component;

class NameWebsiteLogo extends Component
{
    public $profile;

    public $name;
    public $website;

    public $saved;

    public $rules = [
        'name' => ['required', 'min:3'],
        'website' => ['required', 'url'],
    ];

    public function mount()
    {
        $this->fill([
            'profile' => auth()->user('contact')->client,
            'name' => auth()->user('contact')->client->present()->name,
            'website' => auth()->user('contact')->client->present()->website,
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

        $this->profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}
