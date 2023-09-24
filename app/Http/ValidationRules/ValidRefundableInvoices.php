<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules;

use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidRefundableInvoices.
 */
class ValidRefundableInvoices implements Rule
{
    use MakesHash;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    private $error_msg;

    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        if (! array_key_exists('id', $this->input)) {
            $this->error_msg = ctrans('texts.payment_id_required');

            return false;
        }

        /**@var \App\Models\Payment $payment **/
        $payment = Payment::whereId($this->input['id'])->first();

        if (! $payment) {
            $this->error_msg = ctrans('texts.unable_to_retrieve_payment');

            return false;
        }

        /*If no invoices has been sent, then we apply the payment to the client account*/
        $invoices = [];

        if (is_array($value)) {
            $invoices = Invoice::query()->whereIn('id', array_column($this->input['invoices'], 'invoice_id'))->company()->get();
        } else {
            return true;
        }

        foreach ($invoices as $invoice) {
            if (! $invoice->isRefundable()) {
                $this->error_msg = ctrans('texts.invoice_cannot_be_refunded', ['invoice' => $invoice->hashed_id]);

                return false;
            }

            foreach ($this->input['invoices'] as $val) {
                if ($val['invoice_id'] == $invoice->id) {
                    $pivot_record = $payment->paymentables->where('paymentable_id', $invoice->id)->first();

                    if ($val['amount'] > ($pivot_record->amount - $pivot_record->refunded)) {
                        $this->error_msg = ctrans('texts.attempted_refund_failed', ['amount' => $val['amount'], 'refundable_amount' => ($pivot_record->amount - $pivot_record->refunded)]);

                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->error_msg;
    }
}
