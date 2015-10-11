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

        if ($invoice->trashed() || $client->trashed()) {
            return false;
        }

        $account->loadLocalizationSettings($client);

        if ($account->pdf_email_attachment) {
            $invoice->updateCachedPDF();
        }

        $emailTemplate = $account->getEmailTemplate($reminder ?: $entityType);
        $emailSubject = $account->getEmailSubject($reminder ?: $entityType);

        $sent = false;

        foreach ($invoice->invitations as $invitation) {
            if ($this->sendInvitation($invitation, $invoice, $emailTemplate, $emailSubject)) {
                $sent = true;
            }
        }
        
        $account->loadLocalizationSettings();

        if ($sent === true) {
            Event::fire(new InvoiceSent($invoice));
        }

        return $sent ?: trans('texts.email_error');
    }

    private function sendInvitation($invitation, $invoice, $body, $subject)
    {
        $client = $invoice->client;
        $account = $invoice->account;
        
        if (Auth::check()) {
            $user = Auth::user();
        } else {
            $user = $invitation->user;
            if ($invitation->user->trashed()) {
                $user = $account->users()->orderBy('id')->first();
            }
        }

        if (!$user->email || !$user->confirmed) {
            return false;
        }

        if (!$invitation->contact->email || $invitation->contact->trashed()) {
            return false;
        }

        $variables = [
            'account' => $account,
            'client' => $client,
            'invitation' => $invitation,
            'amount' => $invoice->getRequestedAmount()
        ];

        $data['body'] = $this->processVariables($body, $variables);
        $data['link'] = $invitation->getLink();
        $data['entityType'] = $invoice->getEntityType();
        $data['invoiceId'] = $invoice->id;
        $data['invitation'] = $invitation;

        $subject = $this->processVariables($subject, $variables);
        $fromEmail = $user->email;
        $response = $this->sendTo($invitation->contact->email, $fromEmail, $account->getDisplayName(), $subject, ENTITY_INVOICE, $data);

        if ($response === true) {
            Activity::emailInvoice($invitation);
            return true;
        } else {
            return false;
        }
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
            '$firstName' => $data['invitation']->contact->first_name,
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

        return $str;
    }
}
