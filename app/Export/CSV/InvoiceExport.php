<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Export\Decorators\Decorator;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Transformers\InvoiceTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class InvoiceExport extends BaseExport
{
    private $invoice_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->invoice_transformer = new InvoiceTransformer();
        $this->decorator = new Decorator();
    }

    public function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->invoice_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        $query = Invoice::query()
                        ->withTrashed()
                        ->with('client')
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        $query = $this->addDateRange($query);

        if($this->input['status'] ?? false) {
            $query = $this->addInvoiceStatusFilter($query, $this->input['status']);
        }

        if($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;

    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $report = $query->cursor()
                ->map(function ($resource) {
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    public function run()
    {
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
            ->each(function ($invoice) {
                $this->csv->insertOne($this->buildRow($invoice));
            });

        return $this->csv->toString();
    }

    private function buildRow(Invoice $invoice): array
    {
        $transformed_invoice = $this->invoice_transformer->transform($invoice);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'invoice' && array_key_exists($parts[1], $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$parts[1]];
            } else {
                $entity[$key] = $this->decorator->transform($key, $invoice);
            }

        }

        return $this->decorateAdvancedFields($invoice, $entity);
    }

    private function decorateAdvancedFields(Invoice $invoice, array $entity): array
    {

        // if (in_array('invoice.country_id', $this->input['report_keys'])) {
        //     $entity['invoice.country_id'] = $invoice->client->country ? ctrans("texts.country_{$invoice->client->country->name}") : '';
        // }

        // if (in_array('invoice.currency_id', $this->input['report_keys'])) {
        //     $entity['invoice.currency_id'] = $invoice->client->currency() ? $invoice->client->currency()->code : $invoice->company->currency()->code;
        // }

        // if (in_array('invoice.client_id', $this->input['report_keys'])) {
        //     $entity['invoice.client_id'] = $invoice->client->present()->name();
        // }

        // if (in_array('invoice.status', $this->input['report_keys'])) {
        //     $entity['invoice.status'] = $invoice->stringStatus($invoice->status_id);
        // }

        // if (in_array('invoice.recurring_id', $this->input['report_keys'])) {
        //     $entity['invoice.recurring_id'] = $invoice->recurring_invoice->number ?? '';
        // }

        if (in_array('invoice.auto_bill_enabled', $this->input['report_keys'])) {
            $entity['invoice.auto_bill_enabled'] = $invoice->auto_bill_enabled ? ctrans('texts.yes') : ctrans('texts.no');
        }

        if (in_array('invoice.assigned_user_id', $this->input['report_keys'])) {
            $entity['invoice.assigned_user_id'] = $invoice->assigned_user ? $invoice->assigned_user->present()->name() : '';
        }

        if (in_array('invoice.user_id', $this->input['report_keys'])) {
            $entity['invoice.user_id'] = $invoice->user ? $invoice->user->present()->name() : '';
        }

        return $entity;
    }
}
