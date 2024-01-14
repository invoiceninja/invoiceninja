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

namespace App\Repositories;

use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;

/**
 * RecurringInvoiceRepository.
 */
class RecurringInvoiceRepository extends BaseRepository
{
    public function save($data, RecurringInvoice $invoice): ?RecurringInvoice
    {
        $invoice = $this->alternativeSave($data, $invoice);

        return $invoice;
    }

    public function getInvitationByKey($key): ?RecurringInvoiceInvitation
    {
        return RecurringInvoiceInvitation::withTrashed()->where('key', $key)->first();
    }
}
