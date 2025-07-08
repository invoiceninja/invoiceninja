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
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;

class SwitchCompanyController extends Controller
{
    use MakesHash;

    public function __invoke(string $contact)
    {
        $client_contact = ClientContact::query()
                                       ->where('email', auth()->user()->email)
                                       ->where('id', $this->transformKeys($contact))
                                       ->firstOrFail();

        auth()->guard('contact')->loginUsingId($client_contact->id, true);

        request()->session()->invalidate();
        request()->session()->regenerate(true);
        request()->session()->regenerateToken();

        return redirect('/client/dashboard');
    }
}
