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

namespace App\Services\Scheduler;

use App\Export\CSV\ClientExport;
use App\Export\CSV\ContactExport;
use App\Export\CSV\CreditExport;
use App\Export\CSV\DocumentExport;
use App\Export\CSV\ExpenseExport;
use App\Export\CSV\InvoiceExport;
use App\Export\CSV\InvoiceItemExport;
use App\Export\CSV\PaymentExport;
use App\Export\CSV\ProductExport;
use App\Export\CSV\ProductSalesExport;
use App\Export\CSV\QuoteExport;
use App\Export\CSV\QuoteItemExport;
use App\Export\CSV\RecurringInvoiceExport;
use App\Export\CSV\TaskExport;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\DownloadReport;
use App\Models\Client;
use App\Models\Scheduler;
use App\Services\Report\ARDetailReport;
use App\Services\Report\ARSummaryReport;
use App\Services\Report\ClientBalanceReport;
use App\Services\Report\ClientSalesReport;
use App\Services\Report\ProfitLoss;
use App\Services\Report\TaxSummaryReport;
use App\Services\Report\UserSalesReport;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;

class EmailReport
{
    use MakesHash;
    use MakesDates;

    private Client $client;

    private bool $multiple_clients = false;

    private string $file_name = 'file.csv';

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {

        $start_end_dates = $this->calculateStartAndEndDates($this->scheduler->parameters);
        $data = $this->scheduler->parameters;

        $data['start_date'] = $start_end_dates[0];
        $data['end_date'] = $start_end_dates[1];
        $data['date_range'] = $data['date_range'] ?? 'all';
        $data['report_keys'] = $data['report_keys'] ?? [];

        $export = false;

        match($this->scheduler->parameters['report_name']) {
            'product_sales' => $export = (new ProductSalesExport($this->scheduler->company, $data)),
            'ar_detailed' => $export = (new ARDetailReport($this->scheduler->company, $data)),
            'ar_summary' => $export = (new ARSummaryReport($this->scheduler->company, $data)),
            'tax_summary' => $export = (new TaxSummaryReport($this->scheduler->company, $data)),
            'client_balance' => $export = (new ClientBalanceReport($this->scheduler->company, $data)),
            'client_sales' => $export = (new ClientSalesReport($this->scheduler->company, $data)),
            'user_sales' => $export = (new UserSalesReport($this->scheduler->company, $data)),
            'profitloss' => $export = (new ProfitLoss($this->scheduler->company, $data)),
            'client' => $export = (new ClientExport($this->scheduler->company, $data)),
            'client_contact' => $export = (new ContactExport($this->scheduler->company, $data)),
            'credit' => $export = (new CreditExport($this->scheduler->company, $data)),
            'document' => $export = (new DocumentExport($this->scheduler->company, $data)),
            'expense' => $export = (new ExpenseExport($this->scheduler->company, $data)),
            'invoice' => $export = (new InvoiceExport($this->scheduler->company, $data)),
            'invoice_item' => $export = (new InvoiceItemExport($this->scheduler->company, $data)),
            'quote' => $export = (new QuoteExport($this->scheduler->company, $data)),
            'quote_item' => $export = (new QuoteItemExport($this->scheduler->company, $data)),
            'recurring_invoice' => $export = (new RecurringInvoiceExport($this->scheduler->company, $data)),
            'payment' => $export = (new PaymentExport($this->scheduler->company, $data)),
            'product' => $export = (new ProductExport($this->scheduler->company, $data)),
            'task' => $export = (new TaskExport($this->scheduler->company, $data)),
            default => $export = false,
        };

        if(!$export) {
            $this->cancelSchedule();
            return;
        }

        $csv = $export->run();

        //todo - potentially we send this to more than one user.

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new DownloadReport($this->scheduler->company, $csv, $this->file_name);
        $nmo->company = $this->scheduler->company;
        $nmo->settings = $this->scheduler->company->settings;
        $nmo->to_user = $this->scheduler->user;

        NinjaMailerJob::dispatch($nmo);

        //calculate next run dates;
        $this->scheduler->calculateNextRun();

    }

    private function cancelSchedule()
    {
        $this->scheduler->forceDelete();
    }



}
