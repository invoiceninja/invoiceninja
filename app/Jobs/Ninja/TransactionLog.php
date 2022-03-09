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

namespace App\Jobs\Ninja;

use App\DataMapper\Transactions\MarkPaidTransaction;
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
        TransactionEvent::INVOICE_MARK_PAID => MarkPaidTransaction::class,
        TransactionEvent::INVOICE_UPDATED => MarkPaidTransaction::class,
        TransactionEvent::INVOICE_DELETED => MarkPaidTransaction::class,
        TransactionEvent::INVOICE_PAYMENT_APPLIED => MarkPaidTransaction::class,
        TransactionEvent::INVOICE_CANCELLED => MarkPaidTransaction::class,
        TransactionEvent::INVOICE_FEE_APPLIED => MarkPaidTransaction::class,
        TransactionEvent::PAYMENT_MADE => MarkPaidTransaction::class,
        TransactionEvent::PAYMENT_APPLIED => MarkPaidTransaction::class,
        TransactionEvent::PAYMENT_REFUND => MarkPaidTransaction::class,
        TransactionEvent::PAYMENT_FAILED => MarkPaidTransaction::class,
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
        if(!Ninja::isHosted())
            return;

        $this->setTransformer();

        $this->payload =  $this->event_transformer->transform($this->data);

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
