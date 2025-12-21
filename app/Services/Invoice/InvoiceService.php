<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Models\Task;
use App\Utils\Ninja;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\CompanyGateway;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\Jobs\Entity\CreateRawPdf;
use App\Services\Invoice\LocationData;
use App\Jobs\EDocument\CreateEDocument;
use Illuminate\Support\Facades\Storage;
use App\Events\Invoice\InvoiceWasArchived;
use App\Jobs\Inventory\AdjustProductInventory;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Services\EDocument\Standards\Verifactu\SendToAeat;

class InvoiceService
{
    use MakesHash;

    public function __construct(public Invoice $invoice)
    {
    }

    /**
     * Marks as invoice as paid
     * and executes child sub functions.
     * @return $this InvoiceService object
     */
    public function markPaid(?string $reference = null)
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new MarkPaid($this->invoice, $reference))->run();

        return $this;
    }

    /**
     * applyPaymentAmount
     *
     * @param  float $amount
     * @param  ?string $reference
     * @return self
     */
    public function applyPaymentAmount($amount, ?string $reference = null): self
    {
        $this->invoice = (new ApplyPaymentAmount($this->invoice, $amount, $reference))->run();

        return $this;
    }

    /**
     * Applies the invoice number.
     * @return $this InvoiceService object
     */
    public function applyNumber()
    {
        $this->invoice = (new ApplyNumber($this->invoice->client, $this->invoice))->run();

        return $this;
    }

    /**
     * Sets the exchange rate on the invoice if the client currency
     * is different to the company currency.
     */
    public function setExchangeRate($force = false)
    {
        if ($this->invoice->exchange_rate != 1 || $force) {
            return $this;
        }

        $client_currency = $this->invoice->client->getSetting('currency_id');
        $company_currency = $this->invoice->company->settings->currency_id;

        if ($company_currency != $client_currency) {
            $exchange_rate = new CurrencyApi();

            $this->invoice->exchange_rate = 1 / $exchange_rate->exchangeRate($client_currency, $company_currency, now());
        }

        return $this;
    }

    /**
     * Applies the recurring invoice number.
     * @return $this InvoiceService object
     */
    public function applyRecurringNumber()
    {
        $this->invoice = (new ApplyRecurringNumber($this->invoice->client, $this->invoice))->run();

        return $this;
    }

    /**
     * Apply a payment amount to an invoice.
     *
     * *** does not create a paymentable ****
     * @param  Payment $payment        The Payment
     * @param  float   $payment_amount The Payment amount
     * @return InvoiceService          Parent class object
     */
    public function applyPayment(Payment $payment, float $payment_amount)
    {
        $this->invoice = $this->markSent()->save();

        $this->invoice = (new ApplyPayment($this->invoice, $payment, $payment_amount))->run();

        return $this;
    }

    public function addGatewayFee(CompanyGateway $company_gateway, $gateway_type_id, float $amount, string $payment_hash_string)
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new AddGatewayFee($company_gateway, $gateway_type_id, $this->invoice, $amount, $payment_hash_string))->run();

        return $this;
    }

    /**
     * Update an invoice balance.
     *
     * @param  float $balance_adjustment The amount to adjust the invoice by
     * a negative amount will REDUCE the invoice balance, a positive amount will INCREASE
     * the invoice balance
     *
     * @return InvoiceService                     Parent class object
     */
    public function updateBalance($balance_adjustment, bool $is_draft = false)
    {
        if ((bool) $this->invoice->is_deleted !== false) {
            nlog($this->invoice->number.' is deleted returning');

            return $this;
        }

        $this->invoice->balance += $balance_adjustment;

        if (round($this->invoice->balance, 2) == 0 && ! $is_draft) {
            $this->invoice->status_id = Invoice::STATUS_PAID;
        }

        if ((int) $this->invoice->balance == 0) {
            $this->invoice->next_send_date = null;
        }

        return $this;
    }

    public function updatePaidToDate($adjustment)
    {
        $this->invoice->paid_to_date += $adjustment;

        return $this;
    }

    public function createInvitations()
    {
        $this->invoice = (new CreateInvitations($this->invoice))->run();

        return $this;
    }

    public function markSent($fire_event = false)
    {

        $this->invoice->loadMissing(['client' => function ($q) {
            $q->without('documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }]);

        $this->invoice = (new MarkSent($this->invoice->client, $this->invoice))->run($fire_event);

        $this->setExchangeRate();

        return $this;
    }

    public function getInvoicePdf($contact = null)
    {
        return (new GetInvoicePdf($this->invoice, $contact))->run();
    }

    public function getRawInvoicePdf($contact = null)
    {
        $invitation = $contact ? $this->invoice->invitations()->where('contact_id', $contact->id)->first() : $this->invoice->invitations()->first();

        return (new CreateRawPdf($invitation))->handle();
    }

    public function getInvoiceDeliveryNote(Invoice $invoice, ?\App\Models\ClientContact $contact = null)
    {
        return (new GenerateDeliveryNote($invoice, $contact))->run();
    }

    public function getEInvoice($contact = null)
    {
        return (new CreateEDocument($this->invoice))->handle();
    }

    public function getEDocument($contact = null)
    {
        return $this->getEInvoice($contact);
    }

    public function sendEmail($contact = null, $email_type = 'invoice')
    {
        $send_email = new SendEmail($this->invoice, $email_type, $contact);

        return $send_email->run();
    }

    public function handleReversal()
    {
        $this->invoice = (new HandleReversal($this->invoice))->run();

        return $this;
    }

    public function handleCancellation(?string $reason = null)
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new HandleCancellation($this->invoice, $reason))->run();

        return $this;
    }

    public function markDeleted()
    {

        $this->invoice = (new MarkInvoiceDeleted($this->invoice))->run();

        if ($this->invoice->company->verifactuEnabled() && $this->invoice->backup->guid != '') {
            $this->cancelVerifactu();
        }

        return $this;
    }

    public function handleRestore()
    {
        $this->invoice = (new HandleRestore($this->invoice))->run();

        return $this;
    }

    public function reverseCancellation()
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new HandleCancellation($this->invoice))->reverse();

        return $this;
    }

    public function triggeredActions($request)
    {
        $this->invoice = (new TriggeredActions($this->invoice->load('invitations'), $request))->run();

        return $this;
    }

    public function autoBill()
    {
        (new AutoBillInvoice($this->invoice, $this->invoice->company->db))->run();

        return $this;
    }

    public function markViewed()
    {
        $this->invoice->last_viewed = Carbon::now()->format('Y-m-d H:i');

        return $this;
    }

    /* One liners */
    public function setDueDate()
    {
        if ($this->invoice->due_date != '' || $this->invoice->client->getSetting('payment_terms') == '') {
            return $this;
        }

        //12-10-2022
        if ($this->invoice->partial > 0 && !$this->invoice->partial_due_date) {
            $this->invoice->partial_due_date = Carbon::parse($this->invoice->date)->addDays((int)$this->invoice->client->getSetting('payment_terms'));
        } else {
            $this->invoice->due_date = Carbon::parse($this->invoice->date)->addDays((int)$this->invoice->client->getSetting('payment_terms'));
        }

        return $this;
    }

    /**
     * Reset the reminders if only the
     * partial has been paid.
     *
     * We can _ONLY_ call this _IF_ a partial
     * amount has been paid, otherwise we end up wiping
     * all reminders regardless
     *
     * @return self
     */
    public function checkReminderStatus(): self
    {

        if ($this->invoice->partial == 0) {
            $this->invoice->partial_due_date = null;
        }

        if ($this->invoice->partial == 0 && $this->invoice->balance > 0) {
            $this->invoice->reminder1_sent = null;
            $this->invoice->reminder2_sent = null;
            $this->invoice->reminder3_sent = null;

            $this->setReminder();
        }

        return $this;
    }

    public function setReminder($settings = null)
    {
        $this->invoice = (new UpdateReminder($this->invoice, $settings))->run();

        return $this;
    }

    public function setStatus($status)
    {
        $this->invoice->status_id = $status;

        return $this;
    }

    public function setCalculatedStatus()
    {
        if (round($this->invoice->balance, 2) == 0) {
            $this->setStatus(Invoice::STATUS_PAID);
        } elseif ($this->invoice->balance > 0 && $this->invoice->balance < $this->invoice->amount) {
            $this->setStatus(Invoice::STATUS_PARTIAL);
        } elseif ($this->invoice->balance < 0 || $this->invoice->balance > 0) {
            $this->invoice->status_id = Invoice::STATUS_SENT;
        }

        return $this;
    }

    public function updateStatus()
    {
        if ($this->invoice->status_id == Invoice::STATUS_DRAFT) {
            return $this;
        }

        if (round($this->invoice->balance, 2) == 0) {
            $this->invoice->status_id = Invoice::STATUS_PAID;
        } elseif ($this->invoice->balance > 0 && $this->invoice->balance < $this->invoice->amount) {
            $this->invoice->status_id = Invoice::STATUS_PARTIAL;
        } elseif ($this->invoice->balance < 0 || $this->invoice->balance > 0) {
            $this->invoice->status_id = Invoice::STATUS_SENT;
        }

        return $this;
    }

    public function toggleFeesPaid(?string $payment_hash_string = null)
    {
        if ($payment_hash_string) {

            $this->invoice->line_items = collect($this->invoice->line_items)
                                                ->map(function ($item) use ($payment_hash_string) {
                                                    if ($item->type_id == '3' && (($item->unit_code ?? '') == $payment_hash_string)) {
                                                        $item->type_id = '4';
                                                    }

                                                    return $item;
                                                })->toArray();

            $this->deleteEInvoice();

            return $this;

        }

        $this->invoice->line_items = collect($this->invoice->line_items)
                                     ->map(function ($item) {
                                         if ($item->type_id == '3') {
                                             $item->type_id = '4';
                                         }

                                         return $item;
                                     })->toArray();

        $this->deleteEInvoice();

        return $this;
    }

    public function deletePdf()
    {
        $this->invoice->load('invitations');

        //30-06-2023
        $this->invoice->invitations->each(function ($invitation) {
            try {
                // if (Storage::disk(config('filesystems.default'))->exists($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf')) {
                Storage::disk(config('filesystems.default'))->delete($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf');
                // }

                // if (Ninja::isHosted() && Storage::disk('public')->exists($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf')) {
                if (Ninja::isHosted()) {
                    Storage::disk('public')->delete($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf');
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        });

        return $this;
    }

    public function deleteEInvoice()
    {
        $this->invoice->load('invitations');

        $this->invoice->invitations->each(function ($invitation) {
            try {
                Storage::disk(config('filesystems.default'))->delete($this->invoice->client->e_document_filepath($invitation).$this->invoice->getFileName("xml"));

                if (Ninja::isHosted()) {
                    Storage::disk('public')->delete($this->invoice->client->e_document_filepath($invitation).$this->invoice->getFileName("xml"));
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        });

        return $this;
    }

    public function removeUnpaidGatewayFees()
    {
        $balance = $this->invoice->balance;

        //return early if type three does not exist.
        if ($this->invoice->status_id == Invoice::STATUS_PAID || ! collect($this->invoice->line_items)->contains('type_id', 3)) {
            return $this;
        }

        $pre_count = count((array)$this->invoice->line_items);

        $items = collect((array)$this->invoice->line_items)
                    ->filter(function ($item) {
                        return $item->type_id != '3';
                    })->toArray();

        $this->invoice->line_items = array_values($items);

        $this->invoice = $this->invoice->calc()->getInvoice();

        /* 24-03-2022 */
        $new_balance = $this->invoice->balance;

        $post_count = count($this->invoice->line_items);
        nlog("pre count = {$pre_count} post count = {$post_count}");

        if ((int) $pre_count != (int) $post_count) {
            $adjustment = $balance - $new_balance;

            $this->invoice
            ->ledger()
            ->updateInvoiceBalance($adjustment * -1, 'Adjustment for removing gateway fee');

            $this->invoice->client->service()->updateBalance($adjustment * -1);

        }

        return $this;
    }

    /*Set partial value and due date to null*/
    public function clearPartial()
    {
        $this->invoice->partial = null;
        $this->invoice->partial_due_date = null;

        return $this;
    }

    /*Update the partial amount of a invoice*/
    public function updatePartial($amount)
    {
        $this->invoice->partial += $amount;

        return $this;
    }

    /*When a reminder is sent we want to touch the dates they were sent*/
    public function touchReminder(string $reminder_template)
    {
        nrlog(now()->format('Y-m-d h:i:s') . " INV #{$this->invoice->number} : Touching Reminder => {$reminder_template}");
        switch ($reminder_template) {
            case 'reminder1':
                $this->invoice->reminder1_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            case 'reminder2':
                $this->invoice->reminder2_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            case 'reminder3':
                $this->invoice->reminder3_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            case 'endless_reminder':
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            default:
                $this->invoice->reminder1_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
        }

        return $this;
    }

    public function linkEntities()
    {
        //set all task.invoice_ids = 0
        $this->invoice->tasks()->update(['invoice_id' => null]);

        //set all tasks.invoice_ids = x with the current  line_items
        $tasks = collect($this->invoice->line_items)->map(function ($item) {
            if (isset($item->task_id)) {
                $item->task_id = $this->decodePrimaryKey($item->task_id);
            }

            if (isset($item->expense_id)) {
                $item->expense_id = $this->decodePrimaryKey($item->expense_id);
            }

            return $item;
        });

        Task::query()->withTrashed()->whereIn('id', $tasks->pluck('task_id'))->update(['invoice_id' => $this->invoice->id]);
        Expense::query()->withTrashed()->whereIn('id', $tasks->pluck('expense_id'))->update(['invoice_id' => $this->invoice->id]);

        return $this;
    }

    public function unlockDocuments(): self
    {

        //2025-02-20 ** Feature to allow documents to be visible / attachable after payment **
        if ($this->invoice->status_id == Invoice::STATUS_PAID && $this->invoice->client->getSetting('unlock_invoice_documents_after_payment')) {
            $this->invoice->documents()->update(['is_public' => true]);
        }

        return $this;

    }

    public function fillDefaults(bool $is_recurring = false)
    {
        $this->invoice->load('client.company');

        $settings = $this->invoice->client->getMergedSettings();

        if (! $this->invoice->design_id) {
            $this->invoice->design_id = intval($this->decodePrimaryKey($settings->invoice_design_id));
        }

        if (! isset($this->invoice->footer) || empty($this->invoice->footer)) {
            $this->invoice->footer = $settings->invoice_footer;
        }

        if (! isset($this->invoice->terms) || empty($this->invoice->terms)) {
            $this->invoice->terms = $settings->invoice_terms;
        }

        if (! isset($this->invoice->public_notes) || empty($this->invoice->public_notes)) {
            $this->invoice->public_notes = $this->invoice->client->public_notes;
        }

        /* If client currency differs from the company default currency, then insert the client exchange rate on the model.*/
        if (! isset($this->invoice->exchange_rate) && $this->invoice->client->currency()->id != (int) $this->invoice->company->settings->currency_id) {
            $this->invoice->exchange_rate = $this->invoice->client->setExchangeRate();
        }

        if (!$is_recurring && $this->invoice->client->getSetting('auto_bill_standard_invoices')) {
            $this->invoice->auto_bill_enabled = true;
        }

        if ($settings->counter_number_applied == 'when_saved') {
            $this->invoice->service()->applyNumber()->save();
        }

        return $this;
    }

    public function location(bool $set_countries = true): array
    {
        return (new LocationData($this->invoice))->run($set_countries);
    }
    
    public function workFlow()
    {
        if ($this->invoice->status_id == Invoice::STATUS_PAID && $this->invoice->client->getSetting('auto_archive_invoice')) {
            /* Throws: Payment amount xxx does not match invoice totals. */

            if ($this->invoice->trashed()) {
                return $this;
            }

            $this->invoice->delete();

            event(new InvoiceWasArchived($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }

        if ($this->invoice->status_id == Invoice::STATUS_CANCELLED && $this->invoice->client->getSetting('auto_archive_invoice_cancelled')) {
            /* Throws: Payment amount xxx does not match invoice totals. */

            if ($this->invoice->trashed()) {
                return $this;
            }

            $this->invoice->delete();

            event(new InvoiceWasArchived($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }

        return $this;
    }

    public function adjustInventory($old_invoice = [])
    {
        if ($this->invoice->company->track_inventory) {
            (new AdjustProductInventory($this->invoice->company, $this->invoice, $old_invoice))->handle();
        }

        return $this;
    }

    public function setPaymentLink(string $subscription_id): self
    {

        $sub_id = $this->decodePrimaryKey($subscription_id);

        if (Subscription::withTrashed()->where('id', $sub_id)->where('company_id', $this->invoice->company_id)->exists()) {
            $this->invoice->subscription_id = $sub_id;
        }

        return $this;

    }

    /**
     * sendVerifactu
     *
     * @return self
     */
    public function sendVerifactu(): self
    {
        SendToAeat::dispatch($this->invoice->id, $this->invoice->company, 'create');

        return $this;
    }

    /**
     * cancelVerifactu
     *
     * @return self
     */
    public function cancelVerifactu(): self
    {
        SendToAeat::dispatch($this->invoice->id, $this->invoice->company, 'cancel');

        return $this;
    }

    /**
     * Handles all requirements for verifactu saves
     *
     * @param  array $invoice_array
     * @param  bool $new_model
     * @return self
     */
    public function modifyVerifactuWorkflow(array $invoice_array, bool $new_model): self
    {

        /**
         * I need to perform some checks here to ensure that this invoice MUST be sent via AEAT,
         * in some cases we DO NOT send into AEAT, these are:
         *
         * - Sales to foreign consumers
         *
         */
        /** New Invoice - F1 Type */
                
        if ($new_model && $this->invoice->amount >= 0) {
            $this->invoice->backup->document_type = 'F1';
            $this->invoice->backup->adjustable_amount = (new \App\Services\EDocument\Standards\Verifactu($this->invoice))->run()->registro_alta->calc->getTotal();
            $this->invoice->backup->parent_invoice_number = $this->invoice->number;
            $this->invoice->saveQuietly();
        } elseif (isset($invoice_array['modified_invoice_id'])) {
            $document_type = 'R2'; // <- Default to R2 type

            /** Was it a partial or FULL rectification? */
            $modified_invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($invoice_array['modified_invoice_id']));

            if (!$modified_invoice) {
                throw new \Exception('Modified invoice not found');
            }

            if (\App\Utils\BcMath::lessThan(abs($this->invoice->amount), $modified_invoice->amount)) {
                $document_type = 'R1'; // <- If The adjustment amount is less than the original invoice amount, we are doing a partial rectification
            }

            $modified_invoice->backup->child_invoice_ids->push($this->invoice->hashed_id);

            if (isset($invoice_array['reason'])) {
                $this->invoice->backup->notes = $invoice_array['reason'];
            }

            $modified_invoice->save();

            $this->markSent();
            //Update the client balance by the delta amount from the previous invoice to this one.
            $this->invoice->backup->parent_invoice_id = $modified_invoice->hashed_id;
            $this->invoice->backup->document_type = $document_type;
            // $this->invoice->backup->adjustable_amount = $this->invoice->amount; // <- Amount available to be adjusted

            $this->invoice->backup->adjustable_amount = (new \App\Services\EDocument\Standards\Verifactu($this->invoice))->run()->registro_alta->calc->getTotal();
            $this->invoice->backup->parent_invoice_number = $modified_invoice->number;
            $this->invoice->saveQuietly();

            $this->invoice->client->service()->updateBalance(round($this->invoice->amount, 2));
            $this->sendVerifactu();

            $child_invoice_amounts = Invoice::withTrashed()
                                        ->whereIn('id', $this->transformKeys($modified_invoice->backup->child_invoice_ids->toArray()))
                                        ->get()
                                        ->sum('backup.adjustable_amount');

            //@todo verifactu - this won't be accurate as the invoice->amount will be the ex IPRF amount. modified->amount may not have the correct totals due to IRPF.
            if (\App\Utils\BcMath::greaterThan(abs($child_invoice_amounts), $modified_invoice->amount)) {
                $modified_invoice->status_id = Invoice::STATUS_CANCELLED;
                $modified_invoice->saveQuietly();
            }
        }

        return $this;
    }

    /**
     * Saves the invoice.
     * @return Invoice object
     */
    public function save(): ?Invoice
    {
        $this->invoice->saveQuietly();

        return $this->invoice->fresh();
    }
}
