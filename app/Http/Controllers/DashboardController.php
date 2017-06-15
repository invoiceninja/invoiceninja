<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Ninja\Repositories\DashboardRepository;
use Auth;
use Utils;
use View;

/**
 * Class DashboardController.
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
        $account = $user->account;
        $accountId = $account->id;

        $dashboardRepo = $this->dashboardRepo;
        $metrics = $dashboardRepo->totals($accountId, $userId, $viewAll);
        $paidToDate = $dashboardRepo->paidToDate($account, $userId, $viewAll);
        $averageInvoice = $dashboardRepo->averages($account, $userId, $viewAll);
        $balances = $dashboardRepo->balances($accountId, $userId, $viewAll);
        $activities = $dashboardRepo->activities($accountId, $userId, $viewAll);
        $pastDue = $dashboardRepo->pastDue($accountId, $userId, $viewAll);
        $upcoming = $dashboardRepo->upcoming($accountId, $userId, $viewAll);
        $payments = $dashboardRepo->payments($accountId, $userId, $viewAll);
        $expenses = $dashboardRepo->expenses($account, $userId, $viewAll);
        $tasks = $dashboardRepo->tasks($accountId, $userId, $viewAll);

        $showBlueVinePromo = $user->is_admin
            && env('BLUEVINE_PARTNER_UNIQUE_ID')
            && ! $account->company->bluevine_status
            && $account->created_at <= date('Y-m-d', strtotime('-1 month'));

        $showWhiteLabelExpired = Utils::isSelfHost() && $account->company->hasExpiredPlan(PLAN_WHITE_LABEL);

        // check if the account has quotes
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
            'user' => $user,
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
            'showBreadcrumbs' => false,
            'currencies' => $this->getCurrencyCodes(),
            'expenses' => $expenses,
            'tasks' => $tasks,
            'showBlueVinePromo' => $showBlueVinePromo,
            'showWhiteLabelExpired' => $showWhiteLabelExpired,
            'headerClass' => in_array(\App::getLocale(), ['lt', 'pl', 'cs', 'sl', 'tr_TR']) ? 'in-large' : 'in-thin',
            'footerClass' => in_array(\App::getLocale(), ['lt', 'pl', 'cs', 'sl', 'tr_TR']) ? '' : 'in-thin',
        ];

        if ($showBlueVinePromo) {
            $usdLast12Months = 0;
            $pastYear = date('Y-m-d', strtotime('-1 year'));
            $paidLast12Months = $dashboardRepo->paidToDate($account, $userId, $viewAll, $pastYear);

            foreach ($paidLast12Months as $item) {
                if ($item->currency_id == null) {
                    $currency = $user->account->currency_id ?: DEFAULT_CURRENCY;
                } else {
                    $currency = $item->currency_id;
                }

                if ($currency == CURRENCY_DOLLAR) {
                    $usdLast12Months += $item->value;
                }
            }

            $data['usdLast12Months'] = $usdLast12Months;
        }

        return View::make('dashboard', $data);
    }

    private function getCurrencyCodes()
    {
        $account = Auth::user()->account;
        $currencyIds = $account->currency_id ? [$account->currency_id] : [DEFAULT_CURRENCY];

        // get client/invoice currencies
        $data = Client::scope()
            ->withArchived()
            ->distinct()
            ->get(['currency_id'])
            ->toArray();

        array_map(function ($item) use (&$currencyIds) {
            $currencyId = intval($item['currency_id']);
            if ($currencyId && ! in_array($currencyId, $currencyIds)) {
                $currencyIds[] = $currencyId;
            }
        }, $data);

        // get expense currencies
        $data = Expense::scope()
            ->withArchived()
            ->distinct()
            ->get(['expense_currency_id'])
            ->toArray();

        array_map(function ($item) use (&$currencyIds) {
            $currencyId = intval($item['expense_currency_id']);
            if ($currencyId && ! in_array($currencyId, $currencyIds)) {
                $currencyIds[] = $currencyId;
            }
        }, $data);

        $currencies = [];
        foreach ($currencyIds as $currencyId) {
            $currencies[$currencyId] = Utils::getFromCache($currencyId, 'currencies')->code;
        }

        return $currencies;
    }

    public function chartData($groupBy, $startDate, $endDate, $currencyCode, $includeExpenses)
    {
        $includeExpenses = filter_var($includeExpenses, FILTER_VALIDATE_BOOLEAN);
        $data = $this->dashboardRepo->chartData(Auth::user()->account, $groupBy, $startDate, $endDate, $currencyCode, $includeExpenses);

        return json_encode($data);
    }
}
