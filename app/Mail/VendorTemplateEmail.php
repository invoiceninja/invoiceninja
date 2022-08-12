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

namespace App\Mail;

use App\Jobs\Invoice\CreateUbl;
use App\Jobs\Vendor\CreatePurchaseOrderPdf;
use App\Models\Account;
use App\Models\Client;
use App\Models\User;
use App\Models\VendorContact;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use App\Utils\TemplateEngine;
use App\Utils\VendorHtmlEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class VendorTemplateEmail extends Mailable
{
    private $build_email;

    private $vendor;

    private $contact;

    private $company;

    private $invitation;

    public function __construct($build_email, VendorContact $contact, $invitation = null)
    {
        $this->build_email = $build_email;

        $this->contact = $contact;

        $this->vendor = $contact->vendor;

        $this->company = $contact->company;

        $this->invitation = $invitation;
    }

    public function build()
    {
        $template_name = 'email.template.'.$this->build_email->getTemplate();

        if ($this->build_email->getTemplate() == 'light' || $this->build_email->getTemplate() == 'dark') {
            $template_name = 'email.template.client';
        }

        if ($this->build_email->getTemplate() == 'custom') {
            $this->build_email->setBody(str_replace('$body', $this->build_email->getBody(), $this->client->getSetting('email_style_custom')));
        }

        $settings = $this->company->settings;

        if ($this->build_email->getTemplate() !== 'custom') {
            $this->build_email->setBody(
                DesignHelpers::parseMarkdownToHtml($this->build_email->getBody())
            );
        }

        if ($this->invitation) {
            $html_variables = (new VendorHtmlEngine($this->invitation))->makeValues();
            $signature = str_replace(array_keys($html_variables), array_values($html_variables), $settings->email_signature);
        } else {
            $signature = $settings->email_signature;
        }

        if (property_exists($settings, 'email_from_name') && strlen($settings->email_from_name) > 1) {
            $email_from_name = $settings->email_from_name;
        } else {
            $email_from_name = $this->company->present()->name();
        }

        $this->from(config('mail.from.address'), $email_from_name);

        if (strlen($settings->bcc_email) > 1) {
            $this->bcc(explode(',', str_replace(' ', '', $settings->bcc_email)));
        }//remove whitespace if any has been inserted.

        $this->subject($this->build_email->getSubject())
            ->text('email.template.text', [
                'text_body' => $this->build_email->getTextBody(),
                'whitelabel' => $this->vendor->user->account->isPaid() ? true : false,
                'settings' => $settings,
            ])
            ->view($template_name, [
                'greeting' => ctrans('texts.email_salutation', ['name' => $this->contact->present()->name()]),
                'body' => $this->build_email->getBody(),
                'footer' => $this->build_email->getFooter(),
                'view_link' => $this->build_email->getViewLink(),
                'view_text' => $this->build_email->getViewText(),
                'title' => '',
                'signature' => $signature,
                'settings' => $settings,
                'company' => $this->company,
                'whitelabel' => $this->vendor->user->account->isPaid() ? true : false,
                'logo' => $this->company->present()->logo($settings),
            ]);
            //->withSymfonyMessage(function ($message) {
            //    $message->getHeaders()->addTextHeader('Tag', $this->company->company_key);
            //    $message->invitation = $this->invitation;
            //});
            // ->tag($this->company->company_key);

        if(Ninja::isHosted() && $this->invitation){

            $path = false;

            if($this->invitation->purchase_order)
                $path = $this->vendor->purchase_order_filepath($this->invitation).$this->invitation->purchase_order->numberFormatter().'.pdf';

            sleep(1);

            if($path && !Storage::disk(config('filesystems.default'))->exists($path)){

                sleep(2);

                if(!Storage::disk(config('filesystems.default'))->exists($path)) {
                    (new CreatePurchaseOrderPdf($this->invitation))->handle();
                    sleep(2);
                }

            }

        }

        foreach ($this->build_email->getAttachments() as $file) {
            if (is_string($file)) {
                $this->attach($file);
            } elseif (is_array($file)) {
                $this->attach($file['path'], ['as' => $file['name'], 'mime' => null]);
            }
        }

        return $this;
    }
}
