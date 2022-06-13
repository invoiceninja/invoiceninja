<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\VendorPortal;

use App\Events\Misc\InvitationWasViewed;
use App\Events\PurchaseOrder\PurchaseOrderWasViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\VendorPortal\PurchaseOrders\ShowPurchaseOrderRequest;
use App\Http\Requests\VendorPortal\PurchaseOrders\ShowPurchaseOrdersRequest;
use App\Models\PurchaseOrder;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    use MakesHash, MakesDates;

    public const MODULE_RECURRING_INVOICES = 1;
    public const MODULE_CREDITS = 2;
    public const MODULE_QUOTES = 4;
    public const MODULE_TASKS = 8;
    public const MODULE_EXPENSES = 16;
    public const MODULE_PROJECTS = 32;
    public const MODULE_VENDORS = 64;
    public const MODULE_TICKETS = 128;
    public const MODULE_PROPOSALS = 256;
    public const MODULE_RECURRING_EXPENSES = 512;
    public const MODULE_RECURRING_TASKS = 1024;
    public const MODULE_RECURRING_QUOTES = 2048;
    public const MODULE_INVOICES = 4096;
    public const MODULE_PROFORMAL_INVOICES = 8192;
    public const MODULE_PURCHASE_ORDERS = 16384;

    /**
     * Display list of invoices.
     *
     * @return Factory|View
     */
    public function index(ShowPurchaseOrdersRequest $request)
    {

        return $this->render('purchase_orders.index');
    }

    /**
     * Show specific invoice.
     *
     * @param ShowInvoiceRequest $request
     * @param Invoice $invoice
     *
     * @return Factory|View
     */
    public function show(ShowPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        set_time_limit(0);

        $invitation = $purchase_order->invitations()->where('vendor_contact_id', auth()->guard('vendor')->user()->id)->first();

            if ($invitation && auth()->guard('vendor') && !session()->get('is_silent') && ! $invitation->viewed_date) {

                $invitation->markViewed();

                event(new InvitationWasViewed($purchase_order, $invitation, $purchase_order->company, Ninja::eventVars()));
                event(new PurchaseOrderWasViewed($invitation, $invitation->company, Ninja::eventVars()));
            
            }


        $data = [
            'purchase_order' => $purchase_order,
            'key' => $invitation ? $invitation->key : false,
            'settings' => $purchase_order->company->settings,
            'sidebar' => $this->sidebarMenu(),
            'company' => $purchase_order->company
        ];

        if ($request->query('mode') === 'fullscreen') {
            return render('purchase_orders.show-fullscreen', $data);
        }

        return $this->render('purchase_orders.show', $data);
    }



    private function sidebarMenu() :array
    {
        $enabled_modules = auth()->guard('vendor')->user()->company->enabled_modules;
        $data = [];

        // TODO: Enable dashboard once it's completed.
        // $this->settings->enable_client_portal_dashboard
        // $data[] = [ 'title' => ctrans('texts.dashboard'), 'url' => 'client.dashboard', 'icon' => 'activity'];

        if (self::MODULE_PURCHASE_ORDERS & $enabled_modules) {
            $data[] = ['title' => ctrans('texts.purchase_orders'), 'url' => 'vendor.purchase_orders.index', 'icon' => 'file-text'];
        }

        // $data[] = ['title' => ctrans('texts.documents'), 'url' => 'client.documents.index', 'icon' => 'download'];

        return $data;
    }

}
