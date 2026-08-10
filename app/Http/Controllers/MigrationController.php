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

use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\TransactionEvent;
use App\Services\EDocument\Standards\France\FranceReportingEventType;
use App\Services\EDocument\Standards\France\FranceReportingStatus;
use App\Utils\Ninja;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MigrationController extends BaseController
{
    use DispatchesJobs;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Purge Company.
     *
     * @OA\Post(
     *      path="/api/v1/migration/purge/{company}",
     *      operationId="postPurgeCompany",
     *      tags={"migration"},
     *      summary="Attempts to purge a company record and all its child records",
     *      description="Attempts to purge a company record and all its child records",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="company",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function purgeCompany(Company $company)
    {
        if (Ninja::isHosted() && config('ninja.ninja_default_company_id') == $company->id) {
            return response()->json(['message' => 'Cannot purge this company'], 400);
        }

        $account = $company->account;
        $company_id = $company->id;

        $company->delete();

        /*Update the new default company if necessary*/
        if ($company_id == $account->default_company_id && $account->companies->count() >= 1) {
            $new_default_company = $account->companies->first();

            if ($new_default_company) {
                $account->default_company_id = $new_default_company->id;
                $account->save();
            }
        }

        return response()->json(['message' => 'Company purged'], 200);
    }

    /**
     * Purge Company but save settings.
     *
     * @OA\Post(
     *      path="/api/v1/migration/purge_save_settings/{company}",
     *      operationId="postPurgeCompanySaveSettings",
     *      tags={"migration"},
     *      summary="Attempts to purge a companies child records but save the company record and its settings",
     *      description="Attempts to purge a companies child records but save the company record and its settings",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="company",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     * @param Request $request
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function purgeCompanySaveSettings(Request $request, Company $company)
    {
        DB::transaction(function () use ($company): void {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();

            if (TransactionEvent::query()
                ->where('company_id', $company->id)
                ->where(function ($query): void {
                    $query->whereIn('event_id', FranceReportingEventType::retainedValues())
                        ->orWhere(function ($pendingInvalidationQuery): void {
                            $pendingInvalidationQuery
                                ->where('event_id', FranceReportingEventType::ScopeInvalidation->value)
                                ->whereNull('payment_status');
                        })->orWhere(function ($pendingCallbackQuery): void {
                            $pendingCallbackQuery
                                ->where('event_id', FranceReportingEventType::SubmissionCallback->value)
                                ->where('payment_status', FranceReportingStatus::Pending->value);
                        });
                })
                ->exists()) {
                throw ValidationException::withMessages([
                    'company' => 'This company has France reporting records that must be retained.',
                ]);
            }

            $hasReportableSources = (bool) $company->getSetting('france_reporting_enabled')
                && (Invoice::withTrashed()
                    ->where('company_id', $company->id)
                    ->where('is_deleted', false)
                    ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
                    ->exists()
                    || Credit::withTrashed()
                        ->where('company_id', $company->id)
                        ->where('is_deleted', false)
                        ->whereIn('status_id', [Credit::STATUS_SENT, Credit::STATUS_PARTIAL, Credit::STATUS_APPLIED])
                        ->exists());

            if ($hasReportableSources) {
                throw ValidationException::withMessages([
                    'company' => 'This company has documents awaiting France reporting reconciliation.',
                ]);
            }

            $company->clients()->forceDelete();
            $company->products()->forceDelete();
            $company->projects()->forceDelete();
            $company->tasks()->forceDelete();
            $company->vendors()->forceDelete();
            $company->expenses()->forceDelete();
            $company->purchase_orders()->forceDelete();
            $company->bank_transaction_rules()->forceDelete();
            $company->bank_transactions()->forceDelete();
            $company->all_activities()->forceDelete();

            $settings = $company->settings;
            $settings->recurring_invoice_number_counter = 1;
            $settings->invoice_number_counter = 1;
            $settings->quote_number_counter = 1;
            $settings->client_number_counter = 1;
            $settings->credit_number_counter = 1;
            $settings->task_number_counter = 1;
            $settings->expense_number_counter = 1;
            $settings->recurring_expense_number_counter = 1;
            $settings->recurring_quote_number_counter = 1;
            $settings->vendor_number_counter = 1;
            $settings->ticket_number_counter = 1;
            $settings->payment_number_counter = 1;
            $settings->project_number_counter = 1;
            $settings->purchase_order_number_counter = 1;
            $company->settings = $settings;
            $company->save();
        }, attempts: 3);

        return response()->json(['message' => 'Settings preserved'], 200);
    }

}
