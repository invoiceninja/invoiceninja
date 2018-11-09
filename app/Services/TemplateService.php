<?php

namespace App\Services;

use App\Models\Gateway;
use App\Models\GatewayType;
use Form;
use HTML;
use Utils;

class TemplateService
{
    /**
     * @param $template
     * @param array $data
     *
     * @return mixed|string
     */
    public function processVariables($template, array $data)
    {
        /** @var \App\Models\Invitation $invitation */
        $invitation = $data['invitation'];

        /** @var \App\Models\Account $account */
        $account = ! empty($data['account']) ? $data['account'] : $invitation->account;

        /** @var \App\Models\Client $client */
        $client = ! empty($data['client']) ? $data['client'] : $invitation->invoice->client;

        $amount = ! empty($data['amount']) ? $data['amount'] : $invitation->invoice->getRequestedAmount();

        // check if it's a proposal
        if ($invitation->proposal) {
            $invoice = $invitation->proposal->invoice;
            $entityType = ENTITY_PROPOSAL;
        } else {
            $invoice = $invitation->invoice;
            $entityType = $invoice->getEntityType();
        }

        $contact = $invitation->contact;
        $passwordHTML = isset($data['password']) ? '<p>'.trans('texts.password').': '.$data['password'].'<p>' : false;
        $documentsHTML = '';

        if ($account->hasFeature(FEATURE_DOCUMENTS) && $invoice->hasDocuments()) {
            $documentsHTML .= trans('texts.email_documents_header').'<ul>';
            foreach ($invoice->allDocuments() as $document) {
                $documentsHTML .= '<li><a href="'.HTML::entities($document->getClientUrl($invitation)).'">'.HTML::entities($document->name).'</a></li>';
            }
            $documentsHTML .= '</ul>';
        }

        $variables = [
            '$footer' => $account->getEmailFooter(),
            '$emailSignature' => $account->getEmailFooter(),
            '$client' => $client->getDisplayName(),
            '$idNumber' => $client->id_number,
            '$vatNumber' => $client->vat_number,
            '$account' => $account->getDisplayName(),
            '$dueDate' => $account->formatDate($invoice->getOriginal('partial_due_date') ?: $invoice->getOriginal('due_date')),
            '$invoiceDate' => $account->formatDate($invoice->getOriginal('invoice_date')),
            '$contact' => $contact->getDisplayName(),
            '$firstName' => $contact->first_name,
            '$amount' => $account->formatMoney($amount, $client),
            '$total' => $invoice->present()->amount,
            '$balance' => $invoice->present()->balance,
            '$invoice' => $invoice->invoice_number,
            '$quote' => $invoice->invoice_number,
            '$number' => $invoice->invoice_number,
            '$partial' => $invoice->present()->partial,
            '$poNumber' => $invoice->po_number,
            '$terms' => $invoice->terms,
            '$notes' => $invoice->public_notes,
            '$link' => $invitation->getLink(),
            '$password' => $passwordHTML,
            '$viewLink' => $invitation->getLink().'$password',
            '$viewButton' => Form::emailViewButton($invitation->getLink(), $entityType).'$password',
            '$paymentLink' => $invitation->getLink('payment').'$password',
            '$paymentButton' => Form::emailPaymentButton($invitation->getLink('payment')).'$password',
            '$approveLink' => $invitation->getLink('approve').'$password',
            '$approveButton' => Form::emailPaymentButton($invitation->getLink('approve'), 'approve').'$password',
            '$customClient1' => $client->custom_value1,
            '$customClient2' => $client->custom_value2,
            '$customContact1' => $contact->custom_value1,
            '$customContact2' => $contact->custom_value2,
            '$customInvoice1' => $invoice->custom_text_value1,
            '$customInvoice2' => $invoice->custom_text_value2,
            '$documents' => $documentsHTML,
            '$autoBill' => empty($data['autobill']) ? '' : $data['autobill'],
            '$portalLink' => $invitation->contact->link,
            '$portalButton' => Form::emailViewButton($invitation->contact->link, 'portal'),
        ];

        // Add variables for available payment types
        foreach (Gateway::$gatewayTypes as $type) {
            if ($type == GATEWAY_TYPE_TOKEN) {
                continue;
            }
            $camelType = Utils::toCamelCase(GatewayType::getAliasFromId($type));
            $snakeCase = Utils::toSnakeCase(GatewayType::getAliasFromId($type));
            $variables["\${$camelType}Link"] = $invitation->getLink('payment') . "/{$snakeCase}";
            $variables["\${$camelType}Button"] = Form::emailPaymentButton($invitation->getLink('payment')  . "/{$snakeCase}");
        }

        $includesPasswordPlaceholder = strpos($template, '$password') !== false;

        $str = str_replace(array_keys($variables), array_values($variables), $template);

        if (! $includesPasswordPlaceholder && $passwordHTML) {
            $pos = strrpos($str, '$password');
            if ($pos !== false) {
                $str = substr_replace($str, $passwordHTML, $pos, 9/* length of "$password" */);
            }
        }
        $str = str_replace('$password', '', $str);
        $str = autolink($str, 100);

        return $str;
    }
}
