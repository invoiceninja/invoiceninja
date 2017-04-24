<?php

namespace App\Ninja\Mailers;

use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\TemplateService;
use Event;
use Utils;

class ContactMailer extends Mailer
{
    /**
     * @var TemplateService
     */
    protected $templateService;

    /**
     * ContactMailer constructor.
     *
     * @param TemplateService $templateService
     */
    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
    }

    /**
     * @param Invoice $invoice
     * @param bool    $reminder
     * @param bool    $pdfString
     *
     * @return bool|null|string
     */
    public function sendInvoice(Invoice $invoice, $reminder = false, $template = false)
    {
        if ($invoice->is_recurring) {
            return false;
        }

        $invoice->load('invitations', 'client.language', 'account');
        $entityType = $invoice->getEntityType();

        $client = $invoice->client;
        $account = $invoice->account;

        $response = null;

        if ($client->trashed()) {
            return trans('texts.email_error_inactive_client');
        } elseif ($invoice->trashed()) {
            return trans('texts.email_error_inactive_invoice');
        }

        $account->loadLocalizationSettings($client);
        $emailTemplate = !empty($template['body']) ? $template['body'] : $account->getEmailTemplate($reminder ?: $entityType);
        $emailSubject = !empty($template['subject']) ? $template['subject'] : $account->getEmailSubject($reminder ?: $entityType);

        $sent = false;
        $pdfString = false;

        if ($account->attachPDF()) {
            $pdfString = $invoice->getPDFString();
        }

        $documentStrings = [];
        if ($account->document_email_attachment && $invoice->hasDocuments()) {
            $documents = $invoice->documents;

            foreach ($invoice->expenses as $expense) {
                $documents = $documents->merge($expense->documents);
            }

            $documents = $documents->sortBy('size');

            $size = 0;
            $maxSize = MAX_EMAIL_DOCUMENTS_SIZE * 1000;
            foreach ($documents as $document) {
                $size += $document->size;
                if ($size > $maxSize) {
                    break;
                }

                $documentStrings[] = [
                    'name' => $document->name,
                    'data' => $document->getRaw(),
                ];
            }
        }

        $isFirst = true;
        foreach ($invoice->invitations as $invitation) {
            $response = $this->sendInvitation($invitation, $invoice, $emailTemplate, $emailSubject, $pdfString, $documentStrings, $reminder, $isFirst);
            $isFirst = false;
            if ($response === true) {
                $sent = true;
            }
        }

        $account->loadLocalizationSettings();

        if ($sent === true) {
            if ($invoice->isType(INVOICE_TYPE_QUOTE)) {
                event(new QuoteWasEmailed($invoice, $reminder));
            } else {
                event(new InvoiceWasEmailed($invoice, $reminder));
            }
        }

        return $response;
    }

    /**
     * @param Invitation $invitation
     * @param Invoice    $invoice
     * @param $body
     * @param $subject
     * @param $pdfString
     * @param $documentStrings
     * @param mixed $reminder
     *
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     *
     * @return bool|string
     */
    private function sendInvitation(
        Invitation $invitation,
        Invoice $invoice,
        $body,
        $subject,
        $pdfString,
        $documentStrings,
        $reminder,
        $isFirst
    ) {
        $client = $invoice->client;
        $account = $invoice->account;
        $user = $invitation->user;

        if ($user->trashed()) {
            $user = $account->users()->orderBy('id')->first();
        }

        if (! $user->email || ! $user->registered) {
            return trans('texts.email_error_user_unregistered');
        } elseif (! $user->confirmed) {
            return trans('texts.email_error_user_unconfirmed');
        } elseif (! $invitation->contact->email) {
            return trans('texts.email_error_invalid_contact_email');
        } elseif ($invitation->contact->trashed()) {
            return trans('texts.email_error_inactive_contact');
        }

        $variables = [
            'account' => $account,
            'client' => $client,
            'invitation' => $invitation,
            'amount' => $invoice->getRequestedAmount(),
        ];

        // Let the client know they'll be billed later
        if ($client->autoBillLater()) {
            $variables['autobill'] = $invoice->present()->autoBillEmailMessage();
        }

        if (empty($invitation->contact->password) && $account->isClientPortalPasswordEnabled() && $account->send_portal_password) {
            // The contact needs a password
            $variables['password'] = $password = $this->generatePassword();
            $invitation->contact->password = bcrypt($password);
            $invitation->contact->save();
        }

        $data = [
            'body' => $this->templateService->processVariables($body, $variables),
            'link' => $invitation->getLink(),
            'entityType' => $invoice->getEntityType(),
            'invoiceId' => $invoice->id,
            'invitation' => $invitation,
            'account' => $account,
            'client' => $client,
            'invoice' => $invoice,
            'documents' => $documentStrings,
            'notes' => $reminder,
            'bccEmail' => $isFirst ? $account->getBccEmail() : false,
            'fromEmail' => $account->getFromEmail(),
        ];

        if ($account->attachPDF()) {
            $data['pdfString'] = $pdfString;
            $data['pdfFileName'] = $invoice->getFileName();
        }

        $subject = $this->templateService->processVariables($subject, $variables);
        $fromEmail = $account->getReplyToEmail() ?: $user->email;
        $view = $account->getTemplateView(ENTITY_INVOICE);

        $response = $this->sendTo($invitation->contact->email, $fromEmail, $account->getDisplayName(), $subject, $view, $data);

        if ($response === true) {
            return true;
        } else {
            return $response;
        }
    }

    /**
     * @param int $length
     *
     * @return string
     */
    protected function generatePassword($length = 9)
    {
        $sets = [
            'abcdefghjkmnpqrstuvwxyz',
            'ABCDEFGHJKMNPQRSTUVWXYZ',
            '23456789',
        ];
        $all = '';
        $password = '';
        foreach ($sets as $set) {
            $password .= $set[array_rand(str_split($set))];
            $all .= $set;
        }
        $all = str_split($all);
        for ($i = 0; $i < $length - count($sets); $i++) {
            $password .= $all[array_rand($all)];
        }
        $password = str_shuffle($password);

        return $password;
    }

    /**
     * @param Payment $payment
     */
    public function sendPaymentConfirmation(Payment $payment)
    {
        $account = $payment->account;
        $client = $payment->client;

        $account->loadLocalizationSettings($client);

        $invoice = $payment->invoice;
        $accountName = $account->getDisplayName();
        $emailTemplate = $account->getEmailTemplate(ENTITY_PAYMENT);
        $emailSubject = $invoice->account->getEmailSubject(ENTITY_PAYMENT);

        if ($payment->invitation) {
            $user = $payment->invitation->user;
            $contact = $payment->contact;
            $invitation = $payment->invitation;
        } else {
            $user = $payment->user;
            $contact = count($client->contacts) ? $client->contacts[0] : '';
            $invitation = $payment->invoice->invitations[0];
        }

        $variables = [
            'account' => $account,
            'client' => $client,
            'invitation' => $invitation,
            'amount' => $payment->amount,
        ];

        $data = [
            'body' => $this->templateService->processVariables($emailTemplate, $variables),
            'link' => $invitation->getLink(),
            'invoice' => $invoice,
            'client' => $client,
            'account' => $account,
            'payment' => $payment,
            'entityType' => ENTITY_INVOICE,
            'bccEmail' => $account->getBccEmail(),
            'fromEmail' => $account->getFromEmail(),
        ];

        if ($account->attachPDF()) {
            $data['pdfString'] = $invoice->getPDFString();
            $data['pdfFileName'] = $invoice->getFileName();
        }

        $subject = $this->templateService->processVariables($emailSubject, $variables);
        $data['invoice_id'] = $payment->invoice->id;

        $view = $account->getTemplateView('payment_confirmation');
        $fromEmail = $account->getReplyToEmail() ?: $user->email;

        if ($user->email && $contact->email) {
            $this->sendTo($contact->email, $fromEmail, $accountName, $subject, $view, $data);
        }

        $account->loadLocalizationSettings();
    }

    /**
     * @param $name
     * @param $email
     * @param $amount
     * @param $license
     * @param $productId
     */
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
            'client' => $name,
            'amount' => Utils::formatMoney($amount, DEFAULT_CURRENCY, DEFAULT_COUNTRY),
            'license' => $license,
        ];

        $this->sendTo($email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }
}
