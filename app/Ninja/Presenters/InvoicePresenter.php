<?php

namespace App\Ninja\Presenters;

use App\Libraries\Skype\InvoiceCard;
use App\Models\Activity;
use Carbon;
use DropdownButton;
use stdClass;
use Utils;

class InvoicePresenter extends EntityPresenter
{
    public function client()
    {
        return $this->entity->client ? $this->entity->client->getDisplayName() : '';
    }

    public function user()
    {
        return $this->entity->user->getDisplayName();
    }

    public function amount()
    {
        $invoice = $this->entity;
        $account = $invoice->account;

        return $account->formatMoney($invoice->amount, $invoice->client);
    }

    public function balance()
    {
        $invoice = $this->entity;
        $account = $invoice->account;

        return $account->formatMoney($invoice->balance, $invoice->client);
    }

    public function requestedAmount()
    {
        $invoice = $this->entity;
        $account = $invoice->account;

        return $account->formatMoney($invoice->getRequestedAmount(), $invoice->client);
    }

    public function balanceDueLabel()
    {
        if ($this->entity->partial > 0) {
            return 'partial_due';
        } elseif ($this->entity->isType(INVOICE_TYPE_QUOTE)) {
            return 'total';
        } else {
            return 'balance_due';
        }
    }

    public function age()
    {
        if (! $this->entity->due_date || $this->entity->date_date == '0000-00-00') {
            return 0;
        }

        $date = Carbon::parse($this->entity->due_date);

        if ($date->isFuture()) {
            return 0;
        }

        return $date->diffInDays();
    }

    public function ageGroup()
    {
        $age = $this->age();

        if ($age > 120) {
            return 'age_group_120';
        } elseif ($age > 90) {
            return 'age_group_90';
        } elseif ($age > 60) {
            return 'age_group_60';
        } elseif ($age > 30) {
            return 'age_group_30';
        } else {
            return 'age_group_0';
        }
    }

    public function dueDateLabel()
    {
        if ($this->entity->isType(INVOICE_TYPE_STANDARD)) {
            return trans('texts.due_date');
        } else {
            return trans('texts.valid_until');
        }
    }

    public function discount()
    {
        $invoice = $this->entity;

        if ($invoice->is_amount_discount) {
            return $invoice->account->formatMoney($invoice->discount);
        } else {
            return $invoice->discount . '%';
        }
    }

    // https://schema.org/PaymentStatusType
    public function paymentStatus()
    {
        if (! $this->entity->balance) {
            return 'PaymentComplete';
        } elseif ($this->entity->isOverdue()) {
            return 'PaymentPastDue';
        } else {
            return 'PaymentDue';
        }
    }

    public function status()
    {
        if ($this->entity->is_deleted) {
            return trans('texts.deleted');
        } elseif ($this->entity->trashed()) {
            return trans('texts.archived');
        } elseif ($this->entity->is_recurring) {
            return trans('texts.active');
        } else {
            $status = $this->entity->invoice_status ? $this->entity->invoice_status->name : 'draft';
            $status = strtolower($status);

            return trans("texts.status_{$status}");
        }
    }

    public function invoice_date()
    {
        return Utils::fromSqlDate($this->entity->invoice_date);
    }

    public function due_date()
    {
        return Utils::fromSqlDate($this->entity->due_date);
    }

    public function frequency()
    {
        $frequency = $this->entity->frequency ? $this->entity->frequency->name : '';
        $frequency = strtolower($frequency);

        return trans('texts.freq_'.$frequency);
    }

    public function email()
    {
        $client = $this->entity->client;

        return count($client->contacts) ? $client->contacts[0]->email : '';
    }

    public function autoBillEmailMessage()
    {
        $client = $this->entity->client;
        $paymentMethod = $client->defaultPaymentMethod();

        if (! $paymentMethod) {
            return false;
        }

        if ($paymentMethod->payment_type_id == PAYMENT_TYPE_ACH) {
            $paymentMethodString = trans('texts.auto_bill_payment_method_bank_transfer');
        } elseif ($paymentMethod->payment_type_id == PAYMENT_TYPE_PAYPAL) {
            $paymentMethodString = trans('texts.auto_bill_payment_method_paypal');
        } else {
            $paymentMethodString = trans('texts.auto_bill_payment_method_credit_card');
        }

        $data = [
            'payment_method' => $paymentMethodString,
            'due_date' => $this->due_date(),
        ];

        return trans('texts.auto_bill_notification', $data);
    }

