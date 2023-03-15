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

use Illuminate\View\View;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\Factory;

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
        $data['minimum_amount'] = auth()->guard('contact')->user()->client->getSetting('client_initiated_payments_minimum');
        $data['title'] = ctrans('texts.amount'). " " .auth()->guard('contact')->user()->client->currency()->code." (".auth()->guard('contact')->user()->client->currency()->symbol . ")";
        return $this->render('pre_payments.index', $data);
    }

}