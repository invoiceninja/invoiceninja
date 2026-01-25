<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Payment;

use App\Models\Invoice;
use App\Models\Payment;
use App\Helpers\Cache\Atomic;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;
use App\Exceptions\DuplicatePaymentException;
use App\Http\ValidationRules\Credit\CreditsSumRule;
use App\Http\ValidationRules\Credit\ValidCreditsRules;
use App\Http\ValidationRules\ValidPayableInvoicesRule;
use App\Http\ValidationRules\PaymentAmountsBalanceRule;

class StorePaymentRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Payment::class);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'client_id' => ['bail','required',Rule::exists('clients', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)],
            'invoices' => ['bail', 'sometimes', 'nullable', 'array', new ValidPayableInvoicesRule()],
            'invoices.*.amount' => ['bail','required'],
            'invoices.*.invoice_id' => ['bail','required','distinct', Rule::exists('invoices', 'id')->where('company_id', $user->company()->id)->where('client_id', $this->client_id)->where('is_deleted',0)],
            'credits.*.credit_id' => ['bail','required','distinct', new ValidCreditsRules($this->all()),Rule::exists('credits', 'id')->where('company_id', $user->company()->id)->where('client_id', $this->client_id)->where('is_deleted',0)],
            'credits.*.amount' => ['bail','required', new CreditsSumRule($this->all())],
            'amount' => ['bail', 'numeric', new PaymentAmountsBalanceRule(), 'max:99999999999999'],
            'number' => ['bail', 'nullable',  Rule::unique('payments')->where('company_id', $user->company()->id)],
            'idempotency_key' => ['nullable', 'bail', 'string','max:64', Rule::unique('payments')->where('company_id', $user->company()->id)],
            'date' => ['bail', 'nullable', 'sometimes', 'date:Y-m-d'],
        ];

        $rules['file'] = 'bail|sometimes|array';
        $rules['file.*'] = $this->fileValidation();
        $rules['documents'] = 'bail|sometimes|array';
        $rules['documents.*'] = $this->fileValidation();

        return $rules;
    }


    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $invoices = $this->input('invoices') ?? [];
            $clientId = $this->input('client_id');
            $invCollection = Invoice::withTrashed()
                ->whereIn('id', array_column($invoices, 'invoice_id'))
                ->get();

            foreach ($invoices as $index => $invoice) {
                // Check amount exists (if not caught by basic rules)
                if (!array_key_exists('amount', $invoice)) {
                    $validator->errors()->add("invoices.{$index}.amount", ctrans('texts.amount') . ' required');
                    continue;
                }

                if (!array_key_exists('invoice_id', $invoice)) {
                    $validator->errors()->add("invoices.{$index}.invoice_id", ctrans('texts.invoice_id') . ' required');
                    continue;
                }

                // Find invoice
                $inv = $invCollection->firstWhere('id', $invoice['invoice_id']);

                if (!$inv) {
                    $validator->errors()->add("invoices.{$index}.invoice_id", ctrans('texts.invoice_not_found'));
                    continue;
                }

                // Check client match
                if ($inv->client_id != $clientId) {
                    $validator->errors()->add("invoices.{$index}", ctrans('texts.invoices_dont_match_client'));
                    continue;
                }

                // Check amount validation
                if ($inv->status_id == Invoice::STATUS_DRAFT && $invoice['amount'] <= $inv->amount) {
                    //catch here nothing to do - we need this to prevent the last elseif triggering
                } elseif ($invoice['amount'] <= 0 && $inv->amount > 0) {
                    $validator->errors()->add("invoices.{$index}.amount", 'Amount cannot be less than or equal to zero');
                } elseif ($inv->status_id == Invoice::STATUS_DRAFT && floatval($invoice['amount']) > floatval($inv->amount)) {
                    $validator->errors()->add("invoices.{$index}.amount", 'Amount cannot be greater than invoice balance');
                } elseif (floatval($invoice['amount']) > floatval($inv->balance)) {
                    $validator->errors()->add("invoices.{$index}.amount", ctrans('texts.amount_greater_than_balance_v5'));
                } elseif ($inv->is_deleted) {
                    $validator->errors()->add("invoices.{$index}", 'One or more invoices in this request have since been deleted');
                }
            }

            // Check for duplicates
            $invoiceIds = array_column($invoices, 'invoice_id');
            if (count($invoiceIds) !== count(array_unique($invoiceIds))) {
                $validator->errors()->add('invoices', ctrans('texts.duplicate_invoices_submitted'));
            }
        });
    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $input = $this->all();

        $client_id = is_string($this->input('client_id', '')) ? $this->input('client_id') : '';

        if(isset($input['invoices'][0]['invoice_id'])) {
            $hash_key = implode(',', array_column($input['invoices'], 'invoice_id'));
        } 
        else {
            $hash_key = $this->input('amount', 0);
        }

        $hash = $this->ip()."|".$hash_key."|".$client_id."|".$user->company()->company_key;

        // Atomic lock: returns false if key already exists (request in progress)
        if (!Atomic::set($hash, true, 1)) {
            throw new DuplicatePaymentException('Duplicate request.', 429);
        }

        if ($this->file('documents') instanceof \Illuminate\Http\UploadedFile) {
            $this->files->set('documents', [$this->file('documents')]);
        }

        if ($this->file('file') instanceof \Illuminate\Http\UploadedFile) {
            $this->files->set('file', [$this->file('file')]);
        }

        $invoices_total = 0;
        $credits_total = 0;

        if (isset($input['client_id']) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id'], true);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                if (isset($value['invoice_id']) && is_string($value['invoice_id'])) {
                    $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
                }

                if (array_key_exists('amount', $value) && is_numeric($value['amount'])) {
                    $invoices_total += $value['amount'];
                }
            }
        }

        if (isset($input['invoices']) && is_array($input['invoices']) === false) {
            $input['invoices'] = null;
        }

        if (isset($input['credits']) && is_array($input['credits']) !== false) {
            foreach ($input['credits'] as $key => $value) {
                if (isset($value['credit_id']) && is_string($value['credit_id'])) {
                    $input['credits'][$key]['credit_id'] = $this->decodePrimaryKey($value['credit_id']);

                    if (array_key_exists('amount', $value) && is_numeric($value['amount'])) {
                        $credits_total += $value['amount'];
                    }
                }
            }
        }

        if (isset($input['credits']) && is_array($input['credits']) === false) {
            $input['credits'] = null;
        }

        if (! isset($input['amount']) || $input['amount'] == 0) {
            $input['amount'] = $invoices_total - $credits_total;
        }

        if (! isset($input['date'])) {
            $input['date'] = now()->addSeconds($user->company()->utc_offset())->format('Y-m-d');
        }

        if (! isset($input['idempotency_key'])) {
            $input['idempotency_key'] = substr(time()."{$input['date']}{$input['amount']}{$credits_total}{$this->client_id}{$user->company()->company_key}", 0, 64);
        }

        if (array_key_exists('exchange_rate', $input) && $input['exchange_rate'] === null) {
            unset($input['exchange_rate']);
        }

        $input['lock_key'] = $hash;

        $this->replace($input);
    }


}
