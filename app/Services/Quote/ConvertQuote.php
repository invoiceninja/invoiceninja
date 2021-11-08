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
use App\Factory\InvoiceInvitationFactory;
use App\Models\Invoice;
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

        //create invitations here before the repo save()
        //we need to do this here otherwise the repo_save will create
        //invitations for ALL contacts
        $invites = $this->createConversionInvitations($invoice, $quote);
        $invoice_array = $invoice->toArray();
        $invoice_array['invitations'] = $invites;

        $invoice = $this->invoice_repo->save($invoice_array, $invoice);
        
        $invoice->fresh();

        $invoice->service()
                ->fillDefaults()
                ->save();

        $quote->invoice_id = $invoice->id;
        $quote->status_id = Quote::STATUS_CONVERTED;
        $quote->save();

        // maybe should return invoice here
        return $invoice;
    }

    /**
     * Only create the invitations that are defined on the quote.
     * 
     * @return Invoice $invoice
     */
    private function createConversionInvitations($invoice, $quote)
    {
        $invites = [];

        foreach($quote->invitations as $quote_invitation){

            $ii = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
            $ii->key = $this->createDbHash(config('database.default'));
            $ii->client_contact_id = $quote_invitation->client_contact_id;
        
            $invites[] = $ii;
        }

        return $invites;
    }

}
