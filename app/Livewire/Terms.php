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

namespace App\Livewire;

use Livewire\Component;

class Terms extends Component
{
    public $invoice;

    public $context;

    public $variables;

    public function mount()
    {
        $this->invoice = $this->context['invoice'];
        $this->variables = $this->context['variables'];
    }

    public function render()
    {
        return render('components.livewire.terms');
    }
}
