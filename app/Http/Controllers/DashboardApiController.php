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
        $viewAll = $user->hasPermission('view_all');
        $userId = $user->id;
        $accountId = $user->account->id;

        $dashboardRepo = $this->dashboardRepo;
        $metrics = $dashboardRepo->totals($accountId, $userId, $viewAll);
        $paidToDate = $dashboardRepo->paidToDate($user->account, $userId, $viewAll);
        $averageInvoice = $dashboardRepo->averages($user->account, $userId, $viewAll);
        $balances = $dashboardRepo->balances($accountId, $userId, $viewAll);
        $activities = $dashboardRepo->activities($accountId, $userId, $viewAll);
        $pastDue = $dashboardRepo->pastDue($accountId, $userId, $viewAll);
        $upcoming = $dashboardRepo->upcoming($accountId, $userId, $viewAll);
        $payments = $dashboardRepo->payments($accountId, $userId, $viewAll);

        $hasQuotes = false;
        foreach ([$upcoming, $pastDue] as $data) {
            foreach ($data as $invoice) {
                if ($invoice->invoice_type_id == INVOICE_TYPE_QUOTE) {
                    $hasQuotes = true;
                }
            }
        }

        $data = [
            'id' => 1,
            'paidToDate' => count($paidToDate) && $paidToDate[0]->value ? $paidToDate[0]->value : 0,
            'paidToDateCurrency' => count($paidToDate) && $paidToDate[0]->currency_id ? $paidToDate[0]->currency_id : 0,
            'balances' => count($balances) && $balances[0]->value ? $balances[0]->value : 0,
            'balancesCurrency' => count($balances) && $balances[0]->currency_id ? $balances[0]->currency_id : 0,
            'averageInvoice' => count($averageInvoice) && $averageInvoice[0]->invoice_avg ? $averageInvoice[0]->invoice_avg : 0,
            'averageInvoiceCurrency' => count($averageInvoice) && $averageInvoice[0]->currency_id ? $averageInvoice[0]->currency_id : 0,
            'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
            'activeClients' => $metrics ? $metrics->active_clients : 0,
            'activities' => $this->createCollection($activities, new ActivityTransformer(), ENTITY_ACTIVITY),
        ];

        return $this->response($data);
    }
}
