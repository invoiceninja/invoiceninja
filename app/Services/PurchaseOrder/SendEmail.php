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

namespace App\Services\PurchaseOrder;

use App\Events\PurchaseOrder\PurchaseOrderWasEmailed;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Engine\PurchaseOrderEmailEngine;
use App\Mail\VendorTemplateEmail;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Services\AbstractService;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;

class SendEmail extends AbstractService
{
    public function __construct(protected PurchaseOrder $purchase_order, protected ?string $reminder_template = null, protected ?VendorContact $contact = null)
    {
    }

    /**
     * Builds the correct template to send.
     */
    public function run()
    {
        $this->purchase_order->last_sent_date = now();

        $this->purchase_order->invitations->load('contact.vendor.country', 'purchase_order.vendor.country', 'purchase_order.company')->each(function ($invitation) {

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($invitation->contact->preferredLocale());
            $t->replace(Ninja::transformTranslations($this->purchase_order->company->settings));

            /* Mark entity sent */
            $invitation->purchase_order->service()->markSent()->save();

            $template = 'purchase_order';

            $email_builder = (new PurchaseOrderEmailEngine($invitation, $template, null))->build();

            $nmo = new NinjaMailerObject();
            $nmo->mailable = new VendorTemplateEmail($email_builder, $invitation->contact, $invitation);
            $nmo->company = $this->purchase_order->company;
            $nmo->settings = $this->purchase_order->company->settings;
            $nmo->to_user = $invitation->contact;
            $nmo->entity_string = 'purchase_order';
            $nmo->invitation = $invitation;
            $nmo->reminder_template = 'email_template_purchase_order';
            $nmo->entity = $invitation->purchase_order;

            NinjaMailerJob::dispatch($nmo);
        });

        if ($this->purchase_order->invitations->count() >= 1) {
            event(new PurchaseOrderWasEmailed($this->purchase_order->invitations->first(), $this->purchase_order->invitations->first()->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }

    }
}
