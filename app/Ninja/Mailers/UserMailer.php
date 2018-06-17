<?php

namespace App\Ninja\Mailers;

use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

class UserMailer extends Mailer
{
    /**
     * @param User      $user
     * @param User|null $invitor
     */
    public function sendConfirmation(User $user, User $invitor = null)
    {
        if (! $user->email) {
            return;
        }

        $view = 'confirm';
        $subject = trans('texts.confirmation_subject');

        $data = [
            'user' => $user,
            'invitationMessage' => $invitor ? trans('texts.invitation_message', ['invitor' => $invitor->getDisplayName()]) : '',
        ];

        if ($invitor) {
            $fromEmail = $invitor->email;
            $fromName = $invitor->getDisplayName();
        } else {
            $fromEmail = CONTACT_EMAIL;
            $fromName = CONTACT_NAME;
        }

        $this->sendTo($user->email, $fromEmail, $fromName, $subject, $view, $data);
    }

    /**
     * @param User      $user
     * @param User|null $invitor
     */
    public function sendEmailChanged(User $user)
    {
        $oldEmail = $user->getOriginal('email');
        $newEmail = $user->email;

        if (! $oldEmail || ! $newEmail) {
            return;
        }

        $view = 'user_message';
        $subject = trans('texts.email_address_changed');

        $data = [
            'user' => $user,
            'userName' => $user->getDisplayName(),
            'primaryMessage' => trans('texts.email_address_changed_message', ['old_email' => $oldEmail, 'new_email' => $newEmail]),
        ];

        $this->sendTo($oldEmail, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    /**
     * @param User    $user
     * @param Invoice $invoice
     * @param $notificationType
     * @param Payment|null $payment
     */
    public function sendNotification(
        User $user,
        Invoice $invoice,
        $notificationType,
        Payment $payment = null,
        $notes = false
    ) {
        if (! $user->shouldNotify($invoice)) {
            return;
        }

        $entityType = $invoice->getEntityType();
        $view = ($notificationType == 'approved' ? ENTITY_QUOTE : ENTITY_INVOICE) . "_{$notificationType}";
        $account = $user->account;
        $client = $invoice->client;
        $link = $invoice->present()->multiAccountLink;

        $data = [
            'entityType' => $entityType,
            'clientName' => $client->getDisplayName(),
            'accountName' => $account->getDisplayName(),
            'userName' => $user->getDisplayName(),
            'invoiceAmount' => $account->formatMoney($invoice->getRequestedAmount(), $client),
            'invoiceNumber' => $invoice->invoice_number,
            'invoiceLink' => $link,
            'account' => $account,
        ];

        if ($payment) {
            $data['payment'] = $payment;
            $data['paymentAmount'] = $account->formatMoney($payment->amount, $client);
        }

        $subject = trans("texts.notification_{$entityType}_{$notificationType}_subject", [
            'invoice' => $invoice->invoice_number,
            'client' => $client->getDisplayName(),
        ]);

        if ($notes) {
            $subject .= ' [' . trans('texts.notes_' . $notes) . ']';
        }

        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    /**
     * @param Invitation $invitation
     */
    public function sendEmailBounced(Invitation $invitation)
    {
        $user = $invitation->user;
        $account = $user->account;
        $invoice = $invitation->invoice;
        $entityType = $invoice->getEntityType();

        if (! $user->email) {
            return;
        }

        $subject = trans("texts.notification_{$entityType}_bounced_subject", ['invoice' => $invoice->invoice_number]);
        $view = 'email_bounced';
        $data = [
            'userName' => $user->getDisplayName(),
            'emailError' => $invitation->email_error,
            'entityType' => $entityType,
            'contactName' => $invitation->contact->getDisplayName(),
            'invoiceNumber' => $invoice->invoice_number,
        ];

        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    /**
     * @param Invitation $invitation
     */
    public function sendMessage($user, $subject, $message, $data = false)
    {
        if (! $user->email) {
            return;
        }

        $invoice = $data && isset($data['invoice']) ? $data['invoice'] : false;
        $view = 'user_message';

        $data = $data ?: [];
        $data += [
            'userName' => $user->getDisplayName(),
            'primaryMessage' => $message,
            //'secondaryMessage' => $message,
            'invoiceLink' => $invoice ? $invoice->present()->multiAccountLink : false,
        ];

        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    public function sendSecurityCode($user, $code)
    {
        if (! $user->email) {
            return;
        }

        $subject = trans('texts.security_code_email_subject');
        $view = 'security_code';
        $data = [
            'userName' => $user->getDisplayName(),
            'code' => $code,
        ];

        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    public function sendPasswordReset($user, $token)
    {
        if (! $user->email) {
            return;
        }

        $subject = trans('texts.your_password_reset_link');
        $view = 'password';
        $data = [
            'token' => $token,
        ];

        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    public function sendScheduledReport($scheduledReport, $file)
    {
        $user = $scheduledReport->user;
        $config = json_decode($scheduledReport->config);

        if (! $user->email) {
            return;
        }

        $subject = sprintf('%s - %s %s', APP_NAME, trans('texts.' . $config->report_type), trans('texts.report'));
        $view = 'user_message';
        $data = [
            'userName' => $user->getDisplayName(),
            'primaryMessage' => trans('texts.scheduled_report_attached', ['type' => trans('texts.' . $config->report_type)]),
            'documents' => [[
                'name' => $file->filename . '.' . $config->export_format,
                'data' =>  $file->string($config->export_format),
            ]]
        ];

        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }
}
