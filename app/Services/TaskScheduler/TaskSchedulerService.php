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

namespace App\Services\TaskScheduler;

use App\Export\CSV\ClientExport;
use App\Export\CSV\ContactExport;
use App\Export\CSV\CreditExport;
use App\Export\CSV\DocumentExport;
use App\Export\CSV\ExpenseExport;
use App\Export\CSV\InvoiceExport;
use App\Export\CSV\InvoiceItemExport;
use App\Export\CSV\PaymentExport;
use App\Export\CSV\ProductExport;
use App\Export\CSV\QuoteExport;
use App\Export\CSV\QuoteItemExport;
use App\Export\CSV\RecurringInvoiceExport;
use App\Export\CSV\TaskExport;
use App\Http\Requests\Report\GenericReportRequest;
use App\Http\Requests\Report\ProfitLossRequest;
use App\Http\Requests\TaskScheduler\CreateScheduledTaskRequest;
use App\Http\Requests\TaskScheduler\UpdateScheduledJobRequest;
use App\Http\Requests\TaskScheduler\UpdateScheduleRequest;
use App\Jobs\Report\ProfitAndLoss;
use App\Models\Company;
use App\Models\Scheduler;
use App\Utils\Ninja;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Request;

class TaskSchedulerService
{
    public Scheduler $scheduler;

    public function __construct(Scheduler $scheduler)
    {
        $this->scheduler = $scheduler;
    }

    public function store(Scheduler $scheduler, CreateScheduledTaskRequest $request)
    {
        $scheduler->action_name = $request->job;
        $scheduler->paused = $request->get('paused', false);
        $scheduler->start_from = $request->get('start_from') ? Carbon::parse((int) $request->get('start_from')) : Carbon::now();
        $scheduler->repeat_every = $request->get('repeat_every');
        $scheduler->scheduled_run = $request->get('start_from') ? Carbon::parse((int) $request->get('start_from')) : Carbon::now();
        $scheduler->company_id = auth()->user()->company()->id;
        $scheduler = $this->setJobParameters($scheduler, $request);
        $scheduler->save();
    }

    public function update(Scheduler $scheduler, UpdateScheduleRequest $request)
    {
        if (array_key_exists('job', $request->all())) {
            $scheduler->action_name = $request->get('job');
            $scheduler = $this->setJobParameters($scheduler, $request);
        }
        $data = $request->validated();
        $update = $this->scheduler->update($data);
    }

    private function runValidation($form_request, $data)
    {
        $_syn_request_class = new $form_request();
        $_syn_request_class->setContainer(app());
        $_syn_request_class->initialize($data);
        $_syn_request_class->prepareForValidation();
        $_syn_request_class->setValidator(Validator::make($_syn_request_class->all(), $_syn_request_class->rules()));

        return $_syn_request_class->validated();
    }

    public function setJobParameters(Scheduler $scheduler, $request)
    {
        switch ($scheduler->action_name) {
            case Scheduler::CREATE_CLIENT_REPORT:
                $scheduler->action_name = Scheduler::CREATE_CLIENT_REPORT;
                $scheduler->action_class = $this->getClassPath(ClientExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_CLIENT_CONTACT_REPORT:
                $scheduler->action_name = Scheduler::CREATE_CLIENT_CONTACT_REPORT;
                $scheduler->action_class = $this->getClassPath(ContactExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_CREDIT_REPORT:

                $scheduler->action_name = Scheduler::CREATE_CREDIT_REPORT;
                $scheduler->action_class = $this->getClassPath(CreditExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_DOCUMENT_REPORT:
                $scheduler->action_name = Scheduler::CREATE_DOCUMENT_REPORT;
                $scheduler->action_class = $this->getClassPath(DocumentExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_EXPENSE_REPORT:
                $scheduler->action_name = Scheduler::CREATE_EXPENSE_REPORT;
                $scheduler->action_class = $this->getClassPath(ExpenseExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_INVOICE_ITEM_REPORT:
                $scheduler->action_name = Scheduler::CREATE_INVOICE_ITEM_REPORT;
                $scheduler->action_class = $this->getClassPath(InvoiceItemExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_INVOICE_REPORT:
                $scheduler->action_name = Scheduler::CREATE_INVOICE_REPORT;
                $scheduler->action_class = $this->getClassPath(InvoiceExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_PAYMENT_REPORT:
                $scheduler->action_name = Scheduler::CREATE_PAYMENT_REPORT;
                $scheduler->action_class = $this->getClassPath(PaymentExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_PRODUCT_REPORT:
                $scheduler->action_name = Scheduler::CREATE_PRODUCT_REPORT;
                $scheduler->action_class = $this->getClassPath(ProductExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_PROFIT_AND_LOSS_REPORT:
                $scheduler->action_name = Scheduler::CREATE_PROFIT_AND_LOSS_REPORT;
                $scheduler->action_class = $this->getClassPath(ProfitAndLoss::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_QUOTE_ITEM_REPORT:
                $scheduler->action_name = Scheduler::CREATE_QUOTE_ITEM_REPORT;
                $scheduler->action_class = $this->getClassPath(QuoteItemExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_QUOTE_REPORT:
                $scheduler->action_name = Scheduler::CREATE_QUOTE_REPORT;
                $scheduler->action_class = $this->getClassPath(QuoteExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_RECURRING_INVOICE_REPORT:
                $scheduler->action_name = Scheduler::CREATE_RECURRING_INVOICE_REPORT;
                $scheduler->action_class = $this->getClassPath(RecurringInvoiceExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case Scheduler::CREATE_TASK_REPORT:
                $scheduler->action_name = Scheduler::CREATE_TASK_REPORT;
                $scheduler->action_class = $this->getClassPath(TaskExport::class);
                $scheduler->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;

        }

        return $scheduler;
    }

    public function getClassPath($class): string
    {
        return $class = is_object($class) ? get_class($class) : $class;
    }

    public function updateJob(Scheduler $scheduler, UpdateScheduledJobRequest $request)
    {
        $scheduler = $this->setJobParameters($scheduler, $request);
        $scheduler->save();
    }
}
