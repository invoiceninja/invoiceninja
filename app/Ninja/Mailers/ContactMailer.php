<?php namespace App\Ninja\Mailers;

use Utils;
use Event;
use URL;
use Auth;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Activity;
use App\Models\Gateway;
use App\Events\InvoiceSent;

class ContactMailer extends Mailer
{
    public function sendInvoice(Invoice $invoice, $reminder = false)
    {
        $invoice->load('invitations', 'client.language', 'account');
        $entityType = $invoice->getEntityType();

        $client = $invoice->client;
        $account = $invoice->account;

        $account->loadLocalizationSettings($client);

        $view = 'invoice';
        $accountName = $invoice->account->getDisplayName();
        $emailTemplate = $invoice->account->getEmailTemplate($reminder ?: $entityType);
        $emailSubject = $invoice->account->getEmailSubject($reminder ?: $entityType);

        $this->initClosure($invoice);
        $response = false;
        $sent = false;

        foreach ($invoice->invitations as $invitation) {
            if (Auth::check()) {
                $user = Auth::user();
            } else {
                $user = $invitation->user;
                if ($invitation->user->trashed()) {
                    $user = $account->getPrimaryUser();
                }
            }
        
            if (!$user->email || !$user->confirmed) {
                continue;
            }

            if (!$invitation->contact->email
                || $invitation->contact->trashed()) {
                continue;
            }

            $invitation->sent_date = \Carbon::now()->toDateTimeString();
            $invitation->save();

            $variables = [
                'account' => $account,
                'client' => $client,
                'invitation' => $invitation,
                'amount' => $invoice->getRequestedAmount()
            ];

            $data['body'] = $this->processVariables($emailTemplate, $variables);
            $data['link'] = $invitation->getLink();
            $data['entityType'] = $entityType;
            $data['invoice_id'] = $invoice->id;

            $subject = $this->processVariables($emailSubject, $variables);
            $fromEmail = $user->email;
            $response = $this->sendTo($invitation->contact->email, $fromEmail, $accountName, $subject, $view, $data);

            if ($response === true) {
                $sent = true;
                Activity::emailInvoice($invitation);
            }
        }
        
        if ($sent === true) {
            if (!$invoice->isSent()) {
                $invoice->invoice_status_id = INVOICE_STATUS_SENT;
                $invoice->save();
            }
            
            $account->loadLocalizationSettings();
            Event::fire(new InvoiceSent($invoice));
        }

        return $response ?: trans('texts.email_error');
    }

    public function sendPaymentConfirmation(Payment $payment)
    {
        $account = $payment->account;
        $client = $payment->client;

        $account->loadLocalizationSettings($client);

        $invoice = $payment->invoice;
        $view = 'payment_confirmation';
        $accountName = $account->getDisplayName();
        $emailTemplate = $account->getEmailTemplate(ENTITY_PAYMENT);
        $emailSubject = $invoice->account->getEmailSubject(ENTITY_PAYMENT);

        $this->initClosure($invoice);
        
        if ($payment->invitation) {
            $user = $payment->invitation->user;
            $contact = $payment->contact;
            $invitation = $payment->invitation;
        } else {
            $user = $payment->user;
            $contact = $client->contacts[0];
            $invitation = $payment->invoice->invitations[0];
        }

        $variables = [
            'account' => $account,
            'client' => $client,
            'invitation' => $invitation,
            'amount' => $payment->amount
        ];

        $data = [
            'body' => $this->processVariables($emailTemplate, $variables)
        ];
        $subject = $this->processVariables($emailSubject, $variables);

        $data['invoice_id'] = $payment->invoice->id;
        if ($invoice->account->pdf_email_attachment) {
            $invoice->updateCachedPDF();
        }

        if ($user->email && $contact->email) {
            $this->sendTo($contact->email, $user->email, $accountName, $subject, $view, $data);
        }

        $account->loadLocalizationSettings();
    }

    public function sendLicensePaymentConfirmation($name, $email, $amount, $license, $productId)
    {
        $view = 'license_confirmation';
        $subject = trans('texts.payment_subject');
        
        if ($productId == PRODUCT_ONE_CLICK_INSTALL) {
            $license = "Softaculous install license: $license";
        } elseif ($productId == PRODUCT_INVOICE_DESIGNS) {
            $license = "Invoice designs license: $license";
        } elseif ($productId == PRODUCT_WHITE_LABEL) {
            $license = "White label license: $license";
        }
        
        $data = [
            'account' => trans('texts.email_from'),
            'client' => $name,
            'amount' => Utils::formatMoney($amount, 1),
            'license' => $license
        ];
        
        $this->sendTo($email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    private function processVariables($template, $data)
    {
        $variables = [
            '$footer' => $data['account']->getEmailFooter(),
            '$link' => $data['invitation']->getLink(),
            '$client' => $data['client']->getDisplayName(),
            '$account' => $data['account']->getDisplayName(),
            '$contact' => $data['invitation']->contact->getDisplayName(),
            '$amount' => Utils::formatMoney($data['amount'], $data['client']->getCurrencyId()),
            '$invoice' => $data['invitation']->invoice->invoice_number,
            '$quote' => $data['invitation']->invoice->invoice_number,
            '$advancedRawInvoice->' => '$'
        ];

        // Add variables for available payment types
        foreach (Gateway::getPaymentTypeLinks() as $type) {
            $variables["\${$type}_link"] = URL::to("/payment/{$data['invitation']->invitation_key}/{$type}");
        }

        $str = str_replace(array_keys($variables), array_values($variables), $template);
        $str = preg_replace_callback('/\{\{\$?(.*)\}\}/', $this->advancedTemplateHandler, $str);

        return $str;
    }

    private function initClosure($object)
    {
        $this->advancedTemplateHandler = function($match) use ($object) {
            for ($i = 1; $i < count($match); $i++) {
                $blobConversion = $match[$i];

                if (isset($$blobConversion)) {
                    return $$blobConversion;
                } else if (preg_match('/trans\(([\w\.]+)\)/', $blobConversion, $regexTranslation)) {
                    return trans($regexTranslation[1]);
                } else if (strpos($blobConversion, '->') !== false) {
                    return Utils::stringToObjectResolution($object, $blobConversion);
                }

            }
        };
    }
}
