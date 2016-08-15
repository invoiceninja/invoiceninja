<?php namespace App\Http\Controllers;

use stdClass;
use Auth;
use DB;
use View;
use App\Models\Invoice;
use App\Models\Payment;
use App\Ninja\Repositories\DashboardRepository;

/**
 * Class DashboardController
 */
class DashboardController extends BaseController
{
    public function __construct(DashboardRepository $dashboardRepo)
    {
        $this->dashboardRepo = $dashboardRepo;
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $user = Auth::user();
        $viewAll = $user->hasPermission('view_all');
        $userId = $user->id;
        $accountId = $user->account->id;

        $dashboardRepo = $this->dashboardRepo;
        $metrics = $dashboardRepo->totals($accountId, $userId, $viewAll);
        $paidToDate = $dashboardRepo->paidToDate($accountId, $userId, $viewAll);
        $averageInvoice = $dashboardRepo->averages($accountId, $userId, $viewAll);
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
            'account' => $user->account,
            'paidToDate' => $paidToDate,
            'balances' => $balances,
            'averageInvoice' => $averageInvoice,
            'invoicesSent' => $metrics ? $metrics->invoices_sent : 0,
            'activeClients' => $metrics ? $metrics->active_clients : 0,
            'activities' => $activities,
            'pastDue' => $pastDue,
            'upcoming' => $upcoming,
            'payments' => $payments,
            'title' => trans('texts.dashboard'),
            'hasQuotes' => $hasQuotes,
        ];

        return View::make('dashboard', $data);
    }
}
