<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
        'first_name' => ['required'],
        'last_name' => ['required'],
        'email' => ['required', 'email'],
    ];

    public function mount()
    {
        $profile = auth()->user('contact');

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

        if (!empty($this->password)) {
            $this->rules['password'] = ['sometimes', 'nullable', 'required', 'min:6', 'confirmed'];
        }

        $data = $this->validate($this->rules);

        if (!empty($this->password)) {
            $this->profile->password = Hash::make($this->password);
        }

        $this->profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}
