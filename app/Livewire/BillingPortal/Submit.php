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

namespace App\Livewire\BillingPortal;

use Livewire\Component;

class Submit extends Component
{
    public array $context;

    public function mount()
    {
        // This is right place to check if everything is set up correctly.

        $this->dispatch('purchase.submit');
    }

    public function render()
    {
        return <<<'HTML'
            <div></div>    
        HTML;
    }
}
