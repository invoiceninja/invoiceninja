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

namespace App\Jobs\Ninja;

use App\DataMapper\Transactions\ClientStatusTransaction;
use App\DataMapper\Transactions\GatewayPaymentMadeTransaction;
use App\DataMapper\Transactions\InvoiceCancelledTransaction;
use App\DataMapper\Transactions\InvoiceDeletedTransaction;
use App\DataMapper\Transactions\InvoiceFeeAppliedTransaction;
use App\DataMapper\Transactions\InvoicePaymentTransaction;
use App\DataMapper\Transactions\InvoiceReversalTransaction;
use App\DataMapper\Transactions\InvoiceUpdatedTransaction;
use App\DataMapper\Transactions\MarkPaidTransaction;
use App\DataMapper\Transactions\PaymentAppliedTransaction;
use App\DataMapper\Transactions\PaymentDeletedTransaction;
use App\DataMapper\Transactions\PaymentFailedTransaction;
use App\DataMapper\Transactions\PaymentMadeTransaction;
use App\DataMapper\Transactions\PaymentRefundedTransaction;
use App\Libraries\MultiDB;
use App\Models\TransactionEvent;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TransactionLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $payload;

    private string $db;

    private array $data;

    private $event;

    private $event_transformer;

    private array $transformer_array = [
        TransactionEvent::INVOICE_MARK_PAID => MarkPaidTransaction::class, //
        TransactionEvent::INVOICE_UPDATED => InvoiceUpdatedTransaction::class, //
        TransactionEvent::INVOICE_DELETED => InvoiceDeletedTransaction::class, //
        TransactionEvent::INVOICE_PAYMENT_APPLIED => InvoicePaymentTransaction::class,
        TransactionEvent::INVOICE_CANCELLED => InvoiceCancelledTransaction::class,
        TransactionEvent::INVOICE_REVERSED => InvoiceReversalTransaction::class, //
        TransactionEvent::INVOICE_FEE_APPLIED => InvoiceFeeAppliedTransaction::class, //
        TransactionEvent::PAYMENT_MADE => PaymentMadeTransaction::class, //
        TransactionEvent::GATEWAY_PAYMENT_MADE => GatewayPaymentMadeTransaction::class, //
        TransactionEvent::PAYMENT_APPLIED => PaymentAppliedTransaction::class,
        TransactionEvent::PAYMENT_REFUND => PaymentRefundedTransaction::class, //
        TransactionEvent::PAYMENT_FAILED => PaymentFailedTransaction::class,
        TransactionEvent::PAYMENT_DELETED => PaymentDeletedTransaction::class, //
        TransactionEvent::CLIENT_STATUS => ClientStatusTransaction::class, //
    ];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($event, $data, $db)
    {
        $this->db = $db;
        $this->event = $event;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // if(!Ninja::isHosted())
        //     return;

        $this->setTransformer();

        $this->payload = $this->event_transformer->transform($this->data);

        $this->persist();
    }

    private function setTransformer()
    {
        $class = $this->transformer_array[$this->event];

        $this->event_transformer = new $class();

        return $this;
    }

    private function persist()
    {
        MultiDB::setDB($this->db);

        TransactionEvent::create($this->payload);
    }
}
