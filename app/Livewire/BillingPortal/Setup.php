<?php

/**
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
