<?php namespace App\Ninja\Mailers;

use HTML;
use Utils;
use Event;
use URL;
use Auth;
// vendor
use App\Models\VendorActivity;
use App\Models\Gateway;

class VendorContactMailer extends Mailer
{
    public static $variableFields = [
        'footer',
        'account',
        'vendor',
        'amount',
        'contact',
        'firstName',
        'viewLink',
        'viewButton',
        'paymentLink',
        'paymentButton',
    ];

    private function sendInvitation($invitation, $invoice, $body, $subject, $pdfString)
    {
        $vendor = $invoice->vendor;
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
            'vendor' => $vendor,
            'invitation' => $invitation
        ];

        $data = [
            'body' => $this->processVariables($body, $variables),
            'link' => $invitation->getLink(),
            'entityType' => $invoice->getEntityType(),
            'invitation' => $invitation,
            'account' => $account,
            'vendor' => $vendor,
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
            'vendor' => $name,
            'amount' => Utils::formatMoney($amount, DEFAULT_CURRENCY, DEFAULT_COUNTRY),
            'license' => $license
        ];
        
        $this->sendTo($email, CONTACT_EMAIL, CONTACT_NAME, $subject, $view, $data);
    }

    private function processVariables($template, $data)
    {
        $account = $data['account'];
        $vendor = $data['vendor'];
        $invitation = $data['invitation'];
        $invoice = $invitation->invoice;

        $variables = [
            '$footer' => $account->getEmailFooter(),
            '$vendor' => $vendor->getDisplayName(),
            '$account' => $account->getDisplayName(),
            '$contact' => $invitation->contact->getDisplayName(),
            '$firstName' => $invitation->contact->first_name,
            '$amount' => $account->formatMoney($data['amount'], $vendor),
            '$invoice' => $invoice->invoice_number,
            '$quote' => $invoice->invoice_number,
            '$link' => $invitation->getLink(),
            '$dueDate' => $account->formatDate($invoice->due_date),
            '$viewLink' => $invitation->getLink(),
            '$viewButton' => HTML::emailViewButton($invitation->getLink(), $invoice->getEntityType()),
            '$paymentLink' => $invitation->getLink('payment'),
            '$paymentButton' => HTML::emailPaymentButton($invitation->getLink('payment')),
            '$customClient1' => $account->custom_vendor_label1,
            '$customClient2' => $account->custom_vendor_label2,
            '$customInvoice1' => $account->custom_invoice_text_label1,
            '$customInvoice2' => $account->custom_invoice_text_label2,
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
