<?php
namespace App\Services\Quote;

use App\Factory\CloneQuoteToInvoiceFactory;
use App\Quote;
use App\Repositories\InvoiceRepository;

class ConvertQuote
{
    private $client;
    private $invoice_repo;

    public function __construct($client)
    {
        $this->client = $client;
        $this->invoice_repo = new InvoiceRepository();
    }

    /**
     * @param $quote
     * @return mixed
     */
    public function run($quote)
    {
        $invoice = CloneQuoteToInvoiceFactory::create($quote, $quote->user_id, $quote->company_id);
        $invoice = $this->invoice_repo->save([], $invoice);

        $invoice->fresh();

            $invoice->service()
                ->markSent()
                ->createInvitations()
                ->save();

        $quote->invoice_id = $invoice->id;
        $quote->save();
        
        // maybe should return invoice here
        return $invoice;
    }
}
