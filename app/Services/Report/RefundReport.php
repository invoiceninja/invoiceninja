<?php

namespace App\Services\Report;

use Carbon\Carbon;
use App\Utils\Ninja;
use App\Utils\Number;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Activity;
use App\Utils\Translator;
use App\Libraries\MultiDB;
use Illuminate\Support\Str;
use App\Export\CSV\BaseExport;
use App\Utils\Traits\MakesDates;
use League\Csv\CharsetConverter;
use Illuminate\Support\Facades\App;
use Illuminate\Database\Query\Builder;

class RefundReport extends BaseExport
{
    use MakesDates;

    public Writer $csv;

    private string $template = '/views/templates/reports/refund_report.html';

    public array $report_keys = [
        'refund_date',
        'refund_amount',
        'payment_number',
        'invoice_number',
        'invoice_amount',
        'gateway_refund',
    ];

    /**
     * @param array $input
     * [
     *     'date_range',
     *     'start_date',
     *     'end_date',
     *     'clients',
     *     'client_id',
     * ]
     */
    public function __construct(public Company $company, public array $input)
    {
    }

    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->csv = Writer::fromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.refund_report')]);
        $this->csv->insertOne([ctrans('texts.created_on'), ' ', $this->translateDate(now()->format('Y-m-d'), $this->company->date_format(), $this->company->locale())]);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = $this->report_keys;
        }

        $this->csv->insertOne($this->buildHeader());

        // Get all refund activities
        $query = Activity::query()
            ->where('company_id', $this->company->id)
            ->where('activity_type_id', 40) // Refund activity type
            ->with(['payment', 'payment.client']);

        $query = $this->addDateRange($query, 'activities');

        $refundActivities = $query->cursor();

        foreach ($refundActivities as $activity) {
            /** @var Activity $activity */

            // Extract refund amount from notes using regex
            preg_match('/Refunded : (\d+) -/', $activity->notes, $matches);
            $refundAmount = $matches[1] ?? 0;

            // Get payment details
            $payment = $activity->payment;

            // Get gateway refund status from refund_meta
            $gatewayRefund = false;
            if ($payment && $payment->refund_meta) {
                foreach ($payment->refund_meta as $refund) {
                    if (isset($refund['gateway_refund']) && $refund['gateway_refund']) {
                        $gatewayRefund = true;
                        break;
                    }
                }
            }

            // Get invoice details from refund_meta
            $invoices = [];
            if ($payment && $payment->refund_meta) {
                foreach ($payment->refund_meta as $refund) {
                    if (isset($refund['invoices'])) {
                        foreach ($refund['invoices'] as $invoiceRefund) {
                            $invoice = Invoice::query()
                                ->where('company_id', $this->company->id)
                                ->where('id', $invoiceRefund['invoice_id'])
                                ->first();

                            if ($invoice) {
                                $invoices[] = [
                                    'number' => $invoice->number,
                                    'amount' => $invoiceRefund['amount']
                                ];
                            }
                        }
                    }
                }
            }

            // Create a row for each refunded invoice
            foreach ($invoices as $invoice) {
                $this->csv->insertOne([
                    $this->translateDate(\Carbon\Carbon::parse($activity->created_at)->format('Y-m-d'), $this->company->date_format(), $this->company->locale()),
                    Number::formatMoney($refundAmount, $this->company),
                    $payment ? $payment->number : '',
                    $invoice['number'],
                    Number::formatMoney($invoice['amount'], $this->company),
                    $gatewayRefund ? 'Yes' : 'No',
                ]);
            }
        }

        return $this->csv->toString();
    }

    public function buildHeader(): array
    {
        $header = [];

        foreach ($this->input['report_keys'] as $value) {
            $header[] = ctrans("texts.{$value}");
        }

        return $header;
    }
}
