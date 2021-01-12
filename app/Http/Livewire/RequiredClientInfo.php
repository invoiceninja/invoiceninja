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

use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class RequiredClientInfo extends Component
{
    public $fields = [];

    private $gateway;

    public function handleSubmit(array $data): bool
    {
        $rules = [];

        collect($this->fields)->map(function ($field) use (&$rules) {
            $rules[$field['name']] = $field['validation_rules'];
        });


        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            session()->flash('validation_errors', $validator->getMessageBag()->getMessages());

            return false;
        }

        $this->emit('passed-required-fields-check');

        return true;
    }

    public function render()
    {
        // This will be coming from the gateway itself. Something like $gateway->getRequiredRules();

        $this->fields = [
            [
                'name' => 'client_first_name',
                'label' => ctrans('texts.first_name'),
                'type' => 'text',
                'validation_rules' => 'required|min:3'
            ],
            [
                'name' => 'client_billing_address_zip',
                'label' => ctrans('texts.postal_code'),
                'type' => 'number',
                'validation_rules' => 'required|min:2',
            ],
        ];

        return render('components.livewire.required-client-info');
    }
}
