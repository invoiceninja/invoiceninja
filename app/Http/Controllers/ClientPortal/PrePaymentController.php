<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\ClientPortal;

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\PrePayments\StorePrePaymentRequest;
use App\Repositories\InvoiceRepository;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\View\Factory;
use Illuminate\View\View;

/**
 * Class PrePaymentController.
 */
class PrePaymentController extends Controller
{
    use MakesHash;
    use MakesDates;

    /**
     * Show the list of payments.
     *
     * @return Factory|View
     */
    public function index()
    {
        $data = [
            'title' => ctrans('texts.amount'). " " .auth()->guard('contact')->user()->client->currency()->code." (".auth()->guard('contact')->user()->client->currency()->symbol . ")",
            'allows_recurring' => auth()->guard('contact')->user()->client->getSetting('client_initiated_payments_recurring'),
            'allows_recurring' => true,
            'minimum_amount' => auth()->guard('contact')->user()->client->getSetting('client_initiated_payments_minimum'),
        ];

        return $this->render('pre_payments.index', $data);
    }

    public function process(StorePrePaymentRequest $request)
    {
        $invoice = InvoiceFactory::create(auth()->guard('contact')->user()->company_id, auth()->guard('contact')->user()->user_id);
        $invoice->due_date = now()->format('Y-m-d');
        $invoice->is_proforma = true;
        $invoice->client_id = auth()->guard('contact')->user()->client_id;

        $line_item = new InvoiceItem();
        $line_item->cost = (float)$request->amount;
        $line_item->quantity = 1;
        $line_item->product_key = ctrans('texts.pre_payment');
        $line_item->notes = $request->notes;
        $line_item->type_id = '1';

        $items = [];
        $items[] = $line_item;
        $invoice->line_items = $items;
        $invoice->number = ctrans('texts.pre_payment') . " " . now()->format('Y-m-d : H:i:s');

        $invoice_repo = new InvoiceRepository();

        $data = [
            'client_id' => $invoice->client_id,
            'quantity' => 1,
            'date' => now()->format('Y-m-d'),
        ];

        $invoice =  $invoice_repo->save($data, $invoice)
                                ->service()
                                ->markSent()
                                ->applyNumber()
                                ->fillDefaults()
                                ->save();

        $total = $invoice->balance;

        //format totals
        $formatted_total = Number::formatMoney($invoice->amount, auth()->guard('contact')->user()->client);

        $payment_methods = auth()->guard('contact')->user()->client->service()->getPaymentMethods($request->amount);

        //if there is only one payment method -> lets return straight to the payment page
        $invoices = collect();
        $invoices->push($invoice);

        $invoices->map(function ($invoice) {
            $invoice->balance = Number::formatValue($invoice->balance, $invoice->client->currency());
            return $invoice;
        });

        $data = [
            'settings' => auth()->guard('contact')->user()->client->getMergedSettings(),
            'invoices' => $invoices,
            'formatted_total' => $formatted_total,
            'payment_methods' => $payment_methods,
            'hashed_ids' => $invoices->pluck('hashed_id'),
            'total' =>  $total,
            'pre_payment' => true,
            'frequency_id' => $request->frequency_id,
            'remaining_cycles' => $request->remaining_cycles,
            'is_recurring' => $request->is_recurring == 'on' ? true : false,
        ];

        return $this->render('invoices.payment', $data);
    }
}
