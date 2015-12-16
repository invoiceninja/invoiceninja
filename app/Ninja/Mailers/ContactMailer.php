<?php namespace App\Ninja\Mailers;

use HTML;
use Utils;
use Event;
use URL;
use Auth;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Activity;
use App\Models\Gateway;

use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;

class ContactMailer extends Mailer
{
    public static $variableFields = [
        'footer',
        'account',
        'client',
        'amount',
        'contact',
        'firstName',
        'invoice',
        'quote',
        'viewLink',
        'viewButton',
        'paymentLink',
        'paymentButton',
    ];

    public function sendInvoice(Invoice $invoice, $reminder = false, $pdfString = false)
    {
        $invoice->load('invitations', 'client.language', 'account');
        $entityType = $invoice->getEntityType();

        $client = $invoice->client;
        $account = $invoice->account;

        if ($client->trashed()) {
            return trans('texts.email_errors.inactive_client');
        } elseif ($invoice->trashed()) {
            return trans('texts.email_errors.inactive_invoice');
        }

        $account->loadLocalizationSettings($client);
        $emailTemplate = $account->getEmailTemplate($reminder ?: $entityType);
        $emailSubject = $account->getEmailSubject($reminder ?: $entityType);

        $sent = false;

        if ($account->attatchPDF() && !$pdfString) {
            $pdfString = $invoice->getPDFString();
        }

        foreach ($invoice->invitations as $invitation) {
            $response = $this->sendInvitation($invitation, $invoice, $emailTemplate, $emailSubject, $pdfString);
            if ($response === true) {
                $sent = true;
            }
        }
        
        $account->loadLocalizationSettings();

        if ($sent === true) {
            if ($invoice->is_quote) {
                event(new QuoteWasEmailed($invoice));
            } else {
                event(new InvoiceWasEmailed($invoice));
            }
        }

        return $response;
    }

    private function sendInvitation($invitation, $invoice, $body, $subject, $pdfString)
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

        if (!$user->email || !$user->registered) {
            return trans('texts.email_errors.user_unregistered');
        } elseif (!$user->confirmed) {
            return trans('texts.email_errors.user_unconfirmed');
        } elseif (!$invitation->contact->email) {
            return trans('texts.email_errors.invalid_contact_email');
        } elseif ($invitation->contact->trashed()) {
            return trans('texts.email_errors.inactive_contact');
        }

        $variables = [
            'account' => $account,
            'client' => $client,
            'invitation' => $invitation,
            'amount' => $invoice->getRequestedAmount()
        ];

        $data = [
            'body' => $this->processVariables($body, $variables),
            'link' => $invitation->getLink(),
            'entityType' => $invoice->getEntityType(),
            'invoiceId' => $invoice->id,
            'invitation' => $invitation,
            'account' => $account,
            'client' => $client,
            'invoice' => $invoice,
        ];

        if ($account->attatchPDF()) {
            $data['pdfString'] = $pdfString;
            $data['pdfFileName'] = $invoice->getFileName();
        }

        $subject = $this->processVariables($subject, $variables);
        $fromEmail = $user->email;

        if ($account->email_design_id == EMAIL_DESIGN_PLAIN) {
            $view = ENTITY_INVOICE;
        } else {
            $view = 'design' . ($account->email_design_id - 1);
        }
        
        $response = $this->sendTo($invitation->contact->email, $fromEmail, $account->getDisplayName(), $subject, $view, $data);

        if ($response === true) {
            return true;
        } else {
            return $response;
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
            'amount' => $payment->amount,
        ];

        $data = [
            'body' => $this->processVariables($emailTemplate, $variables),
            'link' => $invitation->getLink(),
            'invoice' => $invoice,
            'client' => $client,
            'account' => $account,
            'payment' => $payment,
            'entityType' => ENTITY_INVOICE,
        ];

        if ($account->attatchPDF()) {
            $data['pdfString'] = $invoice->getPDFString();
            $data['pdfFileName'] = $invoice->getFileName();
        }

        $subject = $this->processVariables($emailSubject, $variables);
        $data['invoice_id'] = $payment->invoice->id;

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
            'amount' => Utils::formatMoney($amount, DEFAULT_CURRENCY, DEFAULT_COUNTRY),
            'license' => $license
        ];
        
        $this->sendTo($email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    private function processVariables($template, $data)
    {
        $account = $data['account'];
        $client = $data['client'];
        $invitation = $data['invitation'];
        $invoice = $invitation->invoice;

        $variables = [
            '$footer' => $account->getEmailFooter(),
            '$client' => $client->getDisplayName(),
            '$account' => $account->getDisplayName(),
            '$contact' => $invitation->contact->getDisplayName(),
            '$firstName' => $invitation->contact->first_name,
            '$amount' => $account->formatMoney($data['amount'], $client),
            '$invoice' => $invoice->invoice_number,
            '$quote' => $invoice->invoice_number,
            '$link' => $invitation->getLink(),
            '$viewLink' => $invitation->getLink(),
            '$viewButton' => HTML::emailViewButton($invitation->getLink(), $invoice->getEntityType()),
            '$paymentLink' => $invitation->getLink('payment'),
            '$paymentButton' => HTML::emailPaymentButton($invitation->getLink('payment')),
        ];

        // Add variables for available payment types
        foreach (Gateway::$paymentTypes as $type) {
            $camelType = Gateway::getPaymentTypeName($type);
            $type = Utils::toSnakeCase($camelType);
            $variables["\${$camelType}Link"] = $invitation->getLink() . "/{$type}";
            $variables["\${$camelType}Button"] = HTML::emailPaymentButton($invitation->getLink('payment')  . "/{$type}");
        }

        $str = str_replace(array_keys($variables), array_values($variables), $template);

        return $str;
    }
}
