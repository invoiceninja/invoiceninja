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

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Tests\MockAccountData;
use App\Models\Subscription;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Repositories\InvoiceRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class InvoiceTaxReportTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    public function test_tax_report_meta()
    {
        $client = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Client',
            'address1' => '123 Main St',
            'city' => 'Anytown',
            'state' => 'CA',
            'country_id' => 840,
            'postal_code' => '90210',
        ]);

        $client->save();

        $i = Invoice::factory()->create([
                'client_id' => $client->id,
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'amount' => 0,
                'balance' => 0,
                'status_id' => 2,
                'total_taxes' => 1,
                'date' => now()->format('Y-m-d'),
                'terms' => 'nada',
                'discount' => 0,
                'tax_rate1' => 10,
                'tax_rate2' => 17.5,
                'tax_rate3' => 5,
                'tax_name1' => 'GST',
                'tax_name2' => 'VAT',
                'tax_name3' => 'CA Sales Tax',
                'uses_inclusive_taxes' => false,
                'line_items' => $this->buildLineItems(),
            ]);

        $i = $i->calc()->getInvoice();

        $this->assertNotNull($i);

        //test tax data object to see if we are using automated taxes.
        if (isset($i->tax_data->geoState)) {
            $nexus = $i->tax_data->geoState;
            $country_nexus = 'USA';
        } else {
            $nexus = strlen($i->client->state ?? '') > 0 ? $i->client->state : $i->company->settings->state;
            $country_nexus = strlen($i->client->state ?? '') > 0 ? $i->client->country->iso_3166_2 : $i->company->country()->iso_3166_2;
        }

    }
}
