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

use App\Utils\Traits\WithSecureContext;
use Livewire\Component;

class Terms extends Component
{
    use WithSecureContext;

    public $invoice;

    public $variables;

    public function mount()
    {
        $this->invoice = $this->getContext()['invoice'];
        $this->variables = $this->getContext()['variables'];
    }

    public function render()
    {
        return render('components.livewire.terms');
    }
}
