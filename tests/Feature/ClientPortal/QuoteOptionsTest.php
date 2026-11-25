<?php

namespace Tests\Feature\ClientPortal;

use App\DataMapper\InvoiceItem;
use App\DataMapper\QuoteBackup;
use App\DataMapper\QuoteSync;
use App\Livewire\QuoteOptions;
use App\Livewire\Sign;
use App\Livewire\Flow2\DocuNinjaLoader;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Design;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\User;
use App\Repositories\QuoteRepository;
use App\Services\Quote\QuoteOptions as QuoteOptionsService;
use App\Services\Pdf\PdfService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Livewire\Features\SupportTesting\Testable;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class QuoteOptionsTest extends TestCase
{
    use DatabaseTransactions;

    private Quote $quote;
    private ClientContact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Route::has('client.quote.options')) {
            $this->markTestSkipped('Route client.quote.options is not defined.');
        }

        $account = Account::factory()->create(['plan' => 'pro', 'plan_expires' => now()->addMonth()]);
        $user = User::factory()->create(['account_id' => $account->id, 'email' => uniqid('quote-options') . '@example.test']);
        $company = Company::factory()->create(['account_id' => $account->id]);
        $client = Client::factory()->create(['company_id' => $company->id, 'user_id' => $user->id]);
        $settings = $client->settings;
        $settings->auto_convert_quote = false;
        $settings->require_quote_signature = false;
        $client->settings = $settings;
        $client->save();

        $this->contact = ClientContact::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'user_id' => $user->id,
        ]);

        $items = [];
        foreach ([['Included work', 100, false], ['Chimney repairs', 40, true], ['Trim replacement', 20, true]] as [$name, $cost, $is_optional]) {
            $item = new InvoiceItem();
            $item->product_key = $name;
            $item->quantity = 1;
            $item->cost = $cost;
            $item->is_optional = $is_optional;
            $items[] = $item;
        }

        $this->quote = Quote::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => Quote::STATUS_SENT,
            'number' => 'OPTIONS-TEST',
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addMonth(),
            'discount' => 0,
            'partial' => 20,
            'is_amount_discount' => false,
            'uses_inclusive_taxes' => false,
            'tax_name1' => 'Tax',
            'tax_rate1' => 10,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'line_items' => $items,
            'sync' => new QuoteSync(),
        ]);
        $this->quote->calc()->getInvoice();
        $this->quote->refresh();

        QuoteInvitation::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_contact_id' => $this->contact->id,
            'quote_id' => $this->quote->id,
        ]);
        $this->actingAs($this->contact, 'contact');
    }

    public function testOptionsQuotesRedirectToTheInteractiveDocument(): void
    {
        $this->get(route('client.quote.show', $this->quote->hashed_id))
            ->assertRedirectToRoute('client.quote.options', $this->quote->hashed_id);

        $this->get(route('client.quote.options', $this->quote->hashed_id))
            ->assertOk()
            ->assertSee(ctrans('texts.download_pdf'))
            ->assertSeeHtml('aria-label="' . ctrans('texts.view_quote') . '"')
            ->assertSee('Included work')
            ->assertSee('Chimney repairs');
    }

    public function testSelectionsRecalculateTaxesAndDiscountsWithoutSavingTheQuote(): void
    {
        $original_items = $this->quote->getRawOriginal('line_items');
        $this->quote->discount = 10;
        $this->quote->calc()->getInvoice();

        $component = Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id]);
        $this->assertEquals(99, $component->instance()->preview->amount);

        $component->set('selected_options', ['1']);
        $this->assertEquals(138.6, $component->instance()->preview->amount);
        $this->assertSame($original_items, $this->quote->fresh()->getRawOriginal('line_items'));
        $this->assertEquals(99, $this->quote->fresh()->amount);
        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
    }

    public static function calculatorModes(): array
    {
        return [[false, false], [false, true], [true, false], [true, true]];
    }

    public static function financialDiscountModes(): array
    {
        return [[false, 89.1, 118.8, 12, '25%'], [true, 88, 104.5, 10, '$25.00']];
    }

    public static function columnSyncModes(): array
    {
        return [[false], [true]];
    }

    public function testDisplayedLineTaxesFollowTheSelectedScopeWithAFixedQuoteDiscount(): void
    {
        $items = $this->quote->line_items;
        foreach ($items as $item) {
            $item->tax_name1 = 'Line tax';
            $item->tax_rate1 = 10;
        }
        $this->quote->line_items = $items;
        $this->quote->is_amount_discount = true;
        $this->quote->discount = 50;
        $this->quote->tax_rate1 = 0;
        $this->quote->tax_name1 = '';
        $service = app(QuoteOptionsService::class);

        $this->assertEquals(5, $service->displayItems($this->quote)[0]->tax_amount);
        $selected = $service->preview($this->quote, [1]);
        $display_items = $service->displayItems($this->quote, [1]);
        $this->assertEquals(6.43, $display_items[0]->tax_amount);
        $this->assertEquals($selected->line_items[0]->gross_line_total, $display_items[0]->gross_line_total);
        $this->assertEquals($selected->line_items[1]->tax_amount, $display_items[1]->tax_amount);
        $this->assertTrue($display_items[1]->is_optional);
        $this->assertTrue($display_items[2]->is_optional);
    }

    #[DataProvider('columnSyncModes')]
    public function testFinancialViewIncludesAllConfiguredLineAndTotalFields(bool $sync_columns): void
    {
        $client = $this->quote->client;
        $settings = $client->settings;
        $settings->sync_invoice_quote_columns = $sync_columns;
        $settings->hide_empty_columns_on_pdf = false;
        $settings->pdf_variables = clone $client->getMergedSettings()->pdf_variables;
        $fields = ['item', 'description', 'unit_cost', 'net_cost', 'quantity', 'discount', 'tax_rate1', 'tax_rate2', 'tax_rate3', 'tax_amount', 'line_total', 'gross_line_total', 'tags', 'product1', 'product2', 'product3', 'product4'];
        $columns = array_map(fn ($field) => '$product.' . $field, $fields);
        $settings->pdf_variables->product_columns = $sync_columns ? $columns : ['$product.item'];
        $settings->pdf_variables->product_quote_columns = $sync_columns ? ['$product.item'] : $columns;
        $settings->pdf_variables->task_columns = ['$task.service', '$task.rate', '$task.hours', '$task.task1'];
        $settings->pdf_variables->total_columns = ['$net_subtotal', '$gross_subtotal', '$subtotal', '$discount', '$custom_surcharge1', '$custom_surcharge2', '$custom_surcharge3', '$custom_surcharge4', '$total_taxes', '$line_taxes', '$taxes', '$paid_to_date', '$total'];
        $client->settings = $settings;
        $client->save();
        $this->quote->company->forceFill([
            'enable_product_discount' => true,
            'custom_fields' => (object) ['product1' => 'Surface', 'product2' => 'Finish', 'product3' => 'Area', 'product4' => 'Colour', 'task1' => 'Crew', 'surcharge1' => 'Setup', 'surcharge2' => 'Travel', 'surcharge3' => 'Materials', 'surcharge4' => 'Access'],
        ])->saveQuietly();
        $items = $this->quote->line_items;
        $items[0]->tax_name1 = 'Service tax';
        $items[0]->tax_rate1 = 5;
        $items[1]->cost = 40.123456;
        $items[1]->discount = 5;
        $items[1]->tax_name2 = 'Repair tax';
        $items[1]->tax_rate2 = 3;
        $items[1]->tags = 'Upgrade,Priority';
        foreach ([1 => 'Wood', 2 => 'Satin', 3 => '500 sq ft', 4 => 'White'] as $index => $value) {
            $items[1]->{'custom_value' . $index} = $value;
        }
        $task = clone $items[0];
        $task->type_id = '2';
        $task->product_key = 'Site preparation';
        $task->custom_value1 = 'Two painters';
        $items[] = $task;
        $this->quote->line_items = $items;
        $this->quote->discount = 10;
        foreach ([1 => 5, 2 => 6, 3 => 7, 4 => 8] as $index => $amount) {
            $this->quote->{'custom_surcharge' . $index} = $amount;
        }
        $this->quote->calc()->getInvoice();

        $component = Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id]);
        $document = new \DOMDocument();
        @$document->loadHTML($component->html());
        $xpath = new \DOMXPath($document);
        foreach ($fields as $field) {
            $this->assertSame(1, $xpath->query('//article[@data-quote-item-index="1"]//*[@data-quote-field="' . $field . '"]')->length, $field);
        }
        foreach (['Surface', 'Finish', 'Area', 'Colour', 'Wood', 'Satin', '500 sq ft', 'White', 'Upgrade', 'Priority', 'Crew', 'Two painters', 'Setup', 'Travel', 'Materials', 'Access', 'Service tax', '40.123456'] as $value) {
            $component->assertSee($value);
        }
        foreach (['net_subtotal', 'gross_subtotal', 'subtotal', 'discount', 'custom_surcharge1', 'custom_surcharge2', 'custom_surcharge3', 'custom_surcharge4', 'taxes', 'partial_due', 'total'] as $field) {
            $this->assertSame(1, $xpath->query('//*[@data-testid="quote-options-' . $field . '"]')->length, $field);
        }
        $component->assertDontSeeHtml('data-testid="quote-options-paid_to_date"')
            ->set('selected_options', ['1'])->assertSee('Repair tax')
            ->assertDontSeeHtml('data-testid="quote-options-paid_to_date"');
    }

    public function testFinancialViewUsesJsonDesignColumnsAndTotalLabels(): void
    {
        $design = new Design();
        $design->company_id = $this->quote->company_id;
        $design->user_id = $this->quote->user_id;
        $design->name = 'Financial options';
        $design->is_custom = true;
        $design->is_active = true;
        $design->design = (object) [
            'documentSettings' => ['hideEmptyColumns' => true],
            'blocks' => [
                ['id' => 'quote-lines', 'type' => 'table', 'gridPosition' => ['x' => 0, 'y' => 0, 'w' => 12, 'h' => 4], 'properties' => ['columns' => [
                    ['id' => 'name', 'field' => 'item.product_key', 'header' => 'Scope'],
                    ['id' => 'area', 'field' => 'item.custom_value3', 'header' => 'Measured Area'],
                    ['id' => 'empty', 'field' => 'item.custom_value4', 'header' => 'Unused field'],
                    ['id' => 'amount', 'field' => 'item.line_total', 'header' => 'Price'],
                ]]],
                ['id' => 'quote-totals', 'type' => 'total', 'gridPosition' => ['x' => 8, 'y' => 5, 'w' => 4, 'h' => 3], 'properties' => ['items' => [
                    ['field' => '$custom_surcharge1', 'label' => 'Mobilisation', 'show' => true],
                    ['field' => '$balance_due', 'label' => 'Hidden balance', 'show' => false],
                    ['field' => '$total', 'label' => 'Agreed total', 'isTotal' => true],
                ]]],
            ],
        ];
        $design->save();
        $items = $this->quote->line_items;
        $items[1]->custom_value3 = '500 square feet';
        $this->quote->line_items = $items;
        $this->quote->custom_surcharge1 = 5;
        $this->quote->design_id = $design->id;
        $this->quote->calc()->getInvoice();

        Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->assertSee('Scope')->assertSee('Measured Area')->assertSee('500 square feet')->assertSee('Price')
            ->assertSee('Mobilisation')->assertSee('Agreed total')
            ->assertDontSee('Unused field')->assertDontSee('Hidden balance')
            ->set('selected_options', ['1'])
            ->assertSeeHtml('data-testid="quote-options-total">$159.00');
        $this->assertEquals(115, $this->quote->fresh()->amount);
    }

    #[DataProvider('financialDiscountModes')]
    public function testFinancialViewUpdatesDiscountsAndTotalsForSelectedOptions(bool $amount_discount, float $base_total, float $selected_total, float $quote_discount, string $line_discount): void
    {
        $items = $this->quote->line_items;
        $this->quote->company->forceFill(['enable_product_discount' => true])->saveQuietly();
        $items[0]->discount = 10;
        $items[1]->discount = 25;
        $this->quote->line_items = $items;
        $this->quote->is_amount_discount = $amount_discount;
        $this->quote->discount = 10;
        $this->quote->calc()->getInvoice();

        $component = Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->assertDontSee('<iframe', false)
            ->assertSee('Add to quote')
            ->assertSee($line_discount)
            ->assertSee(\App\Utils\Number::formatMoney($base_total, $this->quote->client));

        $component->set('selected_options', ['1'])
            ->assertSee('Included in total')
            ->assertSee('1 of 2 additions selected')
            ->assertSee(\App\Utils\Number::formatMoney($selected_total, $this->quote->client))
            ->assertSeeHtml('data-testid="quote-options-discount">' . \App\Utils\Number::formatMoney($quote_discount, $this->quote->client));

        $this->assertEquals($base_total, $this->quote->fresh()->amount);
        $component->set('selected_options', [])
            ->assertDontSee('Included in total')
            ->assertSee('0 of 2 additions selected')
            ->assertSee(\App\Utils\Number::formatMoney($base_total, $this->quote->client));
    }

    #[DataProvider('calculatorModes')]
    public function testBaseTotalsExcludeOptionalLinesAndRetainTheirConfiguration(bool $inclusive, bool $amount_discount): void
    {
        $items = $this->quote->line_items;
        foreach ($items as $item) {
            $item->tax_name1 = 'Line tax';
            $item->tax_rate1 = 15;
            $item->discount = 5;
        }
        $items[2]->type_id = '2';
        $items[1]->line_total = 999;
        $items[1]->tax_amount = 999;
        $this->quote->line_items = [$items[1], $items[0], $items[2]];
        $this->quote->uses_inclusive_taxes = $inclusive;
        $this->quote->is_amount_discount = $amount_discount;
        $this->quote->discount = 10;
        $this->quote->tax_name1 = '';
        $this->quote->tax_rate1 = 0;
        $this->quote->custom_surcharge1 = 10;
        $this->quote->custom_surcharge_tax1 = true;

        $required_quote = clone $this->quote;
        $required_quote->line_items = [clone $items[0]];
        $expected = $required_quote->calc();
        $actual = $this->quote->calc();

        $this->assertEquals($expected->getTotal(), $actual->getTotal());
        $this->assertEquals($expected->getTotalTaxes(), $actual->getTotalTaxes());
        $this->assertEquals($expected->getTotalDiscount(), $actual->getTotalDiscount());
        $this->assertEquals($expected->getTaxMap(), $actual->getTaxMap());

        $quote = $actual->getInvoice()->fresh();
        $this->assertCount(3, $quote->line_items);
        $this->assertTrue($quote->line_items[0]->is_optional);
        $this->assertEquals(40, $quote->line_items[0]->cost);
        $this->assertEquals(5, $quote->line_items[0]->discount);
        $this->assertEquals(0, $quote->line_items[0]->line_total);
        $this->assertEquals(0, $quote->line_items[0]->tax_amount);
        $this->assertSame('2', $quote->line_items[2]->type_id);
    }

    public function testOnlyOptionalLinesHaveNoBaseTotal(): void
    {
        $this->quote->line_items = array_slice($this->quote->line_items, 1);

        foreach ([false, true] as $inclusive) {
            $this->quote->uses_inclusive_taxes = $inclusive;
            $quote = $this->quote->calc()->getTempEntity();
            $this->assertEquals(0, $quote->amount);
            $this->assertEquals(0, $quote->total_taxes);
            $this->assertCount(2, $quote->line_items);
        }
    }

    public static function documentDesigns(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('documentDesigns')]
    public function testQuotePdfHtmlSeparatesOptionalRowsAndPreservesTheDesign(bool $json_design): void
    {
        if ($json_design) {
            $design = new Design();
            $design->company_id = $this->quote->company_id;
            $design->user_id = $this->quote->user_id;
            $design->name = 'Optional items design';
            $design->is_custom = true;
            $design->is_active = true;
            $design->design = json_decode(file_get_contents(base_path('tests/Feature/Design/stubs/test_design_1.json')));
            $design->save();
            $this->quote->design_id = $design->id;
            $this->quote->saveQuietly();
        }

        $original_items = $this->quote->getRawOriginal('line_items');
        $invitation = $this->quote->invitations()->first();
        $pdf_service = (new PdfService($invitation))->boot();
        $html = $pdf_service->getHtml();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(1, $xpath->query('//table[@data-quote-group="required"]')->length);
        $this->assertSame(1, $xpath->query('//table[@data-quote-group="optional"]')->length);
        $this->assertStringContainsString('Included work', $xpath->query('//table[@data-quote-group="required"]')->item(0)->textContent);
        $optional_table = $xpath->query('//table[@data-quote-group="optional"]')->item(0);
        if (! $json_design) {
            $this->assertSame('delivery-note-table', $optional_table->getAttribute('id'));
            $this->assertStringContainsString('Chimney repairs', $pdf_service->builder->document->getElementById('delivery-note-table')->textContent);
            $this->assertStringNotContainsString('Chimney repairs', $pdf_service->builder->document->getElementById('product-table')->textContent);
        }
        $this->assertStringContainsString('Chimney repairs', $optional_table->textContent);
        $this->assertStringContainsString('40.00', $optional_table->textContent);
        $this->assertStringContainsString('Trim replacement', $optional_table->textContent);
        $this->assertStringNotContainsString('Included work', $optional_table->textContent);
        $this->assertSame(0, $xpath->query('//input')->length);
        $this->assertSame($original_items, $this->quote->fresh()->getRawOriginal('line_items'));
        $this->assertEquals(110, $this->quote->fresh()->amount);
    }

    public function testOptionalTasksUseProductColumnsInTheOptionalSection(): void
    {
        $item = $this->quote->line_items[2];
        $item->type_id = '2';
        $this->quote->line_items = [$item];
        $this->quote->calc()->getInvoice();

        $service = app(QuoteOptionsService::class);
        $html = $service->documentHtml($this->quote, $service->displayItems($this->quote), []);
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//table[@id="product-table"]')->length);
        $this->assertSame(0, $xpath->query('//table[@data-quote-group="required"]')->length);
        $this->assertSame(0, $xpath->query('//table[@id="task-table"]')->length);
        $this->assertSame(1, $xpath->query('//table[@id="delivery-note-table"]')->length);
        $this->assertSame('0', $xpath->query('//input[@data-quote-option]')->item(0)->getAttribute('value'));
        $this->assertStringContainsString('20.00', $html);
        $this->assertEquals(0, $this->quote->fresh()->amount);
        $this->assertEquals(22, $service->preview($this->quote, [0])->amount);
    }

    public function testActualDeliveryNotesKeepTheirOwnColumnsAndRows(): void
    {
        $html = (new PdfService($this->quote->invitations()->first(), PdfService::DELIVERY_NOTE))->boot()->getHtml();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(1, $xpath->query('//table[@id="delivery-note-table"]')->length);
        $this->assertSame(0, $xpath->query('//table[@data-quote-group="optional"]')->length);
        $this->assertSame(3, $xpath->query('//table[@id="delivery-note-table"]/thead//th')->length);
        $this->assertSame(3, $xpath->query('//table[@id="delivery-note-table"]/tbody/tr')->length);
        $this->assertStringContainsString('Chimney repairs', $document->getElementById('delivery-note-table')->textContent);
        $this->assertSame(0, $xpath->query('//input[@data-quote-option]')->length);
    }

    public function testQuotesWithoutOptionsRemoveTheUnusedDeliveryNotePlaceholder(): void
    {
        $items = $this->quote->line_items;
        foreach ($items as $item) {
            $item->is_optional = false;
        }
        $this->quote->line_items = $items;
        $this->quote->calc()->getInvoice();

        $html = (new PdfService($this->quote->invitations()->first()))->boot()->getHtml();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//table[@id="delivery-note-table"]')->length);
        $this->assertSame(3, $xpath->query('//table[@id="product-table"]/tbody/tr')->length);
        $this->assertEquals(176, $this->quote->fresh()->amount);
    }

    public static function unavailableDeliveryNotePlaceholders(): array
    {
        return [[false], [true]];
    }

    public function testOptionalSectionUsesQuoteColumnsAndItsOwnColumnVisibility(): void
    {
        $client = $this->quote->client;
        $settings = $client->settings;
        $settings->sync_invoice_quote_columns = false;
        $settings->hide_empty_columns_on_pdf = true;
        $settings->pdf_variables = clone $client->getMergedSettings()->pdf_variables;
        $settings->pdf_variables->product_quote_columns = [
            '$product.item', '$product.tags', '$product.product1', '$product.tax', '$product.line_total',
        ];
        $client->settings = $settings;
        $client->save();
        $this->quote->company->forceFill(['custom_fields' => (object) ['product1' => 'Finish']])->saveQuietly();
        $items = $this->quote->line_items;
        $items[1]->tags = 'Upgrade';
        $items[1]->custom_value1 = 'Premium finish';
        $items[1]->tax_name2 = 'Optional tax';
        $items[1]->tax_rate2 = 7.5;
        $items[2]->type_id = '2';
        $items[2]->tax_name3 = 'Task tax';
        $items[2]->tax_rate3 = 2;
        $this->quote->line_items = $items;
        $this->quote->calc()->getInvoice();

        $html = (new PdfService($this->quote->invitations()->first()))->boot()->getHtml();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $optional_table = $document->getElementById('delivery-note-table');
        $this->assertStringContainsString('Premium finish', $optional_table->textContent);
        $this->assertStringContainsString('Upgrade', $optional_table->textContent);
        $this->assertSame(2, $xpath->query('./tbody/tr', $optional_table)->length);
        $this->assertSame(1, $xpath->query('./thead/tr/th[@data-ref="product_table-product.tax2-th"]', $optional_table)->length);
        $this->assertSame(1, $xpath->query('./thead/tr/th[@data-ref="product_table-product.tax3-th"]', $optional_table)->length);
        $this->assertStringNotContainsString('display: none', $xpath->query('./thead/tr/th[@data-ref="product_table-product.tags-th"]', $optional_table)->item(0)->getAttribute('style'));
        $this->assertStringContainsString('display: none', $xpath->query('//table[@id="product-table"]/thead/tr/th[@data-ref="product_table-product.tags-th"]')->item(0)->getAttribute('style'));
    }

    public function testOptionalControlsUseTheVisibleDescriptionWhenTheItemColumnIsHidden(): void
    {
        $client = $this->quote->client;
        $settings = $client->settings;
        $settings->sync_invoice_quote_columns = false;
        $settings->hide_empty_columns_on_pdf = true;
        $settings->pdf_variables = clone $client->getMergedSettings()->pdf_variables;
        $settings->pdf_variables->product_quote_columns = [
            '$product.item', '$product.description', '$product.unit_cost', '$product.line_total',
        ];
        $client->settings = $settings;
        $client->save();
        $items = $this->quote->line_items;
        foreach ($items as $item) {
            $item->notes = $item->product_key;
            $item->product_key = '';
        }
        $this->quote->line_items = $items;
        $this->quote->calc()->getInvoice();

        $service = app(QuoteOptionsService::class);
        $html = $service->documentHtml($service->preview($this->quote, []), $service->displayItems($this->quote), []);
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $checkboxes = $xpath->query('//input[@data-quote-option]');

        $this->assertSame(2, $checkboxes->length);
        $this->assertSame(2, $xpath->query('//table[@data-quote-group="optional"]/tbody/tr/td[1][contains(@style,"display: none")]')->length);
        foreach ($checkboxes as $checkbox) {
            $this->assertSame('product_table-product.description-td', $checkbox->parentNode->getAttribute('data-ref'));
            $this->assertStringNotContainsString('display: none', $checkbox->parentNode->getAttribute('style'));
            $this->assertNotEmpty($checkbox->getAttribute('aria-label'));
        }
    }

    #[DataProvider('unavailableDeliveryNotePlaceholders')]
    public function testCustomDesignsKeepOptionalItemsWhenThePlaceholderIsUnavailable(bool $occupied): void
    {
        $placeholder = $occupied
            ? '<table id="delivery-note-table"><tbody><tr><td>Custom delivery content</td></tr></tbody></table>'
            : '';
        $design = new Design();
        $design->company_id = $this->quote->company_id;
        $design->user_id = $this->quote->user_id;
        $design->name = 'Custom quote layout';
        $design->is_custom = true;
        $design->is_active = true;
        $design->design = (object) ['body' => '<html><body><table id="product-table" data-ref="table"></table>' . $placeholder . '</body></html>'];
        $design->save();
        $this->quote->design_id = $design->id;
        $this->quote->saveQuietly();

        $html = (new PdfService($this->quote->invitations()->first()))->boot()->getHtml();
        $document = new \DOMDocument();
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);

        $this->assertSame(1, $xpath->query('//table[@id="product-table-optional"]')->length);
        $this->assertStringContainsString('Chimney repairs', $document->getElementById('product-table-optional')->textContent);
        if ($occupied) {
            $this->assertSame('Custom delivery content', trim($document->getElementById('delivery-note-table')->textContent));
        }
    }

    public function testReviewPersistsPendingScopeAndFinalApprovalUsesTheRegularPath(): void
    {
        $original_line_items = json_decode($this->quote->getRawOriginal('line_items'), true);

        Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->set('selected_options', ['1'])
            ->call('continueApproval')
            ->assertHasNoErrors()
            ->assertRedirect(route('client.quote.show', $this->quote->hashed_id));

        $quote = $this->quote->fresh();
        $selection = $quote->backup->option_selection;
        $this->assertSame(Quote::STATUS_SENT, $quote->status_id);
        $this->assertEquals(154, $quote->amount);
        $this->assertCount(2, $quote->line_items);
        $this->assertFalse($quote->hasOptions());
        $this->assertSame(QuoteBackup::STATE_PENDING_ACCEPTANCE, $selection['state']);
        $this->assertSame($original_line_items, $selection['line_items']);

        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()
            ->assertViewIs('portal.ninja2020.quotes.approve')
            ->assertSee('Chimney repairs')
            ->assertDontSee('Trim replacement');
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->assertSame($selection, $quote->fresh()->backup->option_selection);

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection['key'],
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('client.quote.show', $quote->hashed_id);

        $quote = $quote->fresh();
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status_id);
        $this->assertSame(QuoteBackup::STATE_ACCEPTED, $quote->backup->option_selection['state']);
        $this->assertEquals(154, $quote->amount);
        $this->assertCount(2, $quote->line_items);
    }

    public function testNoOptionsIsAnExplicitSelectionAndRequiredLinesRemain(): void
    {
        Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->call('continueApproval')
            ->assertHasNoErrors()
            ->assertRedirect(route('client.quote.show', $this->quote->hashed_id));

        $quote = $this->quote->fresh();
        $this->assertEquals(110, $quote->amount);
        $this->assertCount(1, $quote->line_items);
        $this->assertSame([], $quote->backup->option_selection['selected_options']);
        $this->assertSame(QuoteBackup::STATE_PENDING_ACCEPTANCE, $quote->backup->option_selection['state']);
    }

    public function testReviewedQuoteCanBeRejectedWithoutLosingTheOriginalOffer(): void
    {
        $quote = $this->selectOptions();
        $selection = $quote->backup->option_selection;

        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()
            ->assertSee('id="reject-button"', false)
            ->assertSee('id="reject-form"', false)
            ->assertSee('id="displayRejectModal"', false);

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'reject', 'process' => 'true',
            'user_input' => 'We are postponing the work.',
        ])->assertSessionHasNoErrors()->assertRedirectToRoute('client.quotes.index');

        $quote = $quote->fresh();
        $this->assertSame(Quote::STATUS_REJECTED, $quote->status_id);
        $this->assertSame($selection, $quote->backup->option_selection);
        $this->assertCount(2, $quote->line_items);
    }

    public function testDirectApprovalCannotSkipOptionReview(): void
    {
        $this->get(route('client.quote.options', $this->quote->hashed_id))->assertOk();

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$this->quote->hashed_id], 'action' => 'approve', 'process' => 'true',
        ])->assertRedirectToRoute('client.quote.options', $this->quote->hashed_id);

        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
    }

    public function testInvalidIndexesCannotRemoveRequiredWorkOrAddOtherLines(): void
    {
        foreach ([[0], [99], [1, 1]] as $selected_options) {
            Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
                ->set('selected_options', $selected_options)
                ->assertHasErrors('selected_options.*');
        }
    }

    public function testChangedQuotesRequireANewReview(): void
    {
        $component = Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->set('selected_options', ['1']);
        $items = $this->quote->line_items;
        $items[1]->cost = 80;
        $this->quote->line_items = $items;
        $this->quote->saveQuietly();

        $component->call('continueApproval')->assertHasErrors('selected_options');

        $this->assertCount(3, $this->quote->fresh()->line_items);
        $this->assertNull($this->quote->fresh()->backup);
    }

    public function testSelectionsCannotBeCommittedByAnotherClient(): void
    {
        $client = Client::factory()->create([
            'company_id' => $this->contact->company_id, 'user_id' => $this->contact->user_id,
        ]);
        $contact = ClientContact::factory()->create([
            'user_id' => $client->user_id, 'company_id' => $client->company_id, 'client_id' => $client->id,
        ]);
        $this->actingAs($contact, 'contact');

        $this->expectException(ModelNotFoundException::class);
        try {
            Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id]);
        } finally {
            $this->assertCount(3, $this->quote->fresh()->line_items);
            $this->assertNull($this->quote->fresh()->backup);
        }
    }

    public function testSelectionsCannotBeCommittedAcrossCompanies(): void
    {
        $company = Company::factory()->create(['account_id' => $this->quote->company->account_id]);
        $client = Client::factory()->create(['company_id' => $company->id, 'user_id' => $this->contact->user_id]);
        $contact = ClientContact::factory()->create([
            'user_id' => $client->user_id, 'company_id' => $company->id, 'client_id' => $client->id,
        ]);
        $this->actingAs($contact, 'contact');

        $this->expectException(ModelNotFoundException::class);
        try {
            Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id]);
        } finally {
            $this->assertCount(3, $this->quote->fresh()->line_items);
            $this->assertNull($this->quote->fresh()->backup);
        }
    }

    public static function unavailableQuotes(): array
    {
        return [
            'rejected' => [Quote::STATUS_REJECTED, false],
            'cancelled' => [Quote::STATUS_CANCELLED, false],
            'expired' => [Quote::STATUS_SENT, true],
        ];
    }

    #[DataProvider('unavailableQuotes')]
    public function testUnavailableQuotesCannotCommitSelections(int $status_id, bool $expired): void
    {
        $this->quote->status_id = $status_id;
        $this->quote->saveQuietly();
        if ($expired) {
            $this->travelTo(now()->addMonths(2));
        }

        $service = app(QuoteOptionsService::class);
        $this->expectException(HttpException::class);
        try {
            $service->apply($this->quote, [1], $service->revision($this->quote));
        } finally {
            $this->assertNull($this->quote->fresh()->backup);
            $this->assertCount(3, $this->quote->fresh()->line_items);
        }
    }

    #[DataProvider('unavailableQuotes')]
    public function testStatusChangesAfterReviewBlockAcceptanceAndRestart(int $status_id, bool $expired): void
    {
        $quote = $this->selectOptions();
        $selection = $quote->backup->option_selection;
        $quote->status_id = $status_id;
        $quote->saveQuietly();
        if ($expired) {
            $this->travelTo(now()->addMonths(2));
        }
        $this->get(route('client.quotes.index'))->assertOk();

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection['key'],
        ])->assertStatus(409);
        $this->post(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(), 'restart_confirmed' => '1',
        ])->assertStatus(409);

        $this->assertSame($selection, $quote->fresh()->backup->option_selection);
        $this->assertCount(2, $quote->fresh()->line_items);
    }

    public static function approvalMethods(): array
    {
        return [
            'standard approval' => ['approve'],
            'approval without conversion' => ['approveWithNoCoversion'],
        ];
    }

    #[DataProvider('approvalMethods')]
    public function testApprovalServicesMarkTheSelectionAccepted(string $method): void
    {
        $quote = $this->selectOptions();
        $original_line_items = $quote->backup->option_selection['line_items'];

        $quote->service()->{$method}($this->contact)->save();

        $quote = $quote->fresh();
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status_id);
        $this->assertSame(QuoteBackup::STATE_ACCEPTED, $quote->backup->option_selection['state']);
        $this->assertSame($original_line_items, $quote->backup->option_selection['line_items']);
    }

    public function testQuotesWithoutOptionsKeepTheirTraditionalApprovalInput(): void
    {
        $this->enableApprovalInput();
        $this->quote->line_items = [$this->quote->line_items[0]];
        $this->quote->calc()->getInvoice();

        $this->get(route('client.quote.show', $this->quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.quotes.show');
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$this->quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'user_input' => 'ORDINARY-PO',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $quote = $this->quote->fresh();
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status_id);
        $this->assertSame('ORDINARY-PO', $quote->po_number);
        $this->assertNull($quote->backup);
    }

    public function testConfiguredApprovalInputIsSavedBeforeEnteringDocuNinja(): void
    {
        $this->enableDocuNinja();
        $this->enableApprovalInput();
        $quote = $this->selectOptions();
        $selection = $quote->backup->option_selection;
        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertSee('name="accept-user-input" content="1"', false);

        $response = $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection['key'], 'user_input' => 'SIGNING-PO',
        ])->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');

        $quote = $quote->fresh();
        $this->assertSame('SIGNING-PO', $quote->po_number);
        $this->assertSame(Quote::STATUS_SENT, $quote->status_id);
        $this->assertSame(QuoteBackup::STATE_PENDING_ACCEPTANCE, $quote->backup->option_selection['state']);
        $this->assertSame($selection['key'], $quote->backup->option_selection['key']);
        $this->assertSame($selection['line_items'], $quote->backup->option_selection['line_items']);
        app(QuoteOptionsService::class)->assertConfirmed($quote);

        $quote->sync = new QuoteSync(dn_completed: true);
        $quote->saveQuietly();
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'request_hash' => $response->viewData('request_hash'),
            'user_input' => 'STALE-PO',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $quote = $quote->fresh();
        $this->assertSame('SIGNING-PO', $quote->po_number);
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status_id);
        $this->assertSame(QuoteBackup::STATE_ACCEPTED, $quote->backup->option_selection['state']);
    }

    public function testRepeatedApprovalInputDoesNotChangeTheConfirmedSelection(): void
    {
        $this->enableApprovalInput();
        $quote = $this->selectOptions();
        $service = app(QuoteOptionsService::class);
        $key = $quote->backup->option_selection['key'];
        $quote = $service->captureApprovalInput($quote, 'SAME-PO', $key);
        $quote->sync = new QuoteSync(invitations: [['dn_id' => 'existing-document']]);
        $quote->saveQuietly();
        $selection = $quote->backup->option_selection;
        $updated_at = $quote->getRawOriginal('updated_at');
        $this->travel(1)->minute();

        $quote = $service->captureApprovalInput($quote, 'SAME-PO', $key);

        $this->assertSame($selection, $quote->backup->option_selection);
        $this->assertSame($updated_at, $quote->getRawOriginal('updated_at'));
        $this->assertFalse($quote->sync->dn_dirty);

        $quote = $service->captureApprovalInput($quote, 'CHANGED-PO', $key);

        $this->assertTrue($quote->sync->dn_dirty);
        $this->assertSame('CHANGED-PO', $quote->po_number);
    }

    public function testChangedApprovalInputCannotReplaceAnAlreadySignedPoNumber(): void
    {
        $this->enableApprovalInput();
        $quote = $this->selectOptions();
        $quote->sync = new QuoteSync(dn_completed: true);
        $quote->saveQuietly();
        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertSee('name="accept-user-input" content=""', false);

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $quote->backup->option_selection['key'], 'user_input' => 'NEW-PO',
        ])->assertStatus(409);

        $this->assertSame($quote->po_number, $quote->fresh()->po_number);
        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);
    }

    public function testApprovalStillConvertsOnlyTheSelectedScopeIntoAnInvoice(): void
    {
        $client = $this->quote->client;
        $settings = $client->settings;
        $settings->auto_convert_quote = true;
        $client->settings = $settings;
        $client->save();
        $quote = $this->selectOptions();

        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $quote->backup->option_selection['key'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $quote = $quote->fresh();
        $this->assertSame(Quote::STATUS_CONVERTED, $quote->status_id);
        $this->assertSame(QuoteBackup::STATE_ACCEPTED, $quote->backup->option_selection['state']);
        $invoice = $quote->invoice;
        $this->assertNotNull($invoice);
        $this->assertEquals(154, $invoice->amount);
        $this->assertEquals(20, $invoice->partial);
        $this->assertCount(2, $invoice->line_items);
    }

    public function testApplyingOptionsPreservesTheExistingSigningCredentialsAndOriginalOffer(): void
    {
        $this->quote->backup = new QuoteBackup(guid: 'reserved-guid', rules: ['optional' => ['enabled' => true]]);
        $this->quote->sync = new QuoteSync(
            qb_id: 'keep-quickbooks-id',
            invitations: [['dn_id' => 'original-document', 'invitation_key' => 'keep-invitation', 'dn_sig' => 'keep-signature']],
        );
        $this->quote->saveQuietly();
        $original_sync = json_decode($this->quote->getRawOriginal('sync'), true);
        $original_sync['dn_dirty'] = true;
        $original_line_items = json_decode($this->quote->getRawOriginal('line_items'), true);
        $service = app(QuoteOptionsService::class);
        $quote = $this->selectOptions();

        $quote = $this->quote->fresh();
        $this->assertSame($original_sync, json_decode($quote->getRawOriginal('sync'), true));
        $this->assertSame($original_line_items, $quote->backup->option_selection['line_items']);
        $this->assertSame([1], $quote->backup->option_selection['selected_options']);
        $this->assertSame($this->contact->id, $quote->backup->option_selection['contact_id']);
        $this->assertSame('reserved-guid', $quote->backup->guid);
        $this->assertSame(['optional' => ['enabled' => true]], $quote->backup->rules);
        $service->assertConfirmed($quote);
    }

    public function testReturningClientsResumeThePersistedSelectionWithoutCache(): void
    {
        $this->quote->po_number = 'PO-CONFIRMED';
        $this->quote->saveQuietly();
        $quote = $this->selectOptions();

        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()
            ->assertViewIs('portal.ninja2020.quotes.approve')
            ->assertSee('Your selections are confirmed.')
            ->assertSee('Chimney repairs')
            ->assertDontSee('Trim replacement');

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'user_input' => 'REPLACE-PO',
            'selection_key' => $quote->backup->option_selection['key'],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $quote = $quote->fresh();
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status_id);
        $this->assertSame('PO-CONFIRMED', $quote->po_number);
        $this->assertEquals(154, $quote->amount);
        $this->assertCount(2, $quote->line_items);
    }

    public function testConfirmationWithNoAdditionsPersistsAnExplicitEmptySelection(): void
    {
        $quote = $this->selectOptions([]);

        $this->assertTrue($quote->hasOptionSelection());
        $this->assertSame([], $quote->backup->option_selection['selected_options']);
        $this->assertCount(3, $quote->backup->option_selection['line_items']);
        $this->assertCount(1, $quote->line_items);
        $this->assertEquals(110, $quote->amount);
    }

    public function testDraftQuoteNumberIsAssignedBeforeTheConfirmedScopeIsLocked(): void
    {
        $client = $this->quote->client;
        $settings = $client->settings;
        $settings->counter_number_applied = 'when_sent';
        $client->settings = $settings;
        $client->save();
        $this->quote->number = null;
        $this->quote->status_id = Quote::STATUS_DRAFT;
        $this->quote->saveQuietly();
        $this->quote->refresh();
        $service = app(QuoteOptionsService::class);
        $quote = $this->selectOptions();
        $selection_key = $quote->backup->option_selection['key'];

        $this->assertNotEmpty($quote->number);
        $this->assertSame(Quote::STATUS_DRAFT, $quote->status_id);
        $confirmed_number = $quote->number;
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection_key,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $quote = $quote->fresh();
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status_id);
        $this->assertSame($confirmed_number, $quote->number);
        $service->assertConfirmed($quote);
    }

    public function testConfirmedScopeCannotBeChangedThroughTheQuoteRepository(): void
    {
        $quote = $this->selectOptions();

        $this->expectException(ValidationException::class);
        try {
            app(QuoteRepository::class)->save(['discount' => 25], $quote);
        } finally {
            $this->assertEquals(0, $quote->fresh()->discount);
        }
    }

    public function testConfirmedScopeAllowsInternalNotesWithoutUnlockingTheSelection(): void
    {
        $service = app(QuoteOptionsService::class);
        $quote = $this->selectOptions();
        $selection = $quote->backup->option_selection;

        app(QuoteRepository::class)->save(['private_notes' => 'Call before arrival.'], $quote);

        $quote = $quote->fresh();
        $this->assertSame('Call before arrival.', $quote->private_notes);
        $this->assertSame($selection, $quote->backup->option_selection);
        $service->assertConfirmed($quote);
    }

    public function testConfirmedScopeChangedOutsideTheWorkflowCannotBeApproved(): void
    {
        $quote = $this->selectOptions();
        $quote->terms = 'Replaced after confirmation';
        $quote->saveQuietly();
        $this->get(route('client.quotes.index'))->assertOk();

        $this->postJson(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
        ])->assertUnprocessable()->assertJsonValidationErrors('selected_options');

        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);
    }

    public function testRestartRequiresExplicitConfirmationAndRestoresTheOriginalOffer(): void
    {
        $service = app(QuoteOptionsService::class);
        $this->quote->partial = 200;
        $this->quote->po_number = 'ORIGINAL-PO';
        $this->quote->saveQuietly();
        $this->quote->refresh();
        $quote = $this->selectOptions();
        $selection_key = $quote->backup->option_selection['key'];
        $this->enableApprovalInput();
        $quote = $service->captureApprovalInput($quote, 'CONFIRMED-PO', $selection_key);
        $original_line_items = $quote->backup->option_selection['line_items'];

        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertSee('Restart selections')->assertSee('onsubmit=', false);
        $this->postJson(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(),
        ])->assertUnprocessable()->assertJsonValidationErrors('restart_confirmed');
        $this->assertTrue($quote->fresh()->hasOptionSelection());

        $this->post(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(), 'restart_confirmed' => '1',
        ])->assertRedirectToRoute('client.quote.options', $quote->hashed_id);

        $quote = $quote->fresh();
        $this->assertFalse($quote->hasOptionSelection());
        $this->assertTrue($quote->hasOptions());
        $this->assertEquals(200, $quote->partial);
        $this->assertSame('ORIGINAL-PO', $quote->po_number);
        $this->assertEquals(110, $quote->amount);
        $this->assertEquals($original_line_items, json_decode($quote->getRawOriginal('line_items'), true));

        $quote = $this->selectOptions([2], $quote);
        $this->assertEquals(132, $quote->amount);
        $this->assertSame([2], $quote->backup->option_selection['selected_options']);
        $this->assertCount(3, $quote->backup->option_selection['line_items']);
    }

    public function testRestartPreservesConfigurationAndSigningCredentials(): void
    {
        $this->quote->backup = new QuoteBackup(guid: 'reserved-guid', rules: ['deposit' => ['amount' => 200]]);
        $this->quote->sync = new QuoteSync(qb_id: 'quickbooks-id', invitations: [['dn_id' => 'same-document']]);
        $this->quote->saveQuietly();
        $original_sync = json_decode($this->quote->getRawOriginal('sync'), true);
        $original_sync['dn_dirty'] = true;
        $service = app(QuoteOptionsService::class);
        $quote = $this->selectOptions();
        $sync = $quote->sync;
        $sync->dn_dirty = false;
        $quote->sync = $sync;
        $quote->saveQuietly();

        $quote = $service->restart($quote)->fresh();

        $this->assertSame($original_sync, json_decode($quote->getRawOriginal('sync'), true));
        $this->assertSame('reserved-guid', $quote->backup->guid);
        $this->assertSame(['deposit' => ['amount' => 200]], $quote->backup->rules);
        $this->assertNull($quote->backup->option_selection);
    }

    public function testRestartCannotOverwriteAnAdministrativelyChangedScope(): void
    {
        $quote = $this->selectOptions();
        $quote->terms = 'Changed outside the workflow';
        $quote->saveQuietly();
        $this->get(route('client.quotes.index'))->assertOk();

        $this->postJson(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(), 'restart_confirmed' => '1',
        ])->assertUnprocessable()->assertJsonValidationErrors('selected_options');

        $this->assertCount(2, $quote->fresh()->line_items);
        $this->assertSame('Changed outside the workflow', $quote->fresh()->terms);
    }

    public function testRestartIsUnavailableAfterSigningOrApproval(): void
    {
        $quote = $this->selectOptions();
        $quote->sync = new QuoteSync(dn_completed: true, dn_document_hashed_id: 'signed-pdf');
        $quote->saveQuietly();
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk()->assertDontSee('Restart selections');

        $this->post(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(), 'restart_confirmed' => '1',
        ])->assertStatus(409);

        $quote->sync = new QuoteSync();
        $quote->status_id = Quote::STATUS_APPROVED;
        $quote->saveQuietly();
        $this->post(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(), 'restart_confirmed' => '1',
        ])->assertStatus(409);

        $this->assertCount(2, $quote->fresh()->line_items);
    }

    public function testRestartCannotBeRequestedByAnotherClient(): void
    {
        $quote = $this->selectOptions();
        $client = Client::factory()->create(['company_id' => $this->contact->company_id, 'user_id' => $this->contact->user_id]);
        $contact = ClientContact::factory()->create([
            'company_id' => $client->company_id, 'client_id' => $client->id, 'user_id' => $client->user_id,
        ]);
        $this->actingAs($contact, 'contact');
        $this->get(route('client.quotes.index'))->assertOk();

        $this->post(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(), 'restart_confirmed' => '1',
        ])->assertForbidden();

        $this->assertTrue($quote->fresh()->hasOptionSelection());
    }

    public function testPreviouslySignedScopeCannotBeReplacedByOptionConfirmation(): void
    {
        $this->quote->sync = new QuoteSync(dn_completed: true, dn_document_hashed_id: 'signed-pdf');
        $this->quote->saveQuietly();

        Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->set('selected_options', ['1'])
            ->call('continueApproval')
            ->assertStatus(409);

        $this->assertCount(3, $this->quote->fresh()->line_items);
        $this->assertTrue($this->quote->fresh()->sync->dn_completed);
        $this->assertNull($this->quote->fresh()->backup);
    }

    public function testInclusiveTaxesAndDepositAreCalculatedForTheSelectedScope(): void
    {
        $this->quote->uses_inclusive_taxes = true;
        $this->quote->partial = 200;
        $preview = app(QuoteOptionsService::class)->preview($this->quote, [1]);

        $this->assertEquals(140, $preview->amount);
        $this->assertEquals(140, $preview->partial);
        $this->assertEquals(12.73, $preview->total_taxes);
        $this->assertEquals(110, $this->quote->fresh()->amount);
    }

    public function testDuplicateReviewCannotOverwriteTheOriginalOffer(): void
    {
        $first = Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->set('selected_options', ['1']);
        $second = Livewire::test(QuoteOptions::class, ['quote_id' => $this->quote->hashed_id])
            ->set('selected_options', ['2']);
        $first->call('continueApproval')->assertHasNoErrors();
        $selection = $this->quote->fresh()->backup->option_selection;

        $second->call('continueApproval')->assertStatus(409);

        $this->assertSame($selection, $this->quote->fresh()->backup->option_selection);
        $this->assertEquals(154, $this->quote->fresh()->amount);
        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
    }

    public function testSelectedScopeContinuesThroughTheExistingDocuNinjaApproval(): void
    {
        $this->enableDocuNinja();
        $quote = $this->selectOptions();
        $selection_key = $quote->backup->option_selection['key'];
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();

        $response = $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$this->quote->hashed_id],
            'action' => 'approve', 'process' => 'true', 'selection_key' => $selection_key,
        ])->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');

        $this->assertEquals(154, $this->quote->fresh()->amount);
        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
        $request_hash = $response->viewData('request_hash');
        $invitation = $this->quote->invitations()->first();

        Livewire::test(Sign::class, [
            '_key' => $invitation->key, 'invitation_id' => $invitation->id,
            'entity_type' => 'quote', 'db' => $this->quote->company->db, 'request_hash' => $request_hash,
        ])->call('docuNinjaSignatureCaptured')
            ->assertRedirectToRoute('client.quotes.approval.continue', ['request_hash' => $request_hash]);

        $this->post(route('client.quotes.bulk'), ['_token' => csrf_token(), 'request_hash' => $request_hash])
            ->assertRedirect();

        $this->assertEquals(154, $this->quote->fresh()->amount);
        $this->assertSame(Quote::STATUS_APPROVED, $this->quote->fresh()->status_id);
    }

    public function testAbandonedSigningCanResumeAfterContinuationCacheExpires(): void
    {
        $this->enableDocuNinja();
        $quote = $this->selectOptions();
        $selection_key = $quote->backup->option_selection['key'];

        $this->get(route('client.quote.show', $this->quote->hashed_id))
            ->assertOk()->assertSee('name="selection_key"', false);
        $response = $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$this->quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection_key,
        ])->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');

        Cache::forget($response->viewData('request_hash'));
        $this->get(route('client.quote.show', $this->quote->hashed_id))->assertOk();
        $resumed_response = $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$this->quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection_key,
        ])->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');

        $this->assertNotSame($response->viewData('request_hash'), $resumed_response->viewData('request_hash'));
        $this->assertEquals(154, $this->quote->fresh()->amount);
        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
        $this->assertSame($selection_key, $this->quote->fresh()->backup->option_selection['key']);
    }

    public function testRestartInvalidatesEarlierApprovalFormsAndSigningContinuations(): void
    {
        $this->enableDocuNinja();
        $service = app(QuoteOptionsService::class);
        $quote = $this->selectOptions();
        $selection_key = $quote->backup->option_selection['key'];
        $this->get(route('client.quote.show', $this->quote->hashed_id))->assertOk();
        $response = $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$this->quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection_key,
        ])->assertOk();

        $quote = $service->restart($this->quote);
        $quote = $this->selectOptions([2], $quote);
        $new_selection_key = $quote->backup->option_selection['key'];

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(),
            'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection_key,
        ])->assertStatus(409);
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'request_hash' => $response->viewData('request_hash'),
        ])->assertStatus(409);

        $quote = $quote->fresh();
        $this->assertEquals(132, $quote->amount);
        $this->assertSame(Quote::STATUS_SENT, $quote->status_id);
        $this->assertSame($new_selection_key, $quote->backup->option_selection['key']);
    }

    public function testSigningRefreshesOnceWithItsInvitationContactAndSavedSelectedScope(): void
    {
        $this->requireDocuNinjaApi();
        $finder = new ExecutableFinder();
        $chromium = $finder->find('chromium') ?? $finder->find('google-chrome');
        $pdftotext = $finder->find('pdftotext');
        if (! $chromium || ! $pdftotext) {
            $this->markTestSkipped('Requires Chromium and pdftotext for rendered PDF verification.');
        }
        config(['ninja.pdf_generator' => 'snappdf', 'ninja.snappdf_chromium_path' => $chromium]);
        $this->enableDocuNinja();
        $this->enableApprovalInput();
        $this->contact->forceFill(['is_primary' => false, 'email' => 'signer@example.test'])->save();
        ClientContact::factory()->create([
            'user_id' => $this->quote->user_id, 'company_id' => $this->quote->company_id,
            'client_id' => $this->quote->client_id, 'is_primary' => true, 'email' => 'primary@example.test',
        ]);
        $invitation = $this->quote->invitations()->first();
        $invitation->can_sign = true;
        $invitation->saveQuietly();
        $this->quote->public_notes = 'Recipient: $contact.email';
        $this->quote->sync = new QuoteSync(qb_id: 'keep-accounting', dn_dirty: true, invitations: [
            ['invitation_key' => $invitation->key, 'dn_id' => 'existing-document',
                'dn_invitation_id' => 'retained-invitation', 'dn_sig' => 'retained-signature'],
        ]);
        $this->quote->saveQuietly();
        $quote = $this->selectOptions();
        $sync = json_decode($quote->getRawOriginal('sync'), true);
        $sync['dn_dirty'] = false;
        Http::fake(['https://docu.test/api/ninja/refresh_ninja_pdf' => Http::response(['success' => true])]);
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $quote->backup->option_selection['key'], 'user_input' => 'PDF-REFRESH-PO',
        ])->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');

        Http::assertNothingSent();
        $this->assertTrue($quote->fresh()->sync->dn_dirty);
        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id, '_key' => $invitation->key,
        ])->call('loadDocuNinjaData')->assertSet('isReady', true)->assertSet('error', null);

        Http::assertSentCount(1);
        $requests = Http::recorded();
        $this->assertSame('existing-document', $requests[0][0]['document_id']);
        $this->assertSame($quote->company->company_key, $requests[0][0]['company_key']);
        $this->assertSame($quote->hashed_id, $requests[0][0]['entity_id']);
        $pdf = base64_decode($requests[0][0]['file'], true);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $text = (new Process([$pdftotext, '-', '-']))->setInput($pdf)->mustRun()->getOutput();
        $this->assertStringContainsString('Included work', $text);
        $this->assertStringContainsString('Chimney repairs', $text);
        $this->assertStringNotContainsString('Trim replacement', $text);
        $this->assertStringContainsString('PDF-REFRESH-PO', $text);
        $this->assertStringContainsString('154.00', $text);
        $this->assertStringContainsString('signer@example.test', $text);
        $this->assertStringNotContainsString('primary@example.test', $text);
        $this->assertSame($sync, json_decode($quote->fresh()->getRawOriginal('sync'), true));
        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);

        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $quote->backup->option_selection['key'], 'user_input' => 'PDF-REFRESH-PO',
        ])->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');
        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id, '_key' => $invitation->key,
        ])->call('loadDocuNinjaData')->assertSet('isReady', true);
        Http::assertSentCount(1);
    }

    public function testRestartAndReselectionRefreshOnlyWhenSigningResumes(): void
    {
        $this->requireDocuNinjaApi();
        $this->enableDocuNinja();
        $invitation = $this->quote->invitations()->first();
        $invitation->can_sign = true;
        $invitation->saveQuietly();
        $this->quote->sync = new QuoteSync(invitations: [[
            'invitation_key' => $invitation->key, 'dn_id' => 'existing-document',
            'dn_invitation_id' => 'retained-invitation', 'dn_sig' => 'retained-signature',
        ]]);
        $this->quote->saveQuietly();
        Http::fake(['https://docu.test/api/ninja/refresh_ninja_pdf' => Http::response(['success' => true])]);
        $quote = $this->selectOptions();
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $quote->backup->option_selection['key'],
        ])->assertOk();
        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id, '_key' => $invitation->key,
        ])->call('loadDocuNinjaData')->assertSet('isReady', true);
        Http::assertSentCount(1);

        $quote = app(QuoteOptionsService::class)->restart($quote->fresh());
        $this->assertTrue($quote->sync->dn_dirty);
        $quote = $this->selectOptions([2], $quote);
        $this->assertEquals(132, $quote->amount);
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $quote->backup->option_selection['key'],
        ])->assertOk();
        Http::assertSentCount(1);

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id, '_key' => $invitation->key,
        ])->call('loadDocuNinjaData')->assertSet('isReady', true);
        Http::assertSentCount(2);
        $this->assertFalse($quote->fresh()->sync->dn_dirty);
        $this->assertSame('retained-signature', $quote->fresh()->sync->getInvitation($invitation->key)['dn_sig']);
    }

    public function testFailedRefreshKeepsTheSelectionAndCredentialsAvailableForRetry(): void
    {
        $this->requireDocuNinjaApi();
        $this->enableDocuNinja();
        $invitation = $this->quote->invitations()->first();
        $invitation->can_sign = true;
        $invitation->saveQuietly();
        $this->quote->sync = new QuoteSync(invitations: [[
            'invitation_key' => $invitation->key, 'dn_id' => 'existing-document',
            'dn_invitation_id' => 'retained-invitation', 'dn_sig' => 'retained-signature',
        ]]);
        $this->quote->saveQuietly();
        $quote = $this->selectOptions();
        $selection = $quote->backup->option_selection;
        $sync = $quote->getRawOriginal('sync');
        Http::fake(['https://docu.test/api/ninja/refresh_ninja_pdf' => Http::sequence()
            ->push(['message' => 'Unavailable'], 503)->push(['success' => true])]);
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $request = [
            '_token' => csrf_token(), 'quotes' => [$quote->hashed_id], 'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection['key'],
        ];

        $this->post(route('client.quotes.bulk'), $request)
            ->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');
        Http::assertNothingSent();
        $component = Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id, '_key' => $invitation->key,
        ])->call('loadDocuNinjaData')
            ->assertSet('isReady', false)->assertNotDispatched('docuninja-loader-ready');
        $this->assertSame($selection, $quote->fresh()->backup->option_selection);
        $this->assertSame($sync, $quote->fresh()->getRawOriginal('sync'));
        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);

        $component->call('retryLoading')->assertSet('isReady', true)->assertSet('error', null);
        $this->assertSame($selection, $quote->fresh()->backup->option_selection);
        $this->assertFalse($quote->fresh()->sync->dn_dirty);
        Http::assertSentCount(2);
    }

    public function testNewOptionalQuoteCreatesASigningDocumentWithoutRefreshingIt(): void
    {
        $this->requireDocuNinjaApi();
        $quote = $this->selectOptions();
        $this->assertFalse($quote->sync->dn_dirty);
        Http::fake(['https://docu.test/api/ninja/signable_entity/' => Http::response([
            'document_id' => 'new-document', 'document_invitation_id' => 'new-invitation', 'sig' => 'signature',
        ], 201)]);

        $credentials = $quote->service()->getDocuNinjaSignable();

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'refresh_ninja_pdf'));
        $this->assertSame('new-document', $credentials['document_id']);
        $this->assertStringStartsWith('%PDF-', base64_decode(Http::recorded()[0][0]['file'], true));
    }

    public function testResolvingAnUnlinkedSigningDocumentRefreshesBeforeReturningCredentials(): void
    {
        $this->requireDocuNinjaApi();
        $quote = $this->selectOptions();
        Http::fake([
            'https://docu.test/api/ninja/signable_entity/' => Http::response([
                'document_id' => 'resolved-document', 'document_invitation_id' => 'same-invitation', 'sig' => 'same-signature',
            ]),
            'https://docu.test/api/ninja/refresh_ninja_pdf' => Http::response(['success' => true]),
        ]);

        $credentials = $quote->service()->getDocuNinjaSignable();

        Http::assertSentCount(2);
        $requests = Http::recorded();
        $this->assertSame($requests[0][0]['file'], $requests[1][0]['file']);
        $this->assertSame('resolved-document', $requests[1][0]['document_id']);
        $this->assertSame('same-invitation', $credentials['document_invitation_id']);
        $this->assertSame('same-signature', $credentials['sig']);
        $this->assertSame([], $quote->fresh()->sync->invitations);
    }

    public function testFailedUnlinkedRefreshDoesNotReturnSigningCredentials(): void
    {
        $this->requireDocuNinjaApi();
        $quote = $this->selectOptions();
        Http::fake([
            'https://docu.test/api/ninja/signable_entity/' => Http::response([
                'document_id' => 'resolved-document', 'document_invitation_id' => 'invitation', 'sig' => 'signature',
            ]),
            'https://docu.test/api/ninja/refresh_ninja_pdf' => Http::response([], 503),
        ]);
        $this->expectException(RequestException::class);

        try {
            $quote->service()->getDocuNinjaSignable();
        } finally {
            $this->assertFalse($quote->fresh()->sync->dn_completed);
            $this->assertSame([], $quote->fresh()->sync->invitations);
        }
    }

    public function testOrdinaryQuoteSigningDoesNotRequestAPdfRefresh(): void
    {
        $this->requireDocuNinjaApi();
        $this->quote->line_items = [$this->quote->line_items[0]];
        $this->quote->calc()->getInvoice();
        Http::fake(['https://docu.test/api/ninja/signable_entity/' => Http::response([
            'document_id' => 'ordinary-document', 'document_invitation_id' => 'invitation', 'sig' => 'signature',
        ])]);

        $this->quote->fresh()->service()->getDocuNinjaSignable();

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'refresh_ninja_pdf'));
    }

    public function testReopeningStartedSigningResumesTheCurrentSelection(): void
    {
        $this->enableDocuNinja();
        $this->enableApprovalInput();
        $quote = $this->selectOptions();
        $quote = app(QuoteOptionsService::class)->captureApprovalInput(
            $quote, 'CONFIRMED-PO', $quote->backup->option_selection['key'],
        );
        $selection = $quote->backup->option_selection;
        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.quotes.approve');
        $this->signingWindow();
        Http::fake();

        $response = $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');
        $continuation = Cache::get($response->viewData('request_hash'));

        $this->assertSame($selection['key'], $continuation['request']['selection_key']);
        $this->assertSame($this->contact->id, $continuation['client_contact_id']);
        $this->assertSame($quote->invitations()->first()->id, $response->viewData('invitation_id'));
        $this->assertSame('CONFIRMED-PO', $quote->fresh()->po_number);
        $this->assertSame($selection, $quote->fresh()->backup->option_selection);
        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);
        Http::assertNothingSent();
    }

    public function testPreparedSigningResumesAfterItsContextAndContinuationExpire(): void
    {
        $this->enableDocuNinja();
        $quote = $this->selectOptions();
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->signingWindow('expired-signing-continuation');
        $invitation = $quote->invitations()->first();
        Cache::forget($invitation->key);
        Cache::forget('expired-signing-continuation');
        $quote = $quote->fresh();
        $sync = $quote->sync;
        $sync->dn_dirty = false;
        $quote->sync = $sync;
        $quote->saveQuietly();
        $document = $sync->getInvitation($invitation->key);
        Http::fake();

        $response = $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');

        $this->assertSame($quote->backup->option_selection['key'],
            Cache::get($response->viewData('request_hash'))['request']['selection_key']);
        $this->assertSame($document, $quote->fresh()->sync->getInvitation($invitation->key));
        Http::assertNothingSent();
    }

    public function testStaleReviewCannotChangeThePoNumberDuringSigning(): void
    {
        $this->enableDocuNinja();
        $this->enableApprovalInput();
        $quote = $this->selectOptions();
        $quote = app(QuoteOptionsService::class)->captureApprovalInput(
            $quote, 'CONFIRMED-PO', $quote->backup->option_selection['key'],
        );
        $selection = $quote->backup->option_selection;
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->signingWindow();

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'quotes' => [$quote->hashed_id],
            'action' => 'approve', 'process' => 'true',
            'selection_key' => $selection['key'], 'user_input' => 'CHANGED-PO',
        ])->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');

        $this->assertSame('CONFIRMED-PO', $quote->fresh()->po_number);
        $this->assertSame($selection, $quote->fresh()->backup->option_selection);
    }

    public function testFailedSigningRefreshCanBeReopenedOnTheSigner(): void
    {
        $this->requireDocuNinjaApi();
        $this->enableDocuNinja();
        $quote = $this->selectOptions();
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->signingWindow();
        $quote = $quote->fresh();
        $sync = $quote->sync;
        $sync->dn_dirty = true;
        $quote->sync = $sync;
        $quote->saveQuietly();
        $invitation = $quote->invitations()->first();
        Http::fake(['https://docu.test/api/ninja/refresh_ninja_pdf' => Http::response([], 503)]);

        Livewire::test(DocuNinjaLoader::class, [
            'invitation_id' => $invitation->id, '_key' => $invitation->key,
        ])->call('loadDocuNinjaData')->assertSet('isReady', false);

        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');
        $this->assertTrue($quote->fresh()->sync->dn_dirty);
        $this->assertSame($quote->backup->option_selection, $quote->fresh()->backup->option_selection);
        Http::assertSentCount(1);
    }

    public function testDocuNinjaOptionsCanOnlyBeRestartedFromTheSigner(): void
    {
        $this->enableDocuNinja();
        $quote = $this->selectOptions();
        $selection = $quote->backup->option_selection;

        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.quotes.approve')
            ->assertDontSee(route('client.quote.options.restart', $quote->hashed_id), false);
        $window = $this->signingWindow();

        $this->post(route('client.quote.options.restart', $quote->hashed_id), [
            '_token' => csrf_token(), 'restart_confirmed' => '1',
        ])->assertRedirectToRoute('client.quote.show', $quote->hashed_id);
        $this->assertSame($selection, $quote->fresh()->backup->option_selection);

        $window->call('cancelQuoteSigning', 'poll-document', 'poll-invitation')
            ->assertRedirectToRoute('client.quote.show', ['quote' => $quote->hashed_id]);
        $this->assertTrue($quote->fresh()->hasOptions());
        $this->assertNull($quote->fresh()->backup->option_selection);
    }

    public function testAnOldDocumentAndContextCannotSkipFirstEntryApprovalInput(): void
    {
        $this->enableDocuNinja();
        $this->enableApprovalInput();
        $this->signingWindow();
        $quote = $this->selectOptions();
        $invitation = $quote->invitations()->first();
        Cache::put($invitation->key, ['selection_key' => str_repeat('x', 64)], 60);

        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.quotes.approve')
            ->assertSee('name="accept-user-input" content="1"', false);
        $this->assertTrue($quote->fresh()->sync->dn_dirty);
    }

    public function testApprovingOneSelectedQuoteFromTheListRejoinsItsSigningFlow(): void
    {
        $this->enableDocuNinja();
        $quote = $this->selectOptions();
        $this->get(route('client.quote.show', $quote->hashed_id))->assertOk();
        $this->signingWindow();

        $this->post(route('client.quotes.bulk'), [
            '_token' => csrf_token(), 'quotes' => [$quote->hashed_id], 'action' => 'approve',
        ])->assertRedirectToRoute('client.quote.show', $quote->hashed_id);
        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertOk()->assertViewIs('portal.ninja2020.components.docuninja');
        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);
    }

    public function testGroupedApprovalCannotApproveOptionsOrOtherQuotesInTheGroup(): void
    {
        $ordinary_quote = $this->quote->replicate();
        $ordinary_quote->number = 'ORDINARY-GROUP';
        $ordinary_quote->line_items = [$this->quote->line_items[0]];
        $ordinary_quote->saveQuietly();
        $ordinary_quote->calc()->getInvoice();
        $this->get(route('client.quote.show', $this->quote->hashed_id))
            ->assertRedirectToRoute('client.quote.options', $this->quote->hashed_id);
        $request = [
            '_token' => csrf_token(), 'quotes' => [$ordinary_quote->hashed_id, $this->quote->hashed_id],
            'action' => 'approve', 'process' => 'true',
        ];

        $this->post(route('client.quotes.bulk'), $request)
            ->assertRedirectToRoute('client.quote.options', $this->quote->hashed_id);
        $this->assertSame(Quote::STATUS_SENT, $ordinary_quote->fresh()->status_id);
        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);

        $quote = $this->selectOptions();
        $this->post(route('client.quotes.bulk'), $request)->assertStatus(409);
        $this->assertSame(Quote::STATUS_SENT, $ordinary_quote->fresh()->status_id);
        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status_id);
        $this->assertSame($quote->backup->option_selection, $quote->fresh()->backup->option_selection);
    }

    public function testSignerCancellationRestoresOptionsAndReturnsOtherSigningWindows(): void
    {
        $this->enableDocuNinja();
        $quote = $this->selectOptions();
        $original_items = $quote->backup->option_selection['line_items'];
        Http::fake();
        Cache::put('cancelled-signing-request', ['request' => []], 60);

        $first_window = $this->signingWindow('cancelled-signing-request');
        $second_window = $this->signingWindow('other-signing-request');
        $second_window->call('docuninjaLoaderReady')
            ->assertSee('wire:poll.3s.keep-alive', false)
            ->call('checkSigningState')->assertNoRedirect();
        $this->assertArrayNotHasKey('html', $second_window->effects);

        $first_window->dispatch('docuninja-signing-cancelled',
            document_id: 'poll-document', document_invitation_id: 'poll-invitation',
        )->assertRedirectToRoute('client.quote.show', ['quote' => $quote->hashed_id]);

        $quote = $quote->fresh();
        $this->assertNull($quote->backup->option_selection);
        $this->assertTrue($quote->hasOptions());
        $this->assertEquals($original_items, json_decode($quote->getRawOriginal('line_items'), true));
        $this->assertFalse($quote->sync->dn_completed);
        $this->assertSame('poll-document', $quote->sync->invitations[0]['dn_id']);
        $this->assertFalse(Cache::has('cancelled-signing-request'));
        $this->assertFalse(Cache::has($quote->invitations()->first()->key));
        Http::assertNothingSent();

        $second_window->call('checkSigningState')
            ->assertRedirectToRoute('client.quote.show', ['quote' => $quote->hashed_id]);
        $this->get(route('client.quote.show', $quote->hashed_id))
            ->assertRedirectToRoute('client.quote.options', $quote->hashed_id);
    }

    public function testStaleSignerCancellationCannotResetAReplacementSelection(): void
    {
        $this->selectOptions();
        $window = $this->signingWindow();
        $service = app(QuoteOptionsService::class);
        $quote = $service->restart($this->quote->fresh());
        $quote = $this->selectOptions([2], $quote);
        $selection_key = $quote->backup->option_selection['key'];
        $context_key = $quote->invitations()->first()->key;
        Cache::put($context_key, ['replacement' => true], 60);

        $window->call('cancelQuoteSigning', 'poll-document', 'poll-invitation')
            ->assertRedirectToRoute('client.quote.show', ['quote' => $quote->hashed_id]);

        $this->assertSame($selection_key, $quote->fresh()->backup->option_selection['key']);
        $this->assertSame([2], $quote->fresh()->backup->option_selection['selected_options']);
        $this->assertSame(['replacement' => true], Cache::get($context_key));
    }

    public function testSignerCancellationIgnoresAnotherDocument(): void
    {
        $quote = $this->selectOptions();
        $window = $this->signingWindow();

        $window->call('cancelQuoteSigning', 'different-document', 'poll-invitation')
            ->assertNoRedirect()
            ->call('cancelQuoteSigning', 'poll-document', 'different-invitation')
            ->assertNoRedirect();

        $this->assertSame($quote->backup->option_selection['key'], $quote->fresh()->backup->option_selection['key']);
    }

    public function testCompletedSignerCancellationPreservesTheConfirmedOptions(): void
    {
        $quote = $this->selectOptions();
        $window = $this->signingWindow();
        $quote = $quote->fresh();
        $sync = $quote->sync;
        $sync->dn_completed = true;
        $quote->sync = $sync;
        $quote->saveQuietly();

        $window->call('cancelQuoteSigning', 'poll-document', 'poll-invitation')
            ->assertRedirectToRoute('client.quote.show', ['quote' => $quote->hashed_id]);

        $this->assertSame($quote->backup->option_selection['key'], $quote->fresh()->backup->option_selection['key']);
        $this->assertTrue($quote->fresh()->sync->dn_completed);
    }

    public function testSignerPollingAndCancellationDoNotChangeOrdinaryQuotes(): void
    {
        $items = $this->quote->line_items;
        foreach ($items as $item) {
            $item->is_optional = false;
        }
        $this->quote->line_items = $items;
        $this->quote->saveQuietly();
        $window = $this->signingWindow();

        $window->assertSet('selection_key', null)
            ->assertDontSee('wire:poll.3s.keep-alive', false)
            ->call('docuninjaLoaderReady')
            ->call('checkSigningState')->assertNoRedirect()
            ->call('cancelQuoteSigning', 'poll-document', 'poll-invitation')->assertNoRedirect();

        $this->assertSame(Quote::STATUS_SENT, $this->quote->fresh()->status_id);
        $this->assertFalse($this->quote->fresh()->sync->dn_completed);
    }

    public function testSignerActionsRejectAnotherContact(): void
    {
        $quote = $this->selectOptions();
        $polling_window = $this->signingWindow();
        $cancelling_window = $this->signingWindow();
        $other_contact = ClientContact::factory()->create([
            'company_id' => $quote->company_id,
            'client_id' => $quote->client_id,
            'user_id' => $quote->user_id,
        ]);
        $this->actingAs($other_contact, 'contact');

        $polling_window->call('checkSigningState')->assertForbidden();
        $cancelling_window->call('cancelQuoteSigning', 'poll-document', 'poll-invitation')
            ->assertForbidden();
        $this->assertSame($quote->backup->option_selection['key'], $quote->fresh()->backup->option_selection['key']);
    }

    private function signingWindow(?string $request_hash = null): Testable
    {
        $quote = $this->quote->fresh();
        $invitation = $quote->invitations()->first();
        $invitation->can_sign = true;
        $invitation->saveQuietly();
        $sync = $quote->sync;
        if (! $sync->getInvitation($invitation->key)) {
            $sync->addInvitation($invitation->key, 'poll-document', 'poll-invitation', 'poll-signature');
            $quote->sync = $sync;
            $quote->saveQuietly();
        }
        Cache::put($invitation->key, ['docuninja_signable' => [
            'document_id' => 'poll-document',
            'document_invitation_id' => 'poll-invitation',
            'sig' => 'poll-signature',
            'company_key' => $quote->company->company_key,
        ]], 60);

        return Livewire::test(Sign::class, [
            '_key' => $invitation->key,
            'invitation_id' => $invitation->id,
            'entity_type' => 'quote',
            'db' => $quote->company->db,
            'request_hash' => $request_hash,
        ]);
    }

    private function requireDocuNinjaApi(): void
    {
        if (! class_exists(\InvoiceNinja\AdminApi\Services\DocuNinja\DocuNinja::class)) {
            $this->markTestSkipped('Requires the optional admin-api package.');
        }
        config(['ninja.docuninja_api_url' => 'https://docu.test', 'ninja.ninja_hosted_secret' => 'refresh-secret']);
        Http::preventStrayRequests();
    }

    private function selectOptions(array $selected_options = [1], ?Quote $quote = null): Quote
    {
        $quote = ($quote ?? $this->quote)->fresh();
        $service = app(QuoteOptionsService::class);

        return $service->apply($quote, $selected_options, $service->revision($quote));
    }

    private function enableApprovalInput(): void
    {
        $client = $this->quote->client;
        $settings = $client->settings;
        $settings->accept_client_input_quote_approval = true;
        $client->settings = $settings;
        $client->save();
        $this->contact->unsetRelations();
    }

    private function enableDocuNinja(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->quote->company->forceFill(['enable_modules' => true])->saveQuietly();
        $client = $this->quote->client;
        $settings = $client->settings;
        $settings->require_quote_signature = true;
        $client->settings = $settings;
        $client->save();
        $this->contact->unsetRelations();
    }
}
