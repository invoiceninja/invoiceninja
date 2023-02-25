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

namespace App\Services\Pdf;

use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;

class PdfMock
{

    public function __construct()
    {

    }

    public function build()
    {

        $mock = Invoice::factory()->make();    
        $mock->client = Client::factory()->make();
        $mock->tax_map = $this->getTaxMap();
        $mock->total_tax_map = $this->getTotalTaxMap();
        $mock->invitation = InvoiceInvitation::factory()->make();
        $mock->invitation->company = Company::factory()->make();
        $mock->invitation->company->account = Account::factory()->make();

        return $mock;

    }

    private function getTaxMap()
    {

        return collect( [['name' => 'GST', 'total' => 10]]);

    }

    private function getTotalTaxMap()
    {
        return [['name' => 'GST', 'total' => 10]];
    }
}
