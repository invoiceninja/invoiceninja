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

namespace App\Services\Client;

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\Number;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\Pdf\PdfMaker as PdfMakerTrait;
use Illuminate\Support\Carbon;

class Statement
{
    use PdfMakerTrait;

    protected Client $client;

    /**
     * @var Invoice|Payment|null
     */
    protected $entity;

    protected array $options;

    protected bool $rollback = false;

    public function __construct(Client $client, array $options)
    {
        $this->client = $client;

        $this->options = $options;
    }

    public function run(): ?string
    {
        $this
            ->setupOptions()
            ->setupEntity();

        $html = new HtmlEngine($this->getInvitation());

        if ($this->getDesign()->is_custom) {
            $this->options['custom_partials'] = \json_decode(\json_encode($this->getDesign()->design), true);

            $template = new PdfMakerDesign(\App\Services\PdfMaker\Design::CUSTOM, $this->options);
        } else {
            $template = new PdfMakerDesign(strtolower($this->getDesign()->name), $this->options);
        }

        $variables = $html->generateLabelsAndValues();

        $state = [
            'template' => $template->elements([
                'client' => $this->client,
                'entity' => $this->entity,
                'pdf_variables' => (array) $this->entity->company->settings->pdf_variables,
                '$product' => $this->getDesign()->design->product,
                'variables' => $variables,
                'invoices' => $this->getInvoices(),
                'payments' => $this->getPayments(),
                'aging' => $this->getAging(),
            ], \App\Services\PdfMaker\Design::STATEMENT),
            'variables' => $variables,
            'options' => [],
            'process_markdown' => $this->entity->client->company->markdown_enabled,
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($template)
            ->build();

        $pdf = null;

        try {
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                $pdf = (new Phantom)->convertHtmlToPdf($maker->getCompiledHTML(true));
            } elseif (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));
            } else {
                $pdf = $this->makePdf(null, null, $maker->getCompiledHTML(true));
            }
        } catch (\Exception $e) {
            nlog(print_r($e->getMessage(), 1));
        }

        if ($this->rollback) {
            \DB::connection(config('database.default'))->rollBack();
        }

        return $pdf;
    }

    /**
     * Setup correct entity instance.
     *
     * @return Statement
     */
    protected function setupEntity(): self
    {
        if ($this->getInvoices()->count() >= 1) {
            $this->entity = $this->getInvoices()->first();
        }

        if (\is_null($this->entity)) {
            \DB::connection(config('database.default'))->beginTransaction();

            $this->rollback = true;

            $invoice = InvoiceFactory::create($this->client->company->id, $this->client->user->id);
            $invoice->client_id = $this->client->id;
            $invoice->line_items = $this->buildLineItems();
            $invoice->save();

            $invitation = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
            $invitation->invoice_id = $invoice->id;
            $invitation->client_contact_id = $this->client->contacts->first()->id;
            $invitation->save();

            $this->entity = $invoice;
        }

        return $this;
    }

    protected function buildLineItems($count = 1)
    {
        $line_items = [];

        for ($x = 0; $x < $count; $x++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            //$item->cost = 10;

            if (rand(0, 1)) {
                $item->tax_name1 = 'GST';
                $item->tax_rate1 = 10.00;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'VAT';
                $item->tax_rate1 = 17.50;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'Sales Tax';
                $item->tax_rate1 = 5;
            }

            //$product = Product::first();

            $product = new \stdClass;

            $item->cost = (float) 10;
            $item->product_key = 'test';
            $item->notes = 'test notes';
            $item->custom_value1 = 'custom value1';
            $item->custom_value2 = 'custom value2';
            $item->custom_value3 = 'custom value3';
            $item->custom_value4 = 'custom value4';

            $line_items[] = $item;
        }

        return $line_items;
    }

    /**
     * Setup & prepare options.
     *
     * @return Statement
     */
    protected function setupOptions(): self
    {
        if (! \array_key_exists('start_date', $this->options)) {
            $this->options['start_date'] = now()->startOfYear()->format('Y-m-d');
        }

        if (! \array_key_exists('end_date', $this->options)) {
            $this->options['end_date'] = now()->format('Y-m-d');
        }

        if (! \array_key_exists('show_payments_table', $this->options)) {
            $this->options['show_payments_table'] = false;
        }

        if (! \array_key_exists('show_aging_table', $this->options)) {
            $this->options['show_aging_table'] = false;
        }

        return $this;
    }

    /**
     * The collection of invoices for the statement.
     *
     * @return Invoice[]|\Illuminate\Database\Eloquent\Collection
     */
    protected function getInvoices(): \Illuminate\Support\LazyCollection
    {
        return Invoice::withTrashed()
            ->with('payments.type')
            ->where('is_deleted', false)
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', $this->invoiceStatuses())
            ->whereBetween('date', [Carbon::parse($this->options['start_date']), Carbon::parse($this->options['end_date'])])
            ->orderBy('due_date', 'ASC')
            ->orderBy('date', 'ASC')
            ->cursor();
    }

    private function invoiceStatuses() :array
    {
        $status = 'all';

        if (array_key_exists('status', $this->options)) {
            $status = $this->options['status'];
        }

        switch ($status) {
            case 'all':
                return [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];
                break;
            case 'paid':
                return [Invoice::STATUS_PAID];
                break;
            case 'unpaid':
                return [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL];
                break;

            default:
                return [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];
                break;
        }
    }

    /**
     * The collection of payments for the statement.
     *
     * @return Payment[]|\Illuminate\Database\Eloquent\Collection
     */
    protected function getPayments(): \Illuminate\Support\LazyCollection
    {
        return Payment::withTrashed()
            ->with('client.country', 'invoices')
            ->where('is_deleted', false)
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
            ->whereBetween('date', [Carbon::parse($this->options['start_date']), Carbon::parse($this->options['end_date'])])
            ->orderBy('date', 'ASC')
            ->cursor();
    }

    /**
     * Get correct invitation ID.
     *
     * @return int|bool
     */
    protected function getInvitation()
    {
        if ($this->entity instanceof Invoice || $this->entity instanceof Payment) {
            return $this->entity->invitations->first();
        }

        return false;
    }

    /**
     * Get the array of aging data.
     *
     * @return array
     */
    protected function getAging(): array
    {
        return [
            '0-30' => $this->getAgingAmount('30'),
            '30-60' => $this->getAgingAmount('60'),
            '60-90' => $this->getAgingAmount('90'),
            '90-120' => $this->getAgingAmount('120'),
            '120+' => $this->getAgingAmount('120+'),
        ];
    }

    /**
     * Generate aging amount.
     *
     * @param mixed $range
     * @return string
     */
    private function getAgingAmount($range)
    {
        $ranges = $this->calculateDateRanges($range);

        $from = $ranges[0];
        $to = $ranges[1];

        $amount = Invoice::withTrashed()
            ->where('client_id', $this->client->id)
            ->where('company_id', $this->client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0)
            ->whereBetween('due_date', [$to, $from])
            ->sum('balance');

        return Number::formatMoney($amount, $this->client);
    }

    /**
     * Calculate date ranges for aging.
     *
     * @param mixed $range
     * @return array
     */
    private function calculateDateRanges($range)
    {
        $ranges = [];

        switch ($range) {
            case '30':
                $ranges[0] = now()->startOfDay();
                $ranges[1] = now()->startOfDay()->subDays(30);

                return $ranges;
            case '60':
                $ranges[0] = now()->startOfDay()->subDays(30);
                $ranges[1] = now()->startOfDay()->subDays(60);

                return $ranges;
            case '90':
                $ranges[0] = now()->startOfDay()->subDays(60);
                $ranges[1] = now()->startOfDay()->subDays(90);

                return $ranges;
            case '120':
                $ranges[0] = now()->startOfDay()->subDays(90);
                $ranges[1] = now()->startOfDay()->subDays(120);

                return $ranges;
            case '120+':
                $ranges[0] = now()->startOfDay()->subDays(120);
                $ranges[1] = now()->startOfDay()->subYears(20);

                return $ranges;
            default:
                $ranges[0] = now()->startOfDay()->subDays(0);
                $ranges[1] = now()->subDays(30);

                return $ranges;
        }
    }

    /**
     * Get correct design for statement.
     *
     * @return \App\Models\Design
     */
    protected function getDesign(): Design
    {
        $id = 1;

        if (! empty($this->client->getSetting('entity_design_id'))) {
            $id = (int) $this->client->getSetting('entity_design_id');
        }

        return Design::find($id);
    }
}
