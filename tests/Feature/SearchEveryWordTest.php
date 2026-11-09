<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * The ?filter= search matches every word, in any order, each in any
 * searchable column (QueryFilters::whereEveryWord).
 */
class SearchEveryWordTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();
        Model::reguard();

        $this->makeTestData();

        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function headers(): array
    {
        return [
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ];
    }

    /** @return array<int, string> hashed ids returned by the search */
    private function search(string $entity, string $filter): array
    {
        $response = $this->withHeaders($this->headers())
            ->getJson("/api/v1/{$entity}?" . http_build_query(['filter' => $filter, 'per_page' => 100]));

        $response->assertStatus(200);

        return array_column($response->json('data'), 'id');
    }

    private function makeProduct(string $productKey, string $notes): Product
    {
        return Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'product_key' => $productKey,
            'notes' => $notes,
            'custom_value1' => null,
            'custom_value2' => null,
            'custom_value3' => null,
            'custom_value4' => null,
        ]);
    }

    private function makeClient(string $name, string $number): Client
    {
        return Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => $name,
            'number' => $number,
            'id_number' => '',
            'custom_value1' => null,
            'custom_value2' => null,
            'custom_value3' => null,
            'custom_value4' => null,
        ]);
    }

    public function testProductWordsMatchInAnyOrder(): void
    {
        $product = $this->makeProduct('TCGJ-SCREEN', 'Samsung Galaxy S22 5G OLED 120Hz Screen (TCGJ)');
        $id = $product->hashed_id;

        $this->assertContains($id, $this->search('products', 's22 120hz'));
        $this->assertContains($id, $this->search('products', '120hz s22'));
        $this->assertNotContains($id, $this->search('products', 's22 iphone'));
    }

    public function testProductWordsMatchAcrossColumns(): void
    {
        $product = $this->makeProduct('S22-SCR', 'OLED 120Hz');
        $id = $product->hashed_id;

        // "s22" is only in product_key, "oled" only in notes.
        $this->assertContains($id, $this->search('products', 's22 oled'));
        $this->assertNotContains($id, $this->search('products', 's22 lcd'));
    }

    public function testInvoiceMatchesClientNameAndNumber(): void
    {
        $client = $this->makeClient('Zephyrine Holdings', 'ZEPH-1');

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => 'SEW-4471',
            'po_number' => '',
            'amount' => 10,
            'balance' => 10,
            'custom_value1' => null,
            'custom_value2' => null,
            'custom_value3' => null,
            'custom_value4' => null,
            'line_items' => [],
        ]);
        $id = $invoice->hashed_id;

        // "4471" is in the invoice number, "zephyrine" in the client's name.
        $this->assertContains($id, $this->search('invoices', '4471 zephyrine'));
        $this->assertNotContains($id, $this->search('invoices', '4471 quillon'));
    }

    public function testExpenseMatchesVendorNameAndNotes(): void
    {
        $vendor = Vendor::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'name' => 'Brightwater Supplies',
        ]);

        $expense = Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'client_id' => null,
            'category_id' => null,
            'number' => 'EXP-SEW-1',
            'amount' => 10,
            'public_notes' => 'Quarterly toner refill',
            'custom_value1' => null,
            'custom_value2' => null,
            'custom_value3' => null,
            'custom_value4' => null,
        ]);
        $id = $expense->hashed_id;

        // "toner" is in the notes, "brightwater" in the vendor's name.
        $this->assertContains($id, $this->search('expenses', 'toner brightwater'));
        $this->assertNotContains($id, $this->search('expenses', 'toner paper'));
    }

    public function testZeroIsASearchWord(): void
    {
        $withZero = $this->makeClient('Qwvx 0 Traders', 'QWVX-A');
        $withoutZero = $this->makeClient('Qwvx Traders', 'QWVX-B');

        $ids = $this->search('clients', 'qwvx 0');

        // "0" is a word to match, not an empty one to drop.
        $this->assertContains($withZero->hashed_id, $ids);
        $this->assertNotContains($withoutZero->hashed_id, $ids);
    }

    public function testUserMatchesFirstAndLastName(): void
    {
        $this->user->forceFill(['first_name' => 'Zorbak', 'last_name' => 'Quellish'])->save();

        // First and last name are separate columns: the whole phrase is in
        // neither, so a one-phrase search found nobody.
        $ids = $this->search('users', 'zorbak quellish');
        $this->assertContains($this->user->hashed_id, $ids);
        $this->assertNotContains($this->user->hashed_id, $this->search('users', 'zorbak nobody'));
    }

    public function testEachWordStaysInsideItsOwnGroup(): void
    {
        $mine = $this->makeClient('Ostravel Mine', 'OSTR-1');
        $theirs = $this->makeClient('Ostravel Theirs', 'OSTR-2');

        $make = fn (Client $client, string $number) => Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'number' => $number,
            'po_number' => '',
            'amount' => 10,
            'balance' => 10,
            'line_items' => [],
        ]);
        $ours = $make($mine, 'OSTR-9001');
        $other = $make($theirs, 'OSTR-9002');

        // The words' OR chains must not leak past the client scope.
        $response = $this->withHeaders($this->headers())->getJson('/api/v1/invoices?' . http_build_query([
            'filter' => 'ostravel ostr',
            'client_id' => $mine->hashed_id,
            'per_page' => 100,
        ]));
        $response->assertStatus(200);
        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($ours->hashed_id, $ids);
        $this->assertNotContains($other->hashed_id, $ids);
    }

    public function testProjectSearchWithTheTagSort(): void
    {
        // The tag sort joins tags, which also has a name column.
        $this->withHeaders($this->headers())
            ->getJson('/api/v1/projects?' . http_build_query([
                'filter' => 'acme',
                'sort' => 'project_tag_ids|asc',
            ]))
            ->assertStatus(200);
    }
}
