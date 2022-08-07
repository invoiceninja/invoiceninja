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

namespace App\Http\Controllers\ClientPortal;

use App\Events\Credit\CreditWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Credits\ShowCreditRequest;
use App\Http\Requests\ClientPortal\Credits\ShowCreditsRequest;
use App\Models\Credit;
use App\Utils\Ninja;

class CreditController extends Controller
{
    public function index(ShowCreditsRequest $request)
    {
        return $this->render('credits.index');
    }

    public function show(ShowCreditRequest $request, Credit $credit)
    {
        set_time_limit(0);

        $invitation = $credit->invitations()->where('client_contact_id', auth()->user()->id)->first();

        $data = [
            'credit' => $credit,
            'key' => $invitation ? $invitation->key : false,
        ];

        if ($invitation && auth()->guard('contact') && ! request()->has('silent') && ! $invitation->viewed_date) {
            $invitation->markViewed();

            event(new InvitationWasViewed($credit, $invitation, $credit->company, Ninja::eventVars()));
            event(new CreditWasViewed($invitation, $invitation->company, Ninja::eventVars()));
        }

        if ($request->query('mode') === 'fullscreen') {
            return render('credits.show-fullscreen', $data);
        }

        return $this->render('credits.show', $data);
    }
}
