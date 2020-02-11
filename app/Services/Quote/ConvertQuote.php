<?php
namespace App\Services\Quote;

use App\Factory\CloneQuoteToInvoiceFactory;
use App\Quote;
use App\Repositories\InvoiceRepository;

class ConvertQuote
{
    private $client;
    private $invoice_repo;

    public function __construct($client, InvoiceRepository $invoice_repo)
    {
        $this->client = $client;
        $this->invoice_repo = $invoice_repo;
    }

    /**
     * @param $quote
     * @return mixed
     */
    public function __invoke($quote)
    {
        $invoice = CloneQuoteToInvoiceFactory::create($quote, $quote->user_id, $quote->company_id);
        $this->invoice_repo->save([], $invoice);

        // maybe should return invoice here
        return $quote;
    }
}
