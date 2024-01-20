<?php
/**
 * Quote Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Factory;

use App\Models\QuoteInvitation;
use Illuminate\Support\Str;

class QuoteInvitationFactory
{
    public static function create(int $company_id, int $user_id): QuoteInvitation
    {
        $qi = new QuoteInvitation();
        $qi->company_id = $company_id;
        $qi->user_id = $user_id;
        $qi->client_contact_id = null;
        $qi->quote_id = null;
        $qi->key = Str::random(config('ninja.key_length'));
        $qi->transaction_reference = null;
        $qi->message_id = null;
        $qi->email_error = '';
        $qi->signature_base64 = '';
        $qi->signature_date = null;
        $qi->sent_date = null;
        $qi->viewed_date = null;
        $qi->opened_date = null;

        return $qi;
    }
}
