<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Integration;

use App\Designs\Bold;
use App\Designs\Designer;
use App\Events\Client\ClientWasArchived;
use App\Events\Client\ClientWasCreated;
use App\Events\Client\ClientWasDeleted;
use App\Events\Client\ClientWasRestored;
use App\Events\Client\ClientWasUpdated;
use App\Events\Invoice\InvoiceWasArchived;
use App\Events\Invoice\InvoiceWasCreated;
use App\Events\Invoice\InvoiceWasDeleted;
use App\Events\Invoice\InvoiceWasRestored;
use App\Events\Invoice\InvoiceWasUpdated;
use App\Events\Payment\PaymentWasArchived;
use App\Events\Payment\PaymentWasCreated;
use App\Events\Payment\PaymentWasDeleted;
use App\Events\Payment\PaymentWasRestored;
use App\Events\Payment\PaymentWasUpdated;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use Faker\Factory;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class EventTest extends TestCase
{
    use MockAccountData;
    use MakesHash;

    public function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->makeTestData();
    }

    //@TODO paymentwasvoided
    //@TODO paymentwasrefunded

    public function testPaymentEvents()
    {

        $this->expectsEvents([
            PaymentWasCreated::class,
            PaymentWasUpdated::class,
            PaymentWasArchived::class,
            PaymentWasRestored::class,
            PaymentWasDeleted::class,
        ]);

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                'invoice_id' => $this->invoice->hashed_id,
                'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments?include=invoices', $data)
        ->assertStatus(200);

        $arr = $response->json();

        $data = [
            'transaction_reference' => 'testing'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/payments/' . $arr['data']['id'], $data)
        ->assertStatus(200);

        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/payments/bulk?action=delete', $data)
        ->assertStatus(200);
    }


    public function testInvoiceEvents()
    {

        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $this->expectsEvents([
            InvoiceWasCreated::class,
            InvoiceWasUpdated::class,
            InvoiceWasArchived::class,
            InvoiceWasRestored::class,
            InvoiceWasDeleted::class,
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $data)
        ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude2',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/invoices/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/bulk?action=delete', $data)
        ->assertStatus(200);

    }


    public function testClientEvents()
    {
        $this->expectsEvents([
            ClientWasCreated::class,
            ClientWasUpdated::class,
            ClientWasArchived::class,
            ClientWasRestored::class,
            ClientWasDeleted::class,
        ]);

        $data = [
            'name' => $this->faker->firstName,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data)
            ->assertStatus(200);


        $arr = $response->json();

        $data = [
            'name' => $this->faker->firstName,
            'id_number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/' . $arr['data']['id'], $data)
        ->assertStatus(200);


        $data = [
            'ids' => [$arr['data']['id']],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=archive', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=restore', $data)
        ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk?action=delete', $data)
        ->assertStatus(200);

    }

}
