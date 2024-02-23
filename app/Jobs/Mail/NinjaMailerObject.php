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

namespace App\Jobs\Mail;

/**
 * NinjaMailerObject.
 */
class NinjaMailerObject
{
    /* @var Illuminate\Mail\Mailable */
    public $mailable;

    /* @var Company $company */
    public $company;

    public $from_user; //not yet used

    public $to_user;

    public $settings;

    public $transport; //not yet used

    /* Variable for cascading notifications */
    public $entity_string = false;

    /* @var App\Models\InvoiceInvitation | App\Models\QuoteInvitation | App\Models\CreditInvitation | App\Models\RecurringInvoiceInvitation | App\Models\PurchaseOrderInvitation | \bool $invitation*/
    public $invitation = false;

    public $template = false;

    /* @var bool | App\Models\Invoice | App\Models\Quote | App\Models\Credit | App\Models\RecurringInvoice | App\Models\PurchaseOrder  | App\Models\Payment $entity */
    public $entity = false;

    public $reminder_template = '';
}
