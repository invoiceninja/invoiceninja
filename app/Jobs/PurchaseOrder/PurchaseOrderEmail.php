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

namespace App\Jobs\PurchaseOrder;

use App\Events\PurchaseOrder\PurchaseOrderWasEmailed;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Engine\PurchaseOrderEmailEngine;
use App\Mail\VendorTemplateEmail;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Utils\Ninja;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class PurchaseOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public PurchaseOrder $purchase_order;

    public Company $company;

    public $template_data;

    public $tries = 1;

    public function __construct(PurchaseOrder $purchase_order, Company $company, $template_data = null)
    {
        $this->purchase_order = $purchase_order;
        $this->company = $company;
        $this->template_data = $template_data;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $this->purchase_order->last_sent_date = now();

        $this->purchase_order->invitations->load('contact.vendor.country', 'purchase_order.vendor.country', 'purchase_order.company')->each(function ($invitation) {

        /* Don't fire emails if the company is disabled */
            if ($this->company->is_disabled) {
                return true;
            }

            /* Set DB */
            MultiDB::setDB($this->company->db);

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($invitation->contact->preferredLocale());
            $t->replace(Ninja::transformTranslations($this->company->settings));

            /* Mark entity sent */
            $invitation->purchase_order->service()->markSent()->save();

            $email_builder = (new PurchaseOrderEmailEngine($invitation, 'purchase_order', $this->template_data))->build();

            $nmo = new NinjaMailerObject;
            $nmo->mailable = new VendorTemplateEmail($email_builder, $invitation->contact, $invitation);
            $nmo->company = $this->company;
            $nmo->settings = $this->company->settings;
            $nmo->to_user = $invitation->contact;
            $nmo->entity_string = 'purchase_order';
            $nmo->invitation = $invitation;
            $nmo->reminder_template = 'purchase_order';
            $nmo->entity = $invitation->purchase_order;

            NinjaMailerJob::dispatchSync($nmo);
        });

        if ($this->purchase_order->invitations->count() >= 1) {
            event(new PurchaseOrderWasEmailed($this->purchase_order->invitations->first(), $this->purchase_order->invitations->first()->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }
    }
}
