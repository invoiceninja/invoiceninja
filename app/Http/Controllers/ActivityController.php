<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers;

use App\Http\Requests\Activity\DownloadHistoricalEntityRequest;
use App\Http\Requests\Activity\ShowActivityRequest;
use App\Http\Requests\Activity\StoreNoteRequest;
use App\Models\Activity;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\Vendor;
use App\Services\Pdf\Purify;
use App\Transformers\ActivityTransformer;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Pdf\SimulatedPdf;
use App\Utils\Ninja;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use stdClass;

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

        if ($request->has('reactv2')) {

            /** @var \App\Models\User auth()->user() */
            $user = auth()->user();

            $activities = $this->activityFeedQuery($default_activities);

            if (!$user->isAdmin()) {
                $activities->where('user_id', auth()->user()->id);
            }

            $this->filterByUser($activities, $request);

            $this->olderThanCursor($activities, $request);

            return response()->json(['data' => $this->activityStringPayload($activities)], 200);
        }

        /* @var App\Models\Activity[] $activities */
        $activities = Activity::with('user')
                                ->orderBy('created_at', 'DESC')
                                ->company()
                                ->take($default_activities);

        $this->filterByUser($activities, $request);

        return $this->listResponse($activities);
    }

    /**
     * Narrows to the activities of the users in `user_id` (hashed ids, comma separated).
     * An id that doesn't decode matches nothing; it can only narrow, never widen, the user's scope.
     */
    private function filterByUser(Builder $activities, Request $request): void
    {
        $user_id = $request->input('user_id');

        if (!is_string($user_id) || strlen(trim($user_id)) == 0) {
            return;
        }

        $user_ids = collect(explode(',', $user_id))
                        ->map(fn ($id) => $this->decodePrimaryKey(trim($id), true))
                        ->filter(fn ($id) => is_int($id))
                        ->values()
                        ->all();

        $activities->whereIn('user_id', $user_ids);
    }

    /**
     * Keyset cursor for paging the feed: `before` names an activity (hashed id) of this company,
     * and only the activities after it in feed order - created_at, then id, descending - are returned.
     * The stored timestamp is compared as is, so the cut is exact to the microsecond.
     */
    private function olderThanCursor(Builder $activities, Request $request): void
    {
        if (!$request->has('before')) {
            return;
        }

        $request->validate(['before' => ['bail', 'required', 'string']]);

        $id = $this->decodePrimaryKey($request->input('before'), true);

        $boundary = is_int($id) ? Activity::query()->without('backup')->company()->find($id) : null;

        if (!$boundary) {
            throw ValidationException::withMessages(['before' => ctrans('texts.record_not_found')]);
        }

        $created_at = $boundary->getRawOriginal('created_at');

        $activities->where(function ($query) use ($created_at, $boundary) {
            $query->where('created_at', '<', $created_at)
                  ->orWhere(function ($query) use ($created_at, $boundary) {
                      $query->where('created_at', $created_at)
                            ->where('id', '<', $boundary->id);
                  });
        });
    }

    public function entityActivity(ShowActivityRequest $request)
    {

        $default_activities = request()->has('rows') ? request()->input('rows') : 75;

        $activities = $this->activityFeedQuery($default_activities)
                                ->where("{$request->entity}_id", $request->entity_id);

        // Filtered in SQL, so `rows` counts only the requested types (141 = notes).
        if ($request->filled('activity_type_id')) {
            $activities->whereIn('activity_type_id', array_map('intval', explode(',', (string) $request->input('activity_type_id'))));
        }

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();

        $entity = $request->getEntity();

        if ($user->cannot('view', $entity)) {
            $activities->where('user_id', auth()->user()->id);
        }

        return response()->json(['data' => $this->activityStringPayload($activities)], 200);

    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function activityStringPayload(Builder $activities): array
    {
        return $activities->get()
            ->map(function ($activity) {

                /** @var \App\Models\Activity $activity */
                return $activity->activity_string();

            })
            ->values()
            ->all();
    }

    private function activityFeedQuery(int $limit): Builder
    {
        return Activity::query()
            ->without('backup')
            ->with($this->activityStringRelations())
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->company()
            ->take($limit);
    }

    /**
     * @return array<int, string>
     */
    private function activityStringRelations(): array
    {
        return [
            'user',
            'client',
            'vendor',
            'invoice',
            'quote',
            'credit',
            'payment.client.country',
            'payment.client.company',
            'payment.client.group_settings',
            'task',
            'expense',
            'purchase_order',
            'subscription',
            'recurring_invoice',
            'recurring_expense',
            'contact',
            'vendor_contact',
            'company',
        ];
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
        } elseif (config('ninja.pdf_generator') == 'simulator') {
            $pdf = SimulatedPdf::generate($html_backup);

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        } else {
            $pdf = $this->makePdf(null, null, Purify::clean($html_backup));

            $numbered_pdf = $this->pageNumbering($pdf, $activity->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }
        }

        $activity->company->setLocale();

        if (isset($activity->invoice_id)) {
            $filename = $activity->invoice->numberFormatter() . '.pdf';
        } elseif (isset($activity->quote_id)) {
            $filename = $activity->quote->numberFormatter() . '.pdf';
        } elseif (isset($activity->credit_id)) {
            $filename = $activity->credit->numberFormatter() . '.pdf';
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
