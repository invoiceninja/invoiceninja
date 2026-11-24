<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire;

use App\Http\ViewComposers\PortalComposer;
use App\Models\Quote;
use App\Services\Quote\QuoteOptions as QuoteOptionsService;
use App\Utils\Traits\MakesHash;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Quote $quote
 * @property-read Quote $preview
 */
class QuoteOptions extends Component
{
    use MakesHash;

    #[Locked]
    public string $quote_id;

    #[Locked]
    public string $quote_revision;

    public array $selected_options = [];

    public function mount(string $quote_id): void
    {
        $this->quote_id = $quote_id;
        $this->quote_revision = app(QuoteOptionsService::class)->revision($this->quote);
    }

    #[Computed]
    public function quote(): Quote
    {
        $contact = auth()->guard('contact')->user();
        abort_unless($contact && ($contact->company->enabled_modules & PortalComposer::MODULE_QUOTES), 403);

        $quote = Quote::query()
            ->where('company_id', $contact->company_id)
            ->where('client_id', $contact->client_id)
            ->findOrFail($this->decodePrimaryKey($this->quote_id));

        abort_unless(
            in_array($quote->status_id, [Quote::STATUS_DRAFT, Quote::STATUS_SENT])
            && ! $quote->is_deleted && ! $quote->invoice_id && ! $quote->hasLapsedValidUntil()
            && $quote->hasOptions(),
            409
        );

        return $quote;
    }

    #[Computed]
    public function preview(): Quote
    {
        return app(QuoteOptionsService::class)->preview($this->quote, $this->selected_options);
    }

    public function continueApproval(): void
    {
        $quote = app(QuoteOptionsService::class)->apply(
            $this->quote,
            $this->selected_options,
            $this->quote_revision,
        );

        $this->redirectRoute('client.quote.show', ['quote' => $quote->hashed_id]);
        $this->skipRender();
    }

    public function updatedSelectedOptions(): void
    {
        $this->resetValidation();

        try {
            app(QuoteOptionsService::class)->validateSelection($this->quote, $this->selected_options);
        } catch (ValidationException $exception) {
            $this->selected_options = [];
            $this->setErrorBag($exception->errors());
        }
    }

    public function render(): View
    {
        $service = app(QuoteOptionsService::class);
        $line_items = $service->displayItems($this->quote, $this->selected_options);

        return render('components.livewire.quote-options', [
            'quote' => $this->preview,
            'financials' => $service->financials($this->preview, $line_items),
        ]);
    }
}
