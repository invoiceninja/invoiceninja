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

namespace App\Factory;

use App\Models\CreditInvitation;
use Illuminate\Support\Str;

class CreditInvitationFactory
{
    public static function create(int $company_id, int $user_id): CreditInvitation
    {
        $ci = new CreditInvitation();
        $ci->company_id = $company_id;
        $ci->user_id = $user_id;
        $ci->client_contact_id = null;
        $ci->credit_id = null;
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
