<?php

namespace App\Http\Livewire;

use App\Models\ClientContact;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class BillingPortalPurchase extends Component
{
    public $authenticated = false;

    public $email;

    public $password;

    public $steps = [
        'passed_email' => false,
        'existing_user' => false,
        'fetched_payment_methods' => false,
    ];

    protected $rules = [
        'email' => ['required', 'email'],
    ];

    public function authenticate()
    {
        $this->validate();

        // Search for existing e-mail (note on multiple databases).
        // If existing e-mail found, offer to login with password.
        // If not, create a new contact e-mail.

        $contact = ClientContact::where('email', $this->email)->first();

        if ($contact && $this->steps['existing_user'] === false) {
            return $this->steps['existing_user'] = true;
        }

        if ($contact && $this->steps['existing_user']) {
            $attempt = Auth::guard('contact')->attempt(['email' => $this->email, 'password' => $this->password]);

            if (!$attempt) {
                $this->password = '';

                session()->flash('message', 'These credentials do not match our records.');
            }
        }

        $this->steps['existing_user'] = false;
        $this->createBlankClient();
    }

    protected function createBlankClient()
    {

    }

    public function render()
    {
        return render('components.livewire.billing-portal-purchase');
    }
}
