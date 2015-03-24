<?php namespace App\Ninja\Mailers;

use Invoice;
use Payment;
use User;
use Utils;

class UserMailer extends Mailer
{
    public function sendConfirmation(User $user, User $invitor = null)
    {
        if (!$user->email) {
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

    public function sendNotification(User $user, Invoice $invoice, $notificationType, Payment $payment = null)
    {
        if (!$user->email) {
            return;
        }
        
        $view = 'invoice_'.$notificationType;
        $entityType = $invoice->getEntityType();

        $data = [
            'entityType' => $entityType,
            'clientName' => $invoice->client->getDisplayName(),
            'accountName' => $invoice->account->getDisplayName(),
            'userName' => $user->getDisplayName(),
            'invoiceAmount' => Utils::formatMoney($invoice->amount, $invoice->client->currency_id),
            'invoiceNumber' => $invoice->invoice_number,
            'invoiceLink' => SITE_URL."/{$entityType}s/{$invoice->public_id}",
        ];

        if ($payment) {
            $data['paymentAmount'] = Utils::formatMoney($payment->amount, $invoice->client->currency_id);
        }

        $subject = trans("texts.notification_{$entityType}_{$notificationType}_subject", ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->getDisplayName()]);
        
        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }
}
