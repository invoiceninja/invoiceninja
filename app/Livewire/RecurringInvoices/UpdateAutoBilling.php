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

namespace App\Livewire\RecurringInvoices;

use App\Models\Invoice;
use Livewire\Component;
use App\Libraries\MultiDB;
use App\Models\RecurringInvoice;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Illuminate\Support\Facades\DB;

class UpdateAutoBilling extends Component
{
    #[Locked]
    public $invoice_id;

    #[Locked]
    public $db;

    #[Locked]
    public bool $show_radios = false;

    // Hashed ID of the payable invoice; invoice_id above identifies its recurring template.
    #[Locked]
    public ?string $checkout_invoice_id = null;

    #[Locked]
    public ?string $token_billing_policy = null;

    #[Locked]
    public bool $prepayment_recurring = false;

    public function mount()
    {
        MultiDB::setDb($this->db ?? config('database.default'));
    }

    #[Computed]
    public function invoice()
    {
        MultiDB::setDb($this->db ?? config('database.default'));

        $contact = auth()->guard('contact')->user() ?? false;

        abort_unless($contact, 403);

        $invoice = RecurringInvoice::withTrashed()
            ->withoutEagerLoads()
            ->where('company_id', $contact->company_id)
            ->where('client_id', $contact->client_id)
            ->where('is_deleted', false)
            ->find($this->invoice_id);

        // A payable invoice can outlive its deleted recurring template.
        abort_if(! $invoice && ! $this->show_radios, 404);

        return $invoice;
    }

    public function updateAutoBilling(): void
    {
        $invoice = $this->invoice();
        abort_unless($invoice, 404);
        $this->setAutoBilling(! $invoice->auto_bill_enabled);
    }

    #[Computed]
    public function canChooseAutoBilling(): bool
    {
        $invoice = $this->invoice();

        if (! $invoice || ! $this->checkout_invoice_id || ! in_array($invoice->auto_bill, ['optin', 'optout'], true)) {
            return false;
        }

        $first_invoice = $invoice->invoices()
            ->withoutEagerLoads()
            ->where('company_id', $invoice->company_id)
            ->where('client_id', $invoice->client_id)
            ->orderBy('id')
            ->first(['id']);

        return $first_invoice?->hashed_id === $this->checkout_invoice_id;
    }

    public function setAutoBilling(bool $enabled): void
    {
        $invoice = $this->invoice();
        abort_unless($invoice, 404);

        if ($this->show_radios && ! $this->canChooseAutoBilling()) {
            return;
        }

        if ($invoice->auto_bill == 'optin' || $invoice->auto_bill == 'optout') {
            DB::transaction(function () use ($invoice, $enabled) {
                $invoice->auto_bill_enabled = $enabled;
                $invoice->saveQuietly();

                Invoice::withTrashed()
                        ->where('company_id', $invoice->company_id)
                        ->where('client_id', $invoice->client_id)
                        ->where('recurring_id', $invoice->id)
                        ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                        ->where('is_deleted', 0)
                        ->where('balance', '>', 0)
                        ->update(['auto_bill_enabled' => $enabled]);
            });
        }
    }

    public function render()
    {
        return render($this->show_radios
            ? 'components.livewire.recurring-invoices-autobilling-radios'
            : 'components.livewire.recurring-invoices-switch-autobilling');
    }
}
