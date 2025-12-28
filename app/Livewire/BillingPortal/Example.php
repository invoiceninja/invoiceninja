<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal;

use Livewire\Component;

class Example extends Component
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
            <div>This is step after auth. Currently logged in user is {{ $context['contact']['email'] }}.</div>
        HTML;
    }
}
