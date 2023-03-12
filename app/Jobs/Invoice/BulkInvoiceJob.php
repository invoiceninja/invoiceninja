<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Invoice;

use App\Jobs\Entity\EmailEntity;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BulkInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    public $reminder_template;

    public function __construct(Invoice $invoice, string $reminder_template)
    {
        $this->invoice = $invoice;
        $this->reminder_template = $reminder_template;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {   //only the reminder should mark the reminder sent field
        // $this->invoice->service()->touchReminder($this->reminder_template)->markSent()->save();
        $this->invoice->service()->markSent()->save();

        $this->invoice->invitations->load('contact.client.country', 'invoice.client.country', 'invoice.company')->each(function ($invitation) {
            EmailEntity::dispatch($invitation, $this->invoice->company, $this->reminder_template)->delay(now()->addSeconds(5));
        });

        if ($this->invoice->invitations->count() >= 1) {
            $this->invoice->entityEmailEvent($this->invoice->invitations->first(), 'invoice', $this->reminder_template);
        }
    }
}
