<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Controllers\EInvoice;

use App\Http\Controllers\Controller;
use App\Http\Requests\EInvoice\SignupRequest;

class SelfhostController extends Controller
{

    public function index(SignupRequest $request)
    {
        return view('einvoice.index');
    }

}