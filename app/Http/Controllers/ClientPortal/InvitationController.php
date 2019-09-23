<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\Controllers\ClientPortal;

use App\Http\Controllers\Controller;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Class InvitationController
 * @package App\Http\Controllers\ClientPortal\InvitationController
 */

class InvitationController extends Controller
{

    use MakesHash;
    use MakesDates;

    public function invoiceRouter(string $invitation_key)
    {

    	$invitation = InvoiceInvitation::whereInvitationKey($invitation_key)->first();

    	if($invitation){
    		$invitation->markViewed();
    		Auth::guard('contact')->loginUsingId($invitation->client_contact_id, true);
    		return redirect()->route('client.invoice.show', ['invoice' => $this->encodePrimaryKey($invitation->invoice_id)]);
    	}
    	else
    		abort(404);

    }
}
