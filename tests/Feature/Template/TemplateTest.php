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

namespace Tests\Feature\Template;

use App\Jobs\Entity\CreateRawPdf;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker;
use App\Services\Template\TemplateMock;
use App\Services\Template\TemplateService;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\App;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers
 */
class TemplateTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;
    use MakesDates;

    private string $body = '
            
                <ninja>
                    $company.name
                    <table class="min-w-full text-left text-sm font-light">
                        <thead class="border-b font-medium dark:border-neutral-500">
                            <tr class="text-sm leading-normal">
                                <th scope="col" class="px-6 py-4">Item #</th>
                                <th scope="col" class="px-6 py-4">Description</th>
                                <th scope="col" class="px-6 py-4">Ordered</th>
                                <th scope="col" class="px-6 py-4">Delivered</th>
                                <th scope="col" class="px-6 py-4">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                        {% for entity in invoices %}
                        {% for item in entity.line_items|filter(item => item.type_id == "1") %}
                            <tr class="border-b dark:border-neutral-500">
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.product_key }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.notes }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">0</td>
                            </tr>
                        {% endfor %}
                        {% endfor %}
                        </tbody>
                    </table>
                </ninja>
            
            ';

    private string $nested_body = '
            
                <ninja>
                    $company.name
                    <table class="min-w-full text-left text-sm font-light">
                        <thead class="border-b font-medium dark:border-neutral-500">
                            <tr class="text-sm leading-normal">
                                <th scope="col" class="px-6 py-4">Item #</th>
                                <th scope="col" class="px-6 py-4">Description</th>
                                <th scope="col" class="px-6 py-4">Ordered</th>
                                <th scope="col" class="px-6 py-4">Delivered</th>
                                <th scope="col" class="px-6 py-4">Outstanding</th>
                            </tr>
                        </thead>
                        <tbody>
                        {% for entity in invoices %}
                        Client Name: {{ entity.client.name }}
                        Client Name with variables = $client.name
                        {% for item in entity.line_items|filter(item => item.type_id == "1") %}
                            <tr class="border-b dark:border-neutral-500">
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.product_key }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.notes }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ item.quantity }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">0</td>
                            </tr>
                        {% endfor %}
                        {% endfor %}
                        </tbody>
                    </table>
                </ninja>
            
            ';

    private string $payments_body = '
            CoName: $company.name
            ClName: $client.name
            InNumber: $invoice.number
        <ninja>
            CoName: $company.name
            ClName: $client.name
            InNumber: $invoice.number
                <table class="min-w-full text-left text-sm font-light">
                    <thead class="border-b font-medium dark:border-neutral-500">
                        <tr class="text-sm leading-normal">
                            <th scope="col" class="px-6 py-4">Invoice #</th>
                            <th scope="col" class="px-6 py-4">Date</th>
                            <th scope="col" class="px-6 py-4">Due Date</th>
                            <th scope="col" class="px-6 py-4">Total</th>
                            <th scope="col" class="px-6 py-4">Transaction</th>
                            <th scope="col" class="px-6 py-4">Outstanding</th>
                        </tr>
                    </thead>

                    <tbody>
                    {% for invoice in invoices %}
                        <tr class="border-b dark:border-neutral-500">
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.number }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.date }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.due_date }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.amount }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.balance }}</td>
                        </tr>

                        {% for payment in invoice.payments|filter(payment => payment.is_deleted == false) %}
                        
                            {% for pivot in payment.paymentables %}

                            <tr class="border-b dark:border-neutral-500">
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ payment.number }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ payment.date }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">
                                {% if pivot.amount_raw > 0 %}
                                    {{ pivot.amount }} - {{ payment.type.name }}
                                {% else %}
                                    ({{ pivot.refunded }})
                                {% endif %}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                            </tr>

                            {% endfor %}
                        {% endfor %}
                    {% endfor%}
                    </tbody>
                </table>
                
            </ninja>
        ';

    private string $broken_twig_template = '
    <tbody>
                    {% for invoice in invoices %}
                        <tr class="border-b dark:border-neutral-500">
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.number }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.date }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.due_date }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.amount }}</td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                            <td class="whitespace-nowrap px-6 py-4 font-medium">{{ invoice.balance }}</td>
                        </tr>

                        {% for payment in invoice.payments|filter(payment => payment.is_deleted == false) %}
                        
                            {% for pivot in payment.paymentables %}

                            <tr class="border-b dark:border-neutral-500">
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ payment.number }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">{{ payment.date }}</td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium">
                                {% if pivot.amount_raw >
                                    {{ pivot.amount }} - {{ payment.type.name }}
                                {% else %}
                                    ({{ pivot.refunded }})
                                {% endif
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                                <td class="whitespace-nowrap px-6 py-4 font-medium"></td>
                            </tr>

                            {% endfor %}
                        {% endfor %}
                    {% endfor%}
                    </tbody>
    ';

    private string $stack = '<html><div id="company-details" labels="true"></div></html>';

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

    }

    public function testLintingSuccess()
    {

        $ts = new TemplateService();
        $twig = $ts->twig;

        try {
            $twig->parse($twig->tokenize(new \Twig\Source($this->payments_body, '')));
            $this->assertTrue(true);
            echo json_encode(['status' => 'ok']);
        } catch (\Twig\Error\SyntaxError $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }

    }

    public function testLintingFailure()
    {

        $ts = new TemplateService();
        $twig = $ts->twig;

        try {
            $twig->parse($twig->tokenize(new \Twig\Source($this->broken_twig_template, '')));
            echo json_encode(['status' => 'ok']);
        } catch (\Twig\Error\SyntaxError $e) {
            $this->assertTrue(true);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }

    }

    public function testPurchaseOrderDataParse()
    {
        $data = [];

        $p = \App\Models\PurchaseOrder::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $data['purchase_orders'][] = $p;

        $ts = new TemplateService();
        $ts->processData($data);

        $this->assertNotNull($ts);
        $this->assertIsArray($ts->getData());
    }

    public function testTaskDataParse()
    {
        $data = [];

        $p = \App\Models\Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $data['tasks'][] = $p;

        $ts = new TemplateService();
        $ts->processData($data);

        $this->assertNotNull($ts);
        $this->assertIsArray($ts->getData());
    }

    public function testQuoteDataParse()
    {
        $data = [];

        $p = \App\Models\Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $data['quotes'][] = $p;

        $ts = new TemplateService();
        $ts->processData($data);

        $this->assertNotNull($ts);
        $this->assertIsArray($ts->getData());

    }

    public function testProjectDataParse()
    {
        $data = [];

        $p = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $data['projects'][] = $p;

        $ts = new TemplateService();
        $ts->processData($data);

        $this->assertNotNull($ts);
        $this->assertIsArray($ts->getData());

    }

    public function testNegativeDivAttribute()
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($this->stack, 'HTML-ENTITIES', 'UTF-8'));

        $node = $dom->getElementById('company-details');
        $x = $node->getAttribute('nonexistentattribute');

        $this->assertEquals('', $x);

    }

    public function testStackResolutionWithLabels()
    {

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($this->stack, 'HTML-ENTITIES', 'UTF-8'));

        $node = $dom->getElementById('company-details');
        $x = $node->getAttribute('labels');

        $this->assertEquals('true', $x);

    }


    public function testStackResolution()
    {

        $partials['design']['includes'] = '';
        $partials['design']['header'] = '';
        $partials['design']['body'] = $this->stack;
        $partials['design']['footer'] = '';

        $tm = new TemplateMock($this->company);
        $tm->init();

        $variables = $tm->variables[0];

        $ts = new TemplateService();
        $x = $ts->setTemplate($partials)
            ->setCompany($this->company)
            ->overrideVariables($variables)
            ->parseGlobalStacks()
            ->parseVariables()
            ->getHtml();

        $this->assertIsString($x);

    }

    public function testDataMaps()
    {
        $start = microtime(true);

        Invoice::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 100,
            'balance' => 100,
        ]);

        $invoices = Invoice::orderBy('id', 'desc')->where('client_id', $this->client->id)->take(10)->get()->map(function ($c) {
            return $c->service()->markSent()->applyNumber()->save();
        })->map(function ($i) {
            return ['invoice_id' => $i->hashed_id, 'amount' => rand(0, $i->balance)];
        })->toArray();

        Credit::factory()->count(2)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 50,
            'balance' => 50,
        ]);

        $credits = Credit::orderBy('id', 'desc')->where('client_id', $this->client->id)->take(2)->get()->map(function ($c) {
            return $c->service()->markSent()->applyNumber()->save();
        })->map(function ($i) {
            return ['credit_id' => $i->hashed_id, 'amount' => rand(0, $i->balance)];
        })->toArray();

        $data = [
            'invoices' => $invoices,
            'credits' => $credits,
            'date' => now()->format('Y-m-d'),
            'client_id' => $this->client->hashed_id,
            'transaction_reference' => 'My Batch Payment',
            'type_id' => "5",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $start = microtime(true);

        $p = Payment::with('client', 'invoices', 'paymentables', 'credits')
        ->where('id', $this->decodePrimaryKey($arr['data']['id']))
        ->cursor()
        ->map(function ($payment) {

            $this->transformPayment($payment);

        })->toArray();


        nlog("end payments = " . microtime(true) - $start);

        $this->assertIsArray($data);

        $start = microtime(true);

        \DB::enableQueryLog();

        $invoices = Invoice::with('client', 'payments.client', 'payments.paymentables', 'payments.credits', 'credits.client')
        ->orderBy('id', 'desc')
        ->where('client_id', $this->client->id)
        ->take(10)
        ->get()
        ->map(function ($invoice) {

            $payments = [];
            $payments = $invoice->payments->map(function ($payment) {
                // nlog(microtime(true));
                return $this->transformPayment($payment);
            })->toArray();

            return [
                'amount' => Number::formatMoney($invoice->amount, $invoice->client),
                'balance' => Number::formatMoney($invoice->balance, $invoice->client),
                'balance_raw' => $invoice->balance,
                'number' => $invoice->number ?: '',
                'discount' => $invoice->discount,
                'po_number' => $invoice->po_number ?: '',
                'date' => $this->translateDate($invoice->date, $invoice->client->date_format(), $invoice->client->locale()),
                'last_sent_date' => $this->translateDate($invoice->last_sent_date, $invoice->client->date_format(), $invoice->client->locale()),
                'next_send_date' => $this->translateDate($invoice->next_send_date, $invoice->client->date_format(), $invoice->client->locale()),
                'due_date' => $this->translateDate($invoice->due_date, $invoice->client->date_format(), $invoice->client->locale()),
                'terms' => $invoice->terms ?: '',
                'public_notes' => $invoice->public_notes ?: '',
                'private_notes' => $invoice->private_notes ?: '',
                'uses_inclusive_taxes' => (bool) $invoice->uses_inclusive_taxes,
                'tax_name1' => $invoice->tax_name1 ?? '',
                'tax_rate1' => (float) $invoice->tax_rate1,
                'tax_name2' => $invoice->tax_name2 ?? '',
                'tax_rate2' => (float) $invoice->tax_rate2,
                'tax_name3' => $invoice->tax_name3 ?? '',
                'tax_rate3' => (float) $invoice->tax_rate3,
                'total_taxes' => Number::formatMoney($invoice->total_taxes, $invoice->client),
                'total_taxes_raw' => $invoice->total_taxes,
                'is_amount_discount' => (bool) $invoice->is_amount_discount ?? false,
                'footer' => $invoice->footer ?? '',
                'partial' => $invoice->partial ?? 0,
                'partial_due_date' => $this->translateDate($invoice->partial_due_date, $invoice->client->date_format(), $invoice->client->locale()),
                'custom_value1' => (string) $invoice->custom_value1 ?: '',
                'custom_value2' => (string) $invoice->custom_value2 ?: '',
                'custom_value3' => (string) $invoice->custom_value3 ?: '',
                'custom_value4' => (string) $invoice->custom_value4 ?: '',
                'custom_surcharge1' => (float) $invoice->custom_surcharge1,
                'custom_surcharge2' => (float) $invoice->custom_surcharge2,
                'custom_surcharge3' => (float) $invoice->custom_surcharge3,
                'custom_surcharge4' => (float) $invoice->custom_surcharge4,
                'exchange_rate' => (float) $invoice->exchange_rate,
                'custom_surcharge_tax1' => (bool) $invoice->custom_surcharge_tax1,
                'custom_surcharge_tax2' => (bool) $invoice->custom_surcharge_tax2,
                'custom_surcharge_tax3' => (bool) $invoice->custom_surcharge_tax3,
                'custom_surcharge_tax4' => (bool) $invoice->custom_surcharge_tax4,
                'line_items' => $invoice->line_items ?: (array) [],
                'reminder1_sent' => $this->translateDate($invoice->reminder1_sent, $invoice->client->date_format(), $invoice->client->locale()),
                'reminder2_sent' => $this->translateDate($invoice->reminder2_sent, $invoice->client->date_format(), $invoice->client->locale()),
                'reminder3_sent' => $this->translateDate($invoice->reminder3_sent, $invoice->client->date_format(), $invoice->client->locale()),
                'reminder_last_sent' => $this->translateDate($invoice->reminder_last_sent, $invoice->client->date_format(), $invoice->client->locale()),
                'paid_to_date' => Number::formatMoney($invoice->paid_to_date, $invoice->client),
                'auto_bill_enabled' => (bool) $invoice->auto_bill_enabled,
                'client' => [
                    'name' => $invoice->client->present()->name(),
                    'balance' => $invoice->client->balance,
                    'payment_balance' => $invoice->client->payment_balance,
                    'credit_balance' => $invoice->client->credit_balance,
                ],
                'payments' => $payments,
            ];
        });

        $this->assertIsArray($invoices->toArray());

    }

    private function transformPayment(Payment $payment): array
    {

        $data = [];

        $credits = $payment->credits->map(function ($credit) use ($payment) {
            return [
                'credit' => $credit->number,
                'amount_raw' => $credit->pivot->amount,
                'refunded_raw' => $credit->pivot->refunded,
                'net_raw' => $credit->pivot->amount - $credit->pivot->refunded,
                'amount' => Number::formatMoney($credit->pivot->amount, $payment->client),
                'refunded' => Number::formatMoney($credit->pivot->refunded, $payment->client),
                'net' => Number::formatMoney($credit->pivot->amount - $credit->pivot->refunded, $payment->client),
                'is_credit' => true,
                'created_at' => $this->translateDate($credit->pivot->created_at, $payment->client->date_format(), $payment->client->locale()),
                'updated_at' => $this->translateDate($credit->pivot->updated_at, $payment->client->date_format(), $payment->client->locale()),
                'timestamp' => $credit->pivot->created_at->timestamp,
            ];
        });

        $pivot = $payment->invoices->map(function ($invoice) use ($payment) {
            return [
                'invoice' => $invoice->number,
                'amount_raw' => $invoice->pivot->amount,
                'refunded_raw' => $invoice->pivot->refunded,
                'net_raw' => $invoice->pivot->amount - $invoice->pivot->refunded,
                'amount' => Number::formatMoney($invoice->pivot->amount, $payment->client),
                'refunded' => Number::formatMoney($invoice->pivot->refunded, $payment->client),
                'net' => Number::formatMoney($invoice->pivot->amount - $invoice->pivot->refunded, $payment->client),
                'is_credit' => false,
                'created_at' => $this->translateDate($invoice->pivot->created_at, $payment->client->date_format(), $payment->client->locale()),
                'updated_at' => $this->translateDate($invoice->pivot->updated_at, $payment->client->date_format(), $payment->client->locale()),
                'timestamp' => $invoice->pivot->created_at->timestamp,
            ];
        })->merge($credits)->sortBy('timestamp')->toArray();

        return [
            'status' => $payment->stringStatus($payment->status_id),
            'badge' => $payment->badgeForStatus($payment->status_id),
            'amount' => Number::formatMoney($payment->amount, $payment->client),
            'applied' => Number::formatMoney($payment->applied, $payment->client),
            'balance' => Number::formatMoney(($payment->amount - $payment->refunded - $payment->applied), $payment->client),
            'refunded' => Number::formatMoney($payment->refunded, $payment->client),
            'amount_raw' => $payment->amount,
            'applied_raw' => $payment->applied,
            'refunded_raw' => $payment->refunded,
            'balance_raw' => ($payment->amount - $payment->refunded - $payment->applied),
            'date' => $this->translateDate($payment->date, $payment->client->date_format(), $payment->client->locale()),
            'method' => $payment->translatedType(),
            'currency' => $payment->currency->code,
            'exchange_rate' => $payment->exchange_rate,
            'transaction_reference' => $payment->transaction_reference,
            'is_manual' => $payment->is_manual,
            'number' => $payment->number,
            'custom_value1' => $payment->custom_value1 ?? '',
            'custom_value2' => $payment->custom_value2 ?? '',
            'custom_value3' => $payment->custom_value3 ?? '',
            'custom_value4' => $payment->custom_value4 ?? '',
            'client' => [
                'name' => $payment->client->present()->name(),
                'balance' => $payment->client->balance,
                'payment_balance' => $payment->client->payment_balance,
                'credit_balance' => $payment->client->credit_balance,
            ],
            'paymentables' => $pivot,
        ];

        return $data;




    }

    public function testVariableResolutionViaTransformersForPaymentsInStatements()
    {
        Invoice::factory()->count(20)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'amount' => 100,
            'balance' => 100,
        ]);

        $i = Invoice::orderBy('id', 'desc')
                    ->where('client_id', $this->client->id)
                    ->where('status_id', 2)
                    ->cursor()
                    ->each(function ($i) {
                        $i->service()->applyPaymentAmount(random_int(1, 100));
                    });

        $invoices = Invoice::withTrashed()
            ->with('payments.type')
            ->where('is_deleted', false)
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', [2,3,4])
            ->orderBy('due_date', 'ASC')
            ->orderBy('date', 'ASC')
            ->cursor();

        $invoices->each(function ($i) {

            $rand = [1,2,4,5,6,7,8,9,10,11,12,13,14,15,16,17,24,25,32,49,50];

            $i->payments()->each(function ($p) use ($rand) {
                shuffle($rand);
                $p->type_id = $rand[0];
                $p->save();

            });
        });

        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $replicated_design->company_id = $this->company->id;
        $replicated_design->user_id = $this->user->id;
        $design = $replicated_design->design;
        $design->body .= $this->payments_body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->is_template = true;
        $replicated_design->entities = 'client';
        $replicated_design->save();

        $data['invoices'] = $invoices;
        $ts = $replicated_design->service()->build($data);

        $this->assertNotNull($ts->getHtml());

    }

    public function testDoubleEntityNestedDataTemplateServiceBuild()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $replicated_design->company_id = $this->company->id;
        $replicated_design->user_id = $this->user->id;
        $design = $replicated_design->design;
        $design->body .= $this->nested_body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $i2 = Invoice::factory()
        ->for($this->client)
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status_id' => Invoice::STATUS_SENT,
            'design_id' => $replicated_design->id,
            'balance' => 100,
        ]);

        $data = [];
        $data['invoices'] = collect([$this->invoice, $i2]);

        $ts = $replicated_design->service()->build($data);

        // nlog("results = ");
        // nlog($ts->getHtml());
        $this->assertNotNull($ts->getHtml());
    }

    public function testDoubleEntityTemplateServiceBuild()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $replicated_design->company_id = $this->company->id;
        $replicated_design->user_id = $this->user->id;
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $i2 = Invoice::factory()
        ->for($this->client)
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'status_id' => Invoice::STATUS_SENT,
            'design_id' => $replicated_design->id,
            'balance' => 100,
        ]);

        $data = [];
        $data['invoices'] = collect([$this->invoice, $i2]);

        $ts = $replicated_design->service()->build($data);

        // nlog("results = ");
        // nlog($ts->getHtml());
        $this->assertNotNull($ts->getHtml());
    }

    public function testTemplateServiceBuild()
    {
        $design_model = Design::find(2);
        $replicated_design = $design_model->replicate();
        $replicated_design->company_id = $this->company->id;
        $replicated_design->user_id = $this->user->id;
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $data = [];
        $data['invoices'] = collect([$this->invoice]);

        $ts = $replicated_design->service()->build($data);

        // nlog("results = ");
        // nlog($ts->getHtml());
        $this->assertNotNull($ts->getHtml());
    }

    public function testTemplateService()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $replicated_design->company_id = $this->company->id;
        $replicated_design->user_id = $this->user->id;
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $this->assertNotNull($replicated_design->service());
        $this->assertInstanceOf(TemplateService::class, $replicated_design->service());
    }

    public function testTimingOnCleanDesign()
    {
        $design_model = Design::find(2);

        $replicated_design = $design_model->replicate();
        $replicated_design->company_id = $this->company->id;
        $replicated_design->user_id = $this->user->id;
        $design = $replicated_design->design;
        $design->body .= $this->body;
        $replicated_design->design = $design;
        $replicated_design->is_custom = true;
        $replicated_design->save();

        $entity_obj = \App\Models\Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
            'design_id' => $replicated_design->id,
        ]);

        $i = \App\Models\InvoiceInvitation::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'invoice_id' => $entity_obj->id,
            'client_contact_id' => $this->client->contacts->first()->id,
        ]);

        $start = microtime(true);

        $pdf = (new CreateRawPdf($i))->handle();

        $end = microtime(true);

        $this->assertNotNull($pdf);

        // nlog("Twig + PDF Gen Time: " . $end-$start);

    }

    public function testStaticPdfGeneration()
    {
        $start = microtime(true);

        $pdf = (new CreateRawPdf($this->invoice->invitations->first()))->handle();

        $end = microtime(true);

        $this->assertNotNull($pdf);

        // nlog("Plain PDF Gen Time: " . $end-$start);
    }

    public function testTemplateGeneration()
    {
        $entity_obj = $this->invoice;

        $design = new Design();
        $design->design = json_decode(json_encode($this->invoice->company->settings->pdf_variables), true);
        $design->name = 'test';
        $design->is_active = true;
        $design->is_template = true;
        $design->is_custom = true;
        $design->user_id = $this->invoice->user_id;
        $design->company_id = $this->invoice->company_id;

        $design_object = new \stdClass();
        $design_object->includes = '';
        $design_object->header = '';
        $design_object->body = $this->body;
        $design_object->product = '';
        $design_object->task = '';
        $design_object->footer = '';

        $design->design = $design_object;

        $design->save();

        $start = microtime(true);

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($entity_obj->client->locale());
        $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

        $html = new HtmlEngine($entity_obj->invitations()->first());

        $options = [
            'custom_partials' => json_decode(json_encode($design->design), true),
        ];
        $template = new PdfMakerDesign(PdfDesignModel::CUSTOM, $options);

        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => $entity_obj->client,
                'entity' => $entity_obj,
                'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                '$product' => $design->design->product,
                'variables' => $variables,
            ]),
            'variables' => $variables,
            'options' => [
                'all_pages_header' => $entity_obj->client->getSetting('all_pages_header'),
                'all_pages_footer' => $entity_obj->client->getSetting('all_pages_footer'),
                'client' => $entity_obj->client,
                'entity' => [$entity_obj],
                'invoices' => [$entity_obj],
                'variables' => $variables,
            ],
            'process_markdown' => $entity_obj->client->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);
        $maker
                ->design($template)
                ->build();

        $html = $maker->getCompiledHTML(true);

        $end = microtime(true);

        $this->assertNotNull($html);
        $this->assertStringContainsStringIgnoringCase($this->company->settings->name, $html);

        // nlog("Twig Solo Gen Time: ". $end - $start);
    }

}
