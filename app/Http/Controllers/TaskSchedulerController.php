<?php


namespace App\Http\Controllers;

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
use App\Jobs\Report\ProfitAndLoss;
use App\Models\ScheduledJob;
use App\Models\Scheduler;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Request;

class TaskSchedulerController extends BaseController
{
    public function store(CreateScheduledTaskRequest $request)
    {

        $scheduler = new Scheduler();
        $scheduler->paused = $request->get('paused', false);
        $scheduler->archived = (bool)$request->get('archived', false);
        $scheduler->start_from = $request->get('start_from') ? Carbon::parse((int)$request->get('start_from')) : Carbon::now();
        $scheduler->repeat_every = $request->get('repeat_every');
        $scheduler->scheduled_run = $request->get('start_from') ? Carbon::parse((int)$request->get('start_from')) : Carbon::now();;
        $scheduler->save();

        if ($this->createJob($request, $scheduler)) {
            $job = ScheduledJob::query()->latest()->first();
        }
        return $job;
    }

    public function createJob(CreateScheduledTaskRequest $request, Scheduler $scheduler): bool
    {
        $job = new ScheduledJob();

        switch ($request->job) {
            case 'client_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_CLIENT_REPORT;
                $job->action_class = $this->getClassPath(ClientExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'client_contact_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_CLIENT_CONTACT_REPORT;
                $job->action_class = $this->getClassPath(ContactExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'credit_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_CREDIT_REPORT;
                $job->action_class = $this->getClassPath(CreditExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'document_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_DOCUMENT_REPORT;
                $job->action_class = $this->getClassPath(DocumentExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'expense_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_EXPENSE_REPORT;
                $job->action_class = $this->getClassPath(ExpenseExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'invoice_item_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_INVOICE_ITEM_REPORT;
                $job->action_class = $this->getClassPath(InvoiceItemExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'invoice_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_INVOICE_REPORT;
                $job->action_class = $this->getClassPath(InvoiceExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'payment_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_PAYMENT_REPORT;
                $job->action_class = $this->getClassPath(PaymentExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'product_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_PRODUCT_REPORT;
                $job->action_class = $this->getClassPath(ProductExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'profit_and_loss_report':
                $rules = (new ProfitLossRequest())->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_PROFIT_AND_LOSS_REPORT;
                $job->action_class = $this->getClassPath(ProfitAndLoss::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'quote_item_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_QUOTE_ITEM_REPORT;
                $job->action_class = $this->getClassPath(QuoteItemExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'quote_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_QUOTE_REPORT;
                $job->action_class = $this->getClassPath(QuoteExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'recurring_invoice_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_RECURRING_INVOICE_REPORT;
                $job->action_class = $this->getClassPath(RecurringInvoiceExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;
            case 'task_report':
                $rules = (new GenericReportRequest)->rules();
                $validated = $request->validate($rules);
                $job->action_name = ScheduledJob::CREATE_TASK_REPORT;
                $job->action_class = $this->getClassPath(TaskExport::class);
                $job->parameters = $this->saveActionParameters($rules, $request);
                break;

        }
        $job->scheduler_id = $scheduler->id;
        return $job->save();

    }

    public function getClassPath($class): string
    {
        return $class = is_object($class) ? get_class($class) : $class;
    }

    public function saveActionParameters(array $rules, $request): array
    {
        $parameters = [];
        foreach ($rules as $rule => $key) {
            if (isset($request->{$rule})) {
                $parameters[$rule] = $request->{$rule};
            }
        }
        $parameters['company'] = auth()->user()->company();
        return $parameters;
    }


}
