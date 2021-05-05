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

namespace App\Http\Livewire;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class WepaySignup extends Component
{
    public $user;
    public $user_id;
    public $company_key;
    public $first_name;
    public $last_name;
    public $email;

    public $terms;
    public $privacy_policy;

    public $saved;

    protected $rules = [
        'first_name' => ['sometimes'],
        'last_name' => ['sometimes'],
        'email' => ['required', 'email'],
    ];

    public function mount()
    {
        $user = User::find($this->user_id);
        $company = Company::where('company_key', $this->company_key)->first();
        
        $this->fill([
            'user' => $user,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'company_name' => $company->present()->name(),
            'saved' => ctrans('texts.confirm'),
            'terms' => '<a href="https://go.wepay.com/terms-of-service" target="_blank">'.ctrans('texts.terms_of_service').'</a>',
            'privacy_policy' => '<a href="https://go.wepay.com/privacy-policy" target="_blank">'.ctrans('texts.privacy_policy').'</a>',
        ]);
    }

    public function render()
    {
      return render('gateways.wepay.signup.wepay-signup');
    }

    public function submit()
    {

        $data = $this->validate($this->rules);

        // $this->user
        //     ->fill($data)
        //     ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}
