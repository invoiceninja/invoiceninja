<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\Flow2;

use Livewire\Component;

class RequiredFields extends Component
{
    public $context;

    public function mount()
    {
        
    }

    public function render()
    {
        return render('components.livewire.required-fields', ['contact' => $this->context['contact'], 'fields' => $this->context['fields']]);
    }
}
