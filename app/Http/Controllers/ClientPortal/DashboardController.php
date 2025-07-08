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

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function index(): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (auth()->guard('contact')->user()->client->getSetting('enable_client_portal_dashboard') === false) {
            return redirect()->route('client.invoices.index');
        }

        $total_invoices = Invoice::withTrashed()
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->where('is_deleted', 0)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
            ->sum('amount');

        return $this->render('dashboard.index', [
            'total_invoices' => $total_invoices,
        ]);
    }
}
