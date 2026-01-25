<?php

namespace Tests\Feature\Design;

use Tests\TestCase;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Services\Pdf\PdfMock;
use App\Utils\Traits\MakesHash;
use App\Services\Pdf\PdfService;
use App\DataMapper\CompanySettings;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use Tests\Feature\Design\InvoiceDesignRenderer;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DesignParserTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    public function test_design_parser()
    {
        
        $designjson = file_get_contents(base_path('tests/Feature/Design/stubs/test_design_1.json'));
        $design = json_decode($designjson, true);

        $renderer = new InvoiceDesignRenderer();
        $html = $renderer->render($design['blocks']);
        $this->assertNotNull($html);
        file_put_contents(base_path('tests/Feature/Design/stubs/test_design_1.html'), $html);


        $design = [
            'body' => $html,
            'includes' => '',
            'product' => '',
            'task' => '',
            'footer' => '',
            'header' => '',
        ];

        $data = [
            'name' => $this->faker->firstName(),
            'design' => $design,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/designs', $data);


        $arr = $response->json();
        $design_id = $arr['data']['id'];

        // $mock = new PdfMock([
        //     'entity_type' => 'invoice',
        //     'settings_type' => 'company',
        //     'design' => ['includes' => $html, 'header' => '', 'body' => '', 'footer' => ''],
        //     'settings' => CompanySettings::defaults(),
        // ], $company);

        // $mock->build();
        // $html = $mock->getHtml();
        // $this->assertNotNull($html);
        


        $item = InvoiceItemFactory::create();
        $item->quantity = 1.75;
        $item->cost = 49.58;
        $item->product_key = 'test_product';
        $item->notes = 'Lorem Ipsum is simply dummy text of the printing and typesetting industry. Lorem Ipsum has been the industry standard dummy text ever since the 1500s, when an unknown printer took a galley of type and scrambled it to make a type specimen book.';
        $item->tax_name1 = 'mwst';
        $item->tax_rate1 = 19;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;
        $line_items[] = $item;
        $line_items[] = $item;
        $line_items[] = $item;
        $line_items[] = $item;
        $line_items[] = $item;
        $line_items[] = $item;


        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
            'is_amount_discount' => true,
            'design_id' => $this->decodePrimaryKey($design_id),
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();
        $ii = $ii->service()->createInvitations()->markSent()->save();


        $ps = new PdfService($ii->invitations()->first(), 'product', [
            'client' => $this->client ?? false,
            'vendor' => false,
            "invoices" => [$ii],
        ]);

        $html = $ps->boot()->getHtml();

        file_put_contents(base_path('tests/Feature/Design/stubs/test_design_1_mock.html'), $html);
    }
}