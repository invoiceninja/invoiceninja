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

use App\Events\Credit\CreditWasViewed;
use App\Events\Invoice\InvoiceWasViewed;
use App\Events\Misc\InvitationWasViewed;
use App\Events\Quote\QuoteWasViewed;
use App\Http\Controllers\Controller;
use App\Jobs\Entity\CreateRawPdf;
use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Services\ClientPortal\InstantPayment;
use App\Utils\CurlUtils;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Class InvitationController.
 */
class InvitationController extends Controller
{
    use MakesHash;
    use MakesDates;

    public function purchaseOrder(string $invitation_key)
    {

        Auth::logout();

        $invitation = PurchaseOrderInvitation::withTrashed()
                                    ->where('key', $invitation_key)
                                    ->whereHas('purchase_order', function ($query) {
                                         $query->where('is_deleted',0);
                                    })
                                    ->with('contact.vendor')
                                    ->first();

        if(!$invitation)
            return abort(404,'The resource is no longer available.');

        if($invitation->contact->trashed())
            $invitation->contact->restore();

        $vendor_contact = $invitation->contact;
        $entity = 'purchase_order';

        if(empty($vendor_contact->email))
            $vendor_contact->email = Str::random(15) . "@example.com"; $vendor_contact->save();

        if (request()->has('vendor_hash') && request()->input('vendor_hash') == $invitation->contact->vendor->vendor_hash) {
            request()->session()->invalidate();
            auth()->guard('vendor')->loginUsingId($vendor_contact->id, true);

        } else {
            request()->session()->invalidate();
            auth()->guard('vendor')->loginUsingId($vendor_contact->id, true);
        }

        session()->put('is_silent', request()->has('silent'));

        if (auth()->guard('vendor')->user() && ! session()->get('is_silent') && ! $invitation->viewed_date) {

            $invitation->markViewed();
            event(new InvitationWasViewed($invitation->purchase_order, $invitation, $invitation->company, Ninja::eventVars()));
            
        }
        else{

            return redirect()->route('vendor.'.$entity.'.show', [$entity => $this->encodePrimaryKey($invitation->purchase_order_id), 'silent' => session()->get('is_silent')]);

        }

        return redirect()->route('vendor.'.$entity.'.show', [$entity => $this->encodePrimaryKey($invitation->purchase_order_id)]);


    }

    public function download(string $invitation_key)
    {
        $invitation = PurchaseOrderInvitation::withTrashed()
                            ->where('key', $invitation_key)
                            ->with('contact.vendor')
                            ->firstOrFail();

        if(!$invitation)
            return response()->json(["message" => "no record found"], 400);

        $file_name = $invitation->purchase_order->numberFormatter().'.pdf';

        // $file = CreateRawPdf::dispatchNow($invitation, $invitation->company->db);

        $file = (new CreatePurchaseOrderPdf($invitation))->rawPdf();

        $headers = ['Content-Type' => 'application/pdf'];

        if(request()->input('inline') == 'true')
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);

        return response()->streamDownload(function () use($file) {
                echo $file;
        },  $file_name, $headers);
    }




}
