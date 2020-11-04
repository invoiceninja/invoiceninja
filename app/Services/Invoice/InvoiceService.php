<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Jobs\Entity\CreateEntityPdf;
use App\Jobs\Util\UnlinkFile;
use App\Models\CompanyGateway;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Task;
use App\Services\Client\ClientService;
use App\Services\Invoice\ApplyNumber;
use App\Services\Invoice\ApplyPayment;
use App\Services\Invoice\ApplyRecurringNumber;
use App\Services\Invoice\AutoBillInvoice;
use App\Services\Invoice\CreateInvitations;
use App\Services\Invoice\GetInvoicePdf;
use App\Services\Invoice\HandleCancellation;
use App\Services\Invoice\HandleReversal;
use App\Services\Invoice\MarkInvoiceDeleted;
use App\Services\Invoice\MarkInvoicePaid;
use App\Services\Invoice\MarkSent;
use App\Services\Invoice\TriggeredActions;
use App\Services\Invoice\UpdateBalance;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;

class InvoiceService
{
    use MakesHash;

    private $invoice;

    protected $client_service;

    public function __construct($invoice)
    {
        $this->invoice = $invoice;

        $this->client_service = new ClientService($invoice->client);
    }

    /**
     * Marks as invoice as paid
     * and executes child sub functions.
     * @return $this InvoiceService object
     */
    public function markPaid()
    {
        $this->invoice = (new MarkPaid($this->client_service, $this->invoice))->run();

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
     * @param  Payment $payment        The Payment
     * @param  float   $payment_amount The Payment amount
     * @return InvoiceService          Parent class object
     */
    public function applyPayment(Payment $payment, float $payment_amount)
    {
        $this->invoice = (new ApplyPayment($this->invoice, $payment, $payment_amount))->run();

        return $this;
    }

    public function addGatewayFee(CompanyGateway $company_gateway, $gateway_type_id, float $amount)
    {
        $this->invoice = (new AddGatewayFee($company_gateway, $gateway_type_id, $this->invoice, $amount))->run();

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
    public function updateBalance($balance_adjustment)
    {
        $this->invoice = (new UpdateBalance($this->invoice, $balance_adjustment))->run();

            if((int)$this->invoice->balance == 0)
                $this->invoice->next_send_date = null;
            
        return $this;
    }

    public function createInvitations()
    {
        $this->invoice = (new CreateInvitations($this->invoice))->run();

        return $this;
    }

    public function markSent()
    {
        $this->invoice = (new MarkSent($this->invoice->client, $this->invoice))->run();

        return $this;
    }

    public function getInvoicePdf($contact = null)
    {
        return (new GetInvoicePdf($this->invoice, $contact))->run();
    }

    public function getInvoiceDeliveryNote($contact = null)
    {
        //stubbed
    }

    public function sendEmail($contact = null)
    {
        $send_email = new SendEmail($this->invoice, null, $contact);

        return $send_email->run();
    }

    public function handleReversal()
    {
        $this->invoice = (new HandleReversal($this->invoice))->run();

        return $this;
    }

    public function handleCancellation()
    {
        $this->invoice = (new HandleCancellation($this->invoice))->run();

        return $this;
    }

    public function markDeleted()
    {
        $this->invoice = (new MarkInvoiceDeleted($this->invoice))->run();

        return $this;
    }

    public function reverseCancellation()
    {
        $this->invoice = (new HandleCancellation($this->invoice))->reverse();

        return $this;
    }

    public function triggeredActions($request)
    {
        $this->invoice = (new TriggeredActions($this->invoice, $request))->run();

        return $this;
    }

    public function autoBill()
    {
        $this->invoice = (new AutoBillInvoice($this->invoice))->run();

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

        $this->invoice->due_date = Carbon::parse($this->invoice->date)->addDays($this->invoice->client->getSetting('payment_terms'));

        return $this;
    }

    public function setStatus($status)
    {
        $this->invoice->status_id = $status;

        return $this;
    }

    public function setCalculatedStatus()
    {
        if((int)$this->invoice->balance == 0)
            $this->setStatus(Invoice::STATUS_PAID);
        elseif($this->invoice->balance > 0 && $this->invoice->balance < $this->invoice->amount)
            $this->setStatus(Invoice::STATUS_PARTIAL);

        return $this;
    }

    public function updateStatus()
    {

        if((int)$this->invoice->balance == 0)
            $this->setStatus(Invoice::STATUS_PAID);

        if($this->invoice->balance > 0 && $this->invoice->balance < $this->invoice->amount)
            $this->setStatus(Invoice::STATUS_PARTIAL);

        return $this;
    }

    public function toggleFeesPaid()
    {
        $this->invoice->line_items = collect($this->invoice->line_items)
                                     ->map(function ($item) {
                                         if ($item->type_id == '3') {
                                             $item->type_id = '4';
                                         }

                                         return $item;
                                     })->toArray();

        //$this->invoice = $this->invoice->calc()->getInvoice();

        $this->deletePdf();

        return $this;
    }

    public function deletePdf()
    {
        UnlinkFile::dispatchNow(config('filesystems.default'),$this->invoice->client->invoice_filepath() . $this->invoice->number.'.pdf');

        return $this;
    }

    public function removeUnpaidGatewayFees()
    {
        $this->invoice->line_items = collect($this->invoice->line_items)
                                     ->reject(function ($item) {
                                         return $item->type_id == '3';
                                     })->toArray();

        $this->invoice = $this->invoice->calc()->getInvoice();

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

    /**
     * Sometimes we need to refresh the
     * PDF when it is updated etc.
     * @return InvoiceService
     */
    public function touchPdf()
    {
        $this->invoice->invitations->each(function ($invitation){
            CreateEntityPdf::dispatch($invitation);
        });

        return $this;
    }

    /*When a reminder is sent we want to touch the dates they were sent*/
    public function touchReminder(string $reminder_template)
    {
        switch ($reminder_template) {
            case 'reminder1':
                $this->invoice->reminder1_sent = now()->format('Y-m-d');
                $this->invoice->reminder_last_sent = now()->format('Y-m-d');
                break;
            case 'reminder2':
                $this->invoice->reminder2_sent = now()->format('Y-m-d');
                $this->invoice->reminder_last_sent = now()->format('Y-m-d');
                break;
            case 'reminder3':
                $this->invoice->reminder3_sent = now()->format('Y-m-d');
                $this->invoice->reminder_last_sent = now()->format('Y-m-d');
                break;

            default:
                // code...
                break;
        }

        return $this;
    }

    public function linkEntities()
    {
        //set all task.invoice_ids = 0
        $this->invoice->tasks()->update(['invoice_id' => null]);

        //set all tasks.invoice_ids = x with the current  line_items
        $tasks = collect($this->invoice->line_items)->map(function ($item){

            if(isset($item->task_id))
                $item->task_id = $this->decodePrimaryKey($item->task_id);

            if(isset($item->expense_id))
                $item->expense_id = $this->decodePrimaryKey($item->expense_id);

            return $item;

        });

        Task::whereIn('id',$tasks->pluck('task_id'))->update(['invoice_id' => $this->invoice->id]);
        Expense::whereIn('id',$tasks->pluck('expense_id'))->update(['invoice_id' => $this->invoice->id]);

        return $this;
    }


    public function fillDefaults()
    {
        
        return $this;        
    }
    
    /**
     * Saves the invoice.
     * @return Invoice object
     */
    public function save() :?Invoice
    {
        $this->invoice->save();

        return $this->invoice;
    }
}
