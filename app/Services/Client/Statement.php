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

namespace App\Services\Client;

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
use Illuminate\Database\Eloquent\Collection;

class Statement
{
    use PdfMakerTrait;

    protected Client $client;

    /**
     * @var Invoice|Payment|null
     */
    protected $entity;

    protected array $options;

    public function __construct(Client $client, array $options)
    {
        $this->client = $client;

        $this->options = $options;
    }

    public function run(): ?string
    {
        $this->setupEntity()->setupOptions();

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
                'client' => $this->entity->client,
                'entity' => $this->entity,
                'pdf_variables' => (array)$this->entity->company->settings->pdf_variables,
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

        return $pdf;
    }

    /**
     * Setup correct entity instance.
     *
     * @return Statement
     */
    protected function setupEntity(): self
    {
        if (count($this->getInvoices()) >= 1) {
            $this->entity = $this->getInvoices()->first();
        }

        if (count($this->getPayments()) >= 1) {
            $this->entity = $this->getPayments()->first();
        }

        return $this;
    }

    /**
     * Setup & prepare options.
     *
     * @return Statement
     */
    protected function setupOptions(): self
    {
        if (\array_key_exists('start_date', $this->options)) {
            $this->options['start_date'] = now()->startOfYear()->format('Y-m-d');
        }

        if (\array_key_exists('end_date', $this->options)) {
            $this->options['end_date'] = now()->format('Y-m-d');
        }

        if (\array_key_exists('show_payments_table', $this->options)) {
            $this->options['show_payments_table'] = false;
        }

        if (\array_key_exists('show_aging_table', $this->options)) {
            $this->options['show_aging_table'] = false;
        }

        return $this;
    }

    /**
     * The collection of invoices for the statement.
     *
     * @return Invoice[]|\Illuminate\Database\Eloquent\Collection
     */
    protected function getInvoices(): Collection
    {
        return Invoice::where('company_id', $this->client->company->id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
            ->whereBetween('date', [$this->options['start_date'], $this->options['end_date']])
            ->get();
    }

    /**
     * The collection of payments for the statement.
     *
     * @return Payment[]|\Illuminate\Database\Eloquent\Collection
     */
    protected function getPayments(): Collection
    {
        return Payment::where('company_id', $this->client->company->id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
            ->whereBetween('date', [$this->options['start_date'], $this->options['end_date']])
            ->get();
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

        $client = Client::where('id', $this->client->id)->first();

        $amount = Invoice::where('company_id', $this->client->company->id)
            ->where('client_id', $client->id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->whereBetween('date', [$from, $to])
            ->sum('balance');

        return Number::formatMoney($amount, $client);
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
                $ranges[0] = now();
                $ranges[1] = now()->subDays(30);
                return $ranges;
                break;
            case '60':
                $ranges[0] = now()->subDays(30);
                $ranges[1] = now()->subDays(60);
                return $ranges;
                break;
            case '90':
                $ranges[0] = now()->subDays(60);
                $ranges[1] = now()->subDays(90);
                return $ranges;
                break;
            case '120':
                $ranges[0] = now()->subDays(90);
                $ranges[1] = now()->subDays(120);
                return $ranges;
                break;
            case '120+':
                $ranges[0] = now()->subDays(120);
                $ranges[1] = now()->subYears(40);
                return $ranges;
                break;
            default:
                $ranges[0] = now()->subDays(0);
                $ranges[1] = now()->subDays(30);
                return $ranges;
                break;
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

        if (!empty($this->client->getSetting('entity_design_id'))) {
            $id = (int) $this->client->getSetting('entity_design_id');
        }

        return Design::find($id);
    }
}
