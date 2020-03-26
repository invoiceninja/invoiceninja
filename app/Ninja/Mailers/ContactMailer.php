<?php

namespace App\Ninja\Mailers;

use App\Events\InvoiceWasEmailed;
use App\Events\QuoteWasEmailed;
use App\Models\Invoice;
use App\Models\Proposal;
use App\Models\Payment;
use App\Services\TemplateService;
use App\Jobs\ConvertInvoiceToUbl;
use Event;
use Utils;
use Cache;
use Mail;

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
    public function sendInvoice(Invoice $invoice, $reminder = false, $template = false, $proposal = false)
    {
        if ($invoice->is_recurring) {
            return false;
        }

        $invoice->load('invitations', 'client.language', 'account');

        if ($proposal) {
            $entityType = ENTITY_PROPOSAL;
        } else {
            $entityType = $invoice->getEntityType();
        }

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
        $ublString = false;

        if ($account->attachUBL() && ! $proposal) {
            $ublString = dispatch_now(new ConvertInvoiceToUbl($invoice));
        }

        $documentStrings = [];
        if ($account->document_email_attachment && $invoice->hasDocuments()) {
            $documents = $invoice->allDocuments();
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
        $invitations = $proposal ? $proposal->invitations : $invoice->invitations;
        foreach ($invitations as $invitation) {
            if ($account->attachPDF() && ! $proposal) {
                $pdfString = $invoice->getPDFString($invitation);
            }
            $data = [
                'pdfString' => $pdfString,
                'documentStrings' => $documentStrings,
                'ublString' => $ublString,
                'proposal' => $proposal,
            ];
            $response = $this->sendInvitation($invitation, $invoice, $emailTemplate, $emailSubject, $reminder, $isFirst, $data);
            $isFirst = false;
            if ($response === true) {
                $sent = true;
            }
        }

        $account->loadLocalizationSettings();

        if ($sent === true && ! $proposal) {
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
        $invitation,
        Invoice $invoice,
        $body,
        $subject,
        $reminder,
        $isFirst,
        $extra
    ) {
        $client = $invoice->client;
        $account = $invoice->account;
        $user = $invitation->user;
        $proposal = $extra['proposal'];

        if ($user->trashed()) {
            $user = $account->users()->orderBy('id')->first();
        }

        if (! $user->email || ! $user->registered) {
            return trans('texts.email_error_user_unregistered');
        } elseif (! $user->confirmed || $this->isThrottled($account)) {
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

        if (! $proposal) {
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
        }

        $body = $this->templateService->processVariables($body, $variables);

        if (Utils::isNinja()) {
            $body = \HTMLUtils::sanitizeHTML($body);
        }

        $data = [
            'body' => $body,
            'link' => $invitation->getLink(),
            'entityType' => $proposal ? ENTITY_PROPOSAL : $invoice->getEntityType(),
            'invoiceId' => $invoice->id,
            'invitation' => $invitation,
            'account' => $account,
            'client' => $client,
            'invoice' => $invoice,
            'documents' => $extra['documentStrings'],
            'notes' => $reminder,
            'bccEmail' => $isFirst ? $account->getBccEmail() : false,
            'fromEmail' => $account->getFromEmail(),
            'proposal' => $proposal,
            'tag' => $account->account_key,
        ];

        if (! $proposal) {
            if ($account->attachPDF()) {
                $data['pdfString'] = $extra['pdfString'];
                $data['pdfFileName'] = $invoice->getFileName();
            }
            if ($account->attachUBL()) {
                $data['ublString'] = $extra['ublString'];
                $data['ublFileName'] = $invoice->getFileName('xml');
            }
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
    public function sendPaymentConfirmation(Payment $payment, $refunded = 0)
    {
        $account = $payment->account;
        $client = $payment->client;

        $account->loadLocalizationSettings($client);
        $invoice = $payment->invoice;
        $invitation = $payment->invitation ?: $payment->invoice->invitations[0];
        $accountName = $account->getDisplayName();

        if ($refunded > 0) {
            $emailSubject = trans('texts.refund_subject');
            $emailTemplate = trans('texts.refund_body', [
                'amount' => $account->formatMoney($refunded, $client),
                'invoice_number' => $invoice->invoice_number,
            ]);
        } else {
            $emailSubject = $invoice->account->getEmailSubject(ENTITY_PAYMENT);
            $emailTemplate = $account->getEmailTemplate(ENTITY_PAYMENT);
        }

        if ($payment->invitation) {
            $user = $payment->invitation->user;
            $contact = $payment->contact;
        } else {
            $user = $payment->user;
            $contact = $client->contacts->count() ? $client->contacts[0] : '';
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
            'isRefund' => $refunded > 0,
            'tag' => $account->account_key,
        ];

        if (! $refunded && $account->attachPDF()) {
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

    public function sendPasswordReset($contact, $token)
    {
        if (! $contact->email) {
            return;
        }

        $subject = trans('texts.your_password_reset_link');
        $view = 'client_password';
        $data = [
            'token' => $token,
        ];

        $this->sendTo($contact->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    private function isThrottled($account)
    {
        if (Utils::isSelfHost()) {
            return false;
        }

        $key = $account->company_id;

        // http://stackoverflow.com/questions/1375501/how-do-i-throttle-my-sites-api-users
        $day = 60 * 60 * 24;
        $day_limit = $account->getDailyEmailLimit();
        $day_throttle = Cache::get("email_day_throttle:{$key}", null);
        $last_api_request = Cache::get("last_email_request:{$key}", 0);
        $last_api_diff = time() - $last_api_request;

        if (is_null($day_throttle)) {
            $new_day_throttle = 0;
        } else {
            $new_day_throttle = $day_throttle - $last_api_diff;
            $new_day_throttle = $new_day_throttle < 0 ? 0 : $new_day_throttle;
            $new_day_throttle += $day / $day_limit;
            $day_hits_remaining = floor(($day - $new_day_throttle) * $day_limit / $day);
            $day_hits_remaining = $day_hits_remaining >= 0 ? $day_hits_remaining : 0;
        }

        Cache::put("email_day_throttle:{$key}", $new_day_throttle, 60);
        Cache::put("last_email_request:{$key}", time(), 60);

        if ($new_day_throttle > $day) {
            $errorEmail = env('ERROR_EMAIL');
            if ($errorEmail && ! Cache::get("throttle_notified:{$key}")) {
                Mail::raw('Account Throttle: ' . $account->account_key, function ($message) use ($errorEmail, $account) {
                    $message->to($errorEmail)
                            ->from(CONTACT_EMAIL)
                            ->subject("Email throttle triggered for account " . $account->id);
                });
            }
            Cache::put("throttle_notified:{$key}", true, 60 * 24);
            return true;
        }

        return false;
    }
}
