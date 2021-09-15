<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Livewire;

use Illuminate\View\View;
use Livewire\Component;

class Statement extends Component
{
    public string $url;

    public array $options = [
        'show_payments_table' => 0,
        'show_aging_table' => 0,
    ];

    public function mount(): void
    {
        $this->options['start_date'] = now()->startOfYear()->format('Y-m-d');
        $this->options['end_date'] = now()->format('Y-m-d');
    }

    protected function getCurrentUrl(): string
    {
        return route('client.statement.raw', $this->options);
    }

    public function download()
    {
        return redirect()->route('client.statement.raw', \array_merge($this->options, ['download' => 1]));
    }

    public function render(): View
    {
        $this->url = route('client.statement.raw', $this->options);

        return render('components.statement');
    }
}
