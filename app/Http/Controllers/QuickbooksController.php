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

use App\Events\Socket\QuickbooksEntityStatusChanged;
use App\Http\Requests\Quickbooks\ActionQuickbooksRequest;
use App\Http\Requests\Quickbooks\DisconnectQuickbooksRequest;
use App\Http\Requests\Quickbooks\SyncQuickbooksRequest;
use App\Http\Requests\Quickbooks\SyncTaxRatesRequest;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Quickbooks\Jobs\QuickbooksImport;
use App\Services\Quickbooks\QuickbooksService;
use App\Transformers\InvoiceTransformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class QuickbooksController extends BaseController
{
    protected $entity_type = Invoice::class;

    protected $entity_transformer = InvoiceTransformer::class;

    /**
     * sync
     *
     * Syncs the Quickbooks entities to Invoice Ninja
     *
     * @param  SyncQuickbooksRequest $request
     * @return \Illuminate\Http\Response
     */
    public function sync(SyncQuickbooksRequest $request)
    {

        $user = auth()->user();
        $company = $user->company();

        $syncable = [];

        if ($request->client) {
            $syncable[] = 'Customer';
        }
        if ($request->product) {
            $syncable[] = 'Item';
        }
        if ($request->invoice) {
            $syncable[] = 'Invoice';
        }

        QuickbooksImport::dispatch($company->id, $company->db, $syncable);

        return response()->noContent();
    }

    public function action(ActionQuickbooksRequest $request): \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();
        $invoice = $request->getInvoice();
        $action = $request->validated('action');

        if ($action === 'check_record') {
            MultiDB::setDb($company->db);

            $quickbooks = new QuickbooksService($company);
            $invoice = $quickbooks->invoice->check($invoice);
            $transformer = new InvoiceTransformer($request->input('serializer'));

            return $this->response([
                ...$transformer->transform($invoice),
                'meta' => [
                    'quickbooks_check' => $quickbooks->invoice->checkContext(),
                ],
            ]);
        }

        dispatch(function () use ($company, $invoice, $action, $user) {
            MultiDB::setDb($company->db);

            $quickbooks = new QuickbooksService($company);
            $current_status = $invoice->sync->qb_status ?? '';
            $current_status_message = $invoice->sync->qb_status_message ?? '';

            try {
                match ($action) {
                    'force_link' => $quickbooks->invoice->forceLink($invoice),
                    'force_pull' => $quickbooks->invoice->forcePull($invoice),
                    'force_push' => $quickbooks->invoice->forcePush($invoice),
                    default => '',
                };
            } catch (\Throwable $e) {
                nlog("QuickBooks action {$action} failed for invoice {$invoice->id}: {$e->getMessage()}", [
                    'trace' => $e->getTraceAsString(),
                ]);

                return;
            }

            $invoice = $invoice->fresh();
            $new_status = $invoice->sync->qb_status ?? '';
            $new_status_message = $invoice->sync->qb_status_message ?? '';

            if (($current_status != $new_status) || ($current_status_message != $new_status_message)) {
                event(new QuickbooksEntityStatusChanged('invoice', $invoice->hashed_id, $user));
            }
            
        })->afterResponse();

        return response()->noContent();
    }

    /**
     * syncTaxRates
     *
     * Syncs tax rates from Quickbooks to Invoice Ninja
     *
     * @param  SyncTaxRatesRequest $request
     * @return \Illuminate\Http\Response
     */
    public function syncTaxRates(SyncTaxRatesRequest $request)
    {
        $user = auth()->user();
        $company = $user->company();

        $qb = new QuickbooksService($company);
        $qb->syncTaxRates();

        return response()->noContent();
    }

    /**
     * disconnect
     *
     * Disconnects the Quickbooks Account From the Invoice Ninja Company
     *
     * @param  DisconnectQuickbooksRequest $request
     * @return \Illuminate\Http\Response
     */
    public function disconnect(DisconnectQuickbooksRequest $request)
    {

        $user = auth()->user();
        $company = $user->company();

        try {
            $qb = new QuickbooksService($company);
            $qb->disconnect();
        } catch (\Throwable $e) {
            /** Regardless of what happens, we should always set the quickbooks object to null */
            $company->quickbooks = null;
            $company->save();

        }

        return response()->noContent();
    }

    /**
     * reconnectUrl
     *
     * Returns the URL for the user to reconnect their QuickBooks account.
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function reconnectUrl(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();

        if (!$company->quickbooks || !$company->quickbooks->isConfigured()) {
            return response()->json(['error' => 'No QuickBooks connection exists'], 400);
        }

        // Generate a one-time token for the reconnect flow
        $token = Str::random(64);

        Cache::put($token, [
            'context' => 'quickbooks.reconnect',
            'company_key' => $company->company_key,
            'user_id' => $user->id,
        ], now()->addMinutes(30));

        return response()->json([
            'reconnect_url' => route('quickbooks.reconnect', ['token' => $token]),
            'requires_reconnect' => $company->quickbooks->requires_reconnect,
            'refresh_token_expires_at' => $company->quickbooks->refreshTokenExpiresAt,
        ]);
    }
}
