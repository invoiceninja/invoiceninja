<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use stdClass;
use App\Models\Task;
use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Activity;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Utils\PhantomJS\Phantom;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Traits\Pdf\PdfMaker;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\Storage;
use App\Transformers\ActivityTransformer;
use App\Http\Requests\Activity\StoreNoteRequest;
use App\Http\Requests\Activity\ShowActivityRequest;
use App\Http\Requests\Activity\DownloadHistoricalEntityRequest;

class ActivityController extends BaseController
{
    use PdfMaker;
    use PageNumbering;
    use MakesHash;

    protected $entity_type = Activity::class;

    protected $entity_transformer = ActivityTransformer::class;

    public function __construct()
    {
        parent::__construct();
    }

    public function index(Request $request)
    {
        $default_activities = $request->has('rows') ? $request->input('rows') : 75;

        /* @var App\Models\Activity[] $activities */
        $activities = Activity::with('user')
                                ->orderBy('created_at', 'DESC')
                                ->company()
                                ->take($default_activities);

        if ($request->has('reactv2')) {

            /** @var \App\Models\User auth()->user() */
            $user = auth()->user();

            if (!$user->isAdmin()) {
                $activities->where('user_id', auth()->user()->id);
            }

            $system = ctrans('texts.system');

            $data = $activities->cursor()->map(function ($activity) {

                /** @var \App\Models\Activity $activity */
                return $activity->activity_string();

            });

            return response()->json(['data' => $data->toArray()], 200);
        }

        return $this->listResponse($activities);
    }

    public function entityActivity(ShowActivityRequest $request)
    {

        $default_activities = request()->has('rows') ? request()->input('rows') : 75;

        $activities = Activity::with('user')
                                ->orderBy('created_at', 'DESC')
                                ->company()
                                ->where("{$request->entity}_id", $request->entity_id)
                                ->take($default_activities);

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();

        $entity = $request->getEntity();

        if ($user->cannot('view', $entity)) {
            $activities->where('user_id', auth()->user()->id);
        }

        $system = ctrans('texts.system');

        $data = $activities->cursor()->map(function ($activity) {

            /** @var \App\Models\Activity $activity */
            return $activity->activity_string();

        });

        return response()->json(['data' => $data->toArray()], 200);

    }


    /**
     * downloadHistoricalEntity
     *
     * @param  DownloadHistoricalEntityRequest $request
     * @param  Activity $activity
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse
     */
    public function downloadHistoricalEntity(DownloadHistoricalEntityRequest $request, Activity $activity)
    {
        $backup = $activity->backup;
        $html_backup = '';


        if (!$activity->backup) {
            return response()->json(['message' => ctrans('texts.no_backup_exists'), 'errors' => new stdClass()], 404);
        }

        $file = $backup->getFile();

        $html_backup = $file;

        if (!$file) {
            return response()->json(['message' => ctrans('texts.no_backup_exists'), 'errors' => new stdClass()], 404);
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            $pdf = (new Phantom())->convertHtmlToPdf($html_backup);

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        } elseif (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($html_backup);

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        } else {
            $pdf = $this->makePdf(null, null, $html_backup);

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        }

        $activity->company->setLocale();

        if (isset($activity->invoice_id)) {
            $filename = $activity->invoice->numberFormatter().'.pdf';
        } elseif (isset($activity->quote_id)) {
            $filename = $activity->quote->numberFormatter().'.pdf';
        } elseif (isset($activity->credit_id)) {
            $filename = $activity->credit->numberFormatter().'.pdf';
        } else {
            $filename = 'backup.pdf';
        }

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function note(StoreNoteRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $entity = $request->getEntity();

        $activity = new Activity();
        $activity->account_id = $user->account_id;
        $activity->company_id = $user->company()->id;
        $activity->notes = $request->notes;
        $activity->user_id = $user->id;
        $activity->ip = $request->ip();
        $activity->activity_type_id = Activity::USER_NOTE;

        switch (get_class($entity)) {
            case Invoice::class:
                $activity->invoice_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;
                $activity->vendor_id = $entity->vendor_id;

                $activity->save();
                return $this->itemResponse($activity);

            case Credit::class:
                $activity->credit_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;
                $activity->vendor_id = $entity->vendor_id;
                $activity->invoice_id = $entity->invoice_id;

                $activity->save();
                return $this->itemResponse($activity);

            case Client::class:
                $activity->client_id = $entity->id;

                $activity->save();
                return $this->itemResponse($activity);

            case Quote::class:
                $activity->quote_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;
                $activity->vendor_id = $entity->vendor_id;

                $activity->save();
                return $this->itemResponse($activity);

            case RecurringInvoice::class:
                $activity->recurring_invoice_id = $entity->id;
                $activity->client_id = $entity->client_id;
                                
                $activity->save();
                return $this->itemResponse($activity);

            case Expense::class:
                $activity->expense_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;
                $activity->vendor_id = $entity->vendor_id;

                $activity->save();
                return $this->itemResponse($activity);
            case RecurringExpense::class:
                $activity->recurring_expense_id = $entity->id;
                $activity->expense_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;
                $activity->vendor_id = $entity->vendor_id;

                $activity->save();
                return $this->itemResponse($activity);
            case Vendor::class:
                $activity->vendor_id = $entity->id;
                $activity->save();

                return $this->itemResponse($activity);

            case PurchaseOrder::class:
                $activity->purchase_order_id = $entity->id;
                $activity->expense_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;
                $activity->vendor_id = $entity->vendor_id;

                $activity->save();

                return $this->itemResponse($activity);
            case Task::class:
                $activity->task_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;

                $activity->save();

                return $this->itemResponse($activity);

            case Payment::class:
                $activity->payment_id = $entity->id;
                $activity->client_id = $entity->client_id;
                $activity->project_id = $entity->project_id;
                
                $activity->save();
                return $this->itemResponse($activity);

            case Project::class:
                $activity->project_id = $entity->id;
                $activity->save();

                return $this->itemResponse($activity);
            default:
                # code...
                break;
        }

        
    }
}
