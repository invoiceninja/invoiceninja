<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Livewire\Profile\Settings;

use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class General extends Component
{
    public $profile;

    public $first_name;

    public $last_name;

    public $email;

    public $phone;

    public $password;

    public $password_confirmation;

    public $saved;

    protected $rules = [
        'first_name' => ['sometimes'],
        'last_name' => ['sometimes'],
        'email' => ['required', 'email'],
        'phone' => ['sometimes'],
    ];

    public function mount()
    {
        $profile = auth()->guard('contact')->user();

        $this->fill([
            'profile' => $profile,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.general');
    }

    public function submit()
    {
        if ($this->profile->email != $this->email) {
            $this->rules['email'][] = 'unique:client_contacts,email';
        }

        if (! empty($this->password)) {
            $this->rules['password'] = ['sometimes', 'nullable', 'required', 'min:6', 'confirmed'];
        }

        $data = $this->validate($this->rules);

        if (! empty($this->password)) {
            $this->profile->password = Hash::make($this->password);
        }

        $this->profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}
