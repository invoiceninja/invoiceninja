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
use Illuminate\Support\Str;
use Livewire\Component;

class RequiredClientInfo extends Component
{
    public $fields = [];

    public $contact;

    private $gateway;

    private $mappings = [
        'client_name' => 'name',

        'contact_phone' => 'phone',
    ];

    public function handleSubmit(array $data): bool
    {
        $rules = [];

        collect($this->fields)->map(function ($field) use (&$rules) {
            $rules[$field['name']] = array_key_exists('validation_rules', $field)
                ? $field['validation_rules']
                : 'required';
        });

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            session()->flash('validation_errors', $validator->getMessageBag()->getMessages());

            return false;
        }

        if ($this->updateClientDetails($data)) {
            $this->emit('passed-required-fields-check');

            return true;
        }

        // TODO: Throw an exception about not being able to update the profile.
        return false;
    }

    private function updateClientDetails(array $data): bool
    {
        $client = [];
        $contact = [];

        foreach ($data as $field => $value) {
            if (Str::startsWith($field, 'client_', )) {
                $client[$this->mappings[$field]] = $value;
            }

            if (Str::startsWith($field, 'contact_')) {
                $contact[$this->mappings[$field]] = $value;
            }
        }

        $contact_update = $this->contact
            ->fill($contact)
            ->push();

        $client_update = $this->contact->client
            ->fill($client)
            ->push();

        if ($contact_update && $client_update) {
            return true;
        }

        return false;
    }

    public function render()
    {
        // This will be coming from the gateway itself. Something like $gateway->getRequiredRules();

        $this->fields = [
            [
                'name' => 'client_name',
                'label' => ctrans('texts.name'),
                'type' => 'text',
                'validation_rules' => 'required|min:3'
            ],
            [
                'name' => 'contact_phone',
                'label' => ctrans('texts.phone'),
                'type' => 'number',
                'validation_rules' => 'required|min:2',
            ],
        ];

        return render('components.livewire.required-client-info');
    }
}
