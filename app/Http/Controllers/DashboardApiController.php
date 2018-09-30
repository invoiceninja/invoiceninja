<?php

namespace App\Http\Controllers;

use App\Ninja\Repositories\DashboardRepository;
use App\Ninja\Transformers\ActivityTransformer;
use Auth;

class DashboardApiController extends BaseAPIController
{
    public function __construct(DashboardRepository $dashboardRepo)
    {
        parent::__construct();

        $this->dashboardRepo = $dashboardRepo;
    }

    public function index()
    {
        $user = Auth::user();
        $viewAll = $user->hasPermission('view_reports');
        $userId = $user->id;
        $accountId = $user->account->id;
        $defaultCurrency = $user->account->currency_id;

        $dashboardRepo = $this->dashboardRepo;
        $activities = $dashboardRepo->activities($accountId, $userId, $viewAll);

        // optimization for new mobile app
        if (request()->only_activity) {
            return $this->response([
                'id' => 1,
                'activities' => $this->createCollection($activities, new ActivityTransformer(), ENTITY_ACTIVITY),
            ]);
        }

        $metrics = $dashboardRepo->totals($accountId, $userId, $viewAll);
        $paidToDate = $dashboardRepo->paidToDate($user->account, $userId, $viewAll);
        $averageInvoice = $dashboardRepo->averages($user->account, $userId, $viewAll);
        $balances = $dashboardRepo->balances($user->account, $userId, $viewAll);
        $pastDue = $dashboardRepo->pastDue($accountId, $userId, $viewAll);
        $upcoming = $dashboardRepo->upcoming($accountId, $userId, $viewAll);
        $payments = $dashboardRepo->payments($accountId, $userId, $viewAll);

        $data = [
            'id' => 1,
            'paidToDate' => (float) ($paidToDate->count() && $paidToDate[0]->value ? $paidToDate[0]->value : 0),
            'paidToDateCurrency' => (int) ($paidToDate->count() && $paidToDate[0]->currency_id ? $paidToDate[0]->currency_id : $defaultCurrency),
            'balances' => (float) ($balances->count() && $balances[0]->value ? $balances[0]->value : 0),
            'balancesCurrency' => (int) ($balances->count() && $balances[0]->currency_id ? $balances[0]->currency_id : $defaultCurrency),
            'averageInvoice' => (float) ($averageInvoice->count() && $averageInvoice[0]->invoice_avg ? $averageInvoice[0]->invoice_avg : 0),
            'averageInvoiceCurrency' => (int) ($averageInvoice->count() && $averageInvoice[0]->currency_id ? $averageInvoice[0]->currency_id : $defaultCurrency),
            'invoicesSent' => (int) ($metrics ? $metrics->invoices_sent : 0),
            'activeClients' => (int) ($metrics ? $metrics->active_clients : 0),
            'activities' => $this->createCollection($activities, new ActivityTransformer(), ENTITY_ACTIVITY),
        ];

        return $this->response($data);
    }
}
