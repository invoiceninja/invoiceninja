<?php

namespace App\Factory;

use App\Models\PurchaseOrderInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PurchaseOrderInvitationFactory
{
    public static function create(int $company_id, int $user_id) :PurchaseOrderInvitation
    {
        $ci = new PurchaseOrderInvitation();
        $ci->company_id = $company_id;
        $ci->user_id = $user_id;
        $ci->vendor_contact_id = null;
        $ci->purchase_order_id = null;
        $ci->key = Str::random(config('ninja.key_length'));
        $ci->transaction_reference = null;
        $ci->message_id = null;
        $ci->email_error = '';
        $ci->signature_base64 = '';
        $ci->signature_date = null;
        $ci->sent_date = null;
        $ci->viewed_date = null;
        $ci->opened_date = null;

        return $ci;
    }
}
