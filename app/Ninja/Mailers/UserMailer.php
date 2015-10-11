<?php namespace App\Ninja\Mailers;

use Utils;

use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

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
        
        $entityType = $notificationType == 'approved' ? ENTITY_QUOTE : ENTITY_INVOICE;
        $view = "{$entityType}_{$notificationType}";

        $data = [
            'entityType' => $entityType,
            'clientName' => $invoice->client->getDisplayName(),
            'accountName' => $invoice->account->getDisplayName(),
            'userName' => $user->getDisplayName(),
            'invoiceAmount' => Utils::formatMoney($invoice->getRequestedAmount(), $invoice->client->getCurrencyId()),
            'invoiceNumber' => $invoice->invoice_number,
            'invoiceLink' => SITE_URL."/{$entityType}s/{$invoice->public_id}",
        ];

        if ($payment) {
            $data['paymentAmount'] = Utils::formatMoney($payment->amount, $invoice->client->getCurrencyId());
        }

        $subject = trans("texts.notification_{$entityType}_{$notificationType}_subject", ['invoice' => $invoice->invoice_number, 'client' => $invoice->client->getDisplayName()]);
        
        $this->sendTo($user->email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    public function sendEmailBounced(Invitation $invitation)
    {
        $user = $invitation->user;
        $invoice = $invitation->invoice;
        $entityType = $invoice->getEntityType();

        if (!$user->email) {
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
}
