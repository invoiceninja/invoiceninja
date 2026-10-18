<?php

namespace Tests\Feature\ClientPortal;

use App\Http\ViewComposers\PortalComposer;
use App\Livewire\RecurringInvoices\UpdateAutoBilling;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutAutoBillingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'checkout_autobilling_test',
            'database.connections.checkout_autobilling_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);

        // These tests exercise persistence without connecting to an application database.
        Schema::create('recurring_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('client_id');
            $table->string('number');
            $table->string('auto_bill');
            $table->boolean('auto_bill_enabled');
            $table->boolean('is_deleted')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('recurring_id');
            $table->unsignedInteger('status_id');
            $table->decimal('balance');
            $table->boolean('auto_bill_enabled');
            $table->boolean('is_deleted')->default(false);
            $table->softDeletes();
            $table->timestamps();
        });

        $this->actingAs((new ClientContact())->forceFill([
            'id' => 1,
            'client_id' => 10,
            'company_id' => 20,
        ]), 'contact');

        // The global portal composer builds navigation; it is unrelated to this component.
        $composer = Mockery::mock(PortalComposer::class);
        $composer->shouldReceive('compose')->andReturnNull();
        $this->app->instance(PortalComposer::class, $composer);
    }

    private function recurring(string $policy, bool $enabled, array $extra = []): int
    {
        return DB::table('recurring_invoices')->insertGetId(array_merge([
            'company_id' => 20,
            'client_id' => 10,
            'number' => 'R-001',
            'auto_bill' => $policy,
            'auto_bill_enabled' => $enabled,
        ], $extra));
    }

    private function invoice(int $recurringId, array $extra = []): int
    {
        return DB::table('invoices')->insertGetId(array_merge([
            'company_id' => 20,
            'client_id' => 10,
            'recurring_id' => $recurringId,
            'status_id' => Invoice::STATUS_SENT,
            'balance' => 100,
            'auto_bill_enabled' => false,
        ], $extra));
    }

    private function checkoutInvoiceId(int $recurringId): string
    {
        $id = DB::table('invoices')->where('recurring_id', $recurringId)->orderBy('id')->value('id') ?? $this->invoice($recurringId);

        return (new Invoice())->forceFill(['id' => $id])->hashed_id;
    }

    private function autoBillingComponent(int $id, bool $radios = true)
    {
        return Livewire::test(UpdateAutoBilling::class, [
            'invoice_id' => $id,
            'checkout_invoice_id' => $this->checkoutInvoiceId($id),
            'db' => 'checkout_autobilling_test',
            'show_radios' => $radios,
        ]);
    }

    public static function preferences(): array
    {
        return [
            'opt in default' => ['optin', false],
            'opt in previously enabled' => ['optin', true],
            'opt out default' => ['optout', true],
            'opt out previously disabled' => ['optout', false],
        ];
    }

    #[DataProvider('preferences')]
    public function testRadiosReflectSavedPreferenceAndPersistExplicitChoice(string $policy, bool $enabled): void
    {
        $id = $this->recurring($policy, $enabled);
        $component = $this->autoBillingComponent($id);
        $component->assertSee('Automatically pay future invoices in this recurring series?');

        $document = new \DOMDocument();
        @$document->loadHTML($component->html());
        $checked = (new \DOMXPath($document))->query('//input[@type="radio" and @checked]');
        $this->assertCount(1, $checked);
        $this->assertSame($enabled ? '1' : '0', $checked->item(0)->getAttribute('value'));

        // Retried explicit choices must not toggle the value back.
        $component->call('setAutoBilling', ! $enabled)->call('setAutoBilling', ! $enabled);
        $this->assertSame(! $enabled, (bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));
    }

    public function testOnlyOutstandingInvoicesInThisSeriesAreUpdated(): void
    {
        $id = $this->recurring('optin', false);
        $sent = $this->invoice($id);
        $partial = $this->invoice($id, ['status_id' => Invoice::STATUS_PARTIAL]);
        $excluded = [
            $this->invoice($id, ['status_id' => Invoice::STATUS_PAID, 'balance' => 0]),
            $this->invoice($id, ['status_id' => Invoice::STATUS_DRAFT]),
            $this->invoice($id, ['is_deleted' => true]),
            $this->invoice($id, ['client_id' => 11]),
            $this->invoice($id, ['company_id' => 21]),
            $this->invoice($this->recurring('optin', false)),
        ];

        $this->autoBillingComponent($id)->call('setAutoBilling', true);
        foreach ([$sent, $partial] as $invoiceId) {
            $this->assertTrue((bool) DB::table('invoices')->where('id', $invoiceId)->value('auto_bill_enabled'));
        }
        foreach ($excluded as $invoiceId) {
            $this->assertFalse((bool) DB::table('invoices')->where('id', $invoiceId)->value('auto_bill_enabled'));
        }

        $this->autoBillingComponent($id)->call('setAutoBilling', false);
        $this->assertSame(0, DB::table('invoices')->where('auto_bill_enabled', true)->count());
    }

    public function testForcedPoliciesCannotBeChanged(): void
    {
        foreach (['always' => true, 'off' => false] as $policy => $enabled) {
            $id = $this->recurring($policy, $enabled);
            $this->autoBillingComponent($id)
                ->assertDontSee('type="radio"', false)
                ->call('setAutoBilling', ! $enabled);
            $this->assertSame($enabled, (bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));
        }
    }

    public function testOtherClientsCompaniesAndDeletedSeriesAreInaccessible(): void
    {
        foreach ([['client_id' => 11], ['company_id' => 21], ['is_deleted' => true]] as $extra) {
            $id = $this->recurring('optin', false, $extra);
            $this->autoBillingComponent($id)->assertDontSee('type="radio"', false);

            $component = new UpdateAutoBilling();
            $component->invoice_id = $id;
            $component->db = 'checkout_autobilling_test';
            $component->show_radios = true;
            try {
                $component->setAutoBilling(true);
                $this->fail('An inaccessible recurring invoice must not be updated.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(404, $exception->getStatusCode());
            }
            $this->assertFalse((bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));
        }
    }

    public function testExistingCheckboxStillToggles(): void
    {
        $id = $this->recurring('optin', false);
        $this->autoBillingComponent($id, false)->call('updateAutoBilling');
        $this->assertTrue((bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));
    }

    public function testOnlyTheFirstInvoiceCanChangeCheckoutAutoBilling(): void
    {
        $id = $this->recurring('optin', false);
        $first = $this->invoice($id, ['status_id' => Invoice::STATUS_PARTIAL]);
        $second = $this->invoice($id);
        $this->autoBillingComponent($id)->assertSee('type="radio"', false)->call('setAutoBilling', true);
        $this->assertTrue((bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));

        $later = Livewire::test(UpdateAutoBilling::class, [
            'invoice_id' => $id,
            'checkout_invoice_id' => (new Invoice())->forceFill(['id' => $second])->hashed_id,
            'db' => 'checkout_autobilling_test',
            'show_radios' => true,
        ])->assertDontSee('type="radio"', false)->call('setAutoBilling', false);
        $this->assertTrue((bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));

        // Archiving/deleting the original invoice must not promote a later invoice.
        DB::table('invoices')->where('id', $first)->update(['deleted_at' => now(), 'is_deleted' => true]);
        $later->call('setAutoBilling', false)->assertDontSee('type="radio"', false);
        $this->assertTrue((bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));

        $this->autoBillingComponent($id, false)->call('updateAutoBilling');
        $this->assertFalse((bool) DB::table('recurring_invoices')->where('id', $id)->value('auto_bill_enabled'));
    }

    public function testAutoBillingYesForcesSavingAndNoRestoresGatewayChoices(): void
    {
        foreach (['off', 'always', 'optin', 'optout'] as $policy) {
            $id = $this->recurring('optout', true);
            $component = Livewire::test(UpdateAutoBilling::class, [
                'invoice_id' => $id,
                'checkout_invoice_id' => $this->checkoutInvoiceId($id),
                'db' => 'checkout_autobilling_test',
                'show_radios' => true,
                'token_billing_policy' => $policy,
            ]);

            $document = new \DOMDocument();
            @$document->loadHTML($component->html());
            $xpath = new \DOMXPath($document);
            $this->assertCount(1, $xpath->query('//input[@name="token-billing-checkbox" and @value="true" and @checked and @hidden]'));
            $this->assertCount(0, $xpath->query('//input[@name="token-billing-checkbox" and @value="false"]'));

            $component->call('setAutoBilling', false);
            @$document->loadHTML($component->html());
            $xpath = new \DOMXPath($document);
            $default = in_array($policy, ['always', 'optout'], true) ? 'true' : 'false';
            $this->assertCount(1, $xpath->query('//input[@name="token-billing-checkbox" and @value="' . $default . '" and @checked]'));
            $this->assertCount(in_array($policy, ['optin', 'optout'], true) ? 2 : 0, $xpath->query('//input[@name="token-billing-checkbox" and not(@hidden)]'));

            $component->call('setAutoBilling', true);
            @$document->loadHTML($component->html());
            $xpath = new \DOMXPath($document);
            $this->assertCount(1, $xpath->query('//input[@name="token-billing-checkbox" and @value="true" and @checked and @hidden]'));
        }
    }

    public function testForcedAutoBillingRequiresSavingEvenWhenGatewaySavingIsOptional(): void
    {
        $id = $this->recurring('always', true);
        $component = Livewire::test(UpdateAutoBilling::class, [
            'invoice_id' => $id,
            'checkout_invoice_id' => $this->checkoutInvoiceId($id),
            'db' => 'checkout_autobilling_test',
            'show_radios' => true,
            'token_billing_policy' => 'optin',
        ])->assertDontSee('name="auto_bill_enabled_', false);

        $document = new \DOMDocument();
        @$document->loadHTML($component->html());
        $xpath = new \DOMXPath($document);
        $this->assertCount(1, $xpath->query('//input[@name="token-billing-checkbox" and @value="true" and @checked and @hidden]'));
        $this->assertCount(0, $xpath->query('//input[@name="token-billing-checkbox" and @value="false"]'));
    }

    public function testSharedPartialUsesFirstInvoiceAndGroupsPaymentMethodPreferences(): void
    {
        $optin = $this->recurring('optin', false);
        $optout = $this->recurring('optout', true);

        foreach (['off', 'always', 'optin', 'optout'] as $tokenBilling) {
            $gateway = (new CompanyGateway())->forceFill(['id' => 1, 'token_billing' => $tokenBilling]);
            $gateway->setRelation('company', (new Company())->forceFill(['db' => 'checkout_autobilling_test']));
            $html = view('portal.ninja2020.gateways.includes.save_card', [
                'gateway' => $gateway,
                'invoices' => collect([
                    ['recurring_invoice_id' => $optin, 'invoice_id' => $this->checkoutInvoiceId($optin)],
                    ['recurring_invoice_id' => $optout],
                ]),
            ])->render();

            $document = new \DOMDocument();
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);
            $this->assertCount(2, $xpath->query('//input[starts-with(@name, "auto_bill_enabled_")]'));
            $this->assertCount(2, $xpath->query('//input[@name="auto_bill_enabled_' . $optin . '"]'));
            $this->assertCount(0, $xpath->query('//*[@id="save-card--container"]//input[starts-with(@name, "auto_bill_enabled_")]'));
            $this->assertCount(1, $xpath->query('//input[starts-with(@name, "auto_bill_enabled_") and @checked]'));
        }
    }

    public function testSharedPartialAcceptsMissingMetadataAndArrayPayloads(): void
    {
        $gateway = (new CompanyGateway())->forceFill(['token_billing' => 'optin']);
        $recurringId = $this->recurring('optin', false);
        foreach ([
            [],
            ['invoices' => []],
            ['invoices' => [['invoice_id' => 'legacy']]],
            ['invoices' => [['recurring_invoice_id' => null], ['recurring_invoice_id' => $recurringId]]],
        ] as $payload) {
            $html = view('portal.ninja2020.gateways.includes.save_card', ['gateway' => $gateway] + $payload)->render();
            $this->assertStringContainsString('token-billing-checkbox', $html);
            $this->assertStringNotContainsString('name="auto_bill_enabled_', $html);
        }
    }
}
