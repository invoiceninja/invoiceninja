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
    public $mailable;

    /* @var Company $company */
    public $company;

    public $from_user; //not yet used

    public $to_user;

    public $settings;

    public $transport; //not yet used

    /* Variable for cascading notifications */
    public $entity_string = false;

    /* @var bool | App\Models\InvoiceInvitation | app\Models\QuoteInvitation | app\Models\CreditInvitation | app\Models\RecurringInvoiceInvitation | app\Models\PurchaseOrderInvitation $invitation*/
    public $invitation = false;

    public $template = false;

    /* @var bool | App\Models\Invoice | app\Models\Quote | app\Models\Credit | app\Models\RecurringInvoice | app\Models\PurchaseOrder $invitation*/
    public $entity = false;

    public $reminder_template = '';
}
