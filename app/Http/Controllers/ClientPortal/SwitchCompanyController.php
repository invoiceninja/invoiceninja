<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
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
        $current_contact = auth()->guard('contact')->user();

        $client_contact = ClientContact::query()
            ->where('email', $current_contact->email)
            ->where('email', '<>', '')
            ->where('id', $this->transformKeys($contact))
            ->whereHas('company', fn ($query) => $query->where(
                'companies.account_id',
                $current_contact->company->account_id
            ))
            ->firstOrFail();

        
        request()->session()->invalidate();
        request()->session()->regenerate(true);
        request()->session()->regenerateToken();
        
        auth()->guard('contact')->loginUsingId($client_contact->id, true);

        return redirect('/client/dashboard');
    }
}
