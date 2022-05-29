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
use App\Models\ScheduledJob;
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
        $scheduler->paused = $request->get('paused', false);
        $scheduler->start_from = $request->get('start_from') ? Carbon::parse((int)$request->get('start_from')) : Carbon::now();
        $scheduler->repeat_every = $request->get('repeat_every');
        $scheduler->scheduled_run = $request->get('start_from') ? Carbon::parse((int)$request->get('start_from')) : Carbon::now();;
        $scheduler->company_id = auth()->user()->company()->id;
        $scheduler->save();
        
        $this->createJob($request, $scheduler);

    }

    public function update(Scheduler $scheduler, UpdateScheduleRequest $request)
    {

        $data = $request->validated();

        $update = $this->scheduler->update($data);
        if ($update) {
            return response(['successfully_updated_scheduler'], 200);
        }
        return response(['failed_to_update_scheduler'], 400);
    }

    public function createJob(CreateScheduledTaskRequest $request, Scheduler $scheduler): bool
    {
        $job = new ScheduledJob();
        $job = $this->setJobParameters($job, $request);
        $job->scheduler_id = $scheduler->id;
        $job->company_id = auth()->user()->company()->id;
        return $job->save();

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

    public function setJobParameters(ScheduledJob $job, $request): ScheduledJob
    {
        switch ($request->job) {
            case ScheduledJob::CREATE_CLIENT_REPORT:
                $job->action_name = ScheduledJob::CREATE_CLIENT_REPORT;
                $job->action_class = $this->getClassPath(ClientExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_CLIENT_CONTACT_REPORT:

                $job->action_name = ScheduledJob::CREATE_CLIENT_CONTACT_REPORT;
                $job->action_class = $this->getClassPath(ContactExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_CREDIT_REPORT:

                $job->action_name = ScheduledJob::CREATE_CREDIT_REPORT;
                $job->action_class = $this->getClassPath(CreditExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_DOCUMENT_REPORT:
                $job->action_name = ScheduledJob::CREATE_DOCUMENT_REPORT;
                $job->action_class = $this->getClassPath(DocumentExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_EXPENSE_REPORT:
                $job->action_name = ScheduledJob::CREATE_EXPENSE_REPORT;
                $job->action_class = $this->getClassPath(ExpenseExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_INVOICE_ITEM_REPORT:
                $job->action_name = ScheduledJob::CREATE_INVOICE_ITEM_REPORT;
                $job->action_class = $this->getClassPath(InvoiceItemExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_INVOICE_REPORT:
                $job->action_name = ScheduledJob::CREATE_INVOICE_REPORT;
                $job->action_class = $this->getClassPath(InvoiceExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_PAYMENT_REPORT:
                $job->action_name = ScheduledJob::CREATE_PAYMENT_REPORT;
                $job->action_class = $this->getClassPath(PaymentExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_PRODUCT_REPORT:
                $job->action_name = ScheduledJob::CREATE_PRODUCT_REPORT;
                $job->action_class = $this->getClassPath(ProductExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_PROFIT_AND_LOSS_REPORT:
                $job->action_name = ScheduledJob::CREATE_PROFIT_AND_LOSS_REPORT;
                $job->action_class = $this->getClassPath(ProfitAndLoss::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_QUOTE_ITEM_REPORT:
                $job->action_name = ScheduledJob::CREATE_QUOTE_ITEM_REPORT;
                $job->action_class = $this->getClassPath(QuoteItemExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_QUOTE_REPORT:
                $job->action_name = ScheduledJob::CREATE_QUOTE_REPORT;
                $job->action_class = $this->getClassPath(QuoteExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_RECURRING_INVOICE_REPORT:
                $job->action_name = ScheduledJob::CREATE_RECURRING_INVOICE_REPORT;
                $job->action_class = $this->getClassPath(RecurringInvoiceExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;
            case ScheduledJob::CREATE_TASK_REPORT:
                $job->action_name = ScheduledJob::CREATE_TASK_REPORT;
                $job->action_class = $this->getClassPath(TaskExport::class);
                $job->parameters = $this->runValidation(GenericReportRequest::class, $request->all());
                break;

        }
        return $job;
    }

    public function getClassPath($class): string
    {
        return $class = is_object($class) ? get_class($class) : $class;
    }


    public function updateJob(Scheduler $scheduler, UpdateScheduledJobRequest $request)
    {
        $job = $scheduler->job;
        if (!$job) {
            return abort(404);
        }
        $job = $this->setJobParameters($job, $request);
        $job->save();

    }
}