    public function skypeBot()
    {
        return new InvoiceCard($this->entity);
    }

    public function rBits()
    {
        $properties = new stdClass();
        $properties->terms_text = $this->entity->terms;
        $properties->note = $this->entity->public_notes;
        $properties->itemized_receipt = [];

        foreach ($this->entity->invoice_items as $item) {
            $properties->itemized_receipt[] = $item->present()->rBits;
        }

        $data = new stdClass();
        $data->receive_time = time();
        $data->type = 'transaction_details';
        $data->source = 'user';
        $data->properties = $properties;

        return [$data];
    }

    public function moreActions()
    {
        $invoice = $this->entity;
        $entityType = $invoice->getEntityType();

        $actions = [
            ['url' => 'javascript:onCloneClick()', 'label' => trans("texts.clone_{$entityType}")],
            ['url' => url("{$entityType}s/{$entityType}_history/{$invoice->public_id}"), 'label' => trans('texts.view_history')],
            DropdownButton::DIVIDER,
        ];

        if ($entityType == ENTITY_QUOTE) {
            if ($invoice->quote_invoice_id) {
                $actions[] = ['url' => url("invoices/{$invoice->quote_invoice_id}/edit"), 'label' => trans('texts.view_invoice')];
            } else {
                $actions[] = ['url' => 'javascript:onConvertClick()', 'label' => trans('texts.convert_to_invoice')];
            }
        } elseif ($entityType == ENTITY_INVOICE) {
            if ($invoice->quote_id) {
                $actions[] = ['url' => url("quotes/{$invoice->quote_id}/edit"), 'label' => trans('texts.view_quote')];
            }

            if (!$invoice->deleted_at && ! $invoice->is_recurring && $invoice->balance != 0) {
                $actions[] = ['url' => 'javascript:submitBulkAction("markPaid")', 'label' => trans('texts.mark_paid')];
                $actions[] = ['url' => 'javascript:onPaymentClick()', 'label' => trans('texts.enter_payment')];
            }

            foreach ($invoice->payments as $payment) {
                $label = trans('texts.view_payment');
                if (count($invoice->payments) > 1) {
                    $label .= ' - ' . $invoice->account->formatMoney($payment->amount, $invoice->client);
                }
                $actions[] = ['url' => $payment->present()->url, 'label' => $label];
            }
        }

        if (count($actions) > 3) {
            $actions[] = DropdownButton::DIVIDER;
        }

        if (! $invoice->trashed()) {
            $actions[] = ['url' => 'javascript:onArchiveClick()', 'label' => trans("texts.archive_{$entityType}")];
        }
        if (! $invoice->is_deleted) {
            $actions[] = ['url' => 'javascript:onDeleteClick()', 'label' => trans("texts.delete_{$entityType}")];
        }

        return $actions;
    }

    public function gatewayFee($gatewayTypeId = false)
    {
        $invoice = $this->entity;
        $account = $invoice->account;

        if (! $account->gateway_fee_enabled) {
            return '';
        }

        $settings = $account->getGatewaySettings($gatewayTypeId);

        if (! $settings || ! $settings->areFeesEnabled()) {
            return '';
        }

        $fee = $invoice->calcGatewayFee($gatewayTypeId, true);
        $fee = $account->formatMoney($fee, $invoice->client);

        if (floatval($settings->fee_amount) < 0 || floatval($settings->fee_percent) < 0) {
            $label = trans('texts.discount');
        } else {
            $label = trans('texts.fee');
        }

        return ' - ' . $fee . ' ' . $label;
    }

    public function multiAccountLink()
    {
        $invoice = $this->entity;
        $account = $invoice->account;

        if ($account->hasMultipleAccounts()) {
            $link = url(sprintf('/account/%s?redirect_to=%s', $account->account_key, $invoice->present()->path));
        } else {
            $link = $invoice->present()->url;
        }

        return $link;
    }
}
