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

class Setup extends Component
{
    public array $context;

    public function mount()
    {
        $this->dispatch('purchase.context', property: 'quantity', value: 1);
        $this->dispatch('purchase.next');
    }

    public function render()
    {
        return <<<'HTML'
            <template></template>
        HTML;
    }
}
