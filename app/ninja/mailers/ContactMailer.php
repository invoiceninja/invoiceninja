<?php namespace ninja\mailers;

use Invoice;
use Payment;
use Contact;
use Invitation;
use Activity;
use Utils;

class ContactMailer extends Mailer
{
    public function sendInvoice(Invoice $invoice)
    {
        $invoice->load('invitations', 'client', 'account');
        $entityType = $invoice->getEntityType();

        $view = 'invoice';
        $subject = trans("texts.{$entityType}_subject", ['invoice' => $invoice->invoice_number, 'account' => $invoice->account->getDisplayName()]);
        $accountName = $invoice->account->getDisplayName();
        $emailTemplate = $invoice->account->getEmailTemplate($entityType);
        $invoiceAmount = Utils::formatMoney($invoice->amount, $invoice->client->currency_id);

        foreach ($invoice->invitations as $invitation) {
            if (!$invitation->user || !$invitation->user->email) {
                return false;
            }
            if (!$invitation->contact || !$invitation->contact->email) {
                return false;
            }

            $invitation->sent_date = \Carbon::now()->toDateTimeString();
            $invitation->save();

            $variables = [
                '$footer' => $invoice->account->getEmailFooter(),
                '$link' => $invitation->getLink(),
                '$client' => $invoice->client->getDisplayName(),
                '$account' => $accountName,
                '$contact' => $invitation->contact->getDisplayName(),
                '$amount' => $invoiceAmount
            ];

            $data['body'] = str_replace(array_keys($variables), array_values($variables), $emailTemplate);
            
            $fromEmail = $invitation->user->email;
            $this->sendTo($invitation->contact->email, $fromEmail, $accountName, $subject, $view, $data);

            Activity::emailInvoice($invitation);
        }

        if (!$invoice->isSent()) {
            $invoice->invoice_status_id = INVOICE_STATUS_SENT;
            $invoice->save();
        }

        \Event::fire('invoice.sent', $invoice);
    }

    public function sendPaymentConfirmation(Payment $payment)
    {
        $invoice = $payment->invoice;
        $view = 'payment_confirmation';
        $subject = trans('texts.payment_subject', ['invoice' => $invoice->invoice_number]);
        $accountName = $payment->account->getDisplayName();
        $emailTemplate = $invoice->account->getEmailTemplate(ENTITY_PAYMENT);

        $variables = [
            '$footer' => $payment->account->getEmailFooter(),
            '$client' => $payment->client->getDisplayName(),
            '$account' => $accountName,
            '$amount' => Utils::formatMoney($payment->amount, $payment->client->currency_id)
        ];

        $data = ['body' => str_replace(array_keys($variables), array_values($variables), $emailTemplate)];

        $user = $payment->invitation->user;
        $this->sendTo($payment->contact->email, $user->email, $accountName, $subject, $view, $data);
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
}
