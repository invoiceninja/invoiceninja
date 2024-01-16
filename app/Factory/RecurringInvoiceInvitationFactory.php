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

use App\Models\RecurringInvoiceInvitation;
use Illuminate\Support\Str;

class RecurringInvoiceInvitationFactory
{
    public static function create(int $company_id, int $user_id): RecurringInvoiceInvitation
    {
        $ii = new RecurringInvoiceInvitation();
        $ii->company_id = $company_id;
        $ii->user_id = $user_id;
        $ii->client_contact_id = null;
        $ii->recurring_invoice_id = null;
        $ii->key = Str::random(config('ninja.key_length'));
        $ii->transaction_reference = null;
        $ii->message_id = null;
        $ii->email_error = '';
        $ii->signature_base64 = '';
        $ii->signature_date = null;
        $ii->sent_date = null;
        $ii->viewed_date = null;
        $ii->opened_date = null;

        return $ii;
    }
}
