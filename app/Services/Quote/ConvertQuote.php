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

namespace App\Services\Quote;

use App\Factory\CloneQuoteToInvoiceFactory;
use App\Models\Quote;
use App\Repositories\InvoiceRepository;
use App\Utils\Traits\MakesHash;

class ConvertQuote
{
    use MakesHash;

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
        $invoice = CloneQuoteToInvoiceFactory::create($quote, $quote->user_id);
        $invoice->design_id = $this->decodePrimaryKey($this->client->getSetting('invoice_design_id'));
        $invoice = $this->invoice_repo->save($invoice->toArray(), $invoice);
        
        $invoice->fresh();

        $invoice->service()
                ->fillDefaults()
                // ->markSent()
                // ->createInvitations()
                ->save();

        $quote->invoice_id = $invoice->id;
        $quote->status_id = Quote::STATUS_CONVERTED;
        $quote->save();

        // maybe should return invoice here
        return $invoice;
    }
}
